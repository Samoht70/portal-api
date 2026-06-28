<?php

namespace Functional\Applications\Tests\Feature\Filament;

use Filament\Facades\Filament;
use Functional\Applications\Filament\Resources\Applications\Pages\ListApplications;
use Functional\Applications\Models\Application;
use Functional\Users\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class ApplicationResourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_admin_can_list_applications_with_translated_name(): void
    {
        $application = Application::factory()->create();
        $application->translateOrNew(config('app.locale'))->name = 'Timesheet';
        $application->save();

        $user = User::factory()->create();
        $user->givePermissionTo(['access admin panel', 'view global applications']);

        $this->actingAs($user, 'web');

        Livewire::test(ListApplications::class)
            ->searchTable('Timesheet')
            ->assertCanSeeTableRecords([$application]);
    }

    public function test_user_without_view_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access admin panel');

        $this->actingAs($user, 'web');

        Livewire::test(ListApplications::class)->assertForbidden();
    }
}
