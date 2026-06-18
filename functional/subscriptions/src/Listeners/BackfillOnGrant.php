<?php

namespace Functional\Subscriptions\Listeners;

use Functional\Subscriptions\Events\SubscriptionGranted;
use Dailyapps\EventDistribution\Contracts\SubscriberResolver;
use Dailyapps\EventDistribution\Jobs\BackfillSubscriber;

/**
 * On a new subscription, backfill the tenant's current state to the application's
 * sync endpoint, when that application has sync enabled.
 */
final readonly class BackfillOnGrant
{
    public function __construct(private SubscriberResolver $resolver) {}

    public function handle(SubscriptionGranted $event): void
    {
        $subscriber = $this->resolver->resolveApplication($event->applicationId());

        if ($subscriber === null) {
            return;
        }

        BackfillSubscriber::dispatch($subscriber, $event->clientId(), false);
    }
}
