<?php

namespace Technical\OauthServer\Tests\Feature;

use Functional\Users\Models\User;
use Laravel\Passport\Passport;
use Technical\OauthServer\Actions\RegisterChildClient;
use Tests\TestCase;

class StatelessAuthorizationTest extends TestCase
{
    public function test_authorize_api_requires_authentication(): void
    {
        $this->getJson('/api/oauth/authorize')->assertUnauthorized();
    }

    public function test_browser_authorize_redirects_to_the_front_end(): void
    {
        $response = $this->get('/oauth/authorize?client_id=x&redirect_uri=https://child.test/cb&response_type=code');

        $response->assertRedirect();
        $this->assertStringContainsString('/authorize', (string) $response->headers->get('Location'));
    }

    public function test_authenticated_user_mints_an_authorization_code(): void
    {
        $user = User::factory()->withoutManager()->create();
        Passport::actingAs($user);

        $client = app(RegisterChildClient::class)->handle($user, 'Child App', ['https://child.test/callback']);

        $verifier = 'a-pkce-code-verifier-of-sufficient-length-0123456789';
        $challenge = hash('sha256', $verifier, true)
                |> base64_encode(...)
                |> (fn ($x) => strtr($x, '+/', '-_'))
                |> (fn ($x) => rtrim($x, '='));

        $query = http_build_query([
            'client_id' => $client->getKey(),
            'redirect_uri' => 'https://child.test/callback',
            'response_type' => 'code',
            'scope' => 'openid',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'state' => 'opaque-state',
        ]);

        $this->postJson('/api/oauth/authorize?'.$query)
            ->assertOk()
            ->assertJsonStructure(['redirect_url']);
    }
}
