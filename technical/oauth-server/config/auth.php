<?php

use Functional\Users\Models\User;

/*
|--------------------------------------------------------------------------
| Authentication configuration — STATELESS
|--------------------------------------------------------------------------
|
| This layer owns the authentication guards because it owns the access-token
| machinery (Laravel Passport). The whole platform is a stateless API: there
| is no session guard for application access. Every protected endpoint is
| guarded by `auth:api`, which resolves an RSA-signed Passport JWT bearer
| token to the authenticated user.
|
| The User model is referenced as a string (::class does not autoload it),
| so this technical layer does not gain a compile-time dependency on the
| functional/users layer.
|
*/

return [
    'defaults' => [
        'guard' => env('AUTH_GUARD', 'api'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    'guards' => [
        'api' => [
            'driver' => 'passport',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),
];
