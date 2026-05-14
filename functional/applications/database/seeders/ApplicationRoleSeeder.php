<?php

namespace Functional\Applications\Database\Seeders;

use Functional\Applications\Enums\ApplicationSlug;
use Functional\Applications\Models\RoleDefinition;
use Illuminate\Database\Seeder;

class ApplicationRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (ApplicationSlug::cases() as $applicationSlug) {
            $application = $applicationSlug->toModel();

            $roleIds = RoleDefinition::query()
                ->whereIn('slug', $applicationSlug->roles())
                ->pluck('id');

            $application->roles()->sync($roleIds);
        }
    }
}
