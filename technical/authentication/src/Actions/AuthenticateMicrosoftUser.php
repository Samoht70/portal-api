<?php

namespace Technical\Authentication\Actions;

use Functional\Users\Models\User;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Resolves the local user behind a Microsoft (Azure AD) identity.
 *
 * Per the platform policy there is NO auto-provisioning: a Microsoft sign-in
 * only authenticates a user that already exists. When no local account matches
 * the Azure email, this returns null and the caller reports that the user does
 * not exist — it never creates an account on the fly.
 *
 * Because Microsoft asserts the email itself, a matched-but-unverified local
 * account is marked verified at this point.
 */
class AuthenticateMicrosoftUser
{
    public function handle(SocialiteUser $microsoftUser): ?User
    {
        $email = $microsoftUser->getEmail();

        if ($email === null) {
            return null;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            return null;
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return $user;
    }
}
