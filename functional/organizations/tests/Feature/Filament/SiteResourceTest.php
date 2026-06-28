<?php

namespace Functional\Organizations\Tests\Feature\Filament;

use Filament\Facades\Filament;
use Functional\Organizations\Filament\Resources\Sites\Pages\CreateSite;
use Functional\Organizations\Filament\Resources\Sites\Pages\ListSites;
use Functional\Organizations\Models\Client;
use Functional\Organizations\Models\Site;
use Functional\Users\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class SiteResourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['access admin panel', 'view global sites', 'create global sites']);

        return $user;
    }

    public function test_admin_can_list_sites(): void
    {
        Site::factory()->create(['name' => 'Paris HQ']);

        $this->actingAs($this->admin(), 'web');

        Livewire::test(ListSites::class)->assertOk()->assertSee('Paris HQ');
    }

    public function test_admin_can_create_a_site(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->admin(), 'web');

        Livewire::test(CreateSite::class)
            ->fillForm([
                'client_id' => $client->getKey(),
                'name' => 'Lyon',
                'country' => 'France',
                'country_alpha' => 'FR',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('sites', ['name' => 'Lyon']);
    }
}
