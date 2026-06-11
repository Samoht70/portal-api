<?php

namespace Technical\Authentication\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/**
 * Stateless password reset. `forgot` e-mails a reset link pointing at the
 * front-end (see AuthenticationServiceProvider); `reset` consumes the token.
 * Responses never reveal whether an email exists (no user enumeration).
 */
class PasswordResetController extends Controller
{
    public function forgot(Request $request): Response
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        return response()->noContent();
    }

    public function reset(Request $request): Response|JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password): void {
                $user->forceFill(['password' => Hash::make($password)])->save();
            },
        );

        if ($status !== Password::PasswordReset) {
            return response()->json(['error' => __($status)], SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->noContent();
    }
}
