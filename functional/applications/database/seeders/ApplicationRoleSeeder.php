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
            $defaultSlug = $applicationSlug->defaultRole();

            $roles = RoleDefinition::query()
                ->whereIn('slug', $applicationSlug->roles())
                ->get(['id', 'slug']);

            $application->roles()->createMany(
                collect($roles)
                    ->map(
                        fn (RoleDefinition $role) => ['role_definition_id' => $role->getKey(), 'is_default' => $role->slug === $defaultSlug]
                    )
            );
        }
    }
}
