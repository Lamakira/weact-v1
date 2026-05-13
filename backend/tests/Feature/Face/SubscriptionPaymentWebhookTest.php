<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Enums\FaceSubscriptionStatus;
use App\Enums\FinancialEventType;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Jobs\HandleFedapayWebhook;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\FedapayWebhookEvent;
use App\Models\FinancialEvent;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\Producer;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\BookingService;
use App\Services\FaceSubscriptionPaymentService;
use App\Services\MissionPaymentService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SubscriptionPaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private Face $face;

    private User $faceUser;

    private FaceSubscription $pendingSubscription;

    protected function setUp(): void
    {
        parent::setUp();

        config(['face_premium.annual_plan.amount' => 50000]);
        config(['face_premium.annual_plan.currency' => 'XOF']);
        config(['face_premium.annual_plan.provider' => 'fedapay']);

        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
        $this->pendingSubscription = FaceSubscription::factory()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => '777888',
            'paid_amount' => null,
            'metadata' => ['quoted_amount' => 50000],
        ]);
    }

    private function dispatchWebhook(string $fedapayEventId, string $eventName, array $payload): FedapayWebhookEvent
    {
        $webhookEvent = FedapayWebhookEvent::create([
            'fedapay_event_id' => $fedapayEventId,
            'event_name' => $eventName,
            'payload' => $payload,
            'status' => 'received',
        ]);

        $job = new HandleFedapayWebhook($webhookEvent->id, $eventName, $payload);

        $job->handle(
            app(BookingService::class),
            app(MissionPaymentService::class),
            app(WalletService::class),
            app(FaceSubscriptionPaymentService::class),
        );

        return $webhookEvent;
    }

    // -----------------------------------------------------------------------
    // Approved branch (AC #12, #14)
    // -----------------------------------------------------------------------

    public function test_webhook_approved_routes_to_face_subscription_branch_and_activates(): void
    {
        $payload = [
            'id' => 'evt_001',
            'name' => 'transaction.approved',
            'entity' => [
                'id' => 777888,
                'reference' => 'fedapay-ref-A',
                'amount' => 50000,
            ],
        ];

        $webhookEvent = $this->dispatchWebhook('evt_001', 'transaction.approved', $payload);

        $reloaded = $this->pendingSubscription->fresh();
        $this->assertSame(FaceSubscriptionStatus::Active, $reloaded->status);
        $this->assertNotNull($reloaded->starts_at);
        $this->assertNotNull($reloaded->expires_at);
        $this->assertLessThanOrEqual(5, abs($reloaded->starts_at->diffInSeconds(now(), false)));
        $this->assertLessThanOrEqual(5, abs($reloaded->expires_at->diffInSeconds(now()->copy()->addYear(), false)));
        $this->assertSame(50000, $reloaded->paid_amount);

        $this->assertSame('processed', $webhookEvent->fresh()->status);
    }

    public function test_webhook_approved_replay_is_no_op_on_already_active_row(): void
    {
        $payload = [
            'id' => 'evt_replay_1',
            'name' => 'transaction.approved',
            'entity' => [
                'id' => 777888,
                'reference' => 'fedapay-ref-replay',
                'amount' => 50000,
            ],
        ];

        $this->dispatchWebhook('evt_replay_1', 'transaction.approved', $payload);

        $firstExpiresAt = $this->pendingSubscription->fresh()->expires_at?->toIso8601String();
        $this->assertNotNull($firstExpiresAt);

        // Second webhook with distinct event id → envelope-level not deduped,
        // but row-level idempotency applies.
        $payload['id'] = 'evt_replay_2';
        $this->dispatchWebhook('evt_replay_2', 'transaction.approved', $payload);

        $secondExpiresAt = $this->pendingSubscription->fresh()->expires_at?->toIso8601String();
        $this->assertSame($firstExpiresAt, $secondExpiresAt);
        $this->assertSame(FaceSubscriptionStatus::Active, $this->pendingSubscription->fresh()->status);
    }

    public function test_webhook_approved_with_non_integer_amount_refuses_activation(): void
    {
        Log::spy();

        $invalidAmounts = [
            ['raw' => '99500.50', 'event_id' => 'evt_amount_float'],
            ['raw' => 'NaN', 'event_id' => 'evt_amount_nan'],
            ['raw' => '-100', 'event_id' => 'evt_amount_negative'],
            ['raw' => null, 'event_id' => 'evt_amount_null'],
            // P2 patch (code-review Rerun 3 2026-05-13): boolean `true` no longer
            // coerces to "1" via is_scalar — the is_bool guard rejects it.
            ['raw' => true, 'event_id' => 'evt_amount_bool_true'],
            ['raw' => false, 'event_id' => 'evt_amount_bool_false'],
        ];

        foreach ($invalidAmounts as $case) {
            $pending = FaceSubscription::factory()->pendingPayment()->create([
                'face_id' => $this->face->id,
                'provider_reference' => "ref_{$case['event_id']}",
                'paid_amount' => null,
                'metadata' => ['quoted_amount' => 50000],
            ]);

            $entity = [
                'id' => $pending->provider_reference,
                'reference' => "fedapay-{$case['event_id']}",
            ];
            if (array_key_exists('raw', $case)) {
                $entity['amount'] = $case['raw'];
            }

            $this->dispatchWebhook($case['event_id'], 'transaction.approved', [
                'id' => $case['event_id'],
                'name' => 'transaction.approved',
                'entity' => $entity,
            ]);

            $this->assertSame(
                FaceSubscriptionStatus::PendingPayment,
                $pending->fresh()->status,
                "Failed asserting PendingPayment for raw={$case['raw']}"
            );
            $this->assertNull($pending->fresh()->paid_amount);

            Log::shouldHaveReceived('warning')
                ->withArgs(function (string $message): bool {
                    return $message === 'Fedapay webhook: paid_amount fallback used';
                });
            Log::shouldHaveReceived('critical')
                ->withArgs(function (string $message): bool {
                    return $message === 'Fedapay webhook: paid_amount or currency mismatch — refusing activation, row left PendingPayment for admin review';
                });
        }

        // Missing amount key (different shape)
        $pendingMissing = FaceSubscription::factory()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => 'ref_missing_amount',
            'paid_amount' => null,
            'metadata' => ['quoted_amount' => 50000],
        ]);

        $this->dispatchWebhook('evt_amount_missing', 'transaction.approved', [
            'id' => 'evt_amount_missing',
            'name' => 'transaction.approved',
            'entity' => [
                'id' => $pendingMissing->provider_reference,
                'reference' => 'fedapay-missing',
            ],
        ]);

        $this->assertSame(FaceSubscriptionStatus::PendingPayment, $pendingMissing->fresh()->status);
        $this->assertNull($pendingMissing->fresh()->paid_amount);
    }

    // -----------------------------------------------------------------------
    // Declined branch (AC #12, #16)
    // -----------------------------------------------------------------------

    public function test_webhook_declined_routes_to_face_subscription_branch_and_fails(): void
    {
        $payload = [
            'id' => 'evt_002',
            'name' => 'transaction.declined',
            'entity' => [
                'id' => 777888,
                'reference' => 'fedapay-ref-B',
            ],
        ];

        $this->dispatchWebhook('evt_002', 'transaction.declined', $payload);

        $reloaded = $this->pendingSubscription->fresh();
        $this->assertSame(FaceSubscriptionStatus::Failed, $reloaded->status);
        $this->assertIsArray($reloaded->metadata);
        $this->assertSame('Payment transaction.declined', $reloaded->metadata['failure_reason']);
    }

    public function test_webhook_canceled_routes_to_face_subscription_branch_and_fails(): void
    {
        $payload = [
            'id' => 'evt_003',
            'name' => 'transaction.canceled',
            'entity' => [
                'id' => 777888,
                'reference' => 'fedapay-ref-C',
            ],
        ];

        $this->dispatchWebhook('evt_003', 'transaction.canceled', $payload);

        $reloaded = $this->pendingSubscription->fresh();
        $this->assertSame(FaceSubscriptionStatus::Failed, $reloaded->status);
        $this->assertIsArray($reloaded->metadata);
        $this->assertSame('Payment transaction.canceled', $reloaded->metadata['failure_reason']);
    }

    // -----------------------------------------------------------------------
    // Refund / transfer events — manual admin review (DN1 patch Rerun 3)
    // -----------------------------------------------------------------------

    public function test_webhook_refunded_logs_critical_and_does_not_revoke_active_subscription(): void
    {
        // DN1 patch (code-review Rerun 3 2026-05-13): refund events are not
        // auto-revoked (a refund could be partial — entitlement decision needs
        // human judgment). The Active row stays unchanged; ops gets paged via
        // Log::critical for manual admin pipeline.
        Log::spy();

        $activeSubscription = FaceSubscription::factory()->active()->create([
            'face_id' => $this->face->id,
            'provider_reference' => '555111',
        ]);
        $beforeStatus = $activeSubscription->status;
        $beforeExpiresAt = $activeSubscription->expires_at?->toIso8601String();

        $this->dispatchWebhook('evt_refund_001', 'transaction.refunded', [
            'id' => 'evt_refund_001',
            'name' => 'transaction.refunded',
            'entity' => [
                'id' => 555111,
                'reference' => 'fedapay-refund-ref',
                'amount' => 50000,
            ],
        ]);

        $reloaded = $activeSubscription->fresh();
        $this->assertSame($beforeStatus, $reloaded->status);
        $this->assertSame($beforeExpiresAt, $reloaded->expires_at?->toIso8601String());

        Log::shouldHaveReceived('critical')
            ->withArgs(function (string $message, array $context) use ($activeSubscription): bool {
                return $message === 'Fedapay webhook: face subscription refund/transfer requires manual admin review'
                    && ($context['event'] ?? null) === 'transaction.refunded'
                    && ($context['face_subscription_id'] ?? null) === $activeSubscription->id
                    && ($context['face_id'] ?? null) === $activeSubscription->face_id
                    && ($context['fedapay_transaction_id'] ?? null) === 555111;
            })
            ->once();
    }

    public function test_webhook_transferred_logs_critical_and_does_not_revoke_active_subscription(): void
    {
        Log::spy();

        $activeSubscription = FaceSubscription::factory()->active()->create([
            'face_id' => $this->face->id,
            'provider_reference' => '555222',
        ]);
        $beforeStatus = $activeSubscription->status;

        $this->dispatchWebhook('evt_transfer_001', 'transaction.transferred', [
            'id' => 'evt_transfer_001',
            'name' => 'transaction.transferred',
            'entity' => [
                'id' => 555222,
                'reference' => 'fedapay-transfer-ref',
            ],
        ]);

        $this->assertSame($beforeStatus, $activeSubscription->fresh()->status);

        Log::shouldHaveReceived('critical')
            ->withArgs(function (string $message, array $context) use ($activeSubscription): bool {
                return $message === 'Fedapay webhook: face subscription refund/transfer requires manual admin review'
                    && ($context['event'] ?? null) === 'transaction.transferred'
                    && ($context['face_subscription_id'] ?? null) === $activeSubscription->id;
            })
            ->once();
    }

    // -----------------------------------------------------------------------
    // Cross-domain isolation
    // -----------------------------------------------------------------------

    public function test_webhook_for_mission_payment_transaction_id_does_not_match_face_subscription(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
        $mission = Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::PendingPayment,
        ]);

        $missionPayment = MissionPayment::create([
            'mission_id' => $mission->id,
            'producer_id' => $producer->id,
            'status' => MissionPaymentStatus::Pending,
            'nombre_faces_retenues' => 1,
            'budget_par_face' => 90000,
            'montant_sous_total' => 90000,
            'commission_producteur' => 10000,
            'montant_total_producteur' => 100000,
            'commission_faces_total' => 5000,
            'montant_total_faces' => 85000,
            'fedapay_transaction_id' => '99001',
        ]);

        // Face subscription with a different transaction id
        $faceSub = FaceSubscription::factory()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => '888888',
            'paid_amount' => null,
            'metadata' => ['quoted_amount' => 50000],
        ]);

        // First: dispatch for MissionPayment id — Face subscription must NOT be touched
        $this->dispatchWebhook('evt_mission_cross', 'transaction.approved', [
            'id' => 'evt_mission_cross',
            'name' => 'transaction.approved',
            'entity' => [
                'id' => 99001,
                'reference' => 'fedapay-mission-ref',
            ],
        ]);

        // MissionPayment branch handled it — status flipped (Pending → Paid)
        $missionStatusAfterFirst = $missionPayment->fresh()->status;
        $this->assertNotSame(MissionPaymentStatus::Pending, $missionStatusAfterFirst);
        // FaceSubscription with provider_reference=888888 untouched
        $this->assertSame(FaceSubscriptionStatus::PendingPayment, $faceSub->fresh()->status);

        // Reverse: dispatch for FaceSubscription id — MissionPayment must NOT be touched
        $this->dispatchWebhook('evt_face_cross', 'transaction.approved', [
            'id' => 'evt_face_cross',
            'name' => 'transaction.approved',
            'entity' => [
                'id' => 888888,
                'reference' => 'fedapay-face-ref',
                'amount' => 50000,
            ],
        ]);

        $this->assertSame(FaceSubscriptionStatus::Active, $faceSub->fresh()->status);
        // MissionPayment unaffected by face flow (status unchanged since first dispatch)
        $this->assertSame($missionStatusAfterFirst, $missionPayment->fresh()->status);
    }

    public function test_payout_webhook_collision_with_face_subscription_reaches_withdrawal_branch(): void
    {
        $faceSub = FaceSubscription::factory()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => '12345',
            'paid_amount' => null,
        ]);

        $walletTransaction = WalletTransaction::create([
            'user_id' => $this->faceUser->id,
            'booking_id' => null,
            'type' => 'debit',
            'amount' => 25000,
            'reference' => 'withdrawal-test',
            'description' => 'Retrait test',
            'status' => 'pending',
        ]);

        FinancialEvent::create([
            'type' => FinancialEventType::Withdrawal,
            'booking_id' => null,
            'amount' => 25000,
            'fedapay_ref' => '12345',
            'idempotency_key' => 'withdrawal:12345',
            'status' => 'pending',
            'metadata' => [
                'wallet_transaction_id' => $walletTransaction->id,
                'user_id' => $this->faceUser->id,
            ],
        ]);

        $this->dispatchWebhook('evt_payout_collision', 'payout.sent', [
            'id' => 'evt_payout_collision',
            'name' => 'payout.sent',
            'entity' => [
                'id' => 12345,
                'reference' => 'payout-ref',
            ],
        ]);

        $this->assertSame('completed', $walletTransaction->fresh()->status);
        $this->assertSame(FaceSubscriptionStatus::PendingPayment, $faceSub->fresh()->status);
    }

    // -----------------------------------------------------------------------
    // Custom metadata fallback (AC #12bis)
    // -----------------------------------------------------------------------

    public function test_webhook_resolves_orphaned_pending_row_via_custom_metadata_fallback(): void
    {
        Log::spy();

        // Fresh isolated orphan (the setUp pendingSubscription has a provider_reference set,
        // so it would not qualify for the fallback path)
        $orphan = FaceSubscription::factory()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => null,
            'paid_amount' => null,
            'metadata' => [
                'quoted_amount' => 50000,
                'idempotency_key' => 'some-uuid',
            ],
        ]);

        // Primary lookup confirmation
        $this->assertNull(
            FaceSubscription::query()->where('provider_reference', '700001')->first(),
            'Primary lookup must miss before the fallback test runs.'
        );

        $this->dispatchWebhook('evt_orphan_001', 'transaction.approved', [
            'id' => 'evt_orphan_001',
            'name' => 'transaction.approved',
            'entity' => [
                'id' => 700001,
                'reference' => 'fedapay-orphan-ref',
                'amount' => 50000,
                'custom_metadata' => [
                    'face_subscription_id' => $orphan->id,
                    'type' => 'face_subscription',
                    'idempotency_key' => 'some-uuid',
                ],
            ],
        ]);

        $reloaded = $orphan->fresh();
        $this->assertSame(FaceSubscriptionStatus::Active, $reloaded->status);
        $this->assertSame(50000, $reloaded->paid_amount);
        // Patch E: fallback path must backfill provider_reference so a future
        // replay routes through the primary lookup and the unique constraint
        // guards against any collision.
        $this->assertSame('700001', $reloaded->provider_reference);

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context) use ($orphan): bool {
                return $message === 'Fedapay webhook: face subscription resolved via custom_metadata fallback'
                    && ($context['face_subscription_id'] ?? null) === $orphan->id
                    && ($context['fedapay_transaction_id'] ?? null) === '700001';
            });

        // Negative case 1: custom_metadata.type = 'mission' must NOT match face fallback
        $orphan2 = FaceSubscription::factory()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => null,
            'paid_amount' => null,
            'metadata' => ['idempotency_key' => 'uuid-orphan-2'],
        ]);

        $this->dispatchWebhook('evt_orphan_002', 'transaction.approved', [
            'id' => 'evt_orphan_002',
            'name' => 'transaction.approved',
            'entity' => [
                'id' => 700002,
                'reference' => 'fedapay-orphan-mismatch',
                'amount' => 50000,
                'custom_metadata' => [
                    'face_subscription_id' => $orphan2->id,
                    'type' => 'mission',
                ],
            ],
        ]);

        // No fallback match → orphan2 stays Pending
        $this->assertSame(FaceSubscriptionStatus::PendingPayment, $orphan2->fresh()->status);

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message): bool {
                return $message === 'Fedapay webhook: no booking, mission payment or withdrawal found for transaction';
            });

        // Negative case 2: idempotency key mismatch → no fallback match
        $orphan3 = FaceSubscription::factory()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => null,
            'paid_amount' => null,
            'metadata' => [
                'quoted_amount' => 50000,
                'idempotency_key' => 'row-key',
            ],
        ]);

        $this->dispatchWebhook('evt_orphan_idempotency_mismatch', 'transaction.approved', [
            'id' => 'evt_orphan_idempotency_mismatch',
            'name' => 'transaction.approved',
            'entity' => [
                'id' => 700005,
                'reference' => 'fedapay-orphan-key-mismatch',
                'amount' => 50000,
                'custom_metadata' => [
                    'face_subscription_id' => $orphan3->id,
                    'type' => 'face_subscription',
                    'idempotency_key' => 'different-key',
                ],
            ],
        ]);

        $this->assertSame(FaceSubscriptionStatus::PendingPayment, $orphan3->fresh()->status);

        Log::shouldHaveReceived('critical')
            ->withArgs(function (string $message): bool {
                return $message === 'Fedapay webhook: face subscription fallback idempotency mismatch — possible corruption, forged webhook, or unanticipated race';
            });

        // Negative case 3: non-existent face_subscription_id → no fallback match
        $this->dispatchWebhook('evt_orphan_003', 'transaction.approved', [
            'id' => 'evt_orphan_003',
            'name' => 'transaction.approved',
            'entity' => [
                'id' => 700003,
                'reference' => 'fedapay-orphan-missing',
                'amount' => 50000,
                'custom_metadata' => [
                    'face_subscription_id' => 999999,
                    'type' => 'face_subscription',
                ],
            ],
        ]);

        // orphan2 still untouched
        $this->assertSame(FaceSubscriptionStatus::PendingPayment, $orphan2->fresh()->status);

        // Negative case 4: already-resolved row (Active + provider_reference set) — fallback must NOT match
        $resolvedExpiresAt = $reloaded->expires_at?->toIso8601String();
        $resolved = $orphan->fresh();
        $resolved->update(['provider_reference' => '700001']);

        $this->dispatchWebhook('evt_orphan_004', 'transaction.approved', [
            'id' => 'evt_orphan_004',
            'name' => 'transaction.approved',
            'entity' => [
                'id' => 700004,
                'reference' => 'fedapay-orphan-already-resolved',
                'amount' => 50000,
                'custom_metadata' => [
                    'face_subscription_id' => $orphan->id,
                    'type' => 'face_subscription',
                ],
            ],
        ]);

        // Active row's expires_at is unchanged (idempotency at Active row level)
        $this->assertSame($resolvedExpiresAt, $orphan->fresh()->expires_at?->toIso8601String());
        $this->assertSame(FaceSubscriptionStatus::Active, $orphan->fresh()->status);
    }
}
