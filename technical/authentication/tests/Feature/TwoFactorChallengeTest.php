<?php

namespace Technical\Authentication\Tests\Feature;

use Functional\Users\Models\User;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Passport\ClientRepository;
use PragmaRX\Google2FA\Google2FA;
use Technical\Authentication\Enums\AuthErrorCode;
use Technical\Authentication\Support\TwoFactorPendingToken;
use Tests\TestCase;

class TwoFactorChallengeTest extends TestCase
{
    public function test_valid_totp_code_exchanges_the_pending_token_for_an_access_token(): void
    {
        app(ClientRepository::class)->createPersonalAccessGrantClient('Tests');

        $user = User::factory()->withoutManager()->create(['email_verified_at' => now()]);
        app(EnableTwoFactorAuthentication::class)($user);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        $pendingToken = app(TwoFactorPendingToken::class)->issue($user->getKey());
        $code = new Google2FA()->getCurrentOtp(decrypt($user->two_factor_secret));

        $this->postJson('/api/auth/two-factor-challenge', [
            'pending_token' => $pendingToken,
            'code' => $code,
        ])
            ->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in']);
    }

    public function test_forged_pending_token_is_rejected(): void
    {
        $this->postJson('/api/auth/two-factor-challenge', [
            'pending_token' => 'not-a-real-token',
            'code' => '123456',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('error', AuthErrorCode::InvalidPendingToken->value);
    }
}
