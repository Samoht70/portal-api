<?php

namespace Functional\Subscriptions\Tests\Feature;

use Functional\Applications\Models\Application;
use Functional\Organizations\Models\Client;
use Functional\Subscriptions\Models\Subscription;
use Tests\TestCase;

/**
 * The organizations layer exposes the inverse Client → subscriptions relation
 * (Client::subscriptions() + ClientResource), so a client can be navigated to
 * its subscriptions.
 */
class ClientSubscriptionsTest extends TestCase
{
    public function test_a_client_has_many_subscriptions(): void
    {
        $client = Client::factory()->create();
        [$first, $second] = Application::query()->take(2)->get()->all();

        Subscription::factory()->create(['client_id' => $client->getKey(), 'application_id' => $first->getKey()]);
        Subscription::factory()->create(['client_id' => $client->getKey(), 'application_id' => $second->getKey()]);

        $this->assertCount(2, $client->subscriptions);
        $this->assertInstanceOf(Subscription::class, $client->subscriptions->first());
    }
}
