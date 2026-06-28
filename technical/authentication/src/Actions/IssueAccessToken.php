<?php

namespace Technical\Authentication\Actions;

use Functional\Users\Models\User;

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
