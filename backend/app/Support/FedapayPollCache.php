<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Short-lived cache of the REMOTE FedaPay transaction read by the polled
 * status endpoints (subscription verify-payment, mission / booking /
 * commission payment-status).
 *
 * Only a still-pending status is cached (15 s): an approved or terminal status
 * is always read fresh, so the settlement it triggers is decided on a live
 * value and stays idempotent exactly as before. The worst case is a payment
 * noticed up to 15 s later by the poll (the webhook is the primary path).
 *
 * Never used by the webhook settlement path.
 */
final class FedapayPollCache
{
    public const TTL_SECONDS = 15;

    /**
     * @param  Closure(): object  $fetch  Remote read (FedapayService::retrieveTransaction)
     */
    public static function remember(int $transactionId, Closure $fetch): object
    {
        $key = "fedapay:tx:{$transactionId}";

        $cached = Cache::get($key);
        if (is_array($cached)) {
            return self::toObject($cached);
        }

        $transaction = $fetch();

        try {
            if ((string) ($transaction->status ?? '') === 'pending') {
                Cache::put($key, [
                    'id' => self::read($transaction, 'id'),
                    'reference' => self::read($transaction, 'reference'),
                    'status' => 'pending',
                    'amount' => self::read($transaction, 'amount'),
                    'currency' => ['iso' => self::read($transaction, 'currency.iso')],
                ], self::TTL_SECONDS);
            }
        } catch (\Throwable) {
            // The cache is an optimisation: never fail a poll because of it.
        }

        return $transaction;
    }

    private static function read(object $transaction, string $path): mixed
    {
        try {
            return data_get($transaction, $path);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private static function toObject(array $snapshot): object
    {
        $snapshot['currency'] = (object) ($snapshot['currency'] ?? []);

        return (object) $snapshot;
    }
}
