<?php

namespace Functional\Organizations\Tests\Feature;

use Functional\Organizations\Models\Site;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Technical\EventDistribution\Models\DomainEventRecord;
use Tests\TestCase;

class SiteSyncTest extends TestCase
{
    /**
     * @return Collection<int, DomainEventRecord>
     */
    private function eventsFor(Site $site): Collection
    {
        return DomainEventRecord::query()
            ->where('aggregate_id', $site->getKey())
            ->orderBy('sequence')
            ->get();
    }

    public function test_creating_a_site_records_a_tenant_scoped_upsert(): void
    {
        $site = Site::factory()->create(['name' => 'Paris HQ']);

        $events = $this->eventsFor($site);

        $this->assertCount(1, $events);
        $this->assertSame('sites.upserted', $events[0]->event_type);
        $this->assertSame($site->client()->getParentKey(), $events[0]->tenant_scope);
        $this->assertSame('Paris HQ', $events[0]->payload['name']);
        $this->assertNull($events[0]->published_at);
    }

    public function test_updating_a_site_appends_an_upsert_with_a_higher_sequence(): void
    {
        $site = Site::factory()->create();

        $site->update(['name' => 'Renamed']);

        $events = $this->eventsFor($site);

        $this->assertCount(2, $events);
        $this->assertSame('sites.upserted', $events[1]->event_type);
        $this->assertSame('Renamed', $events[1]->payload['name']);
        $this->assertGreaterThan($events[0]->sequence, $events[1]->sequence);
    }

    public function test_soft_delete_then_restore_surface_as_delete_then_upsert(): void
    {
        $site = Site::factory()->create();

        $site->delete();
        $site->restore();

        $events = $this->eventsFor($site);

        $this->assertSame(
            ['sites.upserted', 'sites.deleted', 'sites.upserted'],
            $events->pluck('event_type')->all()
        );
        $this->assertNotNull($events[1]->payload['deleted_at']);
        $this->assertNull($events[2]->payload['deleted_at']);
    }

    public function test_a_rolled_back_transaction_records_no_event(): void
    {
        $site = Site::factory()->create();
        $before = $this->eventsFor($site)->count();

        try {
            DB::transaction(function () use ($site) {
                $site->update(['name' => 'Doomed']);

                throw new RuntimeException('boom');
            });
        } catch (RuntimeException) {
            // expected — the savepoint rolls back the update and its outbox row together
        }

        $this->assertCount($before, $this->eventsFor($site));
        $this->assertSame('Doomed', $site->name);
        $this->assertNotSame('Doomed', $site->fresh()->name);
    }
}
