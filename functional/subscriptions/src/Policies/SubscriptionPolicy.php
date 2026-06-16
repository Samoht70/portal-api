<?php

namespace Functional\Subscriptions\Policies;

use Functional\Subscriptions\Access\Controls\SubscriptionControl;
use Lomkit\Access\Policies\ControlledPolicy;

class SubscriptionPolicy extends ControlledPolicy
{
    protected string $control = SubscriptionControl::class;
}
