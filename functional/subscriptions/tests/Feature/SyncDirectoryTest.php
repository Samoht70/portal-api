<?php

namespace Functional\Subscriptions\Tests\Feature;

use Dailyapps\EventDistribution\Contracts\SyncDirectory;
use Dailyapps\EventDistribution\Values\Subscriber;
use Functional\Applications\Models\Application;
use Functional\Organizations\Models\Client;
use Functional\Subscriptions\Models\ApplicationSyncEndpoint;
use Functional\Subscriptions\Models\Subscription;
use Functional\Subscriptions\Resolver\SyncDirectoryFromSubscriptions;
use Tests\TestCase;

/**
 * The SyncDirectory is the sync routing seam: given an aggregate type and a tenant
 * scope (client_id, or null for global events), subscribersFor() returns the
 * Applications that must receive the event — only those whose sync endpoint is
 * enabled, each carrying its delivery coordinates. Resolved through the container to
 * also assert the functional binding of the transport contract.
 *
 * Applications are seeded (their factory is catalog-driven); clients are made on
 * the fly with their working factory.
 */
class SyncDirectoryTest extends TestCase
{
    private function directory(): SyncDirectory
    {
        return app(SyncDirectory::class);
    }

    /**
     * @return array<int, string>
     */
    private function subscriberApplicationIds(string $aggregateType, ?string $clientId): array
    {
        return collect($this->directory()->subscribersFor($aggregateType, $clientId))
            ->map(fn (Subscriber $subscriber) => $subscriber->applicationId)
            ->all();
    }

    public function test_binding_resolves_to_the_subscription_directory(): void
    {
        $this->assertInstanceOf(
            SyncDirectoryFromSubscriptions::class,
            $this->directory()
        );
    }

    public function test_resolves_subscriptions_for_a_client(): void
    {
        $client = Client::factory()->create();
        [$first, $second] = Application::query()->take(2)->get()->all();

        Subscription::factory()->create(['client_id' => $client->getKey(), 'application_id' => $first->getKey()]);
        Subscription::factory()->create(['client_id' => $client->getKey(), 'application_id' => $second->getKey()]);

        ApplicationSyncEndpoint::factory()->create(['application_id' => $first->getKey(), 'sync_enabled' => true]);
        ApplicationSyncEndpoint::factory()->create(['application_id' => $second->getKey(), 'sync_enabled' => true]);

        $this->assertEqualsCanonicalizing(
            [$first->getKey(), $second->getKey()],
            $this->subscriberApplicationIds('users', $client->getKey())
        );
    }

    public function test_does_not_resolve_subscriptions_of_other_clients(): void
    {
        $client = Client::factory()->create();
        $other = Client::factory()->create();
        $application = Application::query()->firstOrFail();

        Subscription::factory()->create(['client_id' => $other->getKey(), 'application_id' => $application->getKey()]);

        ApplicationSyncEndpoint::factory()->create(['application_id' => $application->getKey(), 'sync_enabled' => true]);

        $this->assertSame([], $this->subscriberApplicationIds('users', $client->getKey()));
    }

    public function test_null_client_resolves_all_subscribers_deduplicated(): void
    {
        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();
        $shared = Application::query()->firstOrFail();

        Subscription::factory()->create(['client_id' => $clientA->getKey(), 'application_id' => $shared->getKey()]);
        Subscription::factory()->create(['client_id' => $clientB->getKey(), 'application_id' => $shared->getKey()]);

        ApplicationSyncEndpoint::factory()->create(['application_id' => $shared->getKey(), 'sync_enabled' => true]);

        $this->assertSame([$shared->getKey()], $this->subscriberApplicationIds('professions', null));
    }

    public function test_does_not_resolve_subscribers_whose_sync_is_disabled(): void
    {
        $client = Client::factory()->create();
        $application = Application::query()->firstOrFail();

        Subscription::factory()->create(['client_id' => $client->getKey(), 'application_id' => $application->getKey()]);

        ApplicationSyncEndpoint::factory()->create(['application_id' => $application->getKey(), 'sync_enabled' => false]);

        $this->assertSame([], $this->subscriberApplicationIds('users', $client->getKey()));
    }

    public function test_resolved_subscriber_carries_delivery_coordinates(): void
    {
        $client = Client::factory()->create();
        $application = Application::query()->firstOrFail();

        Subscription::factory()->create(['client_id' => $client->getKey(), 'application_id' => $application->getKey()]);

        $endpoint = ApplicationSyncEndpoint::factory()->create([
            'application_id' => $application->getKey(),
            'endpoint_url' => 'https://example.test/sync',
            'secret' => 'top-secret-signing-key',
            'sync_enabled' => true,
        ]);

        $subscribers = collect($this->directory()->subscribersFor('users', $client->getKey()));

        $this->assertCount(1, $subscribers);

        /** @var Subscriber $subscriber */
        $subscriber = $subscribers->first();

        $this->assertSame($application->getKey(), $subscriber->applicationId);
        $this->assertSame($endpoint->endpoint_url, $subscriber->endpointUrl);
        $this->assertSame($endpoint->secret, $subscriber->secret);
    }

    public function test_application_for_resolves_a_disabled_independent_endpoint_to_null(): void
    {
        $application = Application::query()->firstOrFail();

        ApplicationSyncEndpoint::factory()->create([
            'application_id' => $application->getKey(),
            'sync_enabled' => false,
        ]);

        $this->assertNull($this->directory()->applicationFor($application->getKey()));
    }

    public function test_application_for_resolves_delivery_coordinates_without_a_subscription(): void
    {
        $application = Application::query()->firstOrFail();

        $endpoint = ApplicationSyncEndpoint::factory()->create([
            'application_id' => $application->getKey(),
            'endpoint_url' => 'https://example.test/sync',
            'secret' => 'revoke-signing-key',
            'sync_enabled' => true,
        ]);

        $subscriber = $this->directory()->applicationFor($application->getKey());

        $this->assertNotNull($subscriber);
        $this->assertSame($endpoint->endpoint_url, $subscriber->endpointUrl);
        $this->assertSame($endpoint->secret, $subscriber->secret);
    }
}
