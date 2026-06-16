<?php

namespace Technical\Authentication\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * A short-lived, stateless token that stands in for the half-authenticated
 * user between the credential step and the two-factor challenge.
 *
 * It is an encrypted (and therefore tamper-proof and authenticated) envelope —
 * no database row, no session, in keeping with the stateless requirement. The
 * trade-off is that a pending token cannot be revoked before it expires, which
 * is acceptable given its short lifetime.
 */
class TwoFactorPendingToken
{
    private const string PURPOSE = 'two_factor_challenge';

    private const int TTL_SECONDS = 300;

    public function issue(string $userId): string
    {
        return Crypt::encrypt([
            'sub' => $userId,
            'purpose' => self::PURPOSE,
            'expires_at' => now()->addSeconds(self::TTL_SECONDS)->getTimestamp(),
        ]);
    }

    /**
     * Resolve a pending token back to its user id, or null when the token is
     * forged, tampered with, expired or issued for another purpose.
     */
    public function resolve(string $token): ?string
    {
        try {
            $payload = Crypt::decrypt($token);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($payload)
            || ($payload['purpose'] ?? null) !== self::PURPOSE
            || ($payload['expires_at'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        return $payload['sub'] ?? null;
    }

    public function ttlSeconds(): int
    {
        return self::TTL_SECONDS;
    }
}
