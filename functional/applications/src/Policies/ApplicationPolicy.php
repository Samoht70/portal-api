<?php

namespace Functional\Applications\Policies;

use Functional\Applications\Access\Controls\ApplicationControl;
use Lomkit\Access\Policies\ControlledPolicy;

class ApplicationPolicy extends ControlledPolicy
{
    protected string $control = ApplicationControl::class;
}
