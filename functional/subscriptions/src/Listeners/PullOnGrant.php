<?php

namespace Functional\Subscriptions\Listeners;

use Dailyapps\EventDistribution\Contracts\SyncDirectory;
use Functional\Subscriptions\Listeners\Concerns\PushesSubscriptionControlEvent;
use Functional\Subscriptions\Models\Subscription;

/**
 * Pushes a `subscription.granted` control event so the new subscriber pulls that tenant now.
 */
final readonly class PullOnGrant
{
    use PushesSubscriptionControlEvent;

    public const string EVENT_TYPE = 'subscription.granted';

    public function __construct(private SyncDirectory $directory) {}

    public function handle(Subscription $subscription): void
    {
        $this->pushControlEvent($subscription, self::EVENT_TYPE);
    }
}
