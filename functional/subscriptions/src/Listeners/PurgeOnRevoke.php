<?php

namespace Functional\Subscriptions\Listeners;

use Functional\Subscriptions\Events\SubscriptionRevoked;
use Dailyapps\EventDistribution\Contracts\SubscriberResolver;
use Dailyapps\EventDistribution\Jobs\BackfillSubscriber;

/**
 * On a revoked subscription, purge the tenant's state from the application's
 * sync endpoint, when that application has sync enabled.
 */
final readonly class PurgeOnRevoke
{
    public function __construct(private SubscriberResolver $resolver) {}

    public function handle(SubscriptionRevoked $event): void
    {
        $subscriber = $this->resolver->resolveApplication($event->applicationId());

        if ($subscriber === null) {
            return;
        }

        BackfillSubscriber::dispatch($subscriber, $event->clientId(), true);
    }
}
