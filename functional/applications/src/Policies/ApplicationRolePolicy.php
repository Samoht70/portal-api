<?php

namespace Functional\Applications\Policies;

use Functional\Applications\Models\Application;
use Technical\AccessControl\Policies\DependentPolicy;

class ApplicationRolePolicy extends DependentPolicy
{
    protected string $delegateTo = Application::class;
}
