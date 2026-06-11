<?php

namespace Technical\OauthServer\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passport\Client as PassportClient;
use Laravel\Passport\ClientRepository;

/**
 * Registers a child application as an Authorization Code + PKCE OAuth client,
 * owned by the administrator who creates it (so it surfaces through that user's
 * oauthApps() relationship).
 *
 * Child apps are public clients (no secret): they prove possession of the
 * authorization code with a PKCE verifier instead. The returned client id and
 * the registered redirect URIs are what the child app embeds in its own
 * OAuth/OIDC configuration.
 */
readonly class RegisterChildClient
{
    public function __construct(
        private ClientRepository $clients,
    ) {}

    /**
     * @param  array<int, string>  $redirectUris
     */
    public function handle(Authenticatable $owner, string $name, array $redirectUris): PassportClient
    {
        return $this->clients->createAuthorizationCodeGrantClient(
            name: $name,
            redirectUris: $redirectUris,
            confidential: false,
            user: $owner,
        );
    }
}
