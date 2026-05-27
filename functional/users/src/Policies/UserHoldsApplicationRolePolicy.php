<?php

namespace Functional\Users\Policies;

use Functional\Users\Models\User;
use Functional\Users\Models\UserHoldsApplicationRole;
use Illuminate\Auth\Access\Response;
use Technical\AccessControl\Policies\DependentPolicy;

class UserHoldsApplicationRolePolicy extends DependentPolicy
{
    protected string $delegateTo = User::class;
}
