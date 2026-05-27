<?php

namespace Functional\Applications\Policies;

use Functional\Applications\Access\Controls\RoleDefinitionControl;
use Lomkit\Access\Policies\ControlledPolicy;

class RoleDefinitionPolicy extends ControlledPolicy
{
    protected string $control = RoleDefinitionControl::class;
}
