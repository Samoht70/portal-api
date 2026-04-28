<?php

namespace Technical\Authentication\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class JwtUser extends Authenticatable implements JWTSubject
{
    const string SSO_SHARED_COLUMN = 'email';

    public function getJWTIdentifier(): string
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'sso_id' => $this->{self::SSO_SHARED_COLUMN},
        ];
    }
}
