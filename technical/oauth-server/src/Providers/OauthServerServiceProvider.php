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
            // Passport 13 only publishes its migrations; the layer vendors and
            // loads them so it owns its oauth_* schema and stays self-contained.
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        }

        $this->bootRouting();
        $this->configurePassport();
    }

    public function register(): void
    {
        // Own the stateless auth guards and the Passport configuration. Done in
        // register() so the override is in place before Passport boots.
        $this->overrideConfigFrom(__DIR__.'/../../config/auth.php', 'auth');
        $this->overrideConfigFrom(__DIR__.'/../../config/passport.php', 'passport');

        // Take over Passport's routing. Passport's own /oauth/authorize is
        // session + StatefulGuard based, which is incompatible with a stateless
        // API; we register stateless, token-guarded replacements instead.
        Passport::ignoreRoutes();
    }

    private function bootRouting(): void
    {
        // Protocol routes at the root /oauth prefix (token, token-guarded
        // authorize), keeping the `passport.*` route names so the OIDC
        // discovery document keeps resolving them.
        Route::group([
            'as' => 'passport.',
            'prefix' => config('passport.path', 'oauth'),
            'middleware' => config('passport.middleware', []),
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../../routes/oauth.php');
        });

        // First-party admin + the stateless authorize API, under /api.
        $this->withRouting(
            api: __DIR__.'/../../routes/api.php',
        );
    }

    private function configurePassport(): void
    {
        // Advertise the OIDC + profile scopes to child applications.
        Passport::tokensCan(OAuthScope::registry());
        Passport::setDefaultScope([OAuthScope::OpenId->value]);

        // Short-lived access tokens, longer-lived refresh tokens. Child apps
        // refresh via the refresh_token grant rather than re-prompting the user.
        Passport::tokensExpireIn(Carbon::now()->addSeconds(config('passport.tokens.access_token_ttl')));
        Passport::refreshTokensExpireIn(Carbon::now()->addSeconds(config('passport.tokens.refresh_token_ttl')));
        Passport::personalAccessTokensExpireIn(Carbon::now()->addSeconds(config('passport.tokens.personal_access_token_ttl')));
    }
}
