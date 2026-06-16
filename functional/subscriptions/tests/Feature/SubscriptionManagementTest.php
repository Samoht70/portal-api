<?php

namespace Functional\Subscriptions\Tests\Feature;

use Functional\Applications\Models\Application;
use Functional\Organizations\Models\Client;
use Functional\Organizations\Models\Site;
use Functional\Subscriptions\Models\Subscription;
use Functional\Users\Models\User;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * REST management of subscriptions: authentication, authorization by permission,
 * and tenant isolation (a client-scoped admin only sees/manages its own client's
 * subscriptions via the ClientPerimeter).
 */
class SubscriptionManagementTest extends TestCase
{
    public function test_search_requires_authentication(): void
    {
        $this->postJson('/api/subscriptions/search')->assertUnauthorized();
    }

    public function test_super_admin_creates_a_subscription_via_mutate(): void
    {
        Passport::actingAs(User::factory()->withoutManager()->superAdmin()->create());

        $client = Client::factory()->create();
        $application = Application::query()->firstOrFail();

        $this->postJson('/api/subscriptions/mutate', [
            'mutate' => [
                [
                    'operation' => 'create',
                    'attributes' => [
                        'client_id' => $client->getKey(),
                        'application_id' => $application->getKey(),
                        'licenses' => 25,
                    ],
                ],
            ],
        ])->assertSuccessful();

        $this->assertDatabaseHas('subscriptions', [
            'client_id' => $client->getKey(),
            'application_id' => $application->getKey(),
            'licenses' => 25,
        ]);
    }

    public function test_standard_user_cannot_create_a_subscription(): void
    {
        Passport::actingAs(User::factory()->withoutManager()->standard()->create());

        $client = Client::factory()->create();
        $application = Application::query()->firstOrFail();

        $this->postJson('/api/subscriptions/mutate', [
            'mutate' => [
                [
                    'operation' => 'create',
                    'attributes' => [
                        'client_id' => $client->getKey(),
                        'application_id' => $application->getKey(),
                    ],
                ],
            ],
        ])->assertForbidden();

        $this->assertDatabaseEmpty('subscriptions');
    }

    public function test_search_is_scoped_to_the_users_client(): void
    {
        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();
        [$appA, $appB] = Application::query()->take(2)->get()->all();

        $ownSubscription = Subscription::factory()->create(['client_id' => $clientA->getKey(), 'application_id' => $appA->getKey()]);
        Subscription::factory()->create(['client_id' => $clientB->getKey(), 'application_id' => $appB->getKey()]);

        $site = Site::factory()->create(['client_id' => $clientA->getKey()]);
        $admin = User::factory()
            ->withoutManager()
            ->administrator()
            ->create(['site_id' => $site->getKey()]);

        Passport::actingAs($admin);

        $this->postJson('/api/subscriptions/search', ['search' => []])
            ->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownSubscription->getKey());
    }
}
