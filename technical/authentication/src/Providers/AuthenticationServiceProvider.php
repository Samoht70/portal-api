<?php

namespace Technical\Authentication\Providers;

use Xefi\LaravelOSDD\LayerServiceProvider;

class AuthenticationServiceProvider extends LayerServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');

        $this->overrideConfigFrom(__DIR__.'/../../config/auth.php', 'auth');
        $this->overrideConfigFrom(__DIR__.'/../../config/jwt.php', 'jwt');
    }

    public function register(): void
    {
        //
    }
}
