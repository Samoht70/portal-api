<?php

namespace Technical\AccessControl\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Technical\AccessControl\Enums\RoleName;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Global
            'view global packs',
            'view global applications',
            'view global role_definitions',
            'view global clients',
            'view global sites',
            'view global users',

            // Client
            'view client sites',
            'view client users',

            // Own
            'view own clients',
            'view own sites',
            'view own users',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $superAdmin = Role::findOrCreate(RoleName::SuperAdmin->value);
        $superAdmin->syncPermissions([
            'view global packs',
            'view global applications',
            'view global role_definitions',
            'view global clients',
            'view global sites',
            'view global users',
        ]);

        $administrator = Role::findOrCreate(RoleName::Admin->value);
        $administrator->syncPermissions([
            'view global packs',
            'view global applications',
            'view global role_definitions',
            'view own clients',
            'view client sites',
            'view client users',
        ]);

        $standard = Role::findOrCreate(RoleName::Standard->value);
        $standard->syncPermissions([
            'view global packs',
            'view global applications',
            'view global role_definitions',
            'view own clients',
            'view own sites',
            'view own users',
        ]);
    }
}
