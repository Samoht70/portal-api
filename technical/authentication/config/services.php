<?php

/*
|--------------------------------------------------------------------------
| Third-party services — Microsoft (Azure AD)
|--------------------------------------------------------------------------
|
| Merged into the application's `services` config by the layer provider. Holds
| the Socialite credentials for the Microsoft / Azure AD identity provider used
| by the "Sign in with Microsoft" flow. `tenant` is the Azure AD tenant id, or
| "common" / "organizations" for multi-tenant sign-in.
|
*/

return [
    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI'),
        'tenant' => env('MICROSOFT_TENANT', 'common'),
    ],
];
