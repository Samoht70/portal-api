<?php

namespace Technical\Authentication\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

/**
 * Validates email / password credentials against the `users` provider without
 * ever establishing a session — the platform is stateless, so we resolve and
 * verify the user through the provider directly instead of logging them in.
 */
class AttemptCredentials
{
    public function handle(string $email, string $password): ?Authenticatable
    {
        $provider = Auth::createUserProvider('users');

        $user = $provider->retrieveByCredentials(['email' => $email]);

        if ($user === null || ! $provider->validateCredentials($user, ['password' => $password])) {
            return null;
        }

        return $user;
    }
}
