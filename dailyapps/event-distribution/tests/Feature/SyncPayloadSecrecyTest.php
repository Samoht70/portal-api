<?php

namespace Dailyapps\EventDistribution\Tests\Feature;

use Dailyapps\EventDistribution\Concerns\SyncsToReplica;
use Dailyapps\EventDistribution\Contracts\SyncableAggregate;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

/**
 * Payload minimization: an aggregate's #[Hidden] attributes (password,
 * two_factor_secret, …) must never reach the wire. The guarantee lives in the
 * SyncsToReplica trait, whose toSyncPayload() defaults to attributesToArray()
 * — the very value DomainEventRecorder writes into the outbox payload — and
 * attributesToArray() honours the model's hidden attributes.
 */
class SyncPayloadSecrecyTest extends TestCase
{
    public function test_hidden_attributes_never_appear_in_the_sync_payload(): void
    {
        $aggregate = new SecretfulAggregate;
        $aggregate->forceFill([
            'id' => 'a0000000-0000-7000-8000-000000000001',
            'name' => 'Visible',
            'password' => 'hunter2',
            'two_factor_secret' => 'TOTPSEED',
            'two_factor_recovery_codes' => '["recovery-code"]',
        ]);

        $payload = $aggregate->toSyncPayload();

        $this->assertSame('Visible', $payload['name']);
        $this->assertArrayNotHasKey('password', $payload);
        $this->assertArrayNotHasKey('two_factor_secret', $payload);
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $payload);
    }
}

#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes'])]
class SecretfulAggregate extends Model implements SyncableAggregate
{
    use SyncsToReplica;
}
