<?php

namespace Functional\Subscriptions\Listeners\Concerns;

use Functional\Subscriptions\Models\Subscription;

/**
 * Reads a subscription's client/application ids via its relations for the sync listeners.
 */
trait CarriesSubscriptionScope
{
    private function clientId(Subscription $subscription): string
    {
        return $subscription->getAttribute($subscription->client()->getForeignKeyName());
    }

    private function applicationId(Subscription $subscription): string
    {
        return $subscription->getAttribute($subscription->application()->getForeignKeyName());
    }
}
