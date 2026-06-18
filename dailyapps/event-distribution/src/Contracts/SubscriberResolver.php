<?php

namespace Dailyapps\EventDistribution\Contracts;

use Dailyapps\EventDistribution\Values\Subscriber;

interface SubscriberResolver
{
    /**
     * Resolve which subscribers must receive an event for the given aggregate type
     * and tenant scope. A null $clientId means a global (untenanted) event → all subscribers.
     *
     * @return iterable<Subscriber>
     */
    public function resolve(string $aggregateType, ?string $clientId): iterable;

    /**
     * Resolve a single Application's delivery coordinates, independent of any
     * subscription (used to backfill on grant and purge on revoke). Returns null
     * when the Application has no sync endpoint or its sync is disabled.
     */
    public function resolveApplication(string $applicationId): ?Subscriber;
}
