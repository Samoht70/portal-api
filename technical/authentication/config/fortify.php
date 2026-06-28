<?php

use Laravel\Fortify\Features;

/*
|--------------------------------------------------------------------------
| Laravel Fortify — headless
|--------------------------------------------------------------------------
|
| Fortify provides the identity back-end (registration, password reset and
| email verification). This is an API: there are no Blade views
| ('views' => false) and the actions run on the `api` middleware group against
| the stateless `api` guard.
|
| Fortify's own session-based login controller is NOT used — this layer ships a
| stateless replacement (see LoginController). We keep Fortify for its email
| verification and password reset plumbing.
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
    ],

];
