<?php

namespace Dailyapps\EventDistribution\Tests\Feature;

use Dailyapps\EventDistribution\Jobs\DeliverDomainEvent;
use Dailyapps\EventDistribution\Support\HmacSigner;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeliverDomainEventTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function envelope(): array
    {
        return [
            'id' => 'e7c1a0b2-0000-7000-8000-000000000001',
            'sequence' => 5,
            'aggregate_type' => 'clients',
            'aggregate_id' => 'c0000000-0000-7000-8000-000000000001',
            'event_type' => 'clients.upserted',
            'tenant_scope' => 'c0000000-0000-7000-8000-000000000001',
            'occurred_at' => '2026-01-01T00:00:00+00:00',
            'schema_version' => 1,
            'payload' => ['id' => 'c0000000-0000-7000-8000-000000000001', 'name' => 'Acme'],
        ];
    }

    public function test_it_posts_a_signed_envelope_to_the_subscriber_endpoint(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $envelope = $this->envelope();

        new DeliverDomainEvent($envelope, 'https://child.test/sync', 'top-secret')
            ->handle(new HmacSigner);

        Http::assertSent(function (Request $request) use ($envelope) {
            $body = $request->body();

            return $request->url() === 'https://child.test/sync'
                && $body === json_encode($envelope)
                && $request->hasHeader('X-Signature', hash_hmac('sha256', $body, 'top-secret'))
                && $request->hasHeader('X-Event-Id', $envelope['id'])
                && $request->hasHeader('X-Event-Sequence', '5')
                && $request->hasHeader('X-Aggregate', 'clients')
                && $request->hasHeader('X-Schema-Version', '1');
        });
    }

    public function test_it_throws_on_a_failed_response_so_the_job_retries(): void
    {
        Http::fake(['*' => Http::response('nope', 500)]);

        $this->expectException(RequestException::class);

        new DeliverDomainEvent($this->envelope(), 'https://child.test/sync', 'top-secret')
            ->handle(new HmacSigner);
    }
}
