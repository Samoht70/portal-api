<?php

namespace Dailyapps\PortalShared\Support;

use Illuminate\Support\Facades\Http;

/**
 * Issues signed GET pulls against the mother sync API, authenticated with the
 * per-Application HMAC over the request line. The single place that knows the
 * mother base URL, the application id and the signing scheme.
 */
class MotherSyncClient
{
    public function isConfigured(): bool
    {
        return (bool) config('portal-shared.mother_url')
            && (bool) config('portal-shared.application_id')
            && (bool) config('portal-shared.sync_secret');
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $path): array
    {
        $signature = hash_hmac('sha256', 'GET '.$path, (string) config('portal-shared.sync_secret'));

        return Http::withHeaders([
            'X-Application' => config('portal-shared.application_id'),
            'X-Signature' => $signature,
        ])
            ->get(config('portal-shared.mother_url').$path)
            ->throw()
            ->json();
    }
}
