<?php

namespace Functional\Users\Policies;

use Functional\Users\Models\User;
use Technical\AccessControl\Policies\DependentPolicy;

class UserHoldsApplicationRolePolicy extends DependentPolicy
{
    protected string $delegateTo = User::class;
}
