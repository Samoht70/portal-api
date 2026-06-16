<?php

namespace Technical\Authentication\Actions;

use Functional\Users\Models\User;

/**
 * Issues a stateless access token for a first-party authenticated user (the
 * mother app's own SSO front-end). This is the single seam through which every
 * first-party login flow — credentials, two-factor challenge, Microsoft —
 * obtains its token, so the response contract stays consistent.
 *
 * The token is an RSA-signed Passport JWT; resource servers validate it
 * statelessly against the JWKS endpoint.
 */
class IssueAccessToken
{
    /**
     * @return array{access_token: string, token_type: string, expires_in: int}
     */
    public function handle(User $user): array
    {
        $token = $user->createToken('sso-frontend');

        return [
            'access_token' => $token->accessToken,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('passport.tokens.access_token_ttl'),
        ];
    }
}
