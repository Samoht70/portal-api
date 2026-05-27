<?php

namespace Functional\Applications\Database\Seeders;

use Functional\Applications\Enums\RoleDefinitionSlug;
use Functional\Applications\Models\RoleDefinition;
use Illuminate\Database\Seeder;

class RoleDefinitionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (RoleDefinitionSlug::cases() as $roleDefinition) {
            RoleDefinition::query()
                ->updateOrCreate(['slug' => $roleDefinition]);
        }
    }
}
