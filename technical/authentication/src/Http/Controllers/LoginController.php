<?php

namespace Technical\Authentication\Http\Controllers;

use Functional\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Technical\Authentication\Actions\AttemptCredentials;
use Technical\Authentication\Actions\IssueAccessToken;
use Technical\Authentication\Enums\AuthErrorCode;
use Technical\Authentication\Http\Requests\LoginRequest;
use Technical\Authentication\Support\TwoFactorPendingToken;

/**
 * First-party stateless login with email / password.
 *
 * Outcomes:
 *  - Bad credentials → 401 invalid_credentials
 *  - Unverified email → 403 email_unverified (verification re-sent)
 *  - Two-factor enabled → 200 { two_factor: true, pending_token }
 *  - Success → 200 { access_token, ... }
 */
class LoginController extends Controller
{
    public function __construct(
        private readonly AttemptCredentials    $attemptCredentials,
        private readonly IssueAccessToken      $issueAccessToken,
        private readonly TwoFactorPendingToken $pendingToken,
    )
    {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->attemptCredentials->handle(
            $request->validated('email'),
            $request->validated('password'),
        );

        if ($user === null) {
            return response()->json(
                ['error' => AuthErrorCode::InvalidCredentials],
                SymfonyResponse::HTTP_UNAUTHORIZED,
            );
        }

        if (!$user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();

            return response()->json(
                ['error' => AuthErrorCode::EmailUnverified],
                SymfonyResponse::HTTP_FORBIDDEN,
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
