<?php

namespace Functional\Subscriptions\Tests\Feature;

use Dailyapps\EventDistribution\Jobs\DeliverDomainEvent;
use Functional\Applications\Models\Application;
use Functional\Organizations\Models\Client;
use Functional\Subscriptions\Listeners\PullOnGrant;
use Functional\Subscriptions\Listeners\PurgeOnRevoke;
use Functional\Subscriptions\Models\ApplicationSyncEndpoint;
use Functional\Subscriptions\Models\Subscription;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Subscription lifecycle drives sync delivery: granting a subscription nudges
 * the child to pull that tenant now, while revoking one notifies the
 * (now-unsubscribed) application with a `subscription.revoked` control event
 * so it purges that tenant locally.
 */
class SubscriptionLifecycleTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function envelopeOf(DeliverDomainEvent $job): array
    {
        return (fn () => $this->envelope)->call($job);
    }

    public function test_granting_a_subscription_notifies_the_subscriber_to_pull(): void
    {
        $client = Client::factory()->create();
        $application = Application::query()->firstOrFail();

        ApplicationSyncEndpoint::factory()->create([
            'application_id' => $application->getKey(),
            'endpoint_url' => 'https://child.test/sync',
            'secret' => 'grant-key',
            'sync_enabled' => true,
        ]);

        Queue::fake();

        Subscription::factory()->create([
            'client_id' => $client->getKey(),
            'application_id' => $application->getKey(),
        ]);

        Queue::assertPushed(DeliverDomainEvent::class, 1);
        Queue::assertPushed(DeliverDomainEvent::class, function (DeliverDomainEvent $job) use ($client) {
            $envelope = $this->envelopeOf($job);

            return $envelope['event_type'] === PullOnGrant::EVENT_TYPE
                && $envelope['payload']['client_id'] === $client->getKey()
                && $envelope['tenant_scope'] === $client->getKey();
        });
    }

    public function test_revoking_a_subscription_notifies_the_subscriber_to_purge(): void
    {
        $client = Client::factory()->create();
        $application = Application::query()->firstOrFail();

        // Subscribe before the sync endpoint exists, so the grant finds no
        // subscriber and the test isolates the revoke path.
        $subscription = Subscription::factory()->create([
            'client_id' => $client->getKey(),
            'application_id' => $application->getKey(),
        ]);

        ApplicationSyncEndpoint::factory()->create([
            'application_id' => $application->getKey(),
            'endpoint_url' => 'https://child.test/sync',
            'secret' => 'revoke-key',
            'sync_enabled' => true,
        ]);

        Queue::fake();

        $subscription->delete();

        Queue::assertPushed(DeliverDomainEvent::class, 1);
        Queue::assertPushed(DeliverDomainEvent::class, function (DeliverDomainEvent $job) use ($client) {
            $envelope = $this->envelopeOf($job);

            return $envelope['event_type'] === PurgeOnRevoke::EVENT_TYPE
                && $envelope['payload']['client_id'] === $client->getKey()
                && $envelope['tenant_scope'] === $client->getKey();
        });
    }

    public function test_revoking_does_nothing_when_the_application_has_no_enabled_endpoint(): void
    {
        $client = Client::factory()->create();
        $application = Application::query()->firstOrFail();

        $subscription = Subscription::factory()->create([
            'client_id' => $client->getKey(),
            'application_id' => $application->getKey(),
        ]);

        Queue::fake();

        $subscription->delete();

        Queue::assertNotPushed(DeliverDomainEvent::class);
    }
}
