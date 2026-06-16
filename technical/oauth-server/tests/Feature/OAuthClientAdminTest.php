<?php

namespace Technical\OauthServer\Tests\Feature;

use Functional\Users\Models\User;
use Laravel\Passport\Passport;
use Tests\TestCase;

class OAuthClientAdminTest extends TestCase
{
    public function test_registering_a_child_client_requires_permission(): void
    {
        Passport::actingAs(User::factory()->withoutManager()->standard()->create());

        $this->postJson('/api/oauth/clients', [
            'name' => 'Child',
            'redirect_uris' => ['https://child.test/callback'],
        ])->assertForbidden();
    }

    public function test_super_admin_registers_a_child_client(): void
    {
        Passport::actingAs(User::factory()->withoutManager()->superAdmin()->create());

        $this->postJson('/api/oauth/clients', [
            'name' => 'Child App',
            'redirect_uris' => ['https://child.test/callback'],
        ])
            ->assertCreated()
            ->assertJsonPath('name', 'Child App');

        $this->assertDatabaseHas('oauth_clients', ['name' => 'Child App']);
    }
}
