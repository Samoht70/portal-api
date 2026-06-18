<?php

namespace Functional\Subscriptions\Events\Concerns;

use Functional\Subscriptions\Models\Subscription;

/**
 * Exposes the tenant scope (client + application) carried by a subscription
 * lifecycle event, resolving foreign-key columns through the model relations.
 * The using event must declare the public readonly Subscription $subscription.
 */
trait CarriesSubscriptionScope
{
    public function clientId(): string
    {
        return $this->subscription->getAttribute(
            $this->subscription->client()->getForeignKeyName()
        );
    }

    public function applicationId(): string
    {
        return $this->subscription->getAttribute(
            $this->subscription->application()->getForeignKeyName()
        );
    }
}
