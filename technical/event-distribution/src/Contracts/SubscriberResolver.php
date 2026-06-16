<?php

namespace Technical\EventDistribution\Contracts;

use Technical\EventDistribution\Values\Subscriber;

interface SubscriberResolver
{
    /**
     * Resolve which subscribers must receive an event for the given aggregate type
     * and tenant scope. A null $clientId means a global (untenanted) event → all subscribers.
     *
     * @return iterable<Subscriber>
     */
    public function resolve(string $aggregateType, ?string $clientId): iterable;
}
