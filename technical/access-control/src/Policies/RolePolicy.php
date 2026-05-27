<?php

namespace Technical\AccessControl\Policies;

use Lomkit\Access\Policies\ControlledPolicy;
use Technical\AccessControl\Access\Controls\RoleControl;

class RolePolicy extends ControlledPolicy
{
    protected string $control = RoleControl::class;
}
