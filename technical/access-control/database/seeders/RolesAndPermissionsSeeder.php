<?php

namespace Technical\AccessControl\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Global perimeter
            'view global field_definitions',
            'view global card_formats',

            // Client perimeter
            'view client sites',
            'view client users',
            'update client users',
            'view client templates',
            'update client templates',
            'delete client templates',
            'view client cards',
            'update client cards',
            'delete client cards',

            // Own perimeter
            'view own clients',
            'view own sites',
            'view own users',
            'update own users',
            'view own templates',
            'view own cards',
            'update own cards',
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['name' => $permission]);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $administrator = Role::updateOrCreate(['name' => 'Administrator']);
        $administrator->syncPermissions([
            'view global field_definitions',
            'view global card_formats',
            'view own clients',
            'view client sites',
            'view client users',
            'update client users',
            'view client templates',
            'update client templates',
            'delete client templates',
            'view client cards',
            'update client cards',
        ]);

        $standard = Role::updateOrCreate(['name' => 'Standard']);
        $standard->syncPermissions([
            'view global field_definitions',
            'view global card_formats',
            'view own clients',
            'view own sites',
            'view own users',
            'update own users',
            'view own templates',
            'view own cards',
            'update own cards',
        ]);
    }
}
