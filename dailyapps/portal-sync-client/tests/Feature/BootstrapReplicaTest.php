<?php

namespace Dailyapps\PortalSync\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
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
            'portal-sync.replica' => true,
            'portal-sync.mother_url' => 'https://mother.test',
            'portal-sync.application_id' => 'app-1',
            'portal-sync.sync_secret' => self::SECRET,
            'portal-sync.snapshot_types' => ['replica_sites'],
        ]);

        Schema::dropIfExists('replica_sites');
        Schema::create('replica_sites', function (Blueprint $table) {
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
        Schema::dropIfExists('replica_sites');

        parent::tearDown();
    }

    public function test_bootstrap_pulls_snapshot_pages_into_the_replica_with_their_sequence_floor(): void
    {
        $first = $this->site('Paris');
        $second = $this->site('Lyon');

        Http::fake([
            'https://mother.test/api/sync/snapshot*' => Http::sequence()
                ->push(['data' => [$first], 'next_cursor' => 'c2'])
                ->push(['data' => [$second], 'next_cursor' => null]),
        ]);

        $this->artisan('sync:bootstrap')->assertSuccessful();

        $this->assertSame(42, (int) DB::table('replica_sites')->where('id', $first['id'])->value('_sync_sequence'));
        $this->assertNotNull(DB::table('replica_sites')->where('id', $second['id'])->first());

        // No watermark endpoint exists any more — the floor rides on each snapshot row.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/api/sync/watermark'));
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
            '_sync_sequence' => 42,
            '_sync_tenant' => (string) Str::uuid(),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
