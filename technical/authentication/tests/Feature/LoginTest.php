<?php

namespace Technical\Authentication\Tests\Feature;

use Functional\Users\Models\User;
use Laravel\Passport\ClientRepository;
use Technical\Authentication\Enums\AuthErrorCode;
use Tests\TestCase;

class LoginTest extends TestCase
{
    public function test_valid_credentials_return_an_access_token(): void
    {
        app(ClientRepository::class)->createPersonalAccessGrantClient('Tests');

        User::factory()->withoutManager()->create([
            'email' => 'sso@example.com',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'sso@example.com',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in'])
            ->assertJsonPath('token_type', 'Bearer');
    }

    public function test_invalid_password_is_rejected(): void
    {
        User::factory()->withoutManager()->create([
            'email' => 'sso@example.com',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'sso@example.com',
            'password' => 'wrong-password',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('error', AuthErrorCode::InvalidCredentials->value);
    }

    public function test_unverified_email_is_forbidden(): void
    {
        User::factory()->withoutManager()->create([
            'email' => 'sso@example.com',
            'password' => 'password',
            'email_verified_at' => null,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'sso@example.com',
            'password' => 'password',
        ])
            ->assertForbidden()
            ->assertJsonPath('error', AuthErrorCode::EmailUnverified->value);
    }
}
