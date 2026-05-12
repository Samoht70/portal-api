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

        $permissions = [];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $superAdmin = Role::findOrCreate(RoleName::SuperAdmin->value);
        $superAdmin->syncPermissions([]);

        $administrator = Role::findOrCreate(RoleName::Admin->value);
        $administrator->syncPermissions([]);

        $standard = Role::findOrCreate(RoleName::Standard->value);
        $standard->syncPermissions([]);
    }
}
