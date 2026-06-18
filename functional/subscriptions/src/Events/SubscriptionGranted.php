<?php

namespace Functional\Subscriptions\Events;

use Functional\Subscriptions\Events\Concerns\CarriesSubscriptionScope;
use Functional\Subscriptions\Models\Subscription;

/**
 * Fired when an org subscribes to an app: triggers a targeted backfill of the
 * tenant's current state to that subscriber.
 */
final class SubscriptionGranted
{
    use CarriesSubscriptionScope;

    public function __construct(public readonly Subscription $subscription) {}
}
