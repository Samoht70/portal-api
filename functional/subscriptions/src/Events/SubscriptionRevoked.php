<?php

namespace Functional\Subscriptions\Events;

use Functional\Subscriptions\Events\Concerns\CarriesSubscriptionScope;
use Functional\Subscriptions\Models\Subscription;

/**
 * Fired when an org's subscription to an app is revoked: triggers a purge of the
 * tenant's state from that subscriber.
 */
final class SubscriptionRevoked
{
    use CarriesSubscriptionScope;

    public function __construct(public readonly Subscription $subscription) {}
}
