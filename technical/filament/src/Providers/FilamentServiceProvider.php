<?php

namespace Technical\Filament\Providers;

use Xefi\LaravelOSDD\LayerServiceProvider;

/** Layer entry point; wires Filament panel registration. */
class FilamentServiceProvider extends LayerServiceProvider
{
    public function register(): void
    {
        $this->app->register(AdminPanelProvider::class);
    }
}
