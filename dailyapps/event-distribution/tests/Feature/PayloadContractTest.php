<?php

namespace Dailyapps\EventDistribution\Tests\Feature;

use Functional\Organizations\Models\Client;
use Functional\Organizations\Models\Site;
use Functional\Users\Models\User;
use Tests\TestCase;

/**
 * Payload contract — the wire shape children depend on.
 */
class PayloadContractTest extends TestCase
{
    public function test_client_payload_carries_its_core_contract(): void
    {
        $payload = Client::factory()->create()->fresh()->toSyncPayload();

        $this->assertContractKeys(
            ['id', 'name', 'created_at', 'updated_at', 'deleted_at'],
            $payload,
        );
    }

    public function test_site_payload_carries_its_core_contract(): void
    {
        $payload = Site::factory()->create()->fresh()->toSyncPayload();

        $this->assertContractKeys(
            ['id', 'name', 'country', 'country_alpha', 'subdivision', 'subdivision_code', 'client_id', 'created_at', 'updated_at', 'deleted_at'],
            $payload,
        );
    }

    public function test_user_payload_carries_its_core_contract_and_never_a_secret(): void
    {
        $payload = User::factory()->create()->fresh()->toSyncPayload();

        $this->assertContractKeys(
            ['id', 'lastname', 'firstname', 'email', 'language', 'site_id', 'manager_id', 'created_at', 'updated_at', 'deleted_at'],
            $payload,
        );

        $this->assertArrayNotHasKey('password', $payload);
    }

    /**
     * @param  array<int, string>  $expected
     * @param  array<string, mixed>  $payload
     */
    private function assertContractKeys(array $expected, array $payload): void
    {
        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $payload, "Sync payload is missing the contract key [{$key}].");
        }
    }
}
