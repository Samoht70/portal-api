<?php

namespace Functional\Subscriptions\Listeners;

use Functional\Subscriptions\Models\Subscription;
use Functional\Subscriptions\Sync\SubscriptionControlEmitter;

final readonly class PurgeOnRevoke
{
    public const string EVENT_TYPE = 'subscription.revoked';

    public function __construct(private SubscriptionControlEmitter $emitter) {}

    public function handle(Subscription $subscription): void
    {
        $this->emitter->emit($subscription, self::EVENT_TYPE);
    }
}
