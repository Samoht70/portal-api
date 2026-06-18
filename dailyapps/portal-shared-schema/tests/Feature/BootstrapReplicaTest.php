<?php

namespace Dailyapps\PortalShared\Tests\Feature;

use Dailyapps\PortalShared\Http\Controllers\HandleDomainEvent;
use Dailyapps\PortalShared\Ingestion\ReplicaWatermark;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class BootstrapReplicaTest extends TestCase
{
    private const string SECRET = 's3cret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'portal-shared.replica' => true,
            'portal-shared.mother_url' => 'https://mother.test',
            'portal-shared.application_id' => 'app-1',
            'portal-shared.sync_secret' => self::SECRET,
            'portal-shared.snapshot_types' => ['replica_sites'],
        ]);

        Schema::dropIfExists('processed_events');
        (include base_path('dailyapps/portal-shared-schema/database/migrations/0000_00_00_000002_create_processed_events_table.php'))->up();

        Schema::dropIfExists('replica_sync_state');
        (include base_path('dailyapps/portal-shared-schema/database/migrations/0000_00_00_000003_create_replica_sync_state_table.php'))->up();

        Schema::dropIfExists('replica_sites');
        Schema::create('replica_sites', function (Blueprint $table) {
            $table->portalSites()->softDeletes();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('replica_sites');
        Schema::dropIfExists('replica_sync_state');
        Schema::dropIfExists('processed_events');

        parent::tearDown();
    }

    public function test_bootstrap_pulls_snapshot_pages_into_the_replica_and_records_the_watermark(): void
    {
        $first = $this->site('Paris');
        $second = $this->site('Lyon');

        Http::fake([
            'https://mother.test/api/sync/watermark' => Http::response(['sequence' => 42]),
            'https://mother.test/api/sync/snapshot*' => Http::sequence()
                ->push(['data' => [$first], 'next_cursor' => 'c2'])
                ->push(['data' => [$second], 'next_cursor' => null]),
        ]);

        $this->artisan('sync:bootstrap')->assertSuccessful();

        $this->assertNotNull(DB::table('replica_sites')->where('id', $first['id'])->first());
        $this->assertNotNull(DB::table('replica_sites')->where('id', $second['id'])->first());
        $this->assertSame(42, app(ReplicaWatermark::class)->get());
    }

    public function test_an_obsolete_event_at_or_below_the_watermark_is_ignored(): void
    {
        app(ReplicaWatermark::class)->set(100);

        $site = $this->site('Marseille');
        $envelope = [
            'id' => (string) Str::uuid(),
            'sequence' => 50,
            'aggregate_type' => 'replica_sites',
            'aggregate_id' => $site['id'],
            'event_type' => 'replica_sites.upserted',
            'tenant_scope' => null,
            'occurred_at' => now()->toDateTimeString(),
            'schema_version' => 1,
            'payload' => $site,
        ];

        $body = json_encode($envelope);
        $sig = hash_hmac('sha256', $body, self::SECRET);

        $request = Request::create('/sync/events', 'POST', server: [
            'HTTP_X_EVENT_ID' => $envelope['id'],
            'HTTP_X_SIGNATURE' => $sig,
        ], content: $body);

        $response = app(HandleDomainEvent::class)($request);

        $this->assertSame('stale', $response->getData(true)['status']);
        $this->assertNull(DB::table('replica_sites')->where('id', $site['id'])->first());
        $this->assertSame(0, DB::table('processed_events')->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function site(string $name): array
    {
        $now = now()->toDateTimeString();

        return [
            'id' => (string) Str::uuid(),
            'name' => $name,
            'country' => 'France',
            'country_alpha' => 'FR',
            'subdivision' => null,
            'subdivision_code' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
