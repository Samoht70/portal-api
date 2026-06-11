<?php

namespace Technical\Authentication\Http\Controllers;

use Functional\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Technical\Authentication\Actions\IssueAccessToken;
use Technical\Authentication\Enums\AuthErrorCode;
use Technical\Authentication\Http\Requests\TwoFactorChallengeRequest;
use Technical\Authentication\Support\TwoFactorPendingToken;

/**
 * Second step of the stateless two-factor login: exchange the pending token
 * plus a TOTP code (or a single-use recovery code) for a real access token.
 */
class TwoFactorChallengeController extends Controller
{
    public function __construct(
        private readonly TwoFactorPendingToken $pendingToken,
        private readonly TwoFactorAuthenticationProvider $twoFactor,
        private readonly IssueAccessToken $issueAccessToken,
    ) {}

    public function store(TwoFactorChallengeRequest $request): JsonResponse
    {
        $userId = $this->pendingToken->resolve($request->validated('pending_token'));

        if ($userId === null) {
            return response()->json(
                ['error' => AuthErrorCode::InvalidPendingToken],
                SymfonyResponse::HTTP_UNAUTHORIZED,
            );
        }

        /** @var User|null $user */
        $user = User::query()->find($userId);

        if ($user === null || ! $this->passesChallenge($user, $request)) {
            return response()->json(
                ['error' => AuthErrorCode::InvalidTwoFactorCode],
                SymfonyResponse::HTTP_UNAUTHORIZED,
            );
        }

        return response()->json($this->issueAccessToken->handle($user));
    }

    private function passesChallenge(User $user, TwoFactorChallengeRequest $request): bool
    {
        if (($code = $request->validated('code')) !== null) {
            return $this->twoFactor->verify(decrypt($user->two_factor_secret), $code);
        }

        return $this->consumeRecoveryCode($user, (string) $request->validated('recovery_code'));
    }

    private function consumeRecoveryCode(User $user, string $recoveryCode): bool
    {
        if (! in_array($recoveryCode, $user->recoveryCodes(), true)) {
            return false;
        }

        // Recovery codes are single-use: swap the consumed one for a fresh code.
        $user->replaceRecoveryCode($recoveryCode);

        return true;
    }
}
