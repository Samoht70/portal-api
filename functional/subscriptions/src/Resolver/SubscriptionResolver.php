<?php

namespace Functional\Subscriptions\Resolver;

use Functional\Subscriptions\Models\Subscription;
use Technical\EventDistribution\Contracts\SubscriberResolver;
use Technical\EventDistribution\Values\Subscriber;

final readonly class SubscriptionResolver implements SubscriberResolver
{
    /**
     * Resolve the subscribers that should receive an event.
     *
     * The $aggregateType is accepted for forward-compatibility but is not yet
     * used to filter: every subscriber receives the whole identity/org core.
     *
     * When $clientId is null the event is global, so all subscribers are
     * returned, deduped by application.
     */
    public function resolve(string $aggregateType, ?string $clientId): iterable
    {
        $clientForeignKey = (new Subscription)->client()->getForeignKeyName();
        $applicationForeignKey = (new Subscription)->application()->getForeignKeyName();

        return Subscription::query()
            ->when($clientId !== null, fn ($query) => $query->where($clientForeignKey, $clientId))
            ->distinct()
            ->pluck($applicationForeignKey)
            ->map(fn (string $applicationId) => new Subscriber($applicationId))
            ->all();
    }
}
