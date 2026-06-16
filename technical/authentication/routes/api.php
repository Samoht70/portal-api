<?php

use Illuminate\Support\Facades\Route;
use Technical\Authentication\Http\Controllers\EmailVerificationController;
use Technical\Authentication\Http\Controllers\LoginController;
use Technical\Authentication\Http\Controllers\MicrosoftController;
use Technical\Authentication\Http\Controllers\PasswordResetController;
use Technical\Authentication\Http\Controllers\SessionController;
use Technical\Authentication\Http\Controllers\TwoFactorAuthenticationController;
use Technical\Authentication\Http\Controllers\TwoFactorChallengeController;

/*
|--------------------------------------------------------------------------
| Authentication routes (prefixed with `api/auth`)
|--------------------------------------------------------------------------
|
| The `api` prefix is applied by the layer provider's withRouting(); only the
| `auth/` segment is declared here. Password reset and two-factor management
| (enable / confirm / recovery codes) are served by Fortify's own headless
| routes — they are not redefined here.
|
*/

Route::prefix('auth')
    ->group(function () {
        // --- Public (stateless) ---
        Route::post('login', [LoginController::class, 'login'])
            ->name('auth.login');

        Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store'])
            ->name('auth.two-factor-challenge');

        Route::get('email/verify/{user}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware('signed')
            ->name('verification.verify');

        Route::post('forgot-password', [PasswordResetController::class, 'forgot'])
            ->middleware('throttle:6,1')
            ->name('password.email');

        Route::post('reset-password', [PasswordResetController::class, 'reset'])
            ->name('password.update');

        // --- Microsoft (Azure AD) ---
        Route::get('microsoft/redirect', [MicrosoftController::class, 'redirect'])
            ->name('auth.microsoft.redirect');

        Route::get('microsoft/callback', [MicrosoftController::class, 'callback'])
            ->name('auth.microsoft.callback');

        // --- Authenticated (auth:api token guard) ---
        Route::middleware('auth:api')
            ->group(function () {
                Route::get('me', [SessionController::class, 'me'])->name('auth.me');
                Route::post('logout', [SessionController::class, 'logout'])->name('auth.logout');

                Route::post('email/verification-notification', [EmailVerificationController::class, 'resend'])
                    ->middleware('throttle:6,1')
                    ->name('verification.send');

                // Two-factor enrolment management.
                Route::post('two-factor-authentication', [TwoFactorAuthenticationController::class, 'store'])
                    ->name('two-factor.enable');
                Route::post('two-factor-authentication/confirm', [TwoFactorAuthenticationController::class, 'confirm'])
                    ->name('two-factor.confirm');
                Route::delete('two-factor-authentication', [TwoFactorAuthenticationController::class, 'destroy'])
                    ->name('two-factor.disable');
                Route::post('two-factor-recovery-codes', [TwoFactorAuthenticationController::class, 'recovery'])
                    ->name('two-factor.recovery-codes');
            });
    });
