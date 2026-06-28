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
 * REST management of subscriptions. The subscriptions resource exposes only
 * `details` and `destroy`: a subscription is created or modified through the
 * owning client (ClientResource's `subscriptions` relation) and deleted
 * directly on the subscriptions resource. Tenant isolation flows from the
 * ClientPerimeter, so a client-scoped admin only sees its own client.
 */
class SubscriptionManagementTest extends TestCase
{
    public function test_super_admin_creates_a_subscription_through_its_client(): void
    {
        Passport::actingAs(User::factory()->withoutManager()->superAdmin()->create());

        $client = Client::factory()->create();
        $application = Application::query()->firstOrFail();

        $this->postJson('/api/clients/mutate', [
            'mutate' => [
                [
                    'operation' => 'update',
                    'key' => $client->getKey(),
                    'relations' => [
                        'subscriptions' => [
                            [
                                'operation' => 'create',
                                'attributes' => [
                                    'application_id' => $application->getKey(),
                                    'licenses' => 25,
                                ],
                            ],
                        ],
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

        $this->postJson('/api/clients/mutate', [
            'mutate' => [
                [
                    'operation' => 'update',
                    'key' => $client->getKey(),
                    'relations' => [
                        'subscriptions' => [
                            [
                                'operation' => 'create',
                                'attributes' => [
                                    'application_id' => $application->getKey(),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ])->assertForbidden();

        $this->assertDatabaseEmpty('subscriptions');
    }

    public function test_super_admin_deletes_a_subscription_on_the_subscriptions_resource(): void
    {
        Passport::actingAs(User::factory()->withoutManager()->superAdmin()->create());

        $subscription = Subscription::factory()->create([
            'application_id' => Application::query()->firstOrFail()->getKey(),
        ]);

        $this->deleteJson('/api/subscriptions', [
            'resources' => [$subscription->getKey()],
        ])->assertSuccessful();

        $this->assertDatabaseMissing('subscriptions', ['id' => $subscription->getKey()]);
    }

    public function test_subscriptions_are_scoped_to_the_users_client(): void
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

        $this->postJson('/api/clients/search', [
            'search' => [
                'includes' => [
                    ['relation' => 'subscriptions'],
                ],
            ],
        ])
            ->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $clientA->getKey())
            ->assertJsonCount(1, 'data.0.subscriptions')
            ->assertJsonPath('data.0.subscriptions.0.id', $ownSubscription->getKey());
    }
}
