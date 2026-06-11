<?php

namespace Technical\Authentication\Http\Controllers;

use Functional\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Technical\Authentication\Actions\AuthenticateMicrosoftUser;
use Technical\Authentication\Actions\IssueAccessToken;
use Technical\Authentication\Enums\AuthErrorCode;
use Technical\Authentication\Support\TwoFactorPendingToken;

/**
 * "Sign in with Microsoft" (Azure AD), driven statelessly — Socialite runs in
 * stateless() mode so no session is needed. There is no auto-provisioning: an
 * unknown email is rejected with user_not_found.
 *
 * NOTE: the callback returns the token as JSON for an SPA-driven (popup / API)
 * exchange. If the front-end uses a full-page redirect instead, swap this for a
 * redirect to a configured FRONTEND_URL carrying a one-time exchange code
 * (never the token itself) — see technical/ARCHITECTURE-SSO.md §B.
 */
class MicrosoftController extends Controller
{
    public function __construct(
        private readonly AuthenticateMicrosoftUser $authenticateMicrosoftUser,
        private readonly IssueAccessToken $issueAccessToken,
        private readonly TwoFactorPendingToken $pendingToken,
    ) {}

    public function redirect(): JsonResponse
    {
        $url = Socialite::driver('microsoft')
            ->stateless()
            ->redirect()
            ->getTargetUrl();

        return response()->json(['redirect_url' => $url]);
    }

    public function callback(): JsonResponse
    {
        $microsoftUser = Socialite::driver('microsoft')->stateless()->user();

        /** @var User|null $user */
        $user = $this->authenticateMicrosoftUser->handle($microsoftUser);

        if ($user === null) {
            return response()->json(
                ['error' => AuthErrorCode::UserNotFound],
                SymfonyResponse::HTTP_NOT_FOUND,
            );
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return response()->json([
                'two_factor' => true,
                'pending_token' => $this->pendingToken->issue($user->getKey()),
                'expires_in' => $this->pendingToken->ttlSeconds(),
            ]);
        }

        return response()->json($this->issueAccessToken->handle($user));
    }
}
