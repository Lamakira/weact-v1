<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\FaceSubscription;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\Producer;
use App\Models\User;
use FedaPay\Balance;
use FedaPay\FedaPay;
use FedaPay\Payout;
use FedaPay\Transaction;
use FedaPay\Webhook;
use Illuminate\Support\Facades\Log;

class FedapayService
{
    public function __construct()
    {
        FedaPay::setApiKey(config('services.fedapay.secret_key'));
        FedaPay::setEnvironment(config('services.fedapay.environment'));
    }

    /**
     * Initiate a hosted checkout payment for a booking.
     * Returns the FedaPay checkout URL to redirect the producer.
     *
     * @return array{fedapay_transaction_id: int, checkout_url: string}
     */
    public function initiatePayment(Booking $booking, string $idempotencyKey): array
    {
        $producer = $booking->producer;
        if (! $producer instanceof User) {
            throw new \RuntimeException('Le producteur du booking est introuvable.');
        }

        /** @var Transaction $transaction */
        $transaction = Transaction::create([
            'description' => "Booking #{$booking->id}",
            'amount' => $booking->montant_total_producteur,
            'currency' => ['iso' => 'XOF'],
            'callback_url' => route('webhooks.fedapay'),
            'custom_metadata' => [
                'booking_id' => $booking->id,
                'idempotency_key' => $idempotencyKey,
            ],
            'customer' => [
                'firstname' => $this->resolveCustomerName($producer),
                'email' => $producer->email,
            ],
        ]);

        /** @var object{url:string} $tokenObj */
        $tokenObj = $transaction->generateToken();

        return [
            'fedapay_transaction_id' => (int) $transaction->id,
            'checkout_url' => $tokenObj->url,
        ];
    }

    /**
     * Initiate a hosted checkout payment for a mission payment.
     *
     * @return array{fedapay_transaction_id: int, checkout_url: string}
     */
    public function initiatePaymentForMission(MissionPayment $payment, string $idempotencyKey): array
    {
        /** @var User $producerUser */
        $producerUser = User::query()->where('userable_type', Producer::class)
            ->where('userable_id', $payment->producer_id)
            ->firstOrFail();

        $mission = $payment->mission;
        if (! $mission instanceof Mission) {
            throw new \RuntimeException('La mission liée au paiement est introuvable.');
        }

        /** @var Transaction $transaction */
        $transaction = Transaction::create([
            'description' => "Mission #{$mission->id} — {$mission->titre}",
            'amount' => $payment->montant_total_producteur,
            'currency' => ['iso' => 'XOF'],
            'callback_url' => route('webhooks.fedapay'),
            'custom_metadata' => [
                'mission_payment_id' => $payment->id,
                'idempotency_key' => $idempotencyKey,
                'type' => 'mission',
            ],
            'customer' => [
                'firstname' => $this->resolveCustomerName($producerUser),
                'email' => $producerUser->email,
            ],
        ]);

        /** @var object{url:string} $tokenObj */
        $tokenObj = $transaction->generateToken();

        return [
            'fedapay_transaction_id' => (int) $transaction->id,
            'checkout_url' => $tokenObj->url,
        ];
    }

    /**
     * Initiate a hosted checkout payment for a Face annual premium subscription.
     * The Fedapay transaction id is stored in face_subscriptions.provider_reference
     * by the caller and is what the webhook handler (HandleFedapayWebhook) uses to
     * route incoming transaction.approved / transaction.declined events back to the
     * subscription row.
     *
     * @return array{fedapay_transaction_id: int, checkout_url: string}
     */
    public function initiatePaymentForFaceSubscription(
        FaceSubscription $subscription,
        User $faceUser,
        string $idempotencyKey,
    ): array {
        /** @var Transaction $transaction */
        $transaction = Transaction::create([
            'description' => "Abonnement {$subscription->plan->label()} annuel — {$faceUser->email}",
            'amount' => $subscription->plan->price(),
            'currency' => ['iso' => (string) config('face_subscription_tiers.currency', 'XOF')],
            'callback_url' => route('webhooks.fedapay'),
            'custom_metadata' => [
                'face_subscription_id' => $subscription->id,
                'idempotency_key' => $idempotencyKey,
                'type' => 'face_subscription',
            ],
            'customer' => [
                'firstname' => $this->resolveCustomerName($faceUser),
                'email' => $faceUser->email,
            ],
        ]);

        /** @var object{url:string} $tokenObj */
        $tokenObj = $transaction->generateToken();

        return [
            'fedapay_transaction_id' => (int) $transaction->id,
            'checkout_url' => $tokenObj->url,
        ];
    }

    /**
     * Initiate a hosted checkout for a UGC booking settlement.
     * RH.2 : charges the full producer settlement (`montant_total_producteur`) —
     * hybrid = cash + service fee, product-only = the product commission (same value
     * as before). The Face's net is escrowed at settlement; see D-RH2.c.
     *
     * @return array{fedapay_transaction_id: int, checkout_url: string}
     */
    public function initiatePaymentForUgcBooking(Booking $booking, string $idempotencyKey): array
    {
        $producer = $booking->producer;
        if (! $producer instanceof User) {
            throw new \RuntimeException('Le producteur du booking est introuvable.');
        }

        return $this->createUgcCommissionCheckout(
            amount: (int) $booking->montant_total_producteur,
            description: "Règlement UGC — Booking #{$booking->id}",
            metadata: ['type' => 'ugc_booking', 'booking_id' => $booking->id, 'idempotency_key' => $idempotencyKey],
            payer: $producer,
        );
    }

    /**
     * Initiate a hosted checkout for a UGC mission publication commission.
     * Charges `commission_ugc` ONLY (publication fee). See D-1.5.a / D-1.5.d.
     *
     * @return array{fedapay_transaction_id: int, checkout_url: string}
     */
    public function initiatePaymentForUgcMission(Mission $mission, string $idempotencyKey): array
    {
        /** @var User $producerUser */
        $producerUser = User::query()->where('userable_type', Producer::class)
            ->where('userable_id', $mission->producer_id)
            ->firstOrFail();

        return $this->createUgcCommissionCheckout(
            amount: (int) $mission->commission_ugc,
            description: "Commission UGC — Mission #{$mission->id} — {$mission->titre}",
            metadata: ['type' => 'ugc_mission', 'mission_id' => $mission->id, 'idempotency_key' => $idempotencyKey],
            payer: $producerUser,
        );
    }

    /**
     * Shared FedaPay transaction builder for UGC commission checkouts (booking + mission).
     *
     * @param  array<string, mixed>  $metadata
     * @return array{fedapay_transaction_id: int, checkout_url: string}
     */
    private function createUgcCommissionCheckout(int $amount, string $description, array $metadata, User $payer): array
    {
        /** @var Transaction $transaction */
        $transaction = Transaction::create([
            'description' => $description,
            'amount' => $amount,
            'currency' => ['iso' => 'XOF'],
            'callback_url' => route('webhooks.fedapay'),
            'custom_metadata' => $metadata,
            'customer' => [
                'firstname' => $this->resolveCustomerName($payer),
                'email' => $payer->email,
            ],
        ]);

        /** @var object{url:string} $tokenObj */
        $tokenObj = $transaction->generateToken();

        return [
            'fedapay_transaction_id' => (int) $transaction->id,
            'checkout_url' => $tokenObj->url,
        ];
    }

    /**
     * Verify a Fedapay webhook signature and return the event.
     *
     * @throws \FedaPay\Error\SignatureVerification
     */
    public function verifyWebhookSignature(string $payload, string $signature): object
    {
        return Webhook::constructEvent(
            $payload,
            $signature,
            config('services.fedapay.webhook_secret')
        );
    }

    /**
     * Retrieve a Fedapay transaction by ID.
     */
    public function retrieveTransaction(int $id): Transaction
    {
        /** @var Transaction $transaction */
        $transaction = Transaction::retrieve($id);

        return $transaction;
    }

    /**
     * Regenerate a fresh checkout URL for an existing Fedapay transaction.
     * Combines retrieve + generateToken in one call so callers that only need
     * a refreshed URL (no status branching) can do it with a single roundtrip.
     *
     * @return array{checkout_url: string, fedapay_status: string}
     */
    public function regenerateTokenForTransaction(int $fedapayTransactionId): array
    {
        /** @var Transaction $transaction */
        $transaction = Transaction::retrieve($fedapayTransactionId);

        return $this->regenerateTokenFromTransaction($transaction);
    }

    /**
     * Companion of regenerateTokenForTransaction for the case where the caller
     * already holds a Transaction object (e.g., after branching on its status).
     * Avoids a second HTTP roundtrip to Fedapay.
     *
     * @return array{checkout_url: string, fedapay_status: string}
     */
    public function regenerateTokenFromTransaction(Transaction $transaction): array
    {
        /** @var object{url:string} $tokenObj */
        $tokenObj = $transaction->generateToken();

        return [
            'checkout_url' => (string) $tokenObj->url,
            'fedapay_status' => (string) ($transaction->status ?? ''),
        ];
    }

    /**
     * Retrieve a summary of the current FedaPay account balances.
     *
     * @return array{
     *   available: bool,
     *   total_amount: int|null,
     *   refreshed_at: string,
     *   error: string|null
     * }
     */
    public function getBalanceSummary(): array
    {
        if ((string) config('services.fedapay.secret_key', '') === '') {
            return [
                'available' => false,
                'total_amount' => null,
                'refreshed_at' => now()->toIso8601String(),
                'error' => 'FedaPay n’est pas configuré.',
            ];
        }

        try {
            /** @var object{balances: iterable<object{amount:int|null}>} $response */
            $response = Balance::all();
            $totalAmount = (int) collect($response->balances ?? [])
                ->sum(fn (object $balance): int => (int) data_get($balance, 'amount', 0));

            return [
                'available' => true,
                'total_amount' => $totalAmount,
                'refreshed_at' => now()->toIso8601String(),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Unable to fetch FedaPay balances', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);

            return [
                'available' => false,
                'total_amount' => null,
                'refreshed_at' => now()->toIso8601String(),
                'error' => 'Impossible de récupérer le solde FedaPay pour le moment.',
            ];
        }
    }

    /**
     * Map internal payment mode slugs to FedaPay Payout API mode codes.
     *
     * @var array<string, string>
     */
    private const PAYOUT_MODE_MAP = [
        'mtn' => 'mtn_open',  // MTN Bénin
        'moov' => 'moov',      // Moov Bénin
        'celtiis' => 'sbin',      // Celtiis Bénin (anciennement BBCI)
        'momo_test' => 'mtn_open',  // Sandbox local — utilise mtn_open
    ];

    /**
     * Initiate a Mobile Money payout (withdrawal) to a Face.
     * Uses Fedapay Payout API: Payout::create() + sendNow().
     *
     * @param  array{number: string, country: string}  $phoneData
     * @return array{fedapay_payout_id: int, status: string}
     */
    public function initiateWithdrawal(int $amount, string $mode, string $idempotencyKey, array $phoneData, User $user): array
    {
        $fedapayMode = self::PAYOUT_MODE_MAP[$mode] ?? $mode;

        /** @var Payout $payout */
        $payout = Payout::create([
            'amount' => $amount,
            'currency' => ['iso' => 'XOF'],
            'mode' => $fedapayMode,
            'customer' => [
                'firstname' => $this->resolveCustomerName($user),
                'email' => $user->email,
                'phone_number' => [
                    'number' => $phoneData['number'],
                    'country' => $phoneData['country'],
                ],
            ],
            'metadata' => [
                'user_id' => $user->id,
                'idempotency_key' => $idempotencyKey,
            ],
        ]);

        $payout->sendNow([
            'phone_number' => [
                'number' => $phoneData['number'],
                'country' => $phoneData['country'],
            ],
        ]);

        return [
            'fedapay_payout_id' => (int) $payout->id,
            'status' => $payout->status,
        ];
    }

    private function resolveCustomerName(User $user): string
    {
        $user->loadMissing('userable');

        $displayName = data_get($user, 'userable.display_name');

        if (is_string($displayName) && $displayName !== '') {
            return $displayName;
        }

        return (string) $user->email;
    }
}
