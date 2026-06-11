<?php

namespace Technical\OauthServer\Providers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Passport;
use Technical\OauthServer\Enums\OAuthScope;
use Xefi\LaravelOSDD\LayerServiceProvider;

class OauthServerServiceProvider extends LayerServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        }

        $this->bootRouting();
        $this->configurePassport();
    }

    public function register(): void
    {
        $this->overrideConfigFrom(__DIR__.'/../../config/auth.php', 'auth');
        $this->overrideConfigFrom(__DIR__.'/../../config/passport.php', 'passport');

        Passport::ignoreRoutes();
    }

    private function bootRouting(): void
    {
        Route::group([
            'as' => 'passport.',
            'prefix' => config('passport.path', 'oauth'),
            'middleware' => config('passport.middleware', []),
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../../routes/oauth.php');
        });

        $this->withRouting(
            api: __DIR__.'/../../routes/api.php',
        );
    }

    private function configurePassport(): void
    {
        Passport::tokensCan(OAuthScope::registry());
        Passport::defaultScopes([OAuthScope::OpenId->value]);

        config(['openid.passport.tokens_can' => OAuthScope::registry()]);

        Passport::tokensExpireIn(Carbon::now()->addSeconds(config('passport.tokens.access_token_ttl')));
        Passport::refreshTokensExpireIn(Carbon::now()->addSeconds(config('passport.tokens.refresh_token_ttl')));
        Passport::personalAccessTokensExpireIn(Carbon::now()->addSeconds(config('passport.tokens.personal_access_token_ttl')));
    }
}
