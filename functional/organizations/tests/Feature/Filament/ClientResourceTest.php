<?php

namespace Functional\Organizations\Tests\Feature\Filament;

use Filament\Facades\Filament;
use Functional\Organizations\Filament\Resources\Clients\Pages\CreateClient;
use Functional\Organizations\Filament\Resources\Clients\Pages\ListClients;
use Functional\Organizations\Models\Client;
use Functional\Users\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class ClientResourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function admin(array $extra = []): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(array_merge(['access admin panel'], $extra));

        return $user;
    }

    public function test_admin_can_list_clients(): void
    {
        $client = Client::factory()->create(['name' => 'Acme']);

        $this->actingAs($this->admin(['view global clients']), 'web');

        Livewire::test(ListClients::class)
            ->searchTable('Acme')
            ->assertCanSeeTableRecords([$client]);
    }

    public function test_admin_can_create_a_client(): void
    {
        $this->actingAs($this->admin(['view global clients', 'create global clients']), 'web');

        Livewire::test(CreateClient::class)
            ->fillForm(['name' => 'New Client'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('clients', ['name' => 'New Client']);
    }

    public function test_user_without_view_permission_is_forbidden(): void
    {
        $this->actingAs($this->admin(), 'web');

        Livewire::test(ListClients::class)->assertForbidden();
    }
}
