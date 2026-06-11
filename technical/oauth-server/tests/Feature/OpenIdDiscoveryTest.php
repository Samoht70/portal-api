<?php

namespace Technical\OauthServer\Tests\Feature;

use Tests\TestCase;

class OpenIdDiscoveryTest extends TestCase
{
    public function test_discovery_document_advertises_the_stateless_oidc_endpoints(): void
    {
        $response = $this->getJson('/.well-known/openid-configuration')->assertOk();

        $response->assertJsonPath('id_token_signing_alg_values_supported', ['RS256']);

        $this->assertStringEndsWith('/oauth/authorize', (string) $response->json('authorization_endpoint'));
        $this->assertStringEndsWith('/oauth/token', (string) $response->json('token_endpoint'));
        $this->assertStringEndsWith('/oauth/jwks', (string) $response->json('jwks_uri'));

        // Scope alignment: OAuthScope is the single source of truth (no phone/address).
        $this->assertSame(['openid', 'profile', 'email'], $response->json('scopes_supported'));
        $this->assertContains('S256', $response->json('code_challenge_methods_supported'));
    }

    public function test_jwks_endpoint_exposes_the_signing_key(): void
    {
        $this->getJson('/oauth/jwks')
            ->assertOk()
            ->assertJsonStructure(['keys' => [['kty']]]);
    }
}
