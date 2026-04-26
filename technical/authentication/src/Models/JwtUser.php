<?php

namespace Technical\Authentication\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class JwtUser extends Authenticatable implements JWTSubject
{
    public function getJWTIdentifier(): string
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'id' => $this->getJWTIdentifier(),
        ];
    }
}
