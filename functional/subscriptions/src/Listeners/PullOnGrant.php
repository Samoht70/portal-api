<?php

namespace Functional\Subscriptions\Listeners;

use Functional\Subscriptions\Models\Subscription;
use Functional\Subscriptions\Sync\SubscriptionControlEmitter;

/**
 * Pushes a `subscription.granted` control event so the new subscriber pulls that tenant now.
 */
final readonly class PullOnGrant
{
    public const string EVENT_TYPE = 'subscription.granted';

    public function __construct(private SubscriptionControlEmitter $emitter) {}

    public function handle(Subscription $subscription): void
    {
        $this->emitter->emit($subscription, self::EVENT_TYPE);
    }
}
