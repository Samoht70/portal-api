<?php

namespace Functional\Subscriptions\Tests\Feature;

use Functional\Applications\Models\Application;
use Functional\Organizations\Models\Client;
use Functional\Subscriptions\Models\ApplicationSyncEndpoint;
use Functional\Subscriptions\Models\Subscription;
use Dailyapps\EventDistribution\Jobs\BackfillSubscriber;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Subscription lifecycle drives sync delivery: granting a subscription backfills
 * the application's sync endpoint with the tenant's state, revoking it purges
 * that endpoint, and nothing happens when the application has no enabled endpoint.
 */
class SubscriptionLifecycleTest extends TestCase
{
    public function test_granting_a_subscription_backfills_the_subscriber(): void
    {
        $client = Client::factory()->create();
        $application = Application::query()->firstOrFail();

        ApplicationSyncEndpoint::factory()->create([
            'application_id' => $application->getKey(),
            'sync_enabled' => true,
        ]);

        Queue::fake();

        Subscription::factory()->create([
            'client_id' => $client->getKey(),
            'application_id' => $application->getKey(),
        ]);

        Queue::assertPushed(BackfillSubscriber::class, 1);
        Queue::assertPushed(BackfillSubscriber::class, function (BackfillSubscriber $job) use ($client) {
            $clientId = (fn () => $this->clientId)->call($job);
            $tombstone = (fn () => $this->tombstone)->call($job);

            return $clientId === $client->getKey() && $tombstone === false;
        });
    }

    public function test_revoking_a_subscription_purges_the_subscriber(): void
    {
        $client = Client::factory()->create();
        $application = Application::query()->firstOrFail();

        ApplicationSyncEndpoint::factory()->create([
            'application_id' => $application->getKey(),
            'sync_enabled' => true,
        ]);

        // Fake before creating: under the sync queue driver, the grant backfill
        // would otherwise run a real HTTP delivery during setup. The grant job is
        // a false-tombstone; the revoke we assert below is the true-tombstone.
        Queue::fake();

        $subscription = Subscription::factory()->create([
            'client_id' => $client->getKey(),
            'application_id' => $application->getKey(),
        ]);

        $subscription->delete();

        Queue::assertPushed(BackfillSubscriber::class, function (BackfillSubscriber $job) use ($client) {
            $clientId = (fn () => $this->clientId)->call($job);
            $tombstone = (fn () => $this->tombstone)->call($job);

            return $clientId === $client->getKey() && $tombstone === true;
        });
    }

    public function test_no_backfill_when_the_application_has_no_enabled_sync_endpoint(): void
    {
        $client = Client::factory()->create();
        $application = Application::query()->firstOrFail();

        Queue::fake();

        Subscription::factory()->create([
            'client_id' => $client->getKey(),
            'application_id' => $application->getKey(),
        ]);

        Queue::assertNotPushed(BackfillSubscriber::class);
    }
}
