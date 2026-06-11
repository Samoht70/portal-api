<?php

use Functional\Users\Models\User;

/*
|--------------------------------------------------------------------------
| Laravel Passport — authorization server
|--------------------------------------------------------------------------
|
| Passport issues RSA-signed JWT access tokens. Child applications validate
| them statelessly against the public key exposed at the JWKS endpoint — no
| introspection round-trip required. Keys are injected from the environment
| (PASSPORT_PRIVATE_KEY / PASSPORT_PUBLIC_KEY) rather than written to disk so
| every container / replica signs and verifies with the same keypair.
|
*/

return [
    'guard' => 'api',

    'connection' => env('PASSPORT_CONNECTION'),

    // First-party client used by the mother application's own SSO front-end
    // (Authorization Code + PKCE). Kept in env so it is environment-specific.
    'first_party' => [
        'client_id' => env('PASSPORT_SPA_CLIENT_ID'),
    ],

    'tokens' => [
        // Access token lifetime — short-lived, refreshed via the refresh-token grant.
        'access_token_ttl' => (int) env('PASSPORT_ACCESS_TOKEN_TTL', 3600),
        'refresh_token_ttl' => (int) env('PASSPORT_REFRESH_TOKEN_TTL', 60 * 60 * 24 * 30),
        'personal_access_token_ttl' => (int) env('PASSPORT_PERSONAL_ACCESS_TOKEN_TTL', 3600),
    ],

    'models' => [
        'user' => User::class,
    ],
];
