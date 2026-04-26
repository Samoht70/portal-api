<?php

namespace Functional\Organizations\Policies;

use Functional\Organizations\Access\Controls\SiteControl;
use Lomkit\Access\Policies\ControlledPolicy;

class SitePolicy extends ControlledPolicy
{
    protected string $control = SiteControl::class;
}
