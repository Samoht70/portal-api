<?php

namespace Dailyapps\PortalShared\Tests\Feature;

use Dailyapps\PortalShared\Ingestion\ReplicaWatermark;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReconcileFromMotherTest extends TestCase
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

        parent::tearDown();
    }

    public function test_full_reconcile_converges_after_a_missed_webhook(): void
    {
        // A stale row the mother has since renamed.
        $stale = $this->site('Paris');
        DB::table('replica_sites')->insert($stale);

        // An extra row the mother no longer has.
        $extra = $this->site('Ghost');
        DB::table('replica_sites')->insert($extra);

        $current = array_merge($stale, ['name' => 'Paris Updated']);

        Http::fake([
            'https://mother.test/api/sync/checksum*' => Http::response([
                'type' => 'replica_sites',
                'count' => 1,
                'last_updated_at' => now()->toIso8601String(),
            ]),
            'https://mother.test/api/sync/snapshot*' => Http::response([
                'data' => [$current],
                'next_cursor' => null,
            ]),
        ]);

        $this->artisan('sync:reconcile', ['--full' => true])->assertSuccessful();

        $this->assertSame('Paris Updated', DB::table('replica_sites')->where('id', $stale['id'])->value('name'));
        $this->assertNotNull(DB::table('replica_sites')->where('id', $extra['id'])->value('deleted_at'));
        $this->assertGreaterThan(0, app(ReplicaWatermark::class)->getValue('last_reconcile_at'));
    }

    public function test_delta_reconcile_skips_when_the_fingerprint_matches(): void
    {
        $site = $this->site('Lyon');
        DB::table('replica_sites')->insert($site);

        $localMax = DB::table('replica_sites')->max('updated_at');

        Http::fake([
            'https://mother.test/api/sync/checksum*' => Http::response([
                'type' => 'replica_sites',
                'count' => 1,
                'last_updated_at' => $localMax,
            ]),
            'https://mother.test/api/sync/snapshot*' => Http::response([
                'data' => [],
                'next_cursor' => null,
            ]),
        ]);

        $this->artisan('sync:reconcile')->assertSuccessful();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/api/sync/snapshot'));
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
