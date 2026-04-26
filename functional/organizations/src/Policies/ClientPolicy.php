<?php

namespace Functional\Organizations\Policies;

use Functional\Organizations\Access\Controls\ClientControl;
use Lomkit\Access\Policies\ControlledPolicy;

class ClientPolicy extends ControlledPolicy
{
    protected string $control = ClientControl::class;
}
