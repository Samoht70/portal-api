<?php

namespace Functional\Users\Policies;

use Functional\Users\Access\Controls\UserControl;
use Lomkit\Access\Policies\ControlledPolicy;

class UserPolicy extends ControlledPolicy
{
    protected string $control = UserControl::class;
}
