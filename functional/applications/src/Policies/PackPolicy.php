<?php

namespace Functional\Applications\Policies;

use Functional\Applications\Access\Controls\PackControl;
use Lomkit\Access\Policies\ControlledPolicy;

class PackPolicy extends ControlledPolicy
{
    protected string $control = PackControl::class;
}
