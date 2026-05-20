<?php

namespace Functional\Applications\Providers;

use Functional\Applications\Access\Controls\ApplicationControl;
use Functional\Applications\Access\Controls\PackControl;
use Functional\Applications\Access\Controls\RoleDefinitionControl;
use Functional\Applications\Database\Seeders\ApplicationRoleSeeder;
use Functional\Applications\Database\Seeders\Applications\AutoSeeder;
use Functional\Applications\Database\Seeders\Applications\EssentioSeeder;
use Functional\Applications\Database\Seeders\Applications\ProductivitySeeder;
use Functional\Applications\Database\Seeders\PackSeeder;
use Functional\Applications\Database\Seeders\RoleDefinitionSeeder;
use Functional\Applications\Models\Application;
use Functional\Applications\Models\ApplicationRole;
use Functional\Applications\Models\Pack;
use Functional\Applications\Models\RoleDefinition;
use Functional\Applications\Policies\ApplicationPolicy;
use Functional\Applications\Policies\ApplicationRolePolicy;
use Functional\Applications\Policies\PackPolicy;
use Functional\Applications\Policies\RoleDefinitionPolicy;
use Technical\AccessControl\ControlRegistry;
use Technical\Osdd\GateRegistry;
use Xefi\LaravelOSDD\LayerServiceProvider;

class ApplicationsServiceProvider extends LayerServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

            $this->loadSeeders([
                PackSeeder::class,
                EssentioSeeder::class,
                ProductivitySeeder::class,
//                AutoSeeder::class,
            ], 1);

            $this->loadSeeders([
                RoleDefinitionSeeder::class,
                ApplicationRoleSeeder::class,
            ], 2);
        }

        $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');

        $this->app->make(ControlRegistry::class)->push([
            PackControl::new(),
            ApplicationControl::new(),
            RoleDefinitionControl::new(),
        ]);

        $this->app->make(GateRegistry::class)->push([
            Pack::class => PackPolicy::class,
            Application::class => ApplicationPolicy::class,
            RoleDefinition::class => RoleDefinitionPolicy::class,
            ApplicationRole::class => ApplicationRolePolicy::class,
        ]);

        Pack::enableDeleteTranslationsCascade();
        Application::enableDeleteTranslationsCascade();
    }

    public function register(): void
    {
        //
    }
}
