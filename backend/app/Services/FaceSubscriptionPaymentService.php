<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FaceSubscriptionPlan;
use App\Enums\FaceSubscriptionStatus;
use App\Events\FaceSubscriptionActivated;
use App\Exceptions\FaceSubscriptionConflictException;
use App\Exceptions\FaceSubscriptionPaymentInitiationException;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Online Face annual premium payment lifecycle.
 *
 * - initiate(): creates a pending row + delegates the hosted-checkout call to
 *   FedapayService, returns the redirect URL to the controller. Idempotent at
 *   the row level (existing pending row → 409) and at the cache lock level
 *   (concurrent initiate from same Face → 422).
 * - markAsPaid(): webhook-driven activation. Idempotent — Active is sticky.
 *   Chains starts_at from any existing active row's expires_at so that
 *   renewals never lose entitlement time.
 * - markAsFailed(): webhook-driven failure. Idempotent — Failed is terminal.
 *
 * Convention: face_subscriptions.provider_reference stores
 * `(string) $fedapay_transaction_id` from initiation onwards. The webhook
 * handler in HandleFedapayWebhook uses this column to route inbound events
 * back to the subscription row. The fedapay 'reference' string (post-approval)
 * lives in metadata.fedapay_reference.
 */
class FaceSubscriptionPaymentService
{
    private const INITIATE_LOCK_TTL_SECONDS = 120;

    private const INITIATE_LOCK_WAIT_SECONDS = 5;

    private const FINALIZE_MAX_ATTEMPTS = 3;

    private const FINALIZE_RETRY_BACKOFF_MICROSECONDS = 50_000;

    public function __construct(
        private readonly FedapayService $fedapayService,
    ) {}

    /**
     * @return array{subscription: FaceSubscription, checkout_url: string, amount: int, currency: string, forfeited_days: int}
     *
     * @throws FaceSubscriptionConflictException
     * @throws FaceSubscriptionPaymentInitiationException
     * @throws ValidationException
     */
    public function initiate(Face $face, User $faceUser, FaceSubscriptionPlan $plan): array
    {
        $configuredAmount = $plan->price();
        $configuredCurrency = (string) config('face_subscription_tiers.currency', 'XOF');
        $configuredProvider = (string) config('face_subscription_tiers.provider', 'fedapay');

        if ($configuredAmount <= 0) {
            throw FaceSubscriptionConflictException::planUnavailable();
        }

        $forfeitedDays = $this->forfeitedDaysFor($face, $plan);

        // Patch B (code-review v2 2026-05-12): the cache lock now wraps the
        // entire flow (pending row creation + Fedapay call + finalize), mirroring
        // MissionPaymentService::withMissionPaymentLock. Concurrent retries
        // wait for the in-flight initiation rather than racing past the DB
        // guard. Lock TTL (120s) bounds the worst-case Fedapay HTTP wait.
        $lock = Cache::lock("face_subscription_initiate_{$face->id}", self::INITIATE_LOCK_TTL_SECONDS);

        try {
            return $lock->block(self::INITIATE_LOCK_WAIT_SECONDS, function () use ($face, $faceUser, $plan, $configuredAmount, $configuredCurrency, $configuredProvider, $forfeitedDays): array {
                $idempotencyKey = Str::uuid()->toString();

                $subscription = DB::transaction(function () use ($face, $plan, $configuredAmount, $configuredCurrency, $configuredProvider, $idempotencyKey): FaceSubscription {
                    Face::query()->lockForUpdate()->findOrFail($face->id);

                    $existingPending = FaceSubscription::query()
                        ->where('face_id', $face->id)
                        ->where('status', FaceSubscriptionStatus::PendingPayment)
                        ->lockForUpdate()
                        ->exists();

                    if ($existingPending) {
                        throw FaceSubscriptionConflictException::pendingPaymentExistsForFace();
                    }

                    return FaceSubscription::create([
                        'face_id' => $face->id,
                        'plan' => $plan,
                        'status' => FaceSubscriptionStatus::PendingPayment,
                        'starts_at' => null,
                        'expires_at' => null,
                        'cancelled_at' => null,
                        'paid_amount' => null,
                        'currency' => $configuredCurrency,
                        'provider' => $configuredProvider,
                        'provider_reference' => null,
                        'metadata' => [
                            'quoted_amount' => $configuredAmount,
                            'quoted_currency' => $configuredCurrency,
                            'idempotency_key' => $idempotencyKey,
                            'initiated_at' => now()->toIso8601String(),
                        ],
                    ]);
                });

                try {
                    $remote = $this->fedapayService->initiatePaymentForFaceSubscription(
                        $subscription,
                        $faceUser,
                        $idempotencyKey,
                    );
                } catch (\Throwable $e) {
                    // Phase 'request_checkout' — preserve the pending row even
                    // though Fedapay returned an error. HTTP semantics cannot
                    // distinguish "Fedapay never created the transaction" from
                    // "Fedapay created the transaction but the response was lost",
                    // and deleting the row in the second case would orphan a
                    // real payment with no recovery handle. Admin recovers via
                    // FP-1.4 endpoints.
                    $this->logInitiationFailure(
                        subscription: $subscription,
                        phase: 'request_checkout',
                        remoteTransactionId: null,
                        manualRecoveryRequired: true,
                        exception: $e,
                    );
                    throw new FaceSubscriptionPaymentInitiationException(
                        'Le paiement de l\'abonnement n\'a pas pu être initialisé. Veuillez réessayer.',
                        previous: $e,
                    );
                }

                // Phase 'finalize_local' — Fedapay succeeded; we must persist
                // provider_reference so the webhook can route back. Retry on
                // transient DB failures, and on terminal failure DO NOT delete
                // the pending row (the webhook fallback at AC #12bis will
                // recover via custom_metadata.face_subscription_id).
                try {
                    $this->finalizePendingWithRetry($subscription, $remote, $idempotencyKey);
                } catch (\Throwable $finalizeException) {
                    $this->logInitiationFailure(
                        subscription: $subscription,
                        phase: 'finalize_local',
                        remoteTransactionId: $remote['fedapay_transaction_id'],
                        manualRecoveryRequired: true,
                        exception: $finalizeException,
                    );
                    throw new FaceSubscriptionPaymentInitiationException(
                        'Le paiement de l\'abonnement n\'a pas pu être initialisé. Veuillez réessayer.',
                        previous: $finalizeException,
                    );
                }

                /** @var FaceSubscription $fresh */
                $fresh = $subscription->fresh();

                if ($fresh->status === FaceSubscriptionStatus::Failed) {
                    throw new FaceSubscriptionPaymentInitiationException(
                        'Le paiement de l\'abonnement n\'a pas pu être initialisé. Veuillez réessayer.'
                    );
                }

                return [
                    'subscription' => $fresh,
                    'checkout_url' => $remote['checkout_url'],
                    'amount' => $configuredAmount,
                    'currency' => $configuredCurrency,
                    'forfeited_days' => $forfeitedDays,
                ];
            });
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages([
                'status' => ['Un paiement est déjà en cours pour cet abonnement.'],
            ]);
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * @param  array<string, mixed>  $rawWebhookPayload
     */
    public function markAsPaid(
        FaceSubscription $subscription,
        string $fedapayRef,
        ?int $paidAmount,
        array $rawWebhookPayload = [],
        ?string $providerReference = null,
    ): FaceSubscription {
        return DB::transaction(function () use ($subscription, $fedapayRef, $paidAmount, $rawWebhookPayload, $providerReference): FaceSubscription {
            /** @var FaceSubscription $locked */
            $locked = FaceSubscription::lockForUpdate()->findOrFail($subscription->id);

            if ($locked->status === FaceSubscriptionStatus::Active) {
                return $locked;
            }

            if ($this->isLocallyFailedPendingCleanup($locked)) {
                $metadata = is_array($locked->metadata) ? $locked->metadata : [];

                // Idempotence: a duplicate late-approval webhook (Fedapay retry,
                // admin replay, distinct event resolving to the same row) must
                // not overwrite the first-occurrence audit or re-fire the
                // manual-review critical alert. The first hit is the load-bearing
                // record for operator triage.
                if (isset($metadata['late_approved_after_local_failure_at'])) {
                    Log::warning('Fedapay webhook: duplicate late-approval webhook ignored — first late-approval audit preserved', [
                        'face_subscription_id' => $locked->id,
                        'face_id' => $locked->face_id,
                        'fedapay_reference' => $fedapayRef,
                        'provider_reference' => $providerReference,
                        'paid_amount' => $paidAmount,
                        'first_late_approved_at' => $metadata['late_approved_after_local_failure_at'],
                    ]);

                    return $locked;
                }

                $now = now()->toIso8601String();
                $updates = [
                    'metadata' => array_merge(
                        $metadata,
                        [
                            'late_approved_after_local_failure_at' => $now,
                            'late_approved_after_local_failure_reason' => 'manual_review_required',
                            'late_approved_fedapay_reference' => $fedapayRef,
                            'late_approved_provider_reference' => $providerReference,
                            'late_approved_paid_amount' => $paidAmount,
                            'late_approved_event_payload_summary' => [
                                'event_id' => $rawWebhookPayload['id'] ?? null,
                                'event_name' => $rawWebhookPayload['name'] ?? null,
                                'transaction_status' => data_get($rawWebhookPayload, 'entity.status')
                                    ?? data_get($rawWebhookPayload, 'data.status'),
                            ],
                        ],
                    ),
                ];

                if ($providerReference !== null && $locked->provider_reference === null) {
                    $updates['provider_reference'] = $providerReference;
                }

                $locked->update($updates);

                Log::critical('Fedapay webhook: approved payment arrived after local face subscription failure — manual review required', [
                    'face_subscription_id' => $locked->id,
                    'face_id' => $locked->face_id,
                    'current_status' => $locked->status->value,
                    'fedapay_reference' => $fedapayRef,
                    'provider_reference' => $providerReference,
                    'paid_amount' => $paidAmount,
                    'local_failure_source' => $this->localFailureSource($locked),
                ]);

                /** @var FaceSubscription $fresh */
                $fresh = $locked->fresh();

                return $fresh;
            }

            if ($locked->status !== FaceSubscriptionStatus::PendingPayment) {
                Log::warning('Fedapay webhook: ignoring approval for non-pending face subscription', [
                    'face_subscription_id' => $locked->id,
                    'current_status' => $locked->status->value,
                    'fedapay_reference' => $fedapayRef,
                ]);

                return $locked;
            }

            Face::query()->whereKey($locked->face_id)->lockForUpdate()->firstOrFail();

            $expectedAmount = $this->expectedAmountFor($locked);
            $expectedCurrency = $this->expectedCurrencyFor($locked);
            $reportedCurrency = $this->reportedCurrencyFrom($rawWebhookPayload);

            $amountMismatch = $paidAmount === null || $expectedAmount === null || $paidAmount !== $expectedAmount;
            $currencyMismatch = $expectedCurrency !== null && $reportedCurrency !== null && $reportedCurrency !== $expectedCurrency;

            if ($amountMismatch || $currencyMismatch) {
                $now = now()->toIso8601String();
                $mismatchMetadata = [
                    'fedapay_reference' => $fedapayRef,
                    'paid_amount_reported' => $paidAmount,
                    'expected_amount' => $expectedAmount,
                ];

                if ($amountMismatch) {
                    $mismatchMetadata['amount_mismatch_detected_at'] = $now;
                }

                if ($currencyMismatch) {
                    $mismatchMetadata['currency_mismatch_detected_at'] = $now;
                    $mismatchMetadata['reported_currency'] = $reportedCurrency;
                    $mismatchMetadata['expected_currency'] = $expectedCurrency;
                }

                $updates = [
                    'metadata' => array_merge(
                        is_array($locked->metadata) ? $locked->metadata : [],
                        $mismatchMetadata,
                    ),
                ];

                if ($providerReference !== null && $locked->provider_reference === null) {
                    $updates['provider_reference'] = $providerReference;
                }

                $locked->update($updates);

                Log::critical('Fedapay webhook: paid_amount or currency mismatch — refusing activation, row left PendingPayment for admin review', [
                    'face_subscription_id' => $locked->id,
                    'face_id' => $locked->face_id,
                    'fedapay_reference' => $fedapayRef,
                    'paid_amount' => $paidAmount,
                    'expected_amount' => $expectedAmount,
                    'reported_currency' => $reportedCurrency,
                    'expected_currency' => $expectedCurrency,
                    'amount_mismatch' => $amountMismatch,
                    'currency_mismatch' => $currencyMismatch,
                ]);

                /** @var FaceSubscription $fresh */
                $fresh = $locked->fresh();

                return $fresh;
            }

            /** @var FaceSubscription|null $existingActive */
            $existingActive = FaceSubscription::query()
                ->where('face_id', $locked->face_id)
                ->where('status', FaceSubscriptionStatus::Active)
                ->where('expires_at', '>', now())
                ->where('id', '!=', $locked->id)
                ->lockForUpdate()
                ->orderByDesc('expires_at')
                ->orderByDesc('id')
                ->first();

            // FP-2.5: a tier change (upgrade or downgrade) is when the Face holds
            // a live active subscription on a DIFFERENT plan than this row. No
            // pro-rata — the 12-month window restarts now and the superseded row
            // is cancelled. A same-plan renewal still chains from the current
            // expiry so paid coverage time is never lost (FP-1.5 behavior).
            $isTierChange = $existingActive !== null && $existingActive->plan !== $locked->plan;

            $startsAt = $isTierChange
                ? Carbon::now()
                : ($existingActive?->expires_at?->copy() ?? Carbon::now());
            $expiresAt = $startsAt->copy()->addYear();

            $updates = [
                'status' => FaceSubscriptionStatus::Active,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'paid_amount' => $paidAmount,
                'metadata' => array_merge(
                    is_array($locked->metadata) ? $locked->metadata : [],
                    [
                        'fedapay_reference' => $fedapayRef,
                        'confirmed_at' => now()->toIso8601String(),
                        'fedapay_event_payload_summary' => [
                            // Whitelisted keys only — DO NOT spread the raw payload (provider leak guard pattern from FP-1.3 AC #5)
                            'event_id' => $rawWebhookPayload['id'] ?? null,
                            'event_name' => $rawWebhookPayload['name'] ?? null,
                            'transaction_status' => data_get($rawWebhookPayload, 'entity.status')
                                ?? data_get($rawWebhookPayload, 'data.status'),
                        ],
                    ],
                    $isTierChange
                        ? ['supersedes_subscription_id' => $existingActive->id]
                        : [],
                ),
            ];

            // AC #12bis fallback path: when the webhook resolved this row via
            // custom_metadata (not provider_reference), provider_reference is
            // still null. Backfill it here so a subsequent replay routes
            // through the primary lookup and the unique(provider_reference)
            // constraint guards against any future collision.
            if ($providerReference !== null && $locked->provider_reference === null) {
                $updates['provider_reference'] = $providerReference;
            }

            // FP-2.5: cancel the superseded active row in the SAME transaction so
            // a replay can never observe two active rows for the Face.
            if ($isTierChange) {
                $existingActive->update([
                    'status' => FaceSubscriptionStatus::Cancelled,
                    'cancelled_at' => $startsAt,
                    'metadata' => array_merge(
                        is_array($existingActive->metadata) ? $existingActive->metadata : [],
                        [
                            'superseded_by_subscription_id' => $locked->id,
                            'superseded_reason' => 'tier_change',
                            'superseded_at' => $startsAt->toIso8601String(),
                        ],
                    ),
                ]);
            }

            $locked->update($updates);

            /** @var FaceSubscription $fresh */
            $fresh = $locked->fresh();

            DB::afterCommit(fn (): mixed => FaceSubscriptionActivated::dispatch($fresh));

            return $fresh;
        });
    }

    public function markAsFailed(
        FaceSubscription $subscription,
        string $fedapayRef,
        string $reason,
        ?string $providerReference = null,
    ): FaceSubscription {
        return DB::transaction(function () use ($subscription, $fedapayRef, $reason, $providerReference): FaceSubscription {
            /** @var FaceSubscription $locked */
            $locked = FaceSubscription::lockForUpdate()->findOrFail($subscription->id);

            if ($locked->status === FaceSubscriptionStatus::Failed) {
                return $locked;
            }

            if ($locked->status !== FaceSubscriptionStatus::PendingPayment) {
                Log::warning('Fedapay webhook: ignoring failure for non-pending face subscription', [
                    'face_subscription_id' => $locked->id,
                    'current_status' => $locked->status->value,
                    'fedapay_reference' => $fedapayRef,
                    'reason' => $reason,
                ]);

                return $locked;
            }

            $updates = [
                'status' => FaceSubscriptionStatus::Failed,
                'metadata' => array_merge(
                    is_array($locked->metadata) ? $locked->metadata : [],
                    [
                        'fedapay_reference' => $fedapayRef,
                        'failure_reason' => $reason,
                        'failed_at' => now()->toIso8601String(),
                    ],
                ),
            ];

            // AC #12bis fallback path symmetry — backfill provider_reference
            // when the webhook resolved via custom_metadata.
            if ($providerReference !== null && $locked->provider_reference === null) {
                $updates['provider_reference'] = $providerReference;
            }

            $locked->update($updates);

            /** @var FaceSubscription $fresh */
            $fresh = $locked->fresh();

            return $fresh;
        });
    }

    /**
     * Flip the Face's pending_payment row to Failed on user request, unblocking
     * the FP-2.5 one-pending-row guard so the Face can immediately re-initiate.
     *
     * Race contract: lockForUpdate serializes against HandleFedapayWebhook's
     * markAsPaid / markAsFailed. If the webhook wins (status becomes Active or
     * Failed before we lock), this method throws noPendingPayment() → 404 ; if
     * we win (row becomes Failed before the webhook lands), the webhook hits
     * the existing non-pending guard in markAsPaid (after the
     * isLocallyFailedPendingCleanup branch) or markAsFailed (after the Failed
     * short-circuit) and early-returns with a Log::warning, leaving our Failed
     * row intact. No double-update.
     *
     * @throws FaceSubscriptionConflictException Via factory `noPendingPayment()` (404, errorCode `NO_PENDING_PAYMENT`) when the Face has no pending row.
     */
    public function cancelOwnPending(Face $face): FaceSubscription
    {
        return DB::transaction(function () use ($face): FaceSubscription {
            /** @var FaceSubscription|null $pending */
            $pending = FaceSubscription::query()
                ->where('face_id', $face->id)
                ->where('status', FaceSubscriptionStatus::PendingPayment)
                ->lockForUpdate()
                ->first();

            if ($pending === null) {
                throw FaceSubscriptionConflictException::noPendingPayment();
            }

            $pending->update([
                'status' => FaceSubscriptionStatus::Failed,
                'metadata' => array_merge(
                    is_array($pending->metadata) ? $pending->metadata : [],
                    [
                        'cancelled_by_user_at' => now()->toIso8601String(),
                        'cancellation_source' => 'user_self_cancel',
                    ],
                ),
            ]);

            /** @var FaceSubscription $fresh */
            $fresh = $pending->fresh();

            return $fresh;
        });
    }

    /**
     * Poll Fedapay for the current pending subscription and reconcile local
     * state when the webhook has not landed yet.
     */
    public function checkAndProcessPayment(Face $face): ?FaceSubscription
    {
        /** @var FaceSubscription|null $subscription */
        $subscription = FaceSubscription::query()
            ->where('face_id', $face->id)
            ->where('status', FaceSubscriptionStatus::PendingPayment)
            ->latest('id')
            ->first();

        if (! $subscription || ! $subscription->provider_reference) {
            return $subscription;
        }

        $transaction = $this->fedapayService->retrieveTransaction((int) $subscription->provider_reference);
        $fedapayRef = (string) ($transaction->reference ?? $subscription->provider_reference);
        $remoteStatus = (string) ($transaction->status ?? '');
        $providerReference = (string) $subscription->provider_reference;

        if ($remoteStatus === 'approved') {
            return $this->markAsPaid(
                $subscription,
                $fedapayRef,
                $this->extractPaidAmountFromTransaction($transaction),
                $this->transactionPayload($transaction),
                $providerReference,
            );
        }

        if (in_array($remoteStatus, ['declined', 'canceled'], true)) {
            return $this->markAsFailed(
                $subscription,
                $fedapayRef,
                "Payment transaction.{$remoteStatus}",
                $providerReference,
            );
        }

        return $subscription;
    }

    /**
     * Persist the Fedapay transaction id on the pending row. Retries up to
     * FINALIZE_MAX_ATTEMPTS times on transient DB failures (lock timeout,
     * deadlock, connection drop). On exhaustion, re-throws the last exception
     * WITHOUT deleting the row — the caller's webhook fallback (AC #12bis)
     * will recover via custom_metadata.face_subscription_id.
     *
     * @param  array{fedapay_transaction_id: int, checkout_url: string}  $remote
     */
    protected function finalizePendingWithRetry(
        FaceSubscription $subscription,
        array $remote,
        string $idempotencyKey,
    ): void {
        $lastException = null;

        for ($attempt = 1; $attempt <= self::FINALIZE_MAX_ATTEMPTS; $attempt++) {
            try {
                $this->runFinalizeTransaction($subscription, $remote, $idempotencyKey);

                return;
            } catch (\Throwable $e) {
                $lastException = $e;

                if ($attempt < self::FINALIZE_MAX_ATTEMPTS) {
                    usleep(self::FINALIZE_RETRY_BACKOFF_MICROSECONDS);
                }
            }
        }

        // All retries exhausted — re-throw the last exception. The caller
        // logs `manual_recovery_required = true` and surfaces a 502. The
        // pending row is intentionally NOT deleted; webhook recovery via
        // AC #12bis custom_metadata fallback remains possible.
        throw $lastException;
    }

    /**
     * Run one finalize transaction attempt. Extracted as a `protected` method
     * to give tests a narrow seam to override (anonymous-subclass pattern):
     * a test fixture can throw from this method to simulate transient DB
     * failures without breaking the initial pending-row insert in `initiate`.
     *
     * @param  array{fedapay_transaction_id: int, checkout_url: string}  $remote
     */
    protected function runFinalizeTransaction(
        FaceSubscription $subscription,
        array $remote,
        string $idempotencyKey,
    ): void {
        DB::transaction(function () use ($subscription, $remote, $idempotencyKey): void {
            /** @var FaceSubscription $locked */
            $locked = FaceSubscription::lockForUpdate()->findOrFail($subscription->id);

            $updates = [
                'provider_reference' => (string) $remote['fedapay_transaction_id'],
            ];

            if ($locked->status === FaceSubscriptionStatus::PendingPayment) {
                $updates['metadata'] = array_merge(
                    is_array($locked->metadata) ? $locked->metadata : [],
                    [
                        'fedapay_transaction_id' => $remote['fedapay_transaction_id'],
                        'idempotency_key' => $idempotencyKey,
                        'initiated_at' => now()->toIso8601String(),
                    ],
                );
            }

            $locked->update($updates);
        });
    }

    /**
     * Whole days the Face forfeits by switching to $plan now: the remaining
     * coverage of their current live active subscription when its tier differs
     * from $plan. Zero for a first-time activation or a same-tier renewal — a
     * renewal chains, so it forfeits nothing. This is an estimate computed at
     * initiation; the authoritative cancellation happens in markAsPaid().
     */
    private function forfeitedDaysFor(Face $face, FaceSubscriptionPlan $plan): int
    {
        /** @var FaceSubscription|null $currentActive */
        $currentActive = FaceSubscription::query()
            ->where('face_id', $face->id)
            ->where('status', FaceSubscriptionStatus::Active)
            ->where('expires_at', '>', now())
            ->orderByDesc('expires_at')
            ->orderByDesc('id')
            ->first();

        if ($currentActive === null
            || $currentActive->plan === $plan
            || $currentActive->expires_at === null
        ) {
            return 0;
        }

        return max(0, (int) ceil(Carbon::now()->diffInDays($currentActive->expires_at)));
    }

    private function expectedAmountFor(FaceSubscription $subscription): ?int
    {
        $metadata = is_array($subscription->metadata) ? $subscription->metadata : [];
        $quotedAmount = $metadata['quoted_amount'] ?? null;

        if (is_int($quotedAmount) && $quotedAmount > 0) {
            return $quotedAmount;
        }

        if (is_string($quotedAmount) && ctype_digit($quotedAmount) && (int) $quotedAmount > 0) {
            return (int) $quotedAmount;
        }

        return null;
    }

    private function expectedCurrencyFor(FaceSubscription $subscription): ?string
    {
        $metadata = is_array($subscription->metadata) ? $subscription->metadata : [];
        $quotedCurrency = $metadata['quoted_currency'] ?? null;

        return is_string($quotedCurrency) && $quotedCurrency !== '' ? $quotedCurrency : null;
    }

    /**
     * @param  array<string, mixed>  $rawWebhookPayload
     */
    private function reportedCurrencyFrom(array $rawWebhookPayload): ?string
    {
        $reported = data_get($rawWebhookPayload, 'entity.currency.iso')
            ?? data_get($rawWebhookPayload, 'data.currency.iso');

        return is_string($reported) && $reported !== '' ? $reported : null;
    }

    private function extractPaidAmountFromTransaction(object $transaction): ?int
    {
        $raw = $transaction->amount ?? null;
        $stringValue = (! is_bool($raw) && is_scalar($raw)) ? (string) $raw : '';

        if ($stringValue !== '' && preg_match('/^\d+$/', $stringValue) === 1) {
            $intValue = (int) $stringValue;

            if ($intValue > 0) {
                return $intValue;
            }
        }

        Log::warning('Fedapay poll: paid_amount fallback used for face subscription', [
            'raw' => $raw,
            'fedapay_transaction_id' => $transaction->id ?? null,
        ]);

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionPayload(object $transaction): array
    {
        return [
            'entity' => [
                'id' => $transaction->id ?? null,
                'reference' => $transaction->reference ?? null,
                'status' => $transaction->status ?? null,
                'amount' => $transaction->amount ?? null,
                'currency' => [
                    'iso' => data_get($transaction, 'currency.iso'),
                ],
            ],
        ];
    }

    private function logInitiationFailure(
        FaceSubscription $subscription,
        string $phase,
        ?int $remoteTransactionId,
        bool $manualRecoveryRequired,
        \Throwable $exception,
    ): void {
        Log::error('Face subscription payment initiation failed', [
            'phase' => $phase,
            'face_subscription_id' => $subscription->id,
            'face_id' => $subscription->face_id,
            'remote_transaction_id' => $remoteTransactionId,
            'manual_recovery_required' => $manualRecoveryRequired,
            'error_class' => $exception::class,
            'error_message' => $exception->getMessage(),
        ]);
    }

    private function isLocallyFailedPendingCleanup(FaceSubscription $subscription): bool
    {
        return $subscription->status === FaceSubscriptionStatus::Failed
            && $this->localFailureSource($subscription) !== null;
    }

    private function localFailureSource(FaceSubscription $subscription): ?string
    {
        $metadata = is_array($subscription->metadata) ? $subscription->metadata : [];

        if (($metadata['cancellation_source'] ?? null) === 'user_self_cancel') {
            return 'user_self_cancel';
        }

        if (($metadata['stale_pending_reason'] ?? null) === 'auto_failed_by_cron') {
            return 'auto_failed_by_cron';
        }

        return null;
    }
}
