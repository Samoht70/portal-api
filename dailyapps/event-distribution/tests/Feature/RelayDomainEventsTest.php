<?php

namespace Dailyapps\EventDistribution\Tests\Feature;

use Dailyapps\EventDistribution\Contracts\SubscriberResolver;
use Dailyapps\EventDistribution\Jobs\DeliverDomainEvent;
use Dailyapps\EventDistribution\Jobs\RelayDomainEvents;
use Dailyapps\EventDistribution\Models\DomainEventRecord;
use Functional\Applications\Models\Application;
use Functional\Organizations\Models\Client;
use Functional\Subscriptions\Models\ApplicationSyncEndpoint;
use Functional\Subscriptions\Models\Subscription;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RelayDomainEventsTest extends TestCase
{
    public function test_it_fans_out_a_delivery_per_subscriber_and_marks_the_row_published(): void
    {
        DomainEventRecord::query()->delete();

        $application = Application::query()->firstOrFail();
        $client = Client::factory()->create();

        Subscription::factory()->create([
            'client_id' => $client->getKey(),
            'application_id' => $application->getKey(),
        ]);
        ApplicationSyncEndpoint::factory()->create([
            'application_id' => $application->getKey(),
            'endpoint_url' => 'https://child.test/sync',
            'secret' => 'top-secret',
            'sync_enabled' => true,
        ]);

        $event = DomainEventRecord::query()->where('aggregate_id', $client->getKey())->firstOrFail();
        $this->assertNull($event->published_at);

        Queue::fake();

        (new RelayDomainEvents)->handle(app(SubscriberResolver::class));

        Queue::assertPushed(DeliverDomainEvent::class, 1);
        Queue::assertPushedOn('sync', DeliverDomainEvent::class);
        $this->assertNotNull($event->refresh()->published_at);
    }

    public function test_it_skips_subscribers_whose_sync_is_disabled(): void
    {
        DomainEventRecord::query()->delete();

        $application = Application::query()->firstOrFail();
        $client = Client::factory()->create();

        Subscription::factory()->create([
            'client_id' => $client->getKey(),
            'application_id' => $application->getKey(),
        ]);
        ApplicationSyncEndpoint::factory()->create([
            'application_id' => $application->getKey(),
            'endpoint_url' => 'https://child.test/sync',
            'secret' => 'top-secret',
            'sync_enabled' => false,
        ]);

        Queue::fake();

        (new RelayDomainEvents)->handle(app(SubscriberResolver::class));

        Queue::assertNotPushed(DeliverDomainEvent::class);
    }

    public function test_it_does_not_deliver_an_event_to_an_application_subscribed_to_another_client(): void
    {
        $subscribedClient = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $application = Application::query()->firstOrFail();

        // The application syncs ONE client only — never the other.
        Subscription::factory()->create([
            'client_id' => $subscribedClient->getKey(),
            'application_id' => $application->getKey(),
        ]);
        ApplicationSyncEndpoint::factory()->create([
            'application_id' => $application->getKey(),
            'endpoint_url' => 'https://child.test/sync',
            'secret' => 'top-secret',
            'sync_enabled' => true,
        ]);

        // Drain setup noise, then emit a single event scoped to the OTHER client.
        DomainEventRecord::query()->delete();
        $otherClient->update(['name' => 'Renamed']);

        Queue::fake();

        (new RelayDomainEvents)->handle(app(SubscriberResolver::class));

        Queue::assertNotPushed(DeliverDomainEvent::class);
    }
}
