<?php

namespace Functional\Subscriptions\Listeners;

use Dailyapps\EventDistribution\Contracts\SyncDirectory;
use Functional\Subscriptions\Listeners\Concerns\PushesSubscriptionControlEvent;
use Functional\Subscriptions\Models\Subscription;

final readonly class PurgeOnRevoke
{
    use PushesSubscriptionControlEvent;

    public const string EVENT_TYPE = 'subscription.revoked';

    public function __construct(private SyncDirectory $directory) {}

    public function handle(Subscription $subscription): void
    {
        $this->pushControlEvent($subscription, self::EVENT_TYPE);
    }
}
