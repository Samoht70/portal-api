<?php

namespace Dailyapps\EventDistribution\Support;

/**
 * Signs webhook bodies with a per-Application secret. The child recomputes the
 * same HMAC over the raw bytes it received and rejects on mismatch.
 */
class HmacSigner
{
    public function sign(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }
}
