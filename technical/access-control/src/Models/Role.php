<?php

namespace Technical\AccessControl\Models;

use Spatie\Permission\Models\Role as SpatieRole;
use Technical\AccessControl\Enums\RoleName;

class Role extends SpatieRole
{
    protected function casts(): array
    {
        return [
            'name' => RoleName::class,
        ];
    }
}
