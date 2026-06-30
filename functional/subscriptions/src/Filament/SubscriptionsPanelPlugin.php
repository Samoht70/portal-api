<?php

namespace Functional\Subscriptions\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Functional\Subscriptions\Filament\Resources\Subscriptions\SubscriptionResource;

class SubscriptionsPanelPlugin implements Plugin
{
    public function getId(): string
    {
        return 'subscriptions';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            SubscriptionResource::class,
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
