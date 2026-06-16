<?php

use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Laravel\Passport\Http\Controllers\TransientTokenController;
use Technical\OauthServer\Http\Controllers\StatelessAuthorizationController;

/*
|--------------------------------------------------------------------------
| OAuth2 / OIDC protocol routes (root /oauth prefix)
|--------------------------------------------------------------------------
|
| Registered under the `passport.` name + the `oauth` prefix to keep the OIDC
| discovery document resolving (it builds endpoints from those route names).
| Passport's default session-based routes are disabled via Passport::ignoreRoutes().
|
| The token endpoint delegates to Passport's controller unchanged (it is already
| stateless). The authorization endpoint is replaced: a top-level browser
| redirect carries no bearer token, so GET /oauth/authorize bounces the browser
| to the SSO front-end, which then drives the stateless authorize API
| (/api/oauth/authorize) while holding the user's first-party token.
|
*/

Route::post('token', [AccessTokenController::class, 'issueToken'])
    ->middleware('throttle')
    ->name('token');

Route::post('token/refresh', [TransientTokenController::class, 'refresh'])
    ->middleware('auth:api')
    ->name('token.refresh');

Route::get('authorize', [StatelessAuthorizationController::class, 'prompt'])
    ->name('authorizations.authorize');
