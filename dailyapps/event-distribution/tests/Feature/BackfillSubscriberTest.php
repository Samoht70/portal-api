<?php

namespace Dailyapps\EventDistribution\Tests\Feature;

use Dailyapps\EventDistribution\Jobs\BackfillSubscriber;
use Dailyapps\EventDistribution\Jobs\DeliverDomainEvent;
use Dailyapps\EventDistribution\SyncableRegistry;
use Dailyapps\EventDistribution\Values\Subscriber;
use Functional\Organizations\Models\Client;
use Functional\Organizations\Models\Site;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BackfillSubscriberTest extends TestCase
{
    public function test_it_delivers_an_upsert_per_tenant_aggregate_to_the_subscriber(): void
    {
        $clientA = Client::factory()->create();
        $site1 = Site::factory()->create(['client_id' => $clientA->getKey()]);
        $site2 = Site::factory()->create(['client_id' => $clientA->getKey()]);

        $clientB = Client::factory()->create();
        Site::factory()->create(['client_id' => $clientB->getKey()]);

        Queue::fake();

        (new BackfillSubscriber(
            new Subscriber('app-1', 'https://child.test/sync', 'top-secret'),
            $clientA->getKey(),
            false,
        ))->handle(app(SyncableRegistry::class));

        Queue::assertPushed(DeliverDomainEvent::class, 3);
        Queue::assertPushedOn('sync', DeliverDomainEvent::class);

        $envelopes = Queue::pushed(DeliverDomainEvent::class)
            ->map(fn ($job) => (fn () => $this->envelope)->call($job));

        $aggregateIds = $envelopes->map(fn ($envelope) => $envelope['aggregate_id'])->all();

        sort($aggregateIds);
        $expected = [$clientA->getKey(), $site1->getKey(), $site2->getKey()];
        sort($expected);

        $this->assertSame($expected, $aggregateIds);

        $envelopes->each(function (array $envelope) use ($clientA): void {
            $this->assertStringEndsWith('.upserted', $envelope['event_type']);
            $this->assertSame($clientA->getKey(), $envelope['tenant_scope']);
        });
    }

    public function test_tombstone_mode_delivers_deletes_with_a_deleted_at(): void
    {
        $clientA = Client::factory()->create();
        Site::factory()->create(['client_id' => $clientA->getKey()]);

        Queue::fake();

        (new BackfillSubscriber(
            new Subscriber('app-1', 'https://child.test/sync', 'top-secret'),
            $clientA->getKey(),
            true,
        ))->handle(app(SyncableRegistry::class));

        $envelopes = Queue::pushed(DeliverDomainEvent::class)
            ->map(fn ($job) => (fn () => $this->envelope)->call($job));

        $this->assertGreaterThan(0, $envelopes->count());

        $envelopes->each(function (array $envelope): void {
            $this->assertStringEndsWith('.deleted', $envelope['event_type']);
            $this->assertNotNull($envelope['payload']['deleted_at']);
        });
    }

    public function test_a_client_with_no_sites_still_pushes_the_client_row(): void
    {
        $clientA = Client::factory()->create();

        Queue::fake();

        (new BackfillSubscriber(
            new Subscriber('app-1', 'https://child.test/sync', 'top-secret'),
            $clientA->getKey(),
            false,
        ))->handle(app(SyncableRegistry::class));

        Queue::assertPushed(DeliverDomainEvent::class, 1);

        $envelope = (fn () => $this->envelope)->call(
            Queue::pushed(DeliverDomainEvent::class)->first(),
        );

        $this->assertSame($clientA->getKey(), $envelope['aggregate_id']);
    }
}
