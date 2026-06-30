<?php

namespace Functional\Users\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Functional\Users\Filament\Resources\Users\UserResource;

class UsersPanelPlugin implements Plugin
{
    public function getId(): string
    {
        return 'users';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            UserResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }
}
