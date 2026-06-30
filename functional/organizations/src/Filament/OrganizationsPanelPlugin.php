<?php

namespace Functional\Organizations\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Functional\Organizations\Filament\Resources\Clients\ClientResource;
use Functional\Organizations\Filament\Resources\Sites\SiteResource;

class OrganizationsPanelPlugin implements Plugin
{
    public function getId(): string
    {
        return 'organizations';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            ClientResource::class,
            SiteResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
    }

    public static function make(): static
    {
        return app(static::class);
    }
}
