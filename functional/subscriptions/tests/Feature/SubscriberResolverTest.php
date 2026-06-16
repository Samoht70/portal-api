<?php

namespace Functional\Subscriptions\Tests\Feature;

use Functional\Applications\Models\Application;
use Functional\Organizations\Models\Client;
use Functional\Subscriptions\Models\Subscription;
use Functional\Subscriptions\Resolver\SubscriptionResolver;
use Technical\EventDistribution\Contracts\SubscriberResolver;
use Technical\EventDistribution\Values\Subscriber;
use Tests\TestCase;

/**
 * The SubscriberResolver is the sync routing key: given an aggregate type and a
 * tenant scope (client_id, or null for global events), it returns the Applications
 * that must receive the event. Resolved through the container to also assert the
 * functional binding of the technical contract.
 *
 * Applications are seeded (their factory is catalog-driven); clients are made on
 * the fly with their working factory.
 */
class SubscriberResolverTest extends TestCase
{
    private function resolver(): SubscriberResolver
    {
        return app(SubscriberResolver::class);
    }

    /**
     * @return array<int, string>
     */
    private function resolvedApplicationIds(string $aggregateType, ?string $clientId): array
    {
        return collect($this->resolver()->resolve($aggregateType, $clientId))
            ->map(fn (Subscriber $subscriber) => $subscriber->applicationId)
            ->all();
    }

    public function test_binding_resolves_to_the_subscription_resolver(): void
    {
        $this->assertInstanceOf(
            SubscriptionResolver::class,
            $this->resolver()
        );
    }

    public function test_resolves_subscriptions_for_a_client(): void
    {
        $client = Client::factory()->create();
        [$first, $second] = Application::query()->take(2)->get()->all();

        Subscription::factory()->create(['client_id' => $client->getKey(), 'application_id' => $first->getKey()]);
        Subscription::factory()->create(['client_id' => $client->getKey(), 'application_id' => $second->getKey()]);

        $this->assertEqualsCanonicalizing(
            [$first->getKey(), $second->getKey()],
            $this->resolvedApplicationIds('users', $client->getKey())
        );
    }

    public function test_does_not_resolve_subscriptions_of_other_clients(): void
    {
        $client = Client::factory()->create();
        $other = Client::factory()->create();
        $application = Application::query()->firstOrFail();

        Subscription::factory()->create(['client_id' => $other->getKey(), 'application_id' => $application->getKey()]);

        $this->assertSame([], $this->resolvedApplicationIds('users', $client->getKey()));
    }

    public function test_null_client_resolves_all_subscribers_deduplicated(): void
    {
        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();
        $shared = Application::query()->firstOrFail();

        Subscription::factory()->create(['client_id' => $clientA->getKey(), 'application_id' => $shared->getKey()]);
        Subscription::factory()->create(['client_id' => $clientB->getKey(), 'application_id' => $shared->getKey()]);

        $this->assertSame([$shared->getKey()], $this->resolvedApplicationIds('professions', null));
    }
}
