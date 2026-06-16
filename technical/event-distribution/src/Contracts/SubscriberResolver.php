<?php

namespace Technical\EventDistribution\Contracts;

interface SubscriberResolver
{
    /**
     * Resolve which subscribers must receive an event for the given aggregate type
     * and tenant scope. A null $clientId means a global (untenanted) event → all subscribers.
     *
     * @return iterable<\Technical\EventDistribution\Values\Subscriber>
     */
    public function resolve(string $aggregateType, ?string $clientId): iterable;
}
