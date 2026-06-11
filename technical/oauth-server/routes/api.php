<?php

use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Technical\OauthServer\Http\Controllers\OAuthClientController;
use Technical\OauthServer\Http\Controllers\StatelessAuthorizationController;

/*
|--------------------------------------------------------------------------
| OAuth server API routes (under /api)
|--------------------------------------------------------------------------
|
| The OIDC package adds the protocol surface (/.well-known/openid-configuration,
| /oauth/jwks, UserInfo) and the root /oauth routes (token, authorize redirect)
| live in routes/oauth.php — neither is redefined here.
|
| This file holds the token-guarded surface the SSO front-end consumes: the
| stateless authorize handshake (consent details + code minting) and the admin
| endpoints to register / revoke child-application clients. Client admin is
| gated by a spatie permission (permissions, not roles).
|
*/

Route::prefix('oauth/authorize')
    ->middleware('auth:api')
    ->group(function () {
        Route::get('/', [StatelessAuthorizationController::class, 'show'])->name('oauth.authorize.show');
        Route::post('/', [StatelessAuthorizationController::class, 'authorize'])->name('oauth.authorize.approve');
    });

Route::prefix('oauth/clients')
    ->middleware('auth:api')
    ->group(function () {
        Route::get('/', [OAuthClientController::class, 'index'])
            ->middleware(PermissionMiddleware::using('view global oauth_clients'))
            ->name('oauth.clients.index');
        Route::post('/', [OAuthClientController::class, 'store'])
            ->middleware(PermissionMiddleware::using('create global oauth_clients'))
            ->name('oauth.clients.store');
        Route::delete('{client}', [OAuthClientController::class, 'destroy'])
            ->middleware(PermissionMiddleware::using('delete global oauth_clients'))
            ->name('oauth.clients.destroy');
    });
