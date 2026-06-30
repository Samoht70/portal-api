<?php

namespace Functional\Users\Tests\Feature\Filament;

use Filament\Facades\Filament;
use Functional\Organizations\Models\Site;
use Functional\Users\Filament\Resources\Users\Pages\CreateUser;
use Functional\Users\Filament\Resources\Users\Pages\ListUsers;
use Functional\Users\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['access admin panel', 'view global users', 'create global users']);

        return $user;
    }

    public function test_admin_can_list_users(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.test']);

        $this->actingAs($this->admin(), 'web');

        Livewire::test(ListUsers::class)
            ->searchTable('jane@example.test')
            ->assertCanSeeTableRecords([$user]);
    }

    public function test_admin_can_create_a_user(): void
    {
        $site = Site::factory()->create();

        $this->actingAs($this->admin(), 'web');

        Livewire::test(CreateUser::class)
            ->fillForm([
                'site_id' => $site->getKey(),
                'email' => 'new@example.test',
                'firstname' => 'New',
                'lastname' => 'User',
                'language' => 'fr',
                'password' => 'password123',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'new@example.test',
            'site_id' => $site->getKey(),
        ]);
    }

    public function test_user_without_permission_cannot_list_users(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access admin panel');

        $this->actingAs($user, 'web');

        Livewire::test(ListUsers::class)->assertForbidden();
    }
}
