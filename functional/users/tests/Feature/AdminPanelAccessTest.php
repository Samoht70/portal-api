<?php

namespace Functional\Users\Tests\Feature;

use Functional\Users\Models\User;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    public function test_user_without_permission_cannot_access_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_user_with_permission_can_access_panel(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access admin panel');

        $this->actingAs($user, 'web')
            ->get('/admin')
            ->assertSuccessful();
    }
}
