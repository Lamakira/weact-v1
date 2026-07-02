<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\FaceSubscriptionStatus;
use App\Exceptions\FaceSubscriptionConflictException;
use App\Exceptions\FaceSubscriptionPaymentInitiationException;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Services\FaceSubscriptionPaymentService;
use App\Services\FedapayService;
use FedaPay\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FaceSubscriptionPaymentServiceResumePendingTest extends TestCase
{
    use RefreshDatabase;

    private Face $face;

    /** @var MockInterface&FedapayService */
    private MockInterface $fedapayMock;

    private FaceSubscriptionPaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->face = Face::factory()->create();

        /** @var MockInterface&FedapayService $mock */
        $mock = Mockery::mock(FedapayService::class);
        $this->fedapayMock = $mock;
        $this->app->instance(FedapayService::class, $mock);

        $this->service = $this->app->make(FaceSubscriptionPaymentService::class);
    }

    public function test_resume_pending_branch_calls_regenerate_token_and_updates_latest_metadata(): void
    {
        $pending = FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => '777',
            'currency' => 'XOF',
            'metadata' => [
                'quoted_amount' => 25000,
                'quoted_currency' => 'XOF',
                'idempotency_key' => 'idem-keep',
                'resume_count' => 5, // verify increment over a non-zero baseline
            ],
        ]);

        $this->fedapayMock->shouldReceive('retrieveTransaction')
            ->once()
            ->with(777)
            ->andReturn(new Transaction([
                'id' => 777,
                'status' => 'pending',
            ]));

        $this->fedapayMock->shouldReceive('regenerateTokenFromTransaction')
            ->once()
            ->andReturn([
                'checkout_url' => 'https://checkout.fedapay.test/sess_pending',
                'fedapay_status' => 'pending',
            ]);

        $result = $this->service->resumePending($this->face);

        $this->assertSame(FaceSubscriptionStatus::PendingPayment->value, $result['status']);
        $this->assertSame('https://checkout.fedapay.test/sess_pending', $result['checkout_url']);
        $this->assertSame(25000, $result['amount']);
        $this->assertSame('XOF', $result['currency']);
        $this->assertSame($pending->id, $result['subscription']->id);

        $fresh = $pending->fresh();
        $this->assertSame(FaceSubscriptionStatus::PendingPayment, $fresh->status);
        $this->assertSame('idem-keep', $fresh->metadata['idempotency_key']);
        $this->assertSame(6, $fresh->metadata['resume_count']);
        $this->assertIsString($fresh->metadata['last_resumed_at']);
    }

    public function test_resume_approved_branch_calls_mark_as_paid_and_returns_active(): void
    {
        $pending = FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => '888',
            'currency' => 'XOF',
            'metadata' => ['quoted_amount' => 25000, 'quoted_currency' => 'XOF'],
        ]);

        $this->fedapayMock->shouldReceive('retrieveTransaction')
            ->once()
            ->with(888)
            ->andReturn(new Transaction([
                'id' => 888,
                'status' => 'approved',
                'reference' => 'fedapay_ref_xyz',
                'amount' => 25000,
                'currency' => ['iso' => 'XOF'],
            ]));

        $this->fedapayMock->shouldNotReceive('regenerateTokenFromTransaction');

        $result = $this->service->resumePending($this->face);

        $this->assertSame(FaceSubscriptionStatus::Active->value, $result['status']);
        $this->assertNull($result['checkout_url']);
        $this->assertSame(25000, $result['amount']);
        $this->assertSame('XOF', $result['currency']);

        $fresh = $pending->fresh();
        $this->assertSame(FaceSubscriptionStatus::Active, $fresh->status);
        $this->assertSame('fedapay_ref_xyz', $fresh->metadata['fedapay_reference']);
    }

    /**
     * @return array<string, array<string>>
     */
    public static function failureFedapayStatusesProvider(): array
    {
        return [
            'declined' => ['declined'],
            'canceled' => ['canceled'],
            'expired' => ['expired'],
        ];
    }

    #[DataProvider('failureFedapayStatusesProvider')]
    public function test_resume_declined_branch_calls_mark_as_failed_and_throws_resume_not_available(
        string $fedapayStatus,
    ): void {
        $pending = FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => '999',
            'currency' => 'XOF',
            'metadata' => ['quoted_amount' => 25000, 'quoted_currency' => 'XOF'],
        ]);

        $this->fedapayMock->shouldReceive('retrieveTransaction')
            ->once()
            ->with(999)
            ->andReturn(new Transaction([
                'id' => 999,
                'status' => $fedapayStatus,
                'reference' => "ref_{$fedapayStatus}",
            ]));

        $this->fedapayMock->shouldNotReceive('regenerateTokenFromTransaction');

        try {
            $this->service->resumePending($this->face);
            $this->fail('Expected FaceSubscriptionConflictException to be thrown.');
        } catch (FaceSubscriptionConflictException $e) {
            $this->assertSame(410, $e->getStatusCode());
        }

        $this->assertSame(FaceSubscriptionStatus::Failed, $pending->fresh()->status);
    }

    public function test_resume_no_pending_row_throws_no_pending_payment(): void
    {
        $this->fedapayMock->shouldNotReceive('retrieveTransaction');

        try {
            $this->service->resumePending($this->face);
            $this->fail('Expected FaceSubscriptionConflictException to be thrown.');
        } catch (FaceSubscriptionConflictException $e) {
            $this->assertSame(404, $e->getStatusCode());
            $this->assertSame('Aucun paiement en attente.', $e->getMessage());
        }
    }

    public function test_resume_null_provider_reference_throws_cannot_resume(): void
    {
        Log::spy();

        $pending = FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => null,
            'metadata' => ['quoted_amount' => 25000, 'quoted_currency' => 'XOF'],
        ]);

        $this->fedapayMock->shouldNotReceive('retrieveTransaction');

        try {
            $this->service->resumePending($this->face);
            $this->fail('Expected FaceSubscriptionConflictException to be thrown.');
        } catch (FaceSubscriptionConflictException $e) {
            $this->assertSame(409, $e->getStatusCode());
            $this->assertSame(
                'Ce paiement ne peut pas être repris automatiquement. Veuillez en initier un nouveau depuis la page Tarifs.',
                $e->getMessage()
            );
        }

        // Pending row unchanged
        $this->assertSame(FaceSubscriptionStatus::PendingPayment, $pending->fresh()->status);

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context) use ($pending): bool {
                return $message === 'Face subscription resume: pending row has no provider_reference (transient finalize_local failure ?)'
                    && ($context['face_subscription_id'] ?? null) === $pending->id;
            })
            ->once();
    }

    public function test_resume_fedapay_throws_wraps_in_payment_initiation_exception(): void
    {
        Log::spy();

        $pending = FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => '444',
            'metadata' => ['quoted_amount' => 25000, 'quoted_currency' => 'XOF'],
        ]);

        $boom = new \RuntimeException('Fedapay HTTP 503');

        $this->fedapayMock->shouldReceive('retrieveTransaction')
            ->once()
            ->with(444)
            ->andThrow($boom);

        try {
            $this->service->resumePending($this->face);
            $this->fail('Expected FaceSubscriptionPaymentInitiationException to be thrown.');
        } catch (FaceSubscriptionPaymentInitiationException $e) {
            $this->assertSame(
                'Le paiement ne peut pas être repris pour le moment. Veuillez réessayer.',
                $e->getMessage()
            );
            $this->assertSame($boom, $e->getPrevious());
        }

        // Pending row unchanged
        $this->assertSame(FaceSubscriptionStatus::PendingPayment, $pending->fresh()->status);

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context) use ($pending): bool {
                return $message === 'Face subscription resume: phase failed'
                    && ($context['face_subscription_id'] ?? null) === $pending->id
                    && ($context['phase'] ?? null) === 'retrieve_transaction'
                    && ($context['exception_class'] ?? null) === \RuntimeException::class;
            })
            ->once();
    }

    public function test_resume_pending_branch_rereads_locked_row_and_preserves_webhook_metadata_if_row_changes_during_token_regeneration(): void
    {
        $pending = FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => '555',
            'currency' => 'XOF',
            'metadata' => [
                'quoted_amount' => 25000,
                'quoted_currency' => 'XOF',
                'idempotency_key' => 'idem-original',
            ],
        ]);

        $this->fedapayMock->shouldReceive('retrieveTransaction')
            ->once()
            ->with(555)
            ->andReturn(new Transaction([
                'id' => 555,
                'status' => 'pending',
            ]));

        // The webhook lands while regenerateTokenFromTransaction is in flight,
        // flipping the row to Active with webhook-specific metadata. The resume's
        // locked re-read must observe Active, return checkout_url=null, and never
        // overwrite the webhook audit fields from the stale pre-Fedapay model.
        $this->fedapayMock->shouldReceive('regenerateTokenFromTransaction')
            ->once()
            ->andReturnUsing(function () use ($pending) {
                FaceSubscription::query()->whereKey($pending->id)->update([
                    'status' => FaceSubscriptionStatus::Active->value,
                    'starts_at' => now(),
                    'expires_at' => now()->addYear(),
                    'paid_amount' => 25000,
                    'metadata' => array_merge(
                        is_array($pending->metadata) ? $pending->metadata : [],
                        [
                            'fedapay_reference' => 'webhook_ref_ABC',
                            'confirmed_at' => now()->toIso8601String(),
                            'fedapay_event_payload_summary' => [
                                'event_id' => 'evt_during_resume',
                                'event_name' => 'transaction.approved',
                            ],
                        ],
                    ),
                ]);

                return [
                    'checkout_url' => 'https://checkout.fedapay.test/sess_should_be_ignored',
                    'fedapay_status' => 'pending',
                ];
            });

        $result = $this->service->resumePending($this->face);

        $this->assertSame(FaceSubscriptionStatus::Active->value, $result['status']);
        $this->assertNull($result['checkout_url']);
        $this->assertSame(25000, $result['amount']);
        $this->assertSame('XOF', $result['currency']);

        $fresh = $pending->fresh();
        $this->assertSame(FaceSubscriptionStatus::Active, $fresh->status);
        // Webhook audit metadata intact, no last_resumed_at / resume_count from stale model
        $this->assertSame('webhook_ref_ABC', $fresh->metadata['fedapay_reference']);
        $this->assertSame('evt_during_resume', $fresh->metadata['fedapay_event_payload_summary']['event_id']);
        $this->assertArrayNotHasKey('last_resumed_at', $fresh->metadata);
        $this->assertArrayNotHasKey('resume_count', $fresh->metadata);
    }

    public function test_resume_approved_branch_throws_payment_under_manual_review_when_mark_as_paid_refuses_activation_on_amount_mismatch(): void
    {
        $pending = FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => '888',
            'currency' => 'XOF',
            'metadata' => [
                'quoted_amount' => 25000,
                'quoted_currency' => 'XOF',
                'idempotency_key' => 'idem-mismatch',
            ],
        ]);

        // Fedapay reports approved with a paid amount that does not match the
        // quoted amount stored at initiation. markAsPaid will refuse activation
        // (FP-2.5 design: leave row PendingPayment for admin review). Without the
        // FP-2.15.2 review-patch guard, the resume endpoint would silently return
        // status=pending_payment + checkout_url=null and the frontend would tell
        // a Face who actually paid to "initier un nouveau paiement".
        $this->fedapayMock->shouldReceive('retrieveTransaction')
            ->once()
            ->with(888)
            ->andReturn(new Transaction([
                'id' => 888,
                'reference' => 'fedapay_ref_mismatch',
                'status' => 'approved',
                'amount' => 19999,
            ]));

        $this->fedapayMock->shouldNotReceive('regenerateTokenFromTransaction');

        try {
            $this->service->resumePending($this->face);
            $this->fail('Expected FaceSubscriptionConflictException for amount mismatch');
        } catch (FaceSubscriptionConflictException $e) {
            $this->assertSame(409, $e->getStatusCode());
            $this->assertSame(
                'Paiement reçu mais en attente de validation manuelle. Notre équipe vous contactera prochainement.',
                $e->getMessage(),
            );
        }

        // markAsPaid wrote the mismatch metadata but left the row PendingPayment
        // for ops to triage (existing FP-2.5 contract preserved).
        $fresh = $pending->fresh();
        $this->assertSame(FaceSubscriptionStatus::PendingPayment, $fresh->status);
        $this->assertArrayHasKey('amount_mismatch_detected_at', $fresh->metadata);
        $this->assertSame(19999, $fresh->metadata['paid_amount_reported']);
        $this->assertSame(25000, $fresh->metadata['expected_amount']);
        $this->assertArrayNotHasKey('last_resumed_at', $fresh->metadata);
        $this->assertArrayNotHasKey('resume_count', $fresh->metadata);
    }

    public function test_resume_pending_branch_throws_resume_not_available_when_local_row_flipped_to_failed_during_token_regeneration(): void
    {
        $pending = FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => '777',
            'currency' => 'XOF',
            'metadata' => [
                'quoted_amount' => 25000,
                'quoted_currency' => 'XOF',
                'idempotency_key' => 'idem-pre-failed',
            ],
        ]);

        $this->fedapayMock->shouldReceive('retrieveTransaction')
            ->once()
            ->with(777)
            ->andReturn(new Transaction([
                'id' => 777,
                'status' => 'pending',
            ]));

        // The webhook lands while regenerateTokenFromTransaction is in flight,
        // flipping the row to Failed (e.g., Fedapay flipped declined just after
        // we read it as pending). The resume's locked re-read must observe Failed
        // and throw resumeNotAvailable('local_failed') instead of writing resume
        // metadata onto a now-terminal row.
        $this->fedapayMock->shouldReceive('regenerateTokenFromTransaction')
            ->once()
            ->andReturnUsing(function () use ($pending) {
                FaceSubscription::query()->whereKey($pending->id)->update([
                    'status' => FaceSubscriptionStatus::Failed->value,
                    'metadata' => array_merge(
                        is_array($pending->metadata) ? $pending->metadata : [],
                        [
                            'failed_at' => now()->toIso8601String(),
                            'failure_reason' => 'Payment transaction.declined',
                        ],
                    ),
                ]);

                return [
                    'checkout_url' => 'https://checkout.fedapay.test/sess_should_be_ignored',
                    'fedapay_status' => 'pending',
                ];
            });

        try {
            $this->service->resumePending($this->face);
            $this->fail('Expected FaceSubscriptionConflictException for local_failed branch');
        } catch (FaceSubscriptionConflictException $e) {
            $this->assertSame(410, $e->getStatusCode());
            $this->assertSame(
                'Ce paiement ne peut plus être repris. Veuillez en initier un nouveau depuis la page Tarifs.',
                $e->getMessage(),
            );
        }

        $fresh = $pending->fresh();
        $this->assertSame(FaceSubscriptionStatus::Failed, $fresh->status);
        // Webhook failure metadata intact, no last_resumed_at / resume_count from stale model
        $this->assertSame('Payment transaction.declined', $fresh->metadata['failure_reason']);
        $this->assertArrayHasKey('failed_at', $fresh->metadata);
        $this->assertArrayNotHasKey('last_resumed_at', $fresh->metadata);
        $this->assertArrayNotHasKey('resume_count', $fresh->metadata);
    }
}
