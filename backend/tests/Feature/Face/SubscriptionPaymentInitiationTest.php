<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Enums\FaceSubscriptionPlan;
use App\Enums\FaceSubscriptionStatus;
use App\Enums\FaceSubscriptionTier;
use App\Events\FaceSubscriptionActivated;
use App\Events\FaceSubscriptionCancelled;
use App\Events\FaceSubscriptionExpired;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Producer;
use App\Models\User;
use App\Services\FaceEntitlementService;
use App\Services\FaceSubscriptionPaymentService;
use App\Services\FedapayService;
use FedaPay\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SubscriptionPaymentInitiationTest extends TestCase
{
    use RefreshDatabase;

    private Face $face;

    private User $faceUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    // -----------------------------------------------------------------------
    // Auth matrix (AC #1, #2)
    // -----------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401_with_envelope(): void
    {
        $response = $this->postJson('/api/v1/face/subscription/initiate-payment', ['plan' => 'pro']);

        $response->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED')
            ->assertJsonPath('error.message', 'Non authentifié.');
    }

    public function test_producer_user_returns_403_with_face_envelope(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
            'email_verified_at' => null,
        ]);

        $response = $this->actingAs($producerUser)
            ->postJson('/api/v1/face/subscription/initiate-payment', ['plan' => 'pro']);

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', 'Accès réservé aux Faces');
    }

    public function test_orphan_user_with_null_userable_returns_403_with_face_envelope(): void
    {
        $orphanUser = User::factory()->create([
            'userable_type' => null,
            'userable_id' => null,
        ]);

        $response = $this->actingAs($orphanUser)
            ->postJson('/api/v1/face/subscription/initiate-payment', ['plan' => 'pro']);

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', 'Accès réservé aux Faces');
    }

    public function test_admin_user_with_bearer_token_returns_403_with_face_envelope(): void
    {
        // Admin authenticating against the Face surface arrives as a User
        // with userable_type = null (no Admin row pivot). This explicitly
        // covers the same gate as the orphan test for clarity in regression
        // hunts.
        $adminLikeUser = User::factory()->create([
            'userable_type' => null,
            'userable_id' => null,
        ]);

        $response = $this->actingAs($adminLikeUser)
            ->postJson('/api/v1/face/subscription/initiate-payment', ['plan' => 'pro']);

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', 'Accès réservé aux Faces');
    }

    public function test_face_with_unverified_email_returns_403_email_not_verified(): void
    {
        $face = Face::factory()->create();
        $unverifiedFaceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email_verified_at' => null,
        ]);

        $response = $this->actingAs($unverifiedFaceUser)
            ->postJson('/api/v1/face/subscription/initiate-payment', ['plan' => 'pro']);

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'EMAIL_NOT_VERIFIED')
            ->assertJsonPath('error.message', 'Vous devez vérifier votre email pour effectuer cette action.');

        // No FaceSubscription row should be created when the verified gate blocks.
        $this->assertSame(0, FaceSubscription::query()->where('face_id', $face->id)->count());
    }

    // -----------------------------------------------------------------------
    // Successful initiation (AC #4, #5, #8)
    // -----------------------------------------------------------------------

    public function test_face_with_no_subscription_initiates_successfully_and_creates_pending_row(): void
    {
        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('initiatePaymentForFaceSubscription')
                ->once()
                ->andReturn([
                    'fedapay_transaction_id' => 123456,
                    'checkout_url' => 'https://checkout.fedapay.com/abc',
                ]);
        });

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/initiate-payment', ['plan' => 'pro']);

        $response->assertOk()
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.plan', 'pro')
            ->assertJsonPath('data.checkout_url', 'https://checkout.fedapay.com/abc')
            ->assertJsonPath('data.amount', 25000)
            ->assertJsonPath('data.currency', 'XOF')
            ->assertJsonPath('data.forfeited_days', 0)
            ->assertJsonPath('message', 'Redirection vers le paiement...');

        $this->assertNotEmpty($response->json('data.subscription_id'));

        $this->assertSame(1, FaceSubscription::query()->where('face_id', $this->face->id)->count());

        /** @var FaceSubscription $row */
        $row = FaceSubscription::query()->where('face_id', $this->face->id)->firstOrFail();
        $this->assertSame(FaceSubscriptionStatus::PendingPayment, $row->status);
        $this->assertSame(FaceSubscriptionPlan::Pro, $row->plan);
        $this->assertSame('123456', $row->provider_reference);
        $this->assertSame('fedapay', $row->provider);
        $this->assertSame('XOF', $row->currency);
        $this->assertNull($row->paid_amount);
        $this->assertNull($row->starts_at);
        $this->assertNull($row->expires_at);

        $metadata = $row->metadata;
        $this->assertIsArray($metadata);
        $this->assertSame(123456, $metadata['fedapay_transaction_id']);
        $this->assertSame(25000, $metadata['quoted_amount']);
        $this->assertSame('XOF', $metadata['quoted_currency']);
        $this->assertIsString($metadata['idempotency_key']);
        // UUID v4 pattern check
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $metadata['idempotency_key']
        );
        $this->assertIsString($metadata['initiated_at']);
    }

    public function test_face_with_existing_active_subscription_can_initiate_renewal(): void
    {
        FaceSubscription::factory()->active()->create([
            'face_id' => $this->face->id,
            'starts_at' => now()->subDays(330),
            'expires_at' => now()->addDays(35),
            'provider_reference' => 'old_active_ref_111',
        ]);

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('initiatePaymentForFaceSubscription')
                ->once()
                ->andReturn([
                    'fedapay_transaction_id' => 999000,
                    'checkout_url' => 'https://checkout.fedapay.com/renew',
                ]);
        });

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/initiate-payment', ['plan' => 'pro']);

        $response->assertOk()
            ->assertJsonPath('data.status', 'pending_payment');

        $this->assertSame(2, FaceSubscription::query()->where('face_id', $this->face->id)->count());
        $this->assertSame(
            1,
            FaceSubscription::query()
                ->where('face_id', $this->face->id)
                ->where('status', FaceSubscriptionStatus::Active)
                ->count()
        );
        $this->assertSame(
            1,
            FaceSubscription::query()
                ->where('face_id', $this->face->id)
                ->where('status', FaceSubscriptionStatus::PendingPayment)
                ->where('provider_reference', '999000')
                ->count()
        );
    }

    public function test_verify_payment_self_heals_approved_pending_subscription(): void
    {
        Carbon::setTestNow('2026-05-14 09:30:00');

        $pending = FaceSubscription::factory()->create([
            'face_id' => $this->face->id,
            'plan' => FaceSubscriptionPlan::Pro,
            'status' => FaceSubscriptionStatus::PendingPayment,
            'provider_reference' => '123456',
            'paid_amount' => null,
            'currency' => 'XOF',
            'starts_at' => null,
            'expires_at' => null,
            'metadata' => [
                'quoted_amount' => 50000,
                'quoted_currency' => 'XOF',
            ],
        ]);

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('retrieveTransaction')
                ->once()
                ->with(123456)
                ->andReturn(new Transaction([
                    'id' => 123456,
                    'status' => 'approved',
                    'reference' => 'fedapay-approved-ref',
                    'amount' => 50000,
                    'currency' => ['iso' => 'XOF'],
                ]));
        });

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/verify-payment');

        $response->assertOk()
            ->assertJsonPath('data.subscription_id', $pending->uuid)
            ->assertJsonPath('data.status', 'active');

        $pending->refresh();
        $this->assertSame(FaceSubscriptionStatus::Active, $pending->status);
        $this->assertSame(50000, $pending->paid_amount);
        $this->assertSame('2026-05-14T09:30:00+00:00', $pending->starts_at->toIso8601String());
        $this->assertSame('2027-05-14T09:30:00+00:00', $pending->expires_at->toIso8601String());
        $this->assertSame('fedapay-approved-ref', $pending->metadata['fedapay_reference']);
    }

    public function test_verify_payment_keeps_pending_when_fedapay_transaction_is_not_approved_yet(): void
    {
        $pending = FaceSubscription::factory()->create([
            'face_id' => $this->face->id,
            'plan' => FaceSubscriptionPlan::Pro,
            'status' => FaceSubscriptionStatus::PendingPayment,
            'provider_reference' => '123456',
            'paid_amount' => null,
            'currency' => 'XOF',
            'starts_at' => null,
            'expires_at' => null,
            'metadata' => [
                'quoted_amount' => 50000,
                'quoted_currency' => 'XOF',
            ],
        ]);

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('retrieveTransaction')
                ->once()
                ->with(123456)
                ->andReturn(new Transaction([
                    'id' => 123456,
                    'status' => 'pending',
                    'reference' => null,
                    'amount' => 50000,
                    'currency' => ['iso' => 'XOF'],
                ]));
        });

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/verify-payment');

        $response->assertOk()
            ->assertJsonPath('data.subscription_id', $pending->uuid)
            ->assertJsonPath('data.status', 'pending_payment');

        $this->assertSame(FaceSubscriptionStatus::PendingPayment, $pending->fresh()->status);
    }

    public function test_initiation_sets_provider_currency_from_config(): void
    {
        config(['face_subscription_tiers.currency' => 'EUR']);

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('initiatePaymentForFaceSubscription')
                ->once()
                ->andReturn([
                    'fedapay_transaction_id' => 111222,
                    'checkout_url' => 'https://checkout.fedapay.com/eur',
                ]);
        });

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/initiate-payment', ['plan' => 'pro']);

        $response->assertOk()
            ->assertJsonPath('data.currency', 'EUR');

        /** @var FaceSubscription $row */
        $row = FaceSubscription::query()->where('face_id', $this->face->id)->firstOrFail();
        $this->assertSame('EUR', $row->currency);
    }

    // -----------------------------------------------------------------------
    // Conflict and plan unavailability (AC #7, #9, #17)
    // -----------------------------------------------------------------------

    public function test_face_with_existing_pending_payment_row_returns_409_pending_payment_exists(): void
    {
        FaceSubscription::factory()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => 'preexisting_ref_555',
        ]);

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldNotReceive('initiatePaymentForFaceSubscription');
        });

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/initiate-payment', ['plan' => 'pro']);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'PENDING_PAYMENT_EXISTS')
            ->assertJsonPath(
                'error.message',
                'Un paiement est déjà en cours pour cette Face. Veuillez attendre la confirmation ou réessayer plus tard.'
            );

        $this->assertSame(1, FaceSubscription::query()->where('face_id', $this->face->id)->count());
    }

    public function test_face_with_terminal_only_history_can_initiate_after_failure(): void
    {
        FaceSubscription::factory()->failed()->create([
            'face_id' => $this->face->id,
            'provider_reference' => 'failed_ref_111',
        ]);
        FaceSubscription::factory()->expired()->create([
            'face_id' => $this->face->id,
            'provider_reference' => 'expired_ref_222',
        ]);

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('initiatePaymentForFaceSubscription')
                ->once()
                ->andReturn([
                    'fedapay_transaction_id' => 444555,
                    'checkout_url' => 'https://checkout.fedapay.com/retry',
                ]);
        });

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/initiate-payment', ['plan' => 'pro']);

        $response->assertOk()
            ->assertJsonPath('data.status', 'pending_payment');

        $this->assertSame(3, FaceSubscription::query()->where('face_id', $this->face->id)->count());
        $this->assertSame(
            1,
            FaceSubscription::query()
                ->where('face_id', $this->face->id)
                ->where('status', FaceSubscriptionStatus::Failed)
                ->count()
        );
        $this->assertSame(
            1,
            FaceSubscription::query()
                ->where('face_id', $this->face->id)
                ->where('status', FaceSubscriptionStatus::Expired)
                ->count()
        );
        $this->assertSame(
            1,
            FaceSubscription::query()
                ->where('face_id', $this->face->id)
                ->where('status', FaceSubscriptionStatus::PendingPayment)
                ->where('provider_reference', '444555')
                ->count()
        );
    }

    public function test_initiation_returns_422_plan_unavailable_when_config_amount_is_zero(): void
    {
        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldNotReceive('initiatePaymentForFaceSubscription');
        });

        foreach ([0, -100] as $invalidAmount) {
            config(['face_subscription_tiers.tiers.pro.price' => $invalidAmount]);

            $response = $this->actingAs($this->faceUser)
                ->postJson('/api/v1/face/subscription/initiate-payment', ['plan' => 'pro']);

            $response->assertStatus(422)
                ->assertJsonPath('error.code', 'PLAN_UNAVAILABLE')
                ->assertJsonPath(
                    'error.message',
                    'L\'abonnement annuel n\'est pas disponible pour le moment. Veuillez réessayer plus tard ou contacter le support.'
                );

            $this->assertSame(0, FaceSubscription::query()->where('face_id', $this->face->id)->count());
        }
    }

    // -----------------------------------------------------------------------
    // Fedapay failure and compensation (AC #6, #6bis)
    // -----------------------------------------------------------------------

    public function test_fedapay_request_checkout_failure_preserves_pending_row_and_returns_502(): void
    {
        // Patch D (code-review v2 2026-05-12): the request_checkout failure
        // path now preserves the pending row instead of compensate-deleting it.
        // HTTP semantics cannot distinguish "Fedapay never created the
        // transaction" from "Fedapay created it but the response was lost";
        // deleting in the second case would orphan a real payment.
        Log::spy();

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('initiatePaymentForFaceSubscription')
                ->once()
                ->andThrow(new \RuntimeException('Fedapay 500'));
        });

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/initiate-payment', ['plan' => 'pro']);

        $response->assertStatus(502)
            ->assertJsonPath('error.code', 'PAYMENT_INITIATION_FAILED')
            ->assertJsonPath(
                'error.message',
                'Le paiement de l\'abonnement n\'a pas pu être initialisé. Veuillez réessayer.'
            );

        // Pending row is preserved (no compensate-delete).
        $this->assertSame(1, FaceSubscription::query()->where('face_id', $this->face->id)->count());
        /** @var FaceSubscription $preserved */
        $preserved = FaceSubscription::query()->where('face_id', $this->face->id)->firstOrFail();
        $this->assertSame(FaceSubscriptionStatus::PendingPayment, $preserved->status);
        $this->assertNull($preserved->provider_reference);
        $this->assertSame(25000, $preserved->metadata['quoted_amount']);

        Log::shouldHaveReceived('error')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Face subscription payment initiation failed'
                    && ($context['phase'] ?? null) === 'request_checkout'
                    && array_key_exists('remote_transaction_id', $context)
                    && $context['remote_transaction_id'] === null
                    && ($context['manual_recovery_required'] ?? null) === true;
            });
    }

    public function test_fedapay_finalize_local_failure_preserves_pending_row_and_returns_502(): void
    {
        Log::spy();

        // Mock Fedapay to succeed
        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('initiatePaymentForFaceSubscription')
                ->once()
                ->andReturn([
                    'fedapay_transaction_id' => 555111,
                    'checkout_url' => 'https://checkout.fedapay.com/x',
                ]);
        });

        // Anonymous subclass: always throws from runFinalizeTransaction,
        // so the retry loop exhausts all 3 attempts.
        $this->app->bind(
            FaceSubscriptionPaymentService::class,
            fn () => new class(app(FedapayService::class)) extends FaceSubscriptionPaymentService
            {
                protected function runFinalizeTransaction(
                    FaceSubscription $subscription,
                    array $remote,
                    string $idempotencyKey,
                ): void {
                    throw new \RuntimeException('DB lock timeout');
                }
            }
        );

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/initiate-payment', ['plan' => 'pro']);

        $response->assertStatus(502)
            ->assertJsonPath('error.code', 'PAYMENT_INITIATION_FAILED')
            ->assertJsonPath(
                'error.message',
                'Le paiement de l\'abonnement n\'a pas pu être initialisé. Veuillez réessayer.'
            );

        // Pending row preserved for webhook recovery
        $rows = FaceSubscription::query()->where('face_id', $this->face->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame(FaceSubscriptionStatus::PendingPayment, $rows->first()->status);
        $this->assertNull($rows->first()->provider_reference);

        Log::shouldHaveReceived('error')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Face subscription payment initiation failed'
                    && ($context['phase'] ?? null) === 'finalize_local'
                    && ($context['remote_transaction_id'] ?? null) === 555111
                    && ($context['manual_recovery_required'] ?? null) === true;
            });
    }

    public function test_fedapay_finalize_local_succeeds_on_retry_after_transient_failure(): void
    {
        Log::spy();

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('initiatePaymentForFaceSubscription')
                ->once()
                ->andReturn([
                    'fedapay_transaction_id' => 555111,
                    'checkout_url' => 'https://checkout.fedapay.com/y',
                ]);
        });

        // Anonymous subclass: throws on attempt 1, succeeds on attempt 2.
        $this->app->bind(
            FaceSubscriptionPaymentService::class,
            fn () => new class(app(FedapayService::class)) extends FaceSubscriptionPaymentService
            {
                private int $attempts = 0;

                protected function runFinalizeTransaction(
                    FaceSubscription $subscription,
                    array $remote,
                    string $idempotencyKey,
                ): void {
                    $this->attempts++;
                    if ($this->attempts === 1) {
                        throw new \RuntimeException('Transient DB lock');
                    }

                    parent::runFinalizeTransaction($subscription, $remote, $idempotencyKey);
                }
            }
        );

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/initiate-payment', ['plan' => 'pro']);

        $response->assertOk()
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.checkout_url', 'https://checkout.fedapay.com/y');

        /** @var FaceSubscription $row */
        $row = FaceSubscription::query()->where('face_id', $this->face->id)->firstOrFail();
        $this->assertSame('555111', $row->provider_reference);
        $this->assertIsArray($row->metadata);
        $this->assertSame(555111, $row->metadata['fedapay_transaction_id']);

        // No "initiation failed" error log should have been emitted on retry success.
        Log::shouldNotHaveReceived('error', function (string $message): bool {
            return $message === 'Face subscription payment initiation failed';
        });
    }

    public function test_fedapay_finalize_local_does_not_return_checkout_when_webhook_failed_row_during_retry(): void
    {
        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('initiatePaymentForFaceSubscription')
                ->once()
                ->andReturn([
                    'fedapay_transaction_id' => 555222,
                    'checkout_url' => 'https://checkout.fedapay.com/failed-before-return',
                ]);
        });

        $this->app->bind(
            FaceSubscriptionPaymentService::class,
            fn () => new class(app(FedapayService::class)) extends FaceSubscriptionPaymentService
            {
                private int $attempts = 0;

                protected function runFinalizeTransaction(
                    FaceSubscription $subscription,
                    array $remote,
                    string $idempotencyKey,
                ): void {
                    $this->attempts++;

                    if ($this->attempts === 1) {
                        $this->markAsFailed(
                            $subscription,
                            'fedapay-ref-declined-during-finalize',
                            'Payment transaction.declined',
                            (string) $remote['fedapay_transaction_id'],
                        );

                        throw new \RuntimeException('Transient DB lock');
                    }

                    parent::runFinalizeTransaction($subscription, $remote, $idempotencyKey);
                }
            }
        );

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/initiate-payment', ['plan' => 'pro']);

        $response->assertStatus(502)
            ->assertJsonPath('error.code', 'PAYMENT_INITIATION_FAILED');

        /** @var FaceSubscription $row */
        $row = FaceSubscription::query()->where('face_id', $this->face->id)->firstOrFail();
        $this->assertSame(FaceSubscriptionStatus::Failed, $row->status);
        $this->assertSame('555222', $row->provider_reference);
    }

    // -----------------------------------------------------------------------
    // Concurrency (AC #10)
    // -----------------------------------------------------------------------

    public function test_initiation_returns_422_validation_error_when_cache_lock_held(): void
    {
        $lock = Cache::lock("face_subscription_initiate_{$this->face->id}", 120);

        $this->assertTrue($lock->get(), 'Expected to acquire the cache lock for the contention test.');

        try {
            $response = $this->actingAs($this->faceUser)
                ->postJson('/api/v1/face/subscription/initiate-payment', ['plan' => 'pro']);

            $response->assertStatus(422)
                ->assertJsonPath('error.code', 'VALIDATION_ERROR')
                ->assertJsonPath(
                    'error.details.status.0',
                    'Un paiement est déjà en cours pour cet abonnement.'
                );

            $this->assertSame(0, FaceSubscription::query()->where('face_id', $this->face->id)->count());
        } finally {
            $lock->release();
        }
    }

    // -----------------------------------------------------------------------
    // markAsPaid invariants (AC #14, #15)
    // -----------------------------------------------------------------------

    public function test_mark_as_paid_activates_pending_subscription_for_one_year_from_now_when_no_existing_active(): void
    {
        $subscription = FaceSubscription::factory()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => 'tx_555',
            'paid_amount' => null,
            'metadata' => ['quoted_amount' => 50000],
        ]);

        $beforeNow = Carbon::now();

        $result = app(FaceSubscriptionPaymentService::class)->markAsPaid(
            $subscription,
            'fedapay-ref-555',
            50000,
            ['id' => 'evt_001', 'name' => 'transaction.approved']
        );

        $this->assertSame(FaceSubscriptionStatus::Active, $result->status);
        $this->assertNotNull($result->starts_at);
        $this->assertNotNull($result->expires_at);
        $this->assertLessThanOrEqual(
            5,
            abs($result->starts_at->diffInSeconds($beforeNow, false))
        );
        $this->assertLessThanOrEqual(
            5,
            abs($result->expires_at->diffInSeconds($beforeNow->copy()->addYear(), false))
        );
        $this->assertSame(50000, $result->paid_amount);

        $metadata = $result->metadata;
        $this->assertIsArray($metadata);
        $this->assertSame('fedapay-ref-555', $metadata['fedapay_reference']);
        $this->assertSame('evt_001', $metadata['fedapay_event_payload_summary']['event_id']);
        $this->assertSame('transaction.approved', $metadata['fedapay_event_payload_summary']['event_name']);
        $this->assertIsString($metadata['confirmed_at']);
    }

    public function test_mark_as_paid_chains_starts_at_from_existing_active_expires_at_when_renewing(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-12 10:00:00'));

        try {
            $existingActive = FaceSubscription::factory()->create([
                'face_id' => $this->face->id,
                'plan' => FaceSubscriptionPlan::Pro,
                'status' => FaceSubscriptionStatus::Active,
                'starts_at' => now()->subDays(330),
                'expires_at' => now()->addDays(35),
                'paid_amount' => 50000,
                'currency' => 'XOF',
                'provider' => 'fedapay',
                'provider_reference' => 'old_active_ref_111',
                'metadata' => null,
            ]);

            $pending = FaceSubscription::factory()->create([
                'face_id' => $this->face->id,
                'plan' => FaceSubscriptionPlan::Pro,
                'status' => FaceSubscriptionStatus::PendingPayment,
                'starts_at' => null,
                'expires_at' => null,
                'paid_amount' => null,
                'currency' => 'XOF',
                'provider' => 'fedapay',
                'provider_reference' => 'new_renewal_ref_222',
                'metadata' => [
                    'fedapay_transaction_id' => 222,
                    'quoted_amount' => 50000,
                ],
            ]);

            $expectedStartsAt = $existingActive->expires_at;
            $expectedExpiresAt = $existingActive->expires_at->copy()->addYear();

            $result = app(FaceSubscriptionPaymentService::class)->markAsPaid(
                $pending,
                'fedapay-ref-222',
                50000
            );

            $this->assertSame(FaceSubscriptionStatus::Active, $result->status);
            $this->assertLessThanOrEqual(
                1,
                abs($result->starts_at->diffInSeconds($expectedStartsAt, false))
            );
            $this->assertLessThanOrEqual(
                1,
                abs($result->expires_at->diffInSeconds($expectedExpiresAt, false))
            );

            // Existing Active row is unchanged
            $freshExisting = $existingActive->fresh();
            $this->assertSame(FaceSubscriptionStatus::Active, $freshExisting->status);
            $this->assertLessThanOrEqual(
                1,
                abs($freshExisting->expires_at->diffInSeconds($existingActive->expires_at, false))
            );

            // Premium continuously preserved across the renewal (Pro tier per the renewing fixture)
            $this->assertSame(FaceSubscriptionTier::Pro, app(FaceEntitlementService::class)->capabilities($this->face->fresh())->tier);
        } finally {
            Carbon::setTestNow(null);
        }
    }

    public function test_mark_as_paid_validates_against_quoted_amount_not_current_config(): void
    {
        $pending = FaceSubscription::factory()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => 'tx_quoted_amount',
            'paid_amount' => null,
            'metadata' => ['quoted_amount' => 50000],
        ]);

        $result = app(FaceSubscriptionPaymentService::class)->markAsPaid(
            $pending,
            'fedapay-ref-quoted',
            50000,
        );

        $this->assertSame(FaceSubscriptionStatus::Active, $result->status);
        $this->assertSame(50000, $result->paid_amount);
    }

    public function test_mark_as_paid_refuses_activation_when_quoted_amount_is_missing(): void
    {
        Log::spy();

        $pending = FaceSubscription::factory()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => 'tx_missing_quote',
            'paid_amount' => null,
            'metadata' => null,
        ]);

        $result = app(FaceSubscriptionPaymentService::class)->markAsPaid(
            $pending,
            'fedapay-ref-missing-quote',
            50000,
        );

        $this->assertSame(FaceSubscriptionStatus::PendingPayment, $result->status);
        $this->assertNull($result->paid_amount);

        Log::shouldHaveReceived('critical')
            ->withArgs(function (string $message, array $context) use ($pending): bool {
                return $message === 'Fedapay webhook: paid_amount or currency mismatch — refusing activation, row left PendingPayment for admin review'
                    && ($context['face_subscription_id'] ?? null) === $pending->id
                    && array_key_exists('expected_amount', $context)
                    && $context['expected_amount'] === null;
            })
            ->once();
    }

    public function test_mark_as_paid_chains_duplicate_pending_approvals_for_same_face(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-13 10:00:00'));

        try {
            $firstPending = FaceSubscription::factory()->pendingPayment()->create([
                'face_id' => $this->face->id,
                'provider_reference' => 'tx_duplicate_1',
                'paid_amount' => null,
                'metadata' => ['quoted_amount' => 50000],
            ]);
            $secondPending = FaceSubscription::factory()->pendingPayment()->create([
                'face_id' => $this->face->id,
                'provider_reference' => 'tx_duplicate_2',
                'paid_amount' => null,
                'metadata' => ['quoted_amount' => 50000],
            ]);

            $first = app(FaceSubscriptionPaymentService::class)->markAsPaid(
                $firstPending,
                'fedapay-ref-duplicate-1',
                50000,
            );
            $second = app(FaceSubscriptionPaymentService::class)->markAsPaid(
                $secondPending,
                'fedapay-ref-duplicate-2',
                50000,
            );

            $this->assertSame(
                $first->expires_at?->toIso8601String(),
                $second->starts_at?->toIso8601String(),
            );
            $this->assertTrue($second->expires_at->greaterThan($first->expires_at));
        } finally {
            Carbon::setTestNow(null);
        }
    }

    public function test_mark_as_paid_is_idempotent_on_already_active_row(): void
    {
        $active = FaceSubscription::factory()->active()->create([
            'face_id' => $this->face->id,
            'provider_reference' => 'tx_A',
        ]);

        $service = app(FaceSubscriptionPaymentService::class);

        $first = $service->markAsPaid($active, 'fedapay-ref-A', 50000);
        $firstExpires = $first->expires_at?->toIso8601String();
        $firstStarts = $first->starts_at?->toIso8601String();

        $second = $service->markAsPaid($active->fresh(), 'fedapay-ref-A', 50000);

        $this->assertSame($firstExpires, $second->expires_at?->toIso8601String());
        $this->assertSame($firstStarts, $second->starts_at?->toIso8601String());
        $this->assertSame(FaceSubscriptionStatus::Active, $second->status);
    }

    public function test_mark_as_paid_logs_warning_and_no_ops_on_failed_or_cancelled_row(): void
    {
        Log::spy();

        $failed = FaceSubscription::factory()->failed()->create([
            'face_id' => $this->face->id,
            'provider_reference' => 'tx_failed',
        ]);

        $result = app(FaceSubscriptionPaymentService::class)->markAsPaid(
            $failed,
            'fedapay-ref-late',
            50000
        );

        $this->assertSame(FaceSubscriptionStatus::Failed, $result->status);

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context) use ($failed): bool {
                return $message === 'Fedapay webhook: ignoring approval for non-pending face subscription'
                    && ($context['face_subscription_id'] ?? null) === $failed->id
                    && ($context['current_status'] ?? null) === 'failed';
            });

        // Cancelled row variant
        $cancelled = FaceSubscription::factory()->cancelled()->create([
            'face_id' => $this->face->id,
            'provider_reference' => 'tx_cancelled',
        ]);

        $cancelledResult = app(FaceSubscriptionPaymentService::class)->markAsPaid(
            $cancelled,
            'fedapay-ref-late-2',
            50000
        );

        $this->assertSame(FaceSubscriptionStatus::Cancelled, $cancelledResult->status);

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context) use ($cancelled): bool {
                return $message === 'Fedapay webhook: ignoring approval for non-pending face subscription'
                    && ($context['face_subscription_id'] ?? null) === $cancelled->id
                    && ($context['current_status'] ?? null) === 'cancelled';
            });
    }

    public function test_mark_as_paid_metadata_does_not_leak_raw_webhook_payload_keys(): void
    {
        $pending = FaceSubscription::factory()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => 'tx_leak',
            'metadata' => ['quoted_amount' => 50000],
        ]);

        $result = app(FaceSubscriptionPaymentService::class)->markAsPaid(
            $pending,
            'fedapay-ref-leak',
            50000,
            [
                'id' => 'evt_X',
                'name' => 'transaction.approved',
                'entity' => [
                    'status' => 'approved',
                    'customer' => ['secret_card_token' => 'AAA-BBB'],
                    'arbitrary' => 'data',
                ],
            ]
        );

        $metadata = $result->metadata;
        $this->assertIsArray($metadata);
        $summary = $metadata['fedapay_event_payload_summary'];
        $this->assertSame(['event_id', 'event_name', 'transaction_status'], array_keys($summary));
        $this->assertSame('evt_X', $summary['event_id']);
        $this->assertSame('transaction.approved', $summary['event_name']);
        $this->assertSame('approved', $summary['transaction_status']);

        // Whole metadata serialized must not leak raw payload keys
        $serialized = json_encode($metadata, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('customer', $serialized);
        $this->assertStringNotContainsString('arbitrary', $serialized);
        $this->assertStringNotContainsString('secret_card_token', $serialized);
    }

    public function test_mark_as_paid_with_amount_mismatch_refuses_activation_and_leaves_pending(): void
    {
        // Patch A (code-review v2 2026-05-12): strict amount validation.
        // When Fedapay reports an amount that does not match the configured
        // plan price (e.g. 1 XOF instead of 50000), activation is refused
        // and the row is left in PendingPayment for admin investigation.
        Log::spy();

        $pending = FaceSubscription::factory()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => 'tx_amount_mismatch',
            'paid_amount' => null,
            'metadata' => ['quoted_amount' => 50000],
        ]);

        $result = app(FaceSubscriptionPaymentService::class)->markAsPaid(
            $pending,
            'fedapay-ref-mismatch',
            1, // expected 50000 per setUp config
        );

        // Row remains in PendingPayment — no activation, no metadata write.
        $this->assertSame(FaceSubscriptionStatus::PendingPayment, $result->status);
        $this->assertNull($result->starts_at);
        $this->assertNull($result->expires_at);
        $this->assertNull($result->paid_amount);

        Log::shouldHaveReceived('critical')
            ->withArgs(function (string $message, array $context) use ($pending): bool {
                return $message === 'Fedapay webhook: paid_amount or currency mismatch — refusing activation, row left PendingPayment for admin review'
                    && ($context['face_subscription_id'] ?? null) === $pending->id
                    && ($context['face_id'] ?? null) === $pending->face_id
                    && ($context['paid_amount'] ?? null) === 1
                    && ($context['expected_amount'] ?? null) === 50000;
            })
            ->once();
    }

    public function test_mark_as_paid_refuses_activation_on_currency_mismatch(): void
    {
        // P3 patch (code-review Rerun 3 2026-05-13): currency drift between
        // quoted at initiation and reported by Fedapay refuses activation. The
        // row stays PendingPayment with currency_mismatch_detected_at flagged
        // even when paid_amount itself matches the quoted amount.
        Log::spy();

        $pending = FaceSubscription::factory()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => 'tx_currency_mismatch',
            'paid_amount' => null,
            'currency' => 'XOF',
            'metadata' => [
                'quoted_amount' => 50000,
                'quoted_currency' => 'XOF',
            ],
        ]);

        $result = app(FaceSubscriptionPaymentService::class)->markAsPaid(
            $pending,
            'fedapay-ref-currency',
            50000, // amount matches quoted_amount
            [
                'id' => 'evt_currency_mismatch',
                'name' => 'transaction.approved',
                'entity' => [
                    'currency' => ['iso' => 'EUR'], // currency drifted from quoted XOF
                ],
            ],
        );

        // Row remains in PendingPayment — currency mismatch refuses activation
        // even with matching amount.
        $this->assertSame(FaceSubscriptionStatus::PendingPayment, $result->status);
        $this->assertNull($result->starts_at);
        $this->assertNull($result->expires_at);
        $this->assertNull($result->paid_amount);

        // Mismatch metadata records the currency drift (and skips amount_mismatch_detected_at
        // because the amount itself matched).
        $this->assertIsArray($result->metadata);
        $this->assertSame('EUR', $result->metadata['reported_currency'] ?? null);
        $this->assertSame('XOF', $result->metadata['expected_currency'] ?? null);
        $this->assertArrayHasKey('currency_mismatch_detected_at', $result->metadata);
        $this->assertArrayNotHasKey('amount_mismatch_detected_at', $result->metadata);

        Log::shouldHaveReceived('critical')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Fedapay webhook: paid_amount or currency mismatch — refusing activation, row left PendingPayment for admin review'
                    && ($context['currency_mismatch'] ?? null) === true
                    && ($context['amount_mismatch'] ?? null) === false
                    && ($context['reported_currency'] ?? null) === 'EUR'
                    && ($context['expected_currency'] ?? null) === 'XOF';
            })
            ->once();
    }

    // -----------------------------------------------------------------------
    // markAsFailed invariants (AC #16)
    // -----------------------------------------------------------------------

    public function test_mark_as_failed_transitions_pending_to_failed(): void
    {
        $pending = FaceSubscription::factory()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => 'tx_fail',
            'paid_amount' => null,
        ]);

        $result = app(FaceSubscriptionPaymentService::class)->markAsFailed(
            $pending,
            'fedapay-ref-X',
            'Payment transaction.declined'
        );

        $this->assertSame(FaceSubscriptionStatus::Failed, $result->status);
        $this->assertNull($result->starts_at);
        $this->assertNull($result->expires_at);
        $this->assertNull($result->paid_amount);

        $metadata = $result->metadata;
        $this->assertIsArray($metadata);
        $this->assertSame('Payment transaction.declined', $metadata['failure_reason']);
        $this->assertSame('fedapay-ref-X', $metadata['fedapay_reference']);
        $this->assertIsString($metadata['failed_at']);
    }

    public function test_mark_as_failed_is_idempotent_on_already_failed_row(): void
    {
        $failed = FaceSubscription::factory()->failed()->create([
            'face_id' => $this->face->id,
            'provider_reference' => 'tx_failed_already',
        ]);

        $service = app(FaceSubscriptionPaymentService::class);

        $first = $service->markAsFailed($failed, 'fedapay-ref-1', 'Reason 1');
        $firstMetadata = $first->metadata;

        $second = $service->markAsFailed($failed->fresh(), 'fedapay-ref-2', 'Reason 2');

        // Idempotent — no metadata mutation on second call
        $this->assertSame($firstMetadata, $second->metadata);
        $this->assertSame(FaceSubscriptionStatus::Failed, $second->status);
    }

    public function test_mark_as_failed_does_not_downgrade_active_to_failed(): void
    {
        Log::spy();

        $active = FaceSubscription::factory()->active()->create([
            'face_id' => $this->face->id,
            'provider_reference' => 'tx_active',
        ]);

        $originalExpiresAt = $active->expires_at?->toIso8601String();

        $result = app(FaceSubscriptionPaymentService::class)->markAsFailed(
            $active,
            'fedapay-ref-late-decline',
            'Payment transaction.declined'
        );

        $this->assertSame(FaceSubscriptionStatus::Active, $result->status);
        $this->assertSame($originalExpiresAt, $result->expires_at?->toIso8601String());

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context) use ($active): bool {
                return $message === 'Fedapay webhook: ignoring failure for non-pending face subscription'
                    && ($context['face_subscription_id'] ?? null) === $active->id
                    && ($context['current_status'] ?? null) === 'active';
            });
    }

    public function test_mark_as_failed_leaves_dates_null(): void
    {
        $pending = FaceSubscription::factory()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => 'tx_pending',
            'paid_amount' => null,
        ]);

        app(FaceSubscriptionPaymentService::class)->markAsFailed(
            $pending,
            'fedapay-ref-pending',
            'Payment transaction.declined'
        );

        /** @var FaceSubscription $reloaded */
        $reloaded = FaceSubscription::query()->findOrFail($pending->id);

        $this->assertSame(FaceSubscriptionStatus::Failed, $reloaded->status);
        $this->assertNull($reloaded->starts_at);
        $this->assertNull($reloaded->expires_at);
        $this->assertNull($reloaded->cancelled_at);
        $this->assertNull($reloaded->paid_amount);
    }

    // -----------------------------------------------------------------------
    // FP-2.5 — tier selection on initiate-payment (AC #5, #6, #12, #14, #28)
    // -----------------------------------------------------------------------

    public function test_initiate_persists_starter_pending_row_with_starter_price(): void
    {
        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('initiatePaymentForFaceSubscription')
                ->once()
                ->andReturn([
                    'fedapay_transaction_id' => 700100,
                    'checkout_url' => 'https://checkout.fedapay.com/starter',
                ]);
        });

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/initiate-payment', ['plan' => 'starter']);

        $response->assertOk()
            ->assertJsonPath('data.plan', 'starter')
            ->assertJsonPath('data.amount', 12000)
            ->assertJsonPath('data.status', 'pending_payment');

        /** @var FaceSubscription $row */
        $row = FaceSubscription::query()->where('face_id', $this->face->id)->firstOrFail();
        $this->assertSame(FaceSubscriptionPlan::Starter, $row->plan);
        $this->assertSame(12000, $row->metadata['quoted_amount']);
    }

    public function test_initiate_persists_elite_pending_row_with_elite_price(): void
    {
        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('initiatePaymentForFaceSubscription')
                ->once()
                ->andReturn([
                    'fedapay_transaction_id' => 700110,
                    'checkout_url' => 'https://checkout.fedapay.com/elite',
                ]);
        });

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/initiate-payment', ['plan' => 'elite']);

        $response->assertOk()
            ->assertJsonPath('data.plan', 'elite')
            ->assertJsonPath('data.amount', 40000)
            ->assertJsonPath('data.status', 'pending_payment');

        /** @var FaceSubscription $row */
        $row = FaceSubscription::query()->where('face_id', $this->face->id)->firstOrFail();
        $this->assertSame(FaceSubscriptionPlan::Elite, $row->plan);
        $this->assertSame(40000, $row->metadata['quoted_amount']);
    }

    public function test_initiate_rejects_missing_plan(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/initiate-payment', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['details' => ['plan']]]);

        $this->assertSame(0, FaceSubscription::query()->where('face_id', $this->face->id)->count());
    }

    public function test_initiate_rejects_unknown_plan(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/initiate-payment', ['plan' => 'platinum']);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['details' => ['plan']]]);

        $this->assertSame(0, FaceSubscription::query()->where('face_id', $this->face->id)->count());
    }

    public function test_initiate_rejects_free_plan(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/initiate-payment', ['plan' => 'free']);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['details' => ['plan']]]);

        $this->assertSame(0, FaceSubscription::query()->where('face_id', $this->face->id)->count());
    }

    public function test_initiate_returns_zero_forfeited_days_for_face_with_no_subscription(): void
    {
        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('initiatePaymentForFaceSubscription')
                ->once()
                ->andReturn([
                    'fedapay_transaction_id' => 700120,
                    'checkout_url' => 'https://checkout.fedapay.com/none',
                ]);
        });

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/initiate-payment', ['plan' => 'elite']);

        $response->assertOk()
            ->assertJsonPath('data.forfeited_days', 0);
    }

    public function test_initiate_returns_zero_forfeited_days_for_same_tier_renewal(): void
    {
        FaceSubscription::factory()->pro()->active()->create([
            'face_id' => $this->face->id,
            'provider_reference' => 'tx_active_pro_renew',
        ]);

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('initiatePaymentForFaceSubscription')
                ->once()
                ->andReturn([
                    'fedapay_transaction_id' => 700130,
                    'checkout_url' => 'https://checkout.fedapay.com/renew',
                ]);
        });

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/initiate-payment', ['plan' => 'pro']);

        $response->assertOk()
            ->assertJsonPath('data.forfeited_days', 0);
    }

    public function test_initiate_returns_forfeited_days_for_tier_change(): void
    {
        Carbon::setTestNow('2026-05-22 12:00:00');

        try {
            FaceSubscription::factory()->pro()->create([
                'face_id' => $this->face->id,
                'status' => FaceSubscriptionStatus::Active,
                'starts_at' => now()->subDays(100),
                'expires_at' => now()->addDays(40),
                'provider_reference' => 'tx_active_pro_upgrade',
            ]);

            $this->mock(FedapayService::class, function ($mock): void {
                $mock->shouldReceive('initiatePaymentForFaceSubscription')
                    ->once()
                    ->andReturn([
                        'fedapay_transaction_id' => 700140,
                        'checkout_url' => 'https://checkout.fedapay.com/upgrade',
                    ]);
            });

            $response = $this->actingAs($this->faceUser)
                ->postJson('/api/v1/face/subscription/initiate-payment', ['plan' => 'elite']);

            $response->assertOk()
                ->assertJsonPath('data.forfeited_days', 40);
        } finally {
            Carbon::setTestNow(null);
        }
    }

    // -----------------------------------------------------------------------
    // FP-2.5 — markAsPaid tier change: cancel old + restart (AC #15, #16, #17, #18)
    // -----------------------------------------------------------------------

    public function test_mark_as_paid_tier_change_cancels_old_active_and_restarts_twelve_months(): void
    {
        Carbon::setTestNow('2026-05-22 12:00:00');

        try {
            $oldActive = FaceSubscription::factory()->pro()->create([
                'face_id' => $this->face->id,
                'status' => FaceSubscriptionStatus::Active,
                'starts_at' => now()->subDays(100),
                'expires_at' => now()->addDays(40),
                'provider_reference' => 'tx_old_pro',
            ]);

            $pendingElite = FaceSubscription::factory()->elite()->pendingPayment()->create([
                'face_id' => $this->face->id,
                'provider_reference' => 'tx_new_elite',
                'paid_amount' => null,
                'metadata' => ['quoted_amount' => 40000],
            ]);

            $result = app(FaceSubscriptionPaymentService::class)->markAsPaid(
                $pendingElite,
                'fedapay-ref-elite',
                40000,
            );

            $this->assertSame(FaceSubscriptionStatus::Active, $result->status);
            $this->assertSame('2026-05-22T12:00:00+00:00', $result->starts_at->toIso8601String());
            $this->assertSame('2027-05-22T12:00:00+00:00', $result->expires_at->toIso8601String());

            $freshOld = $oldActive->fresh();
            $this->assertSame(FaceSubscriptionStatus::Cancelled, $freshOld->status);
            $this->assertSame($result->starts_at->toIso8601String(), $freshOld->cancelled_at->toIso8601String());
            // The superseded row's coverage window and plan are untouched.
            $this->assertSame(FaceSubscriptionPlan::Pro, $freshOld->plan);
            $this->assertSame('2026-02-11T12:00:00+00:00', $freshOld->starts_at->toIso8601String());
            $this->assertSame('2026-07-01T12:00:00+00:00', $freshOld->expires_at->toIso8601String());
        } finally {
            Carbon::setTestNow(null);
        }
    }

    public function test_mark_as_paid_tier_change_records_supersession_metadata(): void
    {
        $oldActive = FaceSubscription::factory()->pro()->active()->create([
            'face_id' => $this->face->id,
            'provider_reference' => 'tx_old_pro_meta',
        ]);

        $pendingElite = FaceSubscription::factory()->elite()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => 'tx_new_elite_meta',
            'paid_amount' => null,
            'metadata' => ['quoted_amount' => 40000],
        ]);

        $result = app(FaceSubscriptionPaymentService::class)->markAsPaid(
            $pendingElite,
            'fedapay-ref-elite-meta',
            40000,
        );

        $this->assertSame($oldActive->id, $result->metadata['supersedes_subscription_id']);

        $freshOld = $oldActive->fresh();
        $this->assertSame($result->id, $freshOld->metadata['superseded_by_subscription_id']);
        $this->assertSame('tier_change', $freshOld->metadata['superseded_reason']);
        $this->assertArrayHasKey('superseded_at', $freshOld->metadata);
    }

    public function test_mark_as_paid_tier_change_dispatches_only_activated_event(): void
    {
        Event::fake([
            FaceSubscriptionActivated::class,
            FaceSubscriptionCancelled::class,
            FaceSubscriptionExpired::class,
        ]);

        FaceSubscription::factory()->pro()->active()->create([
            'face_id' => $this->face->id,
            'provider_reference' => 'tx_old_pro_event',
        ]);

        $pendingElite = FaceSubscription::factory()->elite()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => 'tx_new_elite_event',
            'paid_amount' => null,
            'metadata' => ['quoted_amount' => 40000],
        ]);

        app(FaceSubscriptionPaymentService::class)->markAsPaid(
            $pendingElite,
            'fedapay-ref-elite-event',
            40000,
        );

        Event::assertDispatched(FaceSubscriptionActivated::class, 1);
        Event::assertNotDispatched(FaceSubscriptionCancelled::class);
        Event::assertNotDispatched(FaceSubscriptionExpired::class);
    }

    public function test_mark_as_paid_every_paid_tier_transition(): void
    {
        $transitions = [
            [FaceSubscriptionPlan::Starter, FaceSubscriptionPlan::Pro],
            [FaceSubscriptionPlan::Starter, FaceSubscriptionPlan::Elite],
            [FaceSubscriptionPlan::Pro, FaceSubscriptionPlan::Starter],
            [FaceSubscriptionPlan::Pro, FaceSubscriptionPlan::Elite],
            [FaceSubscriptionPlan::Elite, FaceSubscriptionPlan::Starter],
            [FaceSubscriptionPlan::Elite, FaceSubscriptionPlan::Pro],
        ];

        foreach ($transitions as [$from, $to]) {
            $face = Face::factory()->create();
            $active = FaceSubscription::factory()->active()->create([
                'face_id' => $face->id,
                'plan' => $from,
            ]);
            $pending = FaceSubscription::factory()->pendingPayment()->create([
                'face_id' => $face->id,
                'plan' => $to,
                'paid_amount' => null,
                'provider_reference' => "tx_{$from->value}_{$to->value}",
                'metadata' => ['quoted_amount' => $to->price()],
            ]);

            app(FaceSubscriptionPaymentService::class)->markAsPaid(
                $pending,
                "ref_{$from->value}_{$to->value}",
                $to->price(),
            );

            $this->assertSame(
                FaceSubscriptionStatus::Active,
                $pending->fresh()->status,
                "Transition {$from->value} -> {$to->value} should activate the new row",
            );
            $this->assertSame(
                FaceSubscriptionStatus::Cancelled,
                $active->fresh()->status,
                "Transition {$from->value} -> {$to->value} should cancel the old row",
            );
            $this->assertSame($to, $pending->fresh()->plan);
            $this->assertSame(
                $to->tier(),
                app(FaceEntitlementService::class)->capabilities($face->fresh())->tier,
            );
        }
    }
}
