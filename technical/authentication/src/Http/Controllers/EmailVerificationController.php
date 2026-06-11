<?php

namespace Technical\Authentication\Http\Controllers;

use Functional\Users\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Stateless email verification.
 *
 * The signed link e-mailed to the user points at `verify`, which requires no
 * authentication (the signature IS the proof) and finishes by redirecting the
 * browser to the front-end. `resend` is token-guarded and re-issues the link.
 */
class EmailVerificationController extends Controller
{
    public function verify(User $user, string $hash): RedirectResponse
    {
        $email = $user->getEmailForVerification();

        if (! $this->verifyEmail($email, $hash)) {
            throw new NotFoundHttpException;
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect()->away(
            rtrim((string) config('app.frontend_url'), '/').'/login?verified=1'
        );
    }

    public function resend(#[CurrentUser] User $user): Response
    {
        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->noContent();
    }

    private function verifyEmail(string $email, string $hash): bool
    {
        return hash_equals(sha1($email), $hash);
    }
}
