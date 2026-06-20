<?php

namespace Dailyapps\PortalSync\Tests\Feature;

use Dailyapps\PortalSync\Http\Controllers\HandleDomainEvent;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Child-side ingestion: a signed full-state event is upserted into the replica,
 * gated by the per-row `_sync_sequence` so replays and out-of-order events are no-ops,
 * and a `subscription.revoked` control event purges a tenant by `_sync_tenant`.
 */
class HandleDomainEventTest extends TestCase
{
    private const string SECRET = 'child-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'portal-sync.sync_secret' => self::SECRET,
            'portal-sync.replay_window' => 0,
            'portal-sync.snapshot_types' => ['replica_clients'],
        ]);

        Schema::dropIfExists('replica_clients');
        Schema::create('replica_clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->unsignedBigInteger('_sync_sequence')->default(0);
            $table->uuid('_sync_tenant')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('replica_clients');

        parent::tearDown();
    }

    public function test_a_signed_event_is_applied_to_the_replica(): void
    {
        $id = (string) Str::uuid();

        $response = $this->dispatch($this->envelope($id, 'Acme', sequence: 5));

        $this->assertSame('applied', $response->getData(true)['status']);

        $row = DB::table('replica_clients')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame('Acme', $row->name);
        $this->assertSame(5, (int) $row->_sync_sequence);
    }

    public function test_replaying_an_equal_sequence_does_not_mutate_the_row(): void
    {
        $id = (string) Str::uuid();

        $this->dispatch($this->envelope($id, 'Acme', sequence: 5));
        $this->dispatch($this->envelope($id, 'Renamed', sequence: 5));

        $row = DB::table('replica_clients')->where('id', $id)->first();
        $this->assertSame('Acme', $row->name, 'An equal sequence must not overwrite the row.');
    }

    public function test_an_out_of_order_lower_sequence_is_ignored(): void
    {
        $id = (string) Str::uuid();

        $this->dispatch($this->envelope($id, 'New', sequence: 9));
        $this->dispatch($this->envelope($id, 'Old', sequence: 3));

        $row = DB::table('replica_clients')->where('id', $id)->first();
        $this->assertSame('New', $row->name);
        $this->assertSame(9, (int) $row->_sync_sequence);
    }

    public function test_a_higher_sequence_overwrites(): void
    {
        $id = (string) Str::uuid();

        $this->dispatch($this->envelope($id, 'First', sequence: 1));
        $this->dispatch($this->envelope($id, 'Second', sequence: 2));

        $row = DB::table('replica_clients')->where('id', $id)->first();
        $this->assertSame('Second', $row->name);
        $this->assertSame(2, (int) $row->_sync_sequence);
    }

    public function test_a_bad_signature_is_rejected_and_writes_nothing(): void
    {
        $envelope = $this->envelope((string) Str::uuid(), 'Acme', sequence: 1);
        $body = json_encode($envelope);

        $request = Request::create('/sync/events', 'POST', server: [
            'HTTP_X_SIGNATURE' => 'deadbeef',
        ], content: $body);

        try {
            app(HandleDomainEvent::class)($request);
            $this->fail('Expected an HTTP 401 to be thrown.');
        } catch (HttpException $e) {
            $this->assertSame(401, $e->getStatusCode());
        }

        $this->assertSame(0, DB::table('replica_clients')->count());
    }

    public function test_an_event_older_than_the_replay_window_is_rejected(): void
    {
        config(['portal-sync.replay_window' => 300]);

        $envelope = $this->envelope((string) Str::uuid(), 'Acme', sequence: 1, occurredAt: now()->subMinutes(10)->toIso8601String());

        $response = $this->dispatch($envelope);

        $this->assertSame('expired', $response->getData(true)['status']);
        $this->assertSame(0, DB::table('replica_clients')->count());
    }

    public function test_a_revoke_control_event_purges_only_the_revoked_tenant(): void
    {
        $revoked = (string) Str::uuid();
        $kept = (string) Str::uuid();

        $mine = (string) Str::uuid();
        $theirs = (string) Str::uuid();

        $this->dispatch($this->envelope($mine, 'Mine', sequence: 1, tenantScope: $revoked));
        $this->dispatch($this->envelope($theirs, 'Theirs', sequence: 1, tenantScope: $kept));

        $response = $this->dispatch($this->revokeEnvelope($revoked));

        $this->assertSame('purged', $response->getData(true)['status']);
        $this->assertNotNull(DB::table('replica_clients')->where('id', $mine)->value('deleted_at'));
        $this->assertNull(DB::table('replica_clients')->where('id', $theirs)->value('deleted_at'));
    }

    /**
     * @param  array<string, mixed>  $envelope
     */
    private function dispatch(array $envelope)
    {
        $body = json_encode($envelope);
        $signature = hash_hmac('sha256', $body, self::SECRET);

        $request = Request::create('/sync/events', 'POST', server: [
            'HTTP_X_SIGNATURE' => $signature,
        ], content: $body);

        return app(HandleDomainEvent::class)($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function envelope(string $aggregateId, string $name, int $sequence, ?string $tenantScope = null, ?string $occurredAt = null): array
    {
        $now = now()->toDateTimeString();

        return [
            'id' => (string) Str::uuid(),
            'sequence' => $sequence,
            'aggregate_type' => 'replica_clients',
            'aggregate_id' => $aggregateId,
            'event_type' => 'replica_clients.upserted',
            'tenant_scope' => $tenantScope,
            'occurred_at' => $occurredAt ?? now()->toIso8601String(),
            'schema_version' => 1,
            'payload' => [
                'id' => $aggregateId,
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function revokeEnvelope(string $clientId): array
    {
        return [
            'id' => (string) Str::uuid(),
            'sequence' => 100,
            'aggregate_type' => 'subscription',
            'aggregate_id' => $clientId,
            'event_type' => HandleDomainEvent::REVOKE_EVENT_TYPE,
            'tenant_scope' => $clientId,
            'occurred_at' => now()->toIso8601String(),
            'schema_version' => 1,
            'payload' => ['client_id' => $clientId],
        ];
    }
}
