<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['name' => $permission]);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $superAdmin = Role::updateOrCreate(['name' => 'super-admin']);
        $administrator = Role::updateOrCreate(['name' => 'administrator']);
        $standard = Role::updateOrCreate(['name' => 'standard']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
