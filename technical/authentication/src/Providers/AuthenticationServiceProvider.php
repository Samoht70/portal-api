<?php

namespace Technical\Authentication\Providers;

use Xefi\LaravelOSDD\LayerServiceProvider;

class AuthenticationServiceProvider extends LayerServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
    }

    public function register(): void
    {
        $this->overrideConfigFrom(__DIR__.'/../../config/auth.php', 'auth');
    }
}
