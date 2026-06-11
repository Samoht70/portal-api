<?php

use Laravel\Fortify\Features;

/*
|--------------------------------------------------------------------------
| Laravel Fortify — headless
|--------------------------------------------------------------------------
|
| Fortify provides the identity back-end (registration, password reset, email
| verification and two-factor authentication). This is an API: there are no
| Blade views ('views' => false) and the actions run on the `api` middleware
| group against the stateless `api` guard.
|
| Fortify's own session-based login / two-factor-challenge controllers are NOT
| used — this layer ships stateless replacements (see LoginController and
| TwoFactorChallengeController). We keep Fortify for its 2FA secret / recovery
| code machinery, email verification and password reset plumbing.
|
*/

return [

    'guard' => 'api',

    'middleware' => ['api'],

    'auth_middleware' => 'auth',

    'username' => 'email',

    'email' => 'email',

    'lowercase_usernames' => true,

    'home' => '/',

    'prefix' => 'api/auth',

    'domain' => null,

    'views' => false,

    'features' => [
        Features::registration(),
        Features::resetPasswords(),
        Features::emailVerification(),
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]),
    ],

];
