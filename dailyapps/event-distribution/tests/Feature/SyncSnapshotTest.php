<?php

namespace Dailyapps\EventDistribution\Tests\Feature;

use Dailyapps\EventDistribution\Models\DomainEventRecord;
use Functional\Applications\Models\Application;
use Functional\Organizations\Models\Client;
use Functional\Organizations\Models\Site;
use Functional\Subscriptions\Models\ApplicationSyncEndpoint;
use Functional\Subscriptions\Models\Subscription;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The mother-side pull endpoints: an authenticated child Application reads the
 * outbox watermark and a client-scoped snapshot of an aggregate type, with each
 * request signed by the per-Application HMAC secret.
 */
class SyncSnapshotTest extends TestCase
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

    public function test_watermark_returns_the_max_domain_event_sequence(): void
    {
        $client = Client::factory()->create();
        $applicationId = $this->subscribe($client);

        Site::factory()->count(2)->create(['client_id' => $client->getKey()]);

        $expected = (int) DomainEventRecord::query()->max('sequence');

        $response = $this->signedGet('/api/sync/watermark', $applicationId);

        $response->assertOk();
        $response->assertJson(['sequence' => $expected]);
    }

    public function test_snapshot_is_scoped_to_the_subscribed_clients(): void
    {
        $subscribed = Client::factory()->create();
        $other = Client::factory()->create();
        $applicationId = $this->subscribe($subscribed);

        $mine = Site::factory()->create(['client_id' => $subscribed->getKey()]);
        $theirs = Site::factory()->create(['client_id' => $other->getKey()]);

        $response = $this->signedGet('/api/sync/snapshot?type=sites', $applicationId);

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($mine->getKey(), $ids);
        $this->assertNotContains($theirs->getKey(), $ids);
    }

    public function test_snapshot_since_returns_only_rows_updated_at_or_after_it(): void
    {
        $client = Client::factory()->create();
        $applicationId = $this->subscribe($client);

        $older = Site::factory()->create(['client_id' => $client->getKey()]);
        $newer = Site::factory()->create(['client_id' => $client->getKey()]);

        $older->forceFill(['updated_at' => now()->subMinute()])->save();
        $newer->forceFill(['updated_at' => now()->addMinute()])->save();

        $since = now()->toIso8601String();

        $response = $this->signedGet('/api/sync/snapshot?type=sites&since='.urlencode($since), $applicationId);

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($newer->getKey(), $ids);
        $this->assertNotContains($older->getKey(), $ids);
    }

    public function test_a_bad_signature_is_rejected(): void
    {
        $client = Client::factory()->create();
        $applicationId = $this->subscribe($client);

        $response = $this->getJson('/api/sync/watermark', [
            'X-Application' => $applicationId,
            'X-Signature' => 'not-the-right-signature',
        ]);

        $response->assertStatus(401);
    }

    public function test_an_unknown_application_is_rejected(): void
    {
        $response = $this->signedGet('/api/sync/watermark', 'unknown-application-id');

        $response->assertStatus(401);
    }

    public function test_an_unknown_aggregate_type_is_unprocessable(): void
    {
        $client = Client::factory()->create();
        $applicationId = $this->subscribe($client);

        $response = $this->signedGet('/api/sync/snapshot?type=unknown', $applicationId);

        $response->assertStatus(422);
    }
}
