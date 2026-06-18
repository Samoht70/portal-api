<?php

namespace Dailyapps\PortalShared\Tests\Feature;

use Dailyapps\PortalShared\Http\Controllers\HandleDomainEvent;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class HandleDomainEventTest extends TestCase
{
    private const string SECRET = 'child-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['portal-shared.sync_secret' => self::SECRET]);

        Schema::dropIfExists('processed_events');
        (include base_path('dailyapps/portal-shared-schema/database/migrations/0000_00_00_000002_create_processed_events_table.php'))->up();

        Schema::dropIfExists('replica_sync_state');
        (include base_path('dailyapps/portal-shared-schema/database/migrations/0000_00_00_000003_create_replica_sync_state_table.php'))->up();

        Schema::dropIfExists('replica_clients');
        Schema::create('replica_clients', function (Blueprint $table) {
            $table->portalClients()->softDeletes();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('replica_clients');
        Schema::dropIfExists('replica_sync_state');
        Schema::dropIfExists('processed_events');

        parent::tearDown();
    }

    public function test_a_signed_event_is_applied_to_the_replica(): void
    {
        $id = (string) Str::uuid();

        $response = $this->dispatch($this->envelope($id, 'Acme'));

        $this->assertSame('applied', $response->getData(true)['status']);

        $row = DB::table('replica_clients')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame('Acme', $row->name);

        $this->assertSame(1, DB::table('processed_events')->count());
    }

    public function test_replaying_the_same_event_is_idempotent(): void
    {
        $id = (string) Str::uuid();
        $eventId = (string) Str::uuid();

        $first = $this->dispatch($this->envelope($id, 'Acme'), $eventId);
        $this->assertSame('applied', $first->getData(true)['status']);

        $second = $this->dispatch($this->envelope($id, 'Renamed'), $eventId);
        $this->assertSame('duplicate', $second->getData(true)['status']);

        $row = DB::table('replica_clients')->where('id', $id)->first();
        $this->assertSame('Acme', $row->name);

        $this->assertSame(1, DB::table('processed_events')->count());
    }

    public function test_a_bad_signature_is_rejected(): void
    {
        $envelope = $this->envelope((string) Str::uuid(), 'Acme');
        $body = json_encode($envelope);

        $request = Request::create('/sync/events', 'POST', server: [
            'HTTP_X_EVENT_ID' => $envelope['id'],
            'HTTP_X_SIGNATURE' => 'deadbeef',
        ], content: $body);

        try {
            app(HandleDomainEvent::class)($request);
            $this->fail('Expected an HTTP 401 to be thrown.');
        } catch (HttpException $e) {
            $this->assertSame(401, $e->getStatusCode());
        }

        // A rejected signature must leave no trace: nothing applied, nothing deduped.
        $this->assertSame(0, DB::table('replica_clients')->count());
        $this->assertSame(0, DB::table('processed_events')->count());
    }

    /**
     * @param  array<string, mixed>  $envelope
     */
    private function dispatch(array $envelope, ?string $eventId = null)
    {
        $eventId ??= $envelope['id'];
        $body = json_encode($envelope);
        $sig = hash_hmac('sha256', $body, self::SECRET);

        $request = Request::create('/sync/events', 'POST', server: [
            'HTTP_X_EVENT_ID' => $eventId,
            'HTTP_X_SIGNATURE' => $sig,
        ], content: $body);

        return app(HandleDomainEvent::class)($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function envelope(string $aggregateId, string $name): array
    {
        $now = now()->toDateTimeString();

        return [
            'id' => (string) Str::uuid(),
            'sequence' => 1,
            'aggregate_type' => 'replica_clients',
            'aggregate_id' => $aggregateId,
            'event_type' => 'replica_clients.upserted',
            'tenant_scope' => null,
            'occurred_at' => $now,
            'schema_version' => 1,
            'payload' => [
                'id' => $aggregateId,
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];
    }
}
