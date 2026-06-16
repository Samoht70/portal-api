<?php

namespace Technical\OauthServer\Enums;

/**
 * OAuth2 / OpenID Connect scopes advertised to child applications.
 *
 * `openid` opts a child app into the OpenID Connect flow (it then receives an
 * id_token alongside the access token); the remaining scopes gate the claims
 * exposed through the UserInfo endpoint.
 */
enum OAuthScope: string
{
    case OpenId = 'openid';
    case Profile = 'profile';
    case Email = 'email';

    public function description(): string
    {
        return match ($this) {
            self::OpenId => 'Authenticate you and confirm your identity',
            self::Profile => 'Read your name and profile information',
            self::Email => 'Read your email address',
        };
    }

    /**
     * Scopes registered with Passport, keyed by their identifier.
     *
     * @return array<string, string>
     */
    public static function registry(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $scope): array => [$scope->value => $scope->description()])
            ->all();
    }
}
