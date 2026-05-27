<?php

namespace Technical\AccessControl\Models;

use Lomkit\Access\Controls\HasControl;
use Spatie\Permission\Models\Role as SpatieRole;
use Technical\AccessControl\Enums\RoleName;

class Role extends SpatieRole
{
    use HasControl;

    protected function casts(): array
    {
        return [
            'name' => RoleName::class,
        ];
    }
}
