<?php

namespace Functional\Applications\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Functional\Applications\Filament\Resources\Applications\ApplicationResource;
use Functional\Applications\Filament\Resources\Packs\PackResource;

/** Registers Pack and Application resources in the admin panel. */
class ApplicationsPanelPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'applications';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            PackResource::class,
            ApplicationResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
