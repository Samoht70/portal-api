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
            'create global clients',
            'update global clients',
            'delete global clients',
            'restore global clients',
            'force delete global clients',
            'view global sites',
            'create global sites',
            'update global sites',
            'delete global sites',
            'restore global sites',
            'force delete global sites',
            'view global users',
            'create global users',
            'update global users',
            'delete global users',
            'restore global users',
            'force delete global users',
            'view global roles',

            // Client
            'view client sites',
            'update client sites',
            'delete client sites',
            'view client users',
            'create client users',
            'update client users',
            'delete client users',

            // Own
            'view own clients',
            'update own clients',
            'view own sites',
            'view own users',
            'update own users',
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
            'create global clients',
            'update global clients',
            'delete global clients',
            'restore global clients',
            'force delete global clients',
            'view global sites',
            'create global sites',
            'update global sites',
            'delete global sites',
            'restore global sites',
            'force delete global sites',
            'view global users',
            'create global users',
            'update global users',
            'delete global users',
            'restore global users',
            'force delete global users',
            'view global roles',
        ]);

        $administrator = Role::findOrCreate(RoleName::Admin->value);
        $administrator->syncPermissions([
            'view global packs',
            'view global applications',
            'view global role_definitions',
            'view own clients',
            'update own clients',
            'view client sites',
            'create global sites',
            'update client sites',
            'delete client sites',
            'view client users',
            'create global users',
            'update client users',
            'delete client users',
            'view global roles',
        ]);

        $standard = Role::findOrCreate(RoleName::Standard->value);
        $standard->syncPermissions([
            'view global packs',
            'view global applications',
            'view global role_definitions',
            'view own clients',
            'view own sites',
            'view own users',
            'update own users',
            'view global roles',
        ]);
    }
}
