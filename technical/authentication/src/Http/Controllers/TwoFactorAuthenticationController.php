<?php

namespace Technical\Authentication\Http\Controllers;

use Functional\Users\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

/**
 * Stateless two-factor enrolment, guarded by `auth:api`. Reuses Fortify's
 * guard-free actions; enrolment requires explicit confirmation before 2FA
 * becomes active (config fortify.features twoFactorAuthentication.confirm).
 *
 *  - Store enable 2FA, return the QR code + secret to register an authenticator
 *  - Confirm validate the first TOTP code, activate 2FA, return recovery codes
 *  - Recovery regenerate recovery codes
 *  - Destroy disable 2FA
 */
class TwoFactorAuthenticationController extends Controller
{
    public function store(#[CurrentUser] User $user, EnableTwoFactorAuthentication $enable): JsonResponse
    {
        $enable($user);

        return response()->json([
            'svg' => $user->twoFactorQrCodeSvg(),
            'secret' => decrypt($user->two_factor_secret),
        ]);
    }

    public function confirm(Request $request, #[CurrentUser] User $user, ConfirmTwoFactorAuthentication $confirm): JsonResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        $confirm($user, (string) $request->string('code'));

        return response()->json(['recovery_codes' => $user->recoveryCodes()]);
    }

    public function recovery(#[CurrentUser] User $user, GenerateNewRecoveryCodes $generate): JsonResponse
    {
        $generate($user);

        return response()->json(['recovery_codes' => $user->recoveryCodes()]);
    }

    public function destroy(#[CurrentUser] User $user, DisableTwoFactorAuthentication $disable): Response
    {
        $disable($user);

        return response()->noContent();
    }
}
