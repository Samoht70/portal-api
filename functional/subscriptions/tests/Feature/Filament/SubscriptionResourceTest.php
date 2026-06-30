<?php

namespace Functional\Subscriptions\Tests\Feature\Filament;

use Filament\Facades\Filament;
use Functional\Applications\Models\Application;
use Functional\Organizations\Models\Client;
use Functional\Subscriptions\Filament\Resources\Subscriptions\Pages\CreateSubscription;
use Functional\Subscriptions\Filament\Resources\Subscriptions\Pages\ListSubscriptions;
use Functional\Subscriptions\Models\Subscription;
use Functional\Users\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class SubscriptionResourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['access admin panel', 'view global subscriptions', 'create global subscriptions']);

        return $user;
    }

    public function test_admin_can_list_subscriptions(): void
    {
        $subscription = Subscription::factory()->create(['licenses' => 42]);

        $this->actingAs($this->admin(), 'web');

        Livewire::test(ListSubscriptions::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$subscription]);
    }

    public function test_admin_can_create_a_subscription(): void
    {
        $client = Client::factory()->create();
        $application = Application::factory()->create();

        $this->actingAs($this->admin(), 'web');

        Livewire::test(CreateSubscription::class)
            ->fillForm([
                'client_id' => $client->getKey(),
                'application_id' => $application->getKey(),
                'licenses' => 10,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('subscriptions', [
            'licenses' => 10,
            'client_id' => $client->getKey(),
            'application_id' => $application->getKey(),
        ]);
    }

    public function test_user_without_permission_cannot_list_subscriptions(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('access admin panel');

        $this->actingAs($user, 'web');

        Livewire::test(ListSubscriptions::class)->assertForbidden();
    }
}
