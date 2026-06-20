<?php

namespace Dailyapps\EventDistribution\Jobs;

use Dailyapps\EventDistribution\Outbox\EventEnvelope;
use Dailyapps\EventDistribution\Support\HmacSigner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

/**
 * Delivers one domain event to one subscriber as a signed webhook.
 */
#[Tries(3)]
#[Backoff(10, 30, 60)]
class DeliverDomainEvent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $envelope
     */
    public function __construct(
        private readonly array $envelope,
        private readonly string $endpointUrl,
        private readonly string $secret,
    ) {
        $this->onQueue('sync');
    }

    public function handle(HmacSigner $signer): void
    {
        $body = json_encode($this->envelope);
        $signature = $signer->sign($body, $this->secret);

        Http::withBody($body)
            ->withHeaders([
                'X-Event-Id' => $this->envelope['id'],
                'X-Event-Sequence' => (string) $this->envelope['sequence'],
                'X-Aggregate' => $this->envelope['aggregate_type'],
                'X-Schema-Version' => (string) EventEnvelope::SCHEMA_VERSION,
                'X-Signature' => $signature,
            ])
            ->post($this->endpointUrl)
            ->throw();
    }
}
