<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\FaceSubscriptionStatus;
use App\Enums\FinancialEventType;
use App\Enums\MissionType;
use App\Models\Booking;
use App\Models\FaceSubscription;
use App\Models\FedapayWebhookEvent;
use App\Models\FinancialEvent;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\WalletTransaction;
use App\Services\BookingService;
use App\Services\FaceSubscriptionPaymentService;
use App\Services\MissionPaymentService;
use App\Services\Ugc\UgcCommissionPaymentService;
use App\Services\WalletService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandleFedapayWebhook implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly int $webhookEventId,
        public readonly string $eventName,
        public readonly array $payload,
    ) {}

    public function handle(BookingService $bookingService, MissionPaymentService $missionPaymentService, WalletService $walletService, FaceSubscriptionPaymentService $facePaymentService, ?UgcCommissionPaymentService $ugcPaymentService = null): void
    {
        // Resolved here rather than as a required signature param so existing
        // direct-call tests (BookingPaymentTest, MissionPaymentInitiationTest,
        // SubscriptionCancelReactivationTest) that pass 4 args keep working. Real
        // queue dispatch still injects the concrete service via method injection.
        $ugcPaymentService ??= app(UgcCommissionPaymentService::class);

        $webhookEvent = FedapayWebhookEvent::find($this->webhookEventId);

        if (! $webhookEvent || $webhookEvent->status === 'processed') {
            return;
        }

        $transactionData = $this->payload['entity'] ?? $this->payload['data'] ?? [];
        $transactionId = $transactionData['id'] ?? null;
        $fedapayRef = (string) ($transactionData['reference'] ?? $transactionId ?? '');

        if (! $transactionId) {
            Log::warning('Fedapay webhook missing transaction ID', [
                'event_name' => $this->eventName,
                'webhook_event_id' => $this->webhookEventId,
            ]);
            $this->markProcessed($webhookEvent);

            return;
        }

        // Try to find a Booking first
        $booking = Booking::where('fedapay_transaction_id', $transactionId)->first();

        if ($booking) {
            if ($booking->type_contenu === 'UGC') {
                // UGC commission settlement — dedicated path, no escrow (D-1.5.b).
                match ($this->eventName) {
                    'transaction.approved' => $ugcPaymentService->markBookingCommissionPaid($booking, $fedapayRef),
                    'transaction.declined', 'transaction.canceled' => $ugcPaymentService->markBookingCommissionFailed(
                        $booking,
                        $fedapayRef,
                        "Payment {$this->eventName}"
                    ),
                    // Settlement refund UGC = crédit wallet synchrone (story 2.6) ;
                    // un transaction.refunded UGC ne doit donc plus survenir. S'il
                    // arrive (geste ops manuel hors-procédure au dashboard FedaPay),
                    // on log sans régler (D-2.6.f) — surtout pas de double crédit.
                    'transaction.refunded' => Log::critical('Fedapay webhook: refund UGC inattendu — les refunds UGC se règlent désormais via wallet, vérifier ce geste ops hors-procédure', [
                        'booking_id' => $booking->id,
                        'fedapay_transaction_id' => $transactionId,
                        'transaction_status' => $transactionData['status'] ?? null,
                    ]),
                    default => Log::info('Fedapay webhook: unhandled UGC booking event', ['event' => $this->eventName]),
                };
            } else {
                match ($this->eventName) {
                    'transaction.approved' => $bookingService->markAsPaid($booking, $fedapayRef),
                    'transaction.declined', 'transaction.canceled' => $bookingService->markPaymentFailed(
                        $booking,
                        $fedapayRef,
                        "Payment {$this->eventName}"
                    ),
                    default => Log::info('Fedapay webhook: unhandled event', ['event' => $this->eventName]),
                };
            }

            $this->markProcessed($webhookEvent);

            return;
        }

        // Try to find a MissionPayment
        $missionPayment = MissionPayment::where('fedapay_transaction_id', (string) $transactionId)->first();

        if ($missionPayment) {
            match ($this->eventName) {
                'transaction.approved' => $missionPaymentService->markAsPaid($missionPayment, $fedapayRef),
                'transaction.declined', 'transaction.canceled' => Log::warning('Mission payment declined or canceled by webhook', [
                    'payment_id' => $missionPayment->id,
                    'mission_id' => $missionPayment->mission_id,
                    'producer_id' => $missionPayment->producer_id,
                    'phase' => 'webhook',
                    'remote_transaction_id' => $missionPayment->fedapay_transaction_id,
                    'event_name' => $this->eventName,
                ]),
                default => Log::info('Fedapay webhook: unhandled event', ['event' => $this->eventName]),
            };

            $this->markProcessed($webhookEvent);

            return;
        }

        if (str_starts_with($this->eventName, 'transaction.')) {
            // Try to find a UGC mission (publication commission). Disjoint from the
            // MissionPayment lookup above: a UGC mission has no MissionPayment row,
            // and a standard mission has no missions.fedapay_transaction_id (D-1.5.d).
            // Kept inside the transaction.* guard so a payout.* event cannot match a
            // mission by id collision (payouts fall through to the withdrawal lookup).
            $ugcMission = Mission::where('fedapay_transaction_id', $transactionId)
                ->where('type_mission', MissionType::Ugc)
                ->first();

            if ($ugcMission) {
                match ($this->eventName) {
                    'transaction.approved' => $ugcPaymentService->markMissionCommissionPaid($ugcMission, $fedapayRef),
                    'transaction.declined', 'transaction.canceled' => $ugcPaymentService->markMissionCommissionFailed(
                        $ugcMission,
                        $fedapayRef,
                        "Payment {$this->eventName}"
                    ),
                    // Settlement refund UGC = crédit wallet synchrone (story 2.6) ;
                    // un transaction.refunded UGC ne doit donc plus survenir. S'il
                    // arrive (geste ops manuel hors-procédure au dashboard FedaPay),
                    // on log sans régler (D-2.6.f) — surtout pas de double crédit.
                    'transaction.refunded' => Log::critical('Fedapay webhook: refund UGC inattendu — les refunds UGC se règlent désormais via wallet, vérifier ce geste ops hors-procédure', [
                        'mission_id' => $ugcMission->id,
                        'fedapay_transaction_id' => $transactionId,
                        'transaction_status' => $transactionData['status'] ?? null,
                    ]),
                    default => Log::info('Fedapay webhook: unhandled UGC mission event', ['event' => $this->eventName]),
                };

                $this->markProcessed($webhookEvent);

                return;
            }

            // Try to find a FaceSubscription (annual premium payment).
            // resolveFaceSubscription() does a two-step lookup: primary by
            // provider_reference (happy path), fallback by
            // custom_metadata.face_subscription_id (recovery for the AC #6bis
            // remote-success/local-finalize-failure mode).
            $faceSubscription = $this->resolveFaceSubscription((string) $transactionId, $transactionData);

            if ($faceSubscription) {
                $providerReference = (string) $transactionId;

                match ($this->eventName) {
                    'transaction.approved' => $facePaymentService->markAsPaid(
                        $faceSubscription,
                        $fedapayRef,
                        $this->extractPaidAmount($transactionData),
                        $this->payload,
                        $providerReference,
                    ),
                    'transaction.declined', 'transaction.canceled' => $facePaymentService->markAsFailed(
                        $faceSubscription,
                        $fedapayRef,
                        "Payment {$this->eventName}",
                        $providerReference,
                    ),
                    'transaction.refunded', 'transaction.transferred' => Log::critical('Fedapay webhook: face subscription refund/transfer requires manual admin review', [
                        'event' => $this->eventName,
                        'face_subscription_id' => $faceSubscription->id,
                        'face_id' => $faceSubscription->face_id,
                        'fedapay_transaction_id' => $transactionId,
                    ]),
                    default => Log::info('Fedapay webhook: unhandled event for face subscription', [
                        'event' => $this->eventName,
                        'face_subscription_id' => $faceSubscription->id,
                    ]),
                };

                $this->markProcessed($webhookEvent);

                return;
            }
        }

        // Try to find a withdrawal FinancialEvent (payout.sent / payout.failed)
        $financialEvent = FinancialEvent::where('fedapay_ref', (string) $transactionId)
            ->where('type', FinancialEventType::Withdrawal)
            ->first();

        if ($financialEvent) {
            $this->handlePayoutWebhook($financialEvent, $walletService);
            $this->markProcessed($webhookEvent);

            return;
        }

        Log::warning('Fedapay webhook: no booking, mission payment or withdrawal found for transaction', [
            'transaction_id' => $transactionId,
            'event_name' => $this->eventName,
        ]);

        $this->markProcessed($webhookEvent);
    }

    private function handlePayoutWebhook(FinancialEvent $financialEvent, WalletService $walletService): void
    {
        /** @var array<string, mixed> $metadata */
        $metadata = is_array($financialEvent->metadata) ? $financialEvent->metadata : [];
        $walletTransactionId = isset($metadata['wallet_transaction_id']) ? (int) $metadata['wallet_transaction_id'] : null;
        $userId = isset($metadata['user_id']) ? (int) $metadata['user_id'] : null;

        if ($walletTransactionId === null || $userId === null) {
            Log::warning('Fedapay payout webhook: missing metadata on FinancialEvent', [
                'financial_event_id' => $financialEvent->id,
                'event_name' => $this->eventName,
            ]);

            return;
        }

        $walletTx = WalletTransaction::find($walletTransactionId);

        if (! $walletTx || $walletTx->status !== 'pending') {
            return;
        }

        match ($this->eventName) {
            'payout.sent' => $walletTx->update(['status' => 'completed']),
            'payout.failed' => DB::transaction(function () use ($walletTx, $userId, $financialEvent, $walletService): void {
                $walletTx->update(['status' => 'failed']);
                $walletService->creditDirect(
                    userId: $userId,
                    amount: $financialEvent->amount,
                    description: "Remboursement retrait échoué — ref #{$financialEvent->fedapay_ref}",
                );
                Log::info('Fedapay payout failed — balance refunded', [
                    'user_id' => $userId,
                    'amount' => $financialEvent->amount,
                ]);
            }),
            default => Log::info('Fedapay payout webhook: unhandled event', ['event' => $this->eventName]),
        };
    }

    private function markProcessed(FedapayWebhookEvent $webhookEvent): void
    {
        $webhookEvent->update([
            'status' => 'processed',
            'processed_at' => now(),
        ]);
    }

    /**
     * Resolve the FaceSubscription this webhook belongs to. Two-step lookup:
     *
     * 1. Primary — match by face_subscriptions.provider_reference (the happy
     *    path; provider_reference is written by FaceSubscriptionPaymentService::
     *    finalizePendingWithRetry after Fedapay confirms the transaction id).
     * 2. Fallback — if the primary misses, inspect Fedapay's custom_metadata
     *    (embedded by FedapayService::initiatePaymentForFaceSubscription) and
     *    resolve by face_subscription_id when `type === 'face_subscription'`.
     *    The fallback only matches pending rows with a NULL provider_reference,
     *    so it cannot collide with the primary path. See AC #12bis.
     *
     * Returns null if neither lookup matches; the caller falls through to the
     * "no booking, mission payment or withdrawal found" warning.
     *
     * @param  array<string, mixed>  $transactionData
     */
    private function resolveFaceSubscription(string $transactionId, array $transactionData): ?FaceSubscription
    {
        $primary = FaceSubscription::query()
            ->where('provider_reference', $transactionId)
            ->first();

        if ($primary) {
            return $primary;
        }

        $customMetadata = data_get($transactionData, 'custom_metadata', []);

        if (! is_array($customMetadata)) {
            return null;
        }

        if (($customMetadata['type'] ?? null) !== 'face_subscription') {
            return null;
        }

        $faceSubscriptionId = $customMetadata['face_subscription_id'] ?? null;

        if (! is_int($faceSubscriptionId) && ! (is_string($faceSubscriptionId) && ctype_digit($faceSubscriptionId))) {
            return null;
        }

        $fallback = FaceSubscription::query()
            ->whereKey((int) $faceSubscriptionId)
            ->whereNull('provider_reference')
            ->first();

        if ($fallback && ! $this->canResolveFaceSubscriptionFallback($fallback)) {
            return null;
        }

        if ($fallback) {
            $rowMetadata = is_array($fallback->metadata) ? $fallback->metadata : [];
            $webhookIdempotencyKey = $customMetadata['idempotency_key'] ?? null;
            $rowIdempotencyKey = $rowMetadata['idempotency_key'] ?? null;

            if (! is_string($webhookIdempotencyKey) || ! is_string($rowIdempotencyKey) || $webhookIdempotencyKey !== $rowIdempotencyKey) {
                Log::critical('Fedapay webhook: face subscription fallback idempotency mismatch — possible corruption, forged webhook, or unanticipated race', [
                    'face_subscription_id' => $fallback->id,
                    'fedapay_transaction_id' => $transactionId,
                    'webhook_event_id' => $this->webhookEventId,
                ]);

                return null;
            }
        }

        if ($fallback) {
            Log::warning('Fedapay webhook: face subscription resolved via custom_metadata fallback', [
                'face_subscription_id' => $fallback->id,
                'fedapay_transaction_id' => $transactionId,
                'webhook_event_id' => $this->webhookEventId,
            ]);
        }

        return $fallback;
    }

    private function canResolveFaceSubscriptionFallback(FaceSubscription $subscription): bool
    {
        if ($subscription->status === FaceSubscriptionStatus::PendingPayment) {
            return true;
        }

        if ($subscription->status !== FaceSubscriptionStatus::Failed || $this->eventName !== 'transaction.approved') {
            return false;
        }

        $metadata = is_array($subscription->metadata) ? $subscription->metadata : [];

        return ($metadata['cancellation_source'] ?? null) === 'user_self_cancel'
            || ($metadata['stale_pending_reason'] ?? null) === 'auto_failed_by_cron';
    }

    /**
     * Defensive coercion for Fedapay-supplied `amount` payload values. Returns
     * null on any parse failure (negative, float with decimals, "NaN",
     * non-numeric, missing) so the payment service can refuse activation.
     *
     * Closes FP-1.1 deferred-work item at deferred-work.md:256.
     *
     * @param  array<string, mixed>  $transactionData
     */
    private function extractPaidAmount(array $transactionData): ?int
    {
        $raw = $transactionData['amount'] ?? null;
        $stringValue = (! is_bool($raw) && is_scalar($raw)) ? (string) $raw : '';

        if ($stringValue !== '' && preg_match('/^\d+$/', $stringValue) === 1) {
            $intValue = (int) $stringValue;

            if ($intValue > 0) {
                return $intValue;
            }
        }

        Log::warning('Fedapay webhook: paid_amount fallback used', [
            'raw' => $raw,
            'fedapay_event_id' => $this->webhookEventId,
        ]);

        return null;
    }
}
