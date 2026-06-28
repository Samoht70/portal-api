<?php

namespace Dailyapps\EventDistribution\Tests\Feature;

use Dailyapps\EventDistribution\Concerns\SyncsToReplica;
use Dailyapps\EventDistribution\Contracts\SyncableAggregate;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

/**
 * Payload minimization: an aggregate's #[Hidden] attributes (password,
 * remember_token, …) must never reach the wire. The guarantee lives in the
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
            'remember_token' => 'a-remember-token',
        ]);

        $payload = $aggregate->toSyncPayload();

        $this->assertSame('Visible', $payload['name']);
        $this->assertArrayNotHasKey('password', $payload);
        $this->assertArrayNotHasKey('remember_token', $payload);
    }
}

#[Hidden(['password', 'remember_token'])]
class SecretfulAggregate extends Model implements SyncableAggregate
{
    use SyncsToReplica;
}
