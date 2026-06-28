<?php

namespace Functional\Applications\Tests\Feature\Filament;

use Filament\Facades\Filament;
use Functional\Applications\Filament\Resources\Packs\Pages\ListPacks;
use Functional\Applications\Models\Pack;
use Functional\Users\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class PackResourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_admin_can_list_packs_with_translated_name(): void
    {
        $pack = Pack::factory()->create();
        $pack->translateOrNew(config('app.locale'))->name = 'Pack Pro';
        $pack->save();

        $user = User::factory()->create();
        $user->givePermissionTo(['access admin panel', 'view global packs']);

        $this->actingAs($user, 'web');

        Livewire::test(ListPacks::class)
            ->searchTable('Pack Pro')
            ->assertCanSeeTableRecords([$pack]);
    }

    public function test_user_without_view_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access admin panel');

        $this->actingAs($user, 'web');

        Livewire::test(ListPacks::class)->assertForbidden();
    }
}
