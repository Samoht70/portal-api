<?php

namespace Dailyapps\EventDistribution\Tests\Feature;

use Functional\Applications\Models\Application;
use Functional\Organizations\Models\Client;
use Functional\Organizations\Models\Site;
use Functional\Subscriptions\Models\ApplicationSyncEndpoint;
use Functional\Subscriptions\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The mother-side checksum endpoint: an authenticated child Application reads a
 * cheap count and max updated_at of an aggregate type, scoped to the clients it
 * is subscribed to, so it can detect drift before pulling a full snapshot.
 */
class SyncChecksumTest extends TestCase
{
    private const SECRET = 'top-secret-pull-key';

    /**
     * Subscribe an Application to a client with a sync-enabled endpoint, and
     * return the Application id used as the pull identity.
     */
    private function subscribe(Client $client, string $secret = self::SECRET): string
    {
        $application = Application::query()->firstOrFail();

        Subscription::factory()->create([
            'client_id' => $client->getKey(),
            'application_id' => $application->getKey(),
        ]);
        ApplicationSyncEndpoint::factory()->create([
            'application_id' => $application->getKey(),
            'secret' => $secret,
            'sync_enabled' => true,
        ]);

        return $application->getKey();
    }

    private function signedGet(string $path, string $applicationId, string $secret = self::SECRET): TestResponse
    {
        $signature = hash_hmac('sha256', 'GET '.$path, $secret);

        return $this->getJson($path, [
            'X-Application' => $applicationId,
            'X-Signature' => $signature,
        ]);
    }

    public function test_checksum_counts_only_the_subscribed_scope(): void
    {
        $subscribed = Client::factory()->create();
        $other = Client::factory()->create();
        $applicationId = $this->subscribe($subscribed);

        $mine = Site::factory()->count(2)->create(['client_id' => $subscribed->getKey()]);
        Site::factory()->create(['client_id' => $other->getKey()]);

        $expectedMax = Site::query()
            ->where('client_id', $subscribed->getKey())
            ->max('updated_at');

        $response = $this->signedGet('/api/sync/checksum?type=sites', $applicationId);

        $response->assertOk();
        $response->assertJson([
            'type' => 'sites',
            'count' => 2,
            'last_updated_at' => Carbon::parse($expectedMax)->toIso8601String(),
        ]);
    }

    public function test_checksum_of_an_empty_scope_is_zero_and_null(): void
    {
        $subscribed = Client::factory()->create();
        $applicationId = $this->subscribe($subscribed);

        $response = $this->signedGet('/api/sync/checksum?type=sites', $applicationId);

        $response->assertOk();
        $response->assertExactJson([
            'type' => 'sites',
            'count' => 0,
            'last_updated_at' => null,
        ]);
    }

    public function test_a_bad_signature_is_rejected(): void
    {
        $client = Client::factory()->create();
        $applicationId = $this->subscribe($client);

        $response = $this->getJson('/api/sync/checksum?type=sites', [
            'X-Application' => $applicationId,
            'X-Signature' => 'not-the-right-signature',
        ]);

        $response->assertStatus(401);
    }

    public function test_an_unknown_aggregate_type_is_unprocessable(): void
    {
        $client = Client::factory()->create();
        $applicationId = $this->subscribe($client);

        $response = $this->signedGet('/api/sync/checksum?type=unknown', $applicationId);

        $response->assertStatus(422);
    }
}
