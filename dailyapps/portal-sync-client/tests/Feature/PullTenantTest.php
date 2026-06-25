<?php

namespace Dailyapps\PortalSync\Tests\Feature;

use Dailyapps\PortalSync\Jobs\PullTenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PullTenantTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'portal-sync.mother_url' => 'https://mother.test',
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

    public function test_it_pulls_the_tenant_scoped_snapshot_without_since(): void
    {
        $clientId = (string) Str::uuid();
        $now = now()->toDateTimeString();

        $row = [
            'id' => (string) Str::uuid(),
            'name' => 'Acme HQ',
            '_sync_sequence' => 7,
            '_sync_tenant' => $clientId,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        Http::fake([
            'https://mother.test/api/sync/snapshot*' => Http::response([
                'data' => [$row],
                'next_cursor' => null,
            ]),
        ]);

        app()->call([new PullTenant($clientId), 'handle']);

        $stored = DB::table('replica_sites')->where('id', $row['id'])->first();
        $this->assertNotNull($stored);
        $this->assertSame('Acme HQ', $stored->name);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'tenant='.$clientId)
            && ! str_contains($request->url(), 'since='));
    }
}
