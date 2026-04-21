<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionStatus;
use App\Jobs\HandleFedapayWebhook;
use App\Models\Candidature;
use App\Models\Conversation;
use App\Models\Face;
use App\Models\FedapayWebhookEvent;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use App\Models\User;
use App\Services\BookingService;
use App\Services\FedapayService;
use App\Services\MissionPaymentService;
use App\Services\WalletService;
use App\ValueObjects\MissionPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MissionPaymentInitiationTest extends TestCase
{
    use RefreshDatabase;

    private Producer $producer;

    private User $producerUser;

    private Mission $mission;

    private Candidature $selectedFirst;

    private Candidature $selectedSecond;

    private Candidature $rejectedCandidate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);

        $this->mission = Mission::factory()->published()->create([
            'producer_id' => $this->producer->id,
            'budget' => 90000,
        ]);

        $this->selectedFirst = $this->createPendingCandidature();
        $this->selectedSecond = $this->createPendingCandidature();
        $this->rejectedCandidate = $this->createPendingCandidature();
    }

    public function test_successful_payment_initiation_leaves_candidatures_pending_until_webhook(): void
    {
        // FIX-20.1 (AC #1): confirm-selection + FedaPay initiation must NOT mutate
        // candidature statuses. The Accepted/Rejected transitions are deferred to
        // the webhook (`markAsPaid` → `applySelectionOutcomesOnPaid`). Without this
        // contract, a Face would see "Acceptée" on a candidature she cannot confirm
        // (because the producer's payment is still pending).
        $this->mock(FedapayService::class, function ($mock) {
            $mock->shouldReceive('initiatePaymentForMission')
                ->once()
                ->andReturn([
                    'fedapay_transaction_id' => 123456,
                    'checkout_url' => 'https://checkout.fedapay.com/mission-token',
                ]);
        });

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->uuid}/confirm-selection", [
                'candidature_ids' => [
                    $this->selectedFirst->uuid,
                    $this->selectedSecond->uuid,
                ],
            ]);

        $response->assertOk();

        $this->assertSame(MissionStatus::PendingPayment, $this->mission->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $this->selectedFirst->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $this->selectedSecond->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $this->rejectedCandidate->fresh()->status);
    }

    public function test_successful_payment_initiation_does_not_dispatch_selection_notifications(): void
    {
        // FIX-20.1 (AC #2): no `candidature_accepted` or `candidature_rejected`
        // notifications must be dispatched during initiation. They move to
        // `applySelectionOutcomesOnPaid` and fire only once FedaPay confirms the
        // payment via webhook.
        $this->mock(FedapayService::class, function ($mock) {
            $mock->shouldReceive('initiatePaymentForMission')
                ->once()
                ->andReturn([
                    'fedapay_transaction_id' => 123456,
                    'checkout_url' => 'https://checkout.fedapay.com/mission-token',
                ]);
        });

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->uuid}/confirm-selection", [
                'candidature_ids' => [
                    $this->selectedFirst->uuid,
                    $this->selectedSecond->uuid,
                ],
            ])
            ->assertOk();

        $this->assertDatabaseMissing('notifications', ['type' => 'candidature_accepted']);
        $this->assertDatabaseMissing('notifications', ['type' => 'candidature_rejected']);
        $this->assertDatabaseMissing('notifications', ['type' => 'mission_participation_confirmation_required']);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_failed_payment_initiation_restores_mission_selection_and_returns_business_error(): void
    {
        $this->mock(FedapayService::class, function ($mock) {
            $mock->shouldReceive('initiatePaymentForMission')
                ->once()
                ->andThrow(new \RuntimeException('Fedapay unavailable'));
        });

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->uuid}/confirm-selection", [
                'candidature_ids' => [
                    $this->selectedFirst->uuid,
                    $this->selectedSecond->uuid,
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'PAYMENT_INITIATION_FAILED')
            ->assertJsonPath('error.message', 'Le paiement de la mission n\'a pas pu être initialisé. Veuillez réessayer.');

        $this->assertSame(MissionStatus::Published, $this->mission->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $this->selectedFirst->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $this->selectedSecond->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $this->rejectedCandidate->fresh()->status);

        $this->assertDatabaseMissing('mission_payments', [
            'mission_id' => $this->mission->id,
        ]);
        $this->assertDatabaseCount('mission_payment_candidatures', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_successful_payment_initiation_persists_payment_row_and_escrow_stubs(): void
    {
        $this->mock(FedapayService::class, function ($mock) {
            $mock->shouldReceive('initiatePaymentForMission')
                ->once()
                ->andReturn([
                    'fedapay_transaction_id' => 123456,
                    'checkout_url' => 'https://checkout.fedapay.com/mission-token',
                ]);
        });

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->uuid}/confirm-selection", [
                'candidature_ids' => [
                    $this->selectedFirst->uuid,
                    $this->selectedSecond->uuid,
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.checkout_url', 'https://checkout.fedapay.com/mission-token');

        $this->assertSame(MissionStatus::PendingPayment, $this->mission->fresh()->status);

        $this->assertDatabaseHas('mission_payments', [
            'mission_id' => $this->mission->id,
            'fedapay_transaction_id' => '123456',
            'status' => 'pending',
        ]);
        $this->assertDatabaseCount('mission_payment_candidatures', 2);
    }

    public function test_retry_without_reselecting_faces_reuses_existing_checkout_url(): void
    {
        $payment = $this->createPendingMissionPayment('123456');
        $existingTransaction = $this->makeTransactionStub(
            status: 'pending',
            checkoutUrl: 'https://checkout.fedapay.com/reused-token',
        );

        $this->mock(FedapayService::class, function ($mock) use ($existingTransaction): void {
            $mock->shouldReceive('retrieveTransaction')
                ->once()
                ->with(123456)
                ->andReturn($existingTransaction);
            $mock->shouldNotReceive('initiatePaymentForMission');
        });

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->uuid}/confirm-selection", []);

        $response->assertOk()
            ->assertJsonPath('data.payment_id', $payment->id)
            ->assertJsonPath('data.checkout_url', 'https://checkout.fedapay.com/reused-token')
            ->assertJsonPath('data.status', 'pending');

        $this->assertSame(1, MissionPayment::query()->count());
        $this->assertSame(MissionStatus::PendingPayment, $this->mission->fresh()->status);
        // FIX-20.1: candidatures remain Pending until webhook confirms payment.
        $this->assertSame(CandidatureStatus::Pending, $this->selectedFirst->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $this->selectedSecond->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $this->rejectedCandidate->fresh()->status);
    }

    public function test_retry_with_explicit_empty_selection_array_reuses_existing_checkout_url(): void
    {
        $payment = $this->createPendingMissionPayment('123456');
        $existingTransaction = $this->makeTransactionStub(
            status: 'pending',
            checkoutUrl: 'https://checkout.fedapay.com/reused-token',
        );

        $this->mock(FedapayService::class, function ($mock) use ($existingTransaction): void {
            $mock->shouldReceive('retrieveTransaction')
                ->once()
                ->with(123456)
                ->andReturn($existingTransaction);
            $mock->shouldNotReceive('initiatePaymentForMission');
        });

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->uuid}/confirm-selection", [
                'candidature_ids' => [],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.payment_id', $payment->id)
            ->assertJsonPath('data.checkout_url', 'https://checkout.fedapay.com/reused-token')
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_second_confirmation_request_reuses_same_mission_payment_without_duplicate_mutation(): void
    {
        $existingTransaction = $this->makeTransactionStub(
            status: 'pending',
            checkoutUrl: 'https://checkout.fedapay.com/reused-token',
        );

        $this->mock(FedapayService::class, function ($mock) use ($existingTransaction): void {
            $mock->shouldReceive('initiatePaymentForMission')
                ->once()
                ->andReturn([
                    'fedapay_transaction_id' => 123456,
                    'checkout_url' => 'https://checkout.fedapay.com/mission-token',
                ]);
            $mock->shouldReceive('retrieveTransaction')
                ->once()
                ->with(123456)
                ->andReturn($existingTransaction);
        });

        $firstPayload = [
            'candidature_ids' => [
                $this->selectedFirst->uuid,
                $this->selectedSecond->uuid,
            ],
        ];

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->uuid}/confirm-selection", $firstPayload)
            ->assertOk()
            ->assertJsonPath('data.checkout_url', 'https://checkout.fedapay.com/mission-token');

        // Per the resume contract (story fix-19-2, D1), the second call must NOT
        // include candidature_ids — the SPA strips the field once the mission has
        // moved to PendingPayment. Sending a non-empty body on resume is rejected
        // with 422 prohibited (covered by a dedicated test below).
        $secondResponse = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->uuid}/confirm-selection", []);

        $secondResponse->assertOk()
            ->assertJsonPath('data.checkout_url', 'https://checkout.fedapay.com/reused-token');

        $this->assertSame(1, MissionPayment::query()->count());
        $this->assertDatabaseHas('mission_payments', [
            'mission_id' => $this->mission->id,
            'fedapay_transaction_id' => '123456',
            'status' => 'pending',
        ]);
    }

    public function test_confirmation_request_returns_validation_error_when_mission_payment_lock_is_already_held(): void
    {
        $lock = Cache::lock("mission_payment_{$this->mission->id}", 120);

        $this->assertTrue($lock->get(), 'Expected to acquire the mission payment lock for the contention test.');

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldNotReceive('retrieveTransaction');
            $mock->shouldNotReceive('initiatePaymentForMission');
        });

        try {
            $response = $this->actingAs($this->producerUser)
                ->postJson("/api/v1/producer/missions/{$this->mission->uuid}/confirm-selection", [
                    'candidature_ids' => [
                        $this->selectedFirst->uuid,
                        $this->selectedSecond->uuid,
                    ],
                ]);

            $response->assertStatus(422)
                ->assertJsonPath(
                    'errors.status.0',
                    'Un paiement est deja en cours pour cette mission.'
                );

            $this->assertDatabaseCount('mission_payments', 0);
            $this->assertSame(MissionStatus::Published, $this->mission->fresh()->status);
            $this->assertSame(CandidatureStatus::Pending, $this->selectedFirst->fresh()->status);
            $this->assertSame(CandidatureStatus::Pending, $this->selectedSecond->fresh()->status);
            $this->assertSame(CandidatureStatus::Pending, $this->rejectedCandidate->fresh()->status);
        } finally {
            $lock->release();
        }
    }

    public function test_retry_with_non_empty_candidature_ids_on_resume_is_rejected(): void
    {
        $this->createPendingMissionPayment('123456');

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldNotReceive('retrieveTransaction');
            $mock->shouldNotReceive('initiatePaymentForMission');
        });

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->uuid}/confirm-selection", [
                'candidature_ids' => [
                    $this->selectedFirst->uuid,
                    $this->selectedSecond->uuid,
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath(
                'errors.candidature_ids.0',
                'Ce paiement est déjà initié. Pour changer la sélection, annulez d\'abord le paiement en cours.'
            );
    }

    public function test_retry_after_terminal_remote_failure_reuses_same_payment_record_with_new_checkout(): void
    {
        $payment = $this->createPendingMissionPayment('123456');
        $failedTransaction = $this->makeTransactionStub(
            status: 'declined',
            checkoutUrl: 'https://checkout.fedapay.com/obsolete-token',
            expectsGenerateToken: false,
        );

        $this->mock(FedapayService::class, function ($mock) use ($failedTransaction): void {
            $mock->shouldReceive('retrieveTransaction')
                ->once()
                ->with(123456)
                ->andReturn($failedTransaction);
            $mock->shouldReceive('initiatePaymentForMission')
                ->once()
                ->andReturn([
                    'fedapay_transaction_id' => 987654,
                    'checkout_url' => 'https://checkout.fedapay.com/retry-token',
                ]);
        });

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->uuid}/confirm-selection", []);

        $response->assertOk()
            ->assertJsonPath('data.payment_id', $payment->id)
            ->assertJsonPath('data.checkout_url', 'https://checkout.fedapay.com/retry-token')
            ->assertJsonPath('data.status', 'pending');

        $this->assertSame(1, MissionPayment::query()->count());
        $this->assertDatabaseHas('mission_payments', [
            'id' => $payment->id,
            'mission_id' => $this->mission->id,
            'fedapay_transaction_id' => '987654',
            'status' => 'pending',
        ]);
        // FIX-20.1: candidatures remain Pending — no webhook has fired yet.
        $this->assertSame(CandidatureStatus::Pending, $this->selectedFirst->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $this->selectedSecond->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $this->rejectedCandidate->fresh()->status);
    }

    public function test_retry_after_remote_approval_reconciles_local_state_and_returns_mission_page_url(): void
    {
        $payment = $this->createPendingMissionPayment('123456');
        $approvedTransaction = $this->makeTransactionStub(
            status: 'approved',
            checkoutUrl: 'https://checkout.fedapay.com/already-approved-token',
            expectsGenerateToken: false,
            reference: 'fedapay-approved-ref',
        );

        $this->mock(FedapayService::class, function ($mock) use ($approvedTransaction): void {
            $mock->shouldReceive('retrieveTransaction')
                ->once()
                ->with(123456)
                ->andReturn($approvedTransaction);
            $mock->shouldNotReceive('initiatePaymentForMission');
        });

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->uuid}/confirm-selection", []);

        $response->assertOk()
            ->assertJsonPath('data.payment_id', $payment->id)
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('message', 'Paiement déjà confirmé. Redirection vers la mission...')
            ->assertJsonPath(
                'data.checkout_url',
                rtrim(
                    (string) config('app.frontend_url', (string) config('app.url', '')),
                    '/'
                )."/producer/missions/{$this->mission->uuid}/candidatures?payment=confirmed"
            );

        $this->assertSame(MissionStatus::Closed, $this->mission->fresh()->status);
        $this->assertDatabaseHas('mission_payments', [
            'id' => $payment->id,
            'status' => 'paid',
            'fedapay_ref' => 'fedapay-approved-ref',
        ]);

        // FIX-20.1: self-healing triggers markAsPaid which now also runs
        // applySelectionOutcomesOnPaid — selected Faces transition to Accepted,
        // non-selected Face transitions to Rejected and receives a candidature_rejected notification.
        $this->assertSame(CandidatureStatus::Accepted, $this->selectedFirst->fresh()->status);
        $this->assertSame(CandidatureStatus::Accepted, $this->selectedSecond->fresh()->status);
        $this->assertSame(CandidatureStatus::Rejected, $this->rejectedCandidate->fresh()->status);

        $this->assertDatabaseHas('conversations', ['candidature_id' => $this->selectedFirst->id]);
        $this->assertDatabaseHas('conversations', ['candidature_id' => $this->selectedSecond->id]);

        // Self-healing must fan out the same notifications the webhook would have created:
        // one producer confirmation + one participation-confirmation per selected Face +
        // one candidature_rejected per non-selected Face.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->producerUser->id,
            'type' => 'mission_payment_confirmed',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->getFaceUserId($this->selectedFirst),
            'type' => 'mission_participation_confirmation_required',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->getFaceUserId($this->selectedSecond),
            'type' => 'mission_participation_confirmation_required',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->getFaceUserId($this->rejectedCandidate),
            'type' => 'candidature_rejected',
        ]);
    }

    public function test_webhook_and_producer_retry_race_do_not_double_credit(): void
    {
        $payment = $this->createPendingMissionPayment('123456');
        $approvedTransaction = $this->makeTransactionStub(
            status: 'approved',
            checkoutUrl: 'https://checkout.fedapay.com/already-approved-token',
            expectsGenerateToken: false,
            reference: 'fedapay-approved-ref',
        );

        $this->mock(FedapayService::class, function ($mock) use ($approvedTransaction): void {
            $mock->shouldReceive('retrieveTransaction')
                ->once()
                ->with(123456)
                ->andReturn($approvedTransaction);
            $mock->shouldNotReceive('initiatePaymentForMission');
        });

        // Step 1: producer retries first and hits the self-healing branch.
        // markAsPaid() runs inside the cache lock and fans out notifications.
        // FIX-20.1: 4 notifications = 1 mission_payment_confirmed (producer) +
        // 2 mission_participation_confirmation_required (selected Faces) +
        // 1 candidature_rejected (non-selected Face).
        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->uuid}/confirm-selection", [])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseCount('notifications', 4);

        // Step 2: the FedaPay webhook lands LATE (it was delayed, not lost).
        // HandleFedapayWebhook ultimately calls markAsPaid() on the same payment.
        // The idempotency guard (status === Paid early-return) MUST keep this a
        // no-op: no duplicate notifications, no double escrow lock, no duplicate
        // mission mutation, no duplicate Conversation rows. This regression test
        // locks in that contract so a future refactor that removes the early-return
        // fails loudly here.
        app(MissionPaymentService::class)->markAsPaid($payment->fresh(), 'fedapay-approved-ref');

        $this->assertDatabaseCount('notifications', 4);
        $this->assertSame(2, Conversation::query()->count());
        $this->assertSame(1, MissionPayment::query()->count());
        $this->assertDatabaseHas('mission_payments', [
            'id' => $payment->id,
            'status' => 'paid',
            'fedapay_ref' => 'fedapay-approved-ref',
        ]);
        $this->assertSame(MissionStatus::Closed, $this->mission->fresh()->status);
    }

    public function test_retry_resume_failure_returns_business_error_instead_of_server_error(): void
    {
        Log::spy();

        $payment = $this->createPendingMissionPayment('123456');

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('retrieveTransaction')
                ->once()
                ->with(123456)
                ->andThrow(new \RuntimeException('Fedapay unavailable during resume.'));
            $mock->shouldNotReceive('initiatePaymentForMission');
        });

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->uuid}/confirm-selection", []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'PAYMENT_INITIATION_FAILED')
            ->assertJsonPath('error.message', 'Le paiement de la mission n\'a pas pu être initialisé. Veuillez réessayer.');

        $this->assertDatabaseHas('mission_payments', [
            'id' => $payment->id,
            'status' => 'pending',
            'fedapay_transaction_id' => '123456',
        ]);
        $this->assertSame(MissionStatus::PendingPayment, $this->mission->fresh()->status);

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context) use ($payment): bool {
                return $message === 'Mission payment resume failed'
                    && ($context['payment_id'] ?? null) === $payment->id
                    && ($context['mission_id'] ?? null) === $this->mission->id
                    && ($context['producer_id'] ?? null) === $this->producer->id
                    && ($context['phase'] ?? null) === 'resume'
                    && ($context['remote_transaction_id'] ?? null) === '123456'
                    && ($context['error_class'] ?? null) === \RuntimeException::class
                    && ($context['error_message'] ?? null) === 'Fedapay unavailable during resume.';
            });
    }

    public function test_finalization_failure_after_checkout_returns_business_error_and_restores_state(): void
    {
        Log::spy();

        $this->partialMock(MissionPaymentService::class, function ($mock): void {
            $mock->shouldAllowMockingProtectedMethods();
            $mock->shouldReceive('requestHostedCheckout')
                ->once()
                ->andReturn([
                    'fedapay_transaction_id' => 654321,
                    'checkout_url' => 'https://checkout.fedapay.com/mission-token',
                ]);
            $mock->shouldReceive('finalizePreparedPayment')
                ->once()
                ->andThrow(new \RuntimeException('Database write failed after checkout creation.'));
        });

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->uuid}/confirm-selection", [
                'candidature_ids' => [
                    $this->selectedFirst->uuid,
                    $this->selectedSecond->uuid,
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'PAYMENT_INITIATION_FAILED')
            ->assertJsonPath('error.message', 'Le paiement de la mission n\'a pas pu être initialisé. Veuillez réessayer.');

        $this->assertSame(MissionStatus::Published, $this->mission->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $this->selectedFirst->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $this->selectedSecond->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $this->rejectedCandidate->fresh()->status);

        $this->assertDatabaseMissing('mission_payments', [
            'mission_id' => $this->mission->id,
        ]);
        $this->assertDatabaseCount('mission_payment_candidatures', 0);
        $this->assertDatabaseCount('notifications', 0);

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Mission payment initiation failed'
                    && ($context['mission_id'] ?? null) === $this->mission->id
                    && ($context['producer_id'] ?? null) === $this->producer->id
                    && is_int($context['payment_id'] ?? null)
                    && ($context['payment_id'] ?? 0) > 0
                    && ($context['phase'] ?? null) === 'finalize_local'
                    && ($context['remote_transaction_id'] ?? null) === 654321
                    && ($context['needs_compensation'] ?? null) === true
                    && ($context['compensation_attempted'] ?? null) === true
                    && ($context['compensation_failed'] ?? null) === false
                    && ($context['compensation_outcome'] ?? null) === 'succeeded'
                    && ($context['manual_recovery_required'] ?? null) === true;
            });
    }

    public function test_compensation_failure_after_finalize_still_returns_business_error_and_logs_context(): void
    {
        Log::spy();

        $this->partialMock(MissionPaymentService::class, function ($mock): void {
            $mock->shouldAllowMockingProtectedMethods();
            $mock->shouldReceive('requestHostedCheckout')
                ->once()
                ->andReturn([
                    'fedapay_transaction_id' => 987654,
                    'checkout_url' => 'https://checkout.fedapay.com/mission-token',
                ]);
            $mock->shouldReceive('finalizePreparedPayment')
                ->once()
                ->andThrow(new \RuntimeException('Database write failed after checkout creation.'));
            $mock->shouldReceive('compensateFailedPreparation')
                ->once()
                ->andThrow(new \RuntimeException('Compensation transaction deadlocked.'));
        });

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->uuid}/confirm-selection", [
                'candidature_ids' => [
                    $this->selectedFirst->uuid,
                    $this->selectedSecond->uuid,
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'PAYMENT_INITIATION_FAILED')
            ->assertJsonPath('error.message', 'Le paiement de la mission n\'a pas pu être initialisé. Veuillez réessayer.');

        // Compensation was attempted but threw, so prepared state is left in place
        // (MissionPayment row + mission pending). FIX-20.1: candidatures were never
        // mutated during prepare, so they stay Pending regardless of compensation outcome.
        $this->assertSame(MissionStatus::PendingPayment, $this->mission->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $this->selectedFirst->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $this->selectedSecond->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $this->rejectedCandidate->fresh()->status);

        // Compensation failed → the mission_payments row was not deleted, so we can
        // capture its real id and assert the log context points at the same row.
        $persistedPaymentId = MissionPayment::where('mission_id', $this->mission->id)->value('id');
        $this->assertIsInt($persistedPaymentId);

        Log::shouldHaveReceived('error')->twice();

        Log::shouldHaveReceived('error')
            ->withArgs(function (string $message, array $context) use ($persistedPaymentId): bool {
                return $message === 'Mission payment initiation failed'
                    && ($context['mission_id'] ?? null) === $this->mission->id
                    && ($context['producer_id'] ?? null) === $this->producer->id
                    && ($context['payment_id'] ?? null) === $persistedPaymentId
                    && ($context['phase'] ?? null) === 'finalize_local'
                    && ($context['remote_transaction_id'] ?? null) === 987654
                    && ($context['compensation_attempted'] ?? null) === true
                    && ($context['compensation_failed'] ?? null) === true
                    && ($context['compensation_outcome'] ?? null) === 'failed'
                    && ($context['manual_recovery_required'] ?? null) === true;
            });

        Log::shouldHaveReceived('error')
            ->withArgs(function (string $message, array $context) use ($persistedPaymentId): bool {
                return $message === 'Mission payment compensation failed after initiation error'
                    && ($context['mission_id'] ?? null) === $this->mission->id
                    && ($context['producer_id'] ?? null) === $this->producer->id
                    && ($context['payment_id'] ?? null) === $persistedPaymentId
                    && ($context['phase'] ?? null) === 'compensate'
                    && ($context['remote_transaction_id'] ?? null) === 987654
                    && ($context['compensation_error_message'] ?? null) === 'Compensation transaction deadlocked.';
            });
    }

    public function test_failed_request_checkout_logs_full_mission_payment_failure_context(): void
    {
        Log::spy();

        $this->mock(FedapayService::class, function ($mock) {
            $mock->shouldReceive('initiatePaymentForMission')
                ->once()
                ->andThrow(new \RuntimeException('Fedapay unavailable'));
        });

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->uuid}/confirm-selection", [
                'candidature_ids' => [
                    $this->selectedFirst->uuid,
                    $this->selectedSecond->uuid,
                ],
            ])
            ->assertStatus(422);

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Mission payment initiation failed'
                    && is_int($context['payment_id'] ?? null)
                    && ($context['payment_id'] ?? 0) > 0
                    && ($context['mission_id'] ?? null) === $this->mission->id
                    && ($context['producer_id'] ?? null) === $this->producer->id
                    && ($context['phase'] ?? null) === 'request_checkout'
                    && ($context['remote_transaction_id'] ?? null) === null
                    && ($context['needs_compensation'] ?? null) === true
                    && ($context['compensation_attempted'] ?? null) === true
                    && ($context['compensation_outcome'] ?? null) === 'succeeded'
                    && ($context['manual_recovery_required'] ?? null) === false
                    && ($context['error_class'] ?? null) === \RuntimeException::class;
            });
    }

    public function test_initiation_failure_leaves_mission_payment_table_clean_enough_for_a_fresh_retry_to_succeed(): void
    {
        // FIX-19.5 regression guard (AC #1): the fix-19-1 compensation path must leave
        // the DB in a state where the very next confirm-selection request can create a
        // brand-new payment row without tripping any leftover unique constraint or
        // stale candidature status. A failure in fix-19-1 compensation would typically
        // manifest as a second attempt returning 500 (unique violation on `mission_id`)
        // or 422 ("mission not in Published status") instead of 200.

        $callCount = 0;
        $this->mock(FedapayService::class, function ($mock) use (&$callCount): void {
            $mock->shouldReceive('initiatePaymentForMission')
                ->twice()
                ->andReturnUsing(function () use (&$callCount): array {
                    $callCount++;
                    if ($callCount === 1) {
                        throw new \RuntimeException('Fedapay unavailable');
                    }

                    return [
                        'fedapay_transaction_id' => 778899,
                        'checkout_url' => 'https://checkout.fedapay.com/fresh-retry-token',
                    ];
                });
        });

        // First attempt — fails before a transaction is persisted.
        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->uuid}/confirm-selection", [
                'candidature_ids' => [
                    $this->selectedFirst->uuid,
                    $this->selectedSecond->uuid,
                ],
            ])
            ->assertStatus(422);

        $this->assertSame(MissionStatus::Published, $this->mission->fresh()->status);
        $this->assertDatabaseMissing('mission_payments', [
            'mission_id' => $this->mission->id,
        ]);
        // Compensation must also wipe the escrow stubs — a regression that
        // leaves orphan rows behind would let the second retry succeed at the
        // payments table while quietly accumulating duplicate candidature stubs.
        $this->assertDatabaseCount('mission_payment_candidatures', 0);

        // Second attempt — MUST succeed using the same (now-restored) candidatures.
        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->uuid}/confirm-selection", [
                'candidature_ids' => [
                    $this->selectedFirst->uuid,
                    $this->selectedSecond->uuid,
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.checkout_url', 'https://checkout.fedapay.com/fresh-retry-token');

        $this->assertSame(MissionStatus::PendingPayment, $this->mission->fresh()->status);
        // FIX-20.1: candidatures remain Pending after a successful prepare — they
        // only transition at webhook time. Second attempt persisted the payment row;
        // the webhook has not fired yet.
        $this->assertSame(CandidatureStatus::Pending, $this->selectedFirst->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $this->selectedSecond->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $this->rejectedCandidate->fresh()->status);
        $this->assertSame(1, MissionPayment::query()->count());
        $this->assertDatabaseHas('mission_payments', [
            'mission_id' => $this->mission->id,
            'fedapay_transaction_id' => '778899',
            'status' => 'pending',
        ]);
        // Exactly two escrow stubs after the successful retry — guards against
        // a regression that leaves orphan rows from the first failed attempt
        // alongside the new ones.
        $this->assertDatabaseCount('mission_payment_candidatures', 2);
    }

    public function test_transient_resume_failure_keeps_payment_row_resumable_on_the_next_attempt(): void
    {
        // FIX-19.5 regression guard (AC #2): a transient failure on the resume
        // branch (e.g. FedaPay `retrieveTransaction` timeout) must leave the pending
        // mission payment row untouched so the producer's very next retry can still
        // resume against the same remote transaction. Regressing fix-19-2 by deleting
        // the payment on resume-failure or mutating its status would turn a blip into
        // a hard deadlock for the producer.

        $payment = $this->createPendingMissionPayment('123456');

        $successTransaction = $this->makeTransactionStub(
            status: 'pending',
            checkoutUrl: 'https://checkout.fedapay.com/second-attempt-token',
        );

        $call = 0;
        $this->mock(FedapayService::class, function ($mock) use (&$call, $successTransaction): void {
            $mock->shouldReceive('retrieveTransaction')
                ->twice()
                ->with(123456)
                ->andReturnUsing(function () use (&$call, $successTransaction) {
                    $call++;
                    if ($call === 1) {
                        throw new \RuntimeException('Fedapay unreachable — transient.');
                    }

                    return $successTransaction;
                });
            $mock->shouldNotReceive('initiatePaymentForMission');
        });

        // First retry fails transiently.
        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->uuid}/confirm-selection", [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PAYMENT_INITIATION_FAILED')
            ->assertJsonPath('error.message', 'Le paiement de la mission n\'a pas pu être initialisé. Veuillez réessayer.');

        // Row is still pending with the same remote id — no data loss on resume failure.
        $this->assertDatabaseHas('mission_payments', [
            'id' => $payment->id,
            'status' => 'pending',
            'fedapay_transaction_id' => '123456',
        ]);
        $this->assertSame(MissionStatus::PendingPayment, $this->mission->fresh()->status);
        $this->assertSame(1, MissionPayment::query()->count());
        // FIX-20.1: candidatures are Pending pre-webhook and must stay that way
        // across a transient resume failure — no spurious mutations or rollbacks.
        $this->assertSame(CandidatureStatus::Pending, $this->selectedFirst->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $this->selectedSecond->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $this->rejectedCandidate->fresh()->status);
        // Escrow stubs must also stay intact across the transient failure.
        $this->assertDatabaseCount('mission_payment_candidatures', 2);

        // Second retry succeeds against the same record.
        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->uuid}/confirm-selection", []);

        $response->assertOk()
            ->assertJsonPath('data.payment_id', $payment->id)
            ->assertJsonPath('data.checkout_url', 'https://checkout.fedapay.com/second-attempt-token')
            ->assertJsonPath('data.status', 'pending');

        $this->assertSame(1, MissionPayment::query()->count());
    }

    public function test_webhook_decline_emits_warning_with_full_mission_payment_failure_context(): void
    {
        Log::spy();

        $payment = $this->createPendingMissionPayment('444555');

        $webhookEvent = FedapayWebhookEvent::create([
            'fedapay_event_id' => 'evt_mission_decline',
            'event_name' => 'transaction.declined',
            'payload' => [
                'entity' => [
                    'id' => 444555,
                    'reference' => 'ref_mission_fail',
                ],
            ],
            'status' => 'received',
        ]);

        $job = new HandleFedapayWebhook(
            $webhookEvent->id,
            'transaction.declined',
            [
                'entity' => [
                    'id' => 444555,
                    'reference' => 'ref_mission_fail',
                ],
            ]
        );

        $job->handle(
            app(BookingService::class),
            app(MissionPaymentService::class),
            app(WalletService::class),
        );

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context) use ($payment): bool {
                return $message === 'Mission payment declined or canceled by webhook'
                    && ($context['payment_id'] ?? null) === $payment->id
                    && ($context['mission_id'] ?? null) === $this->mission->id
                    && ($context['producer_id'] ?? null) === $this->producer->id
                    && ($context['phase'] ?? null) === 'webhook'
                    && ($context['remote_transaction_id'] ?? null) === '444555'
                    && ($context['event_name'] ?? null) === 'transaction.declined';
            });

        $webhookEvent->refresh();
        $this->assertSame('processed', $webhookEvent->status);
    }

    public function test_full_flow_prep_then_webhook_approved_applies_selection_outcomes(): void
    {
        // FIX-20.1 (AC #3, #4, #5): end-to-end flow — producer confirms selection,
        // FedaPay webhook fires transaction.approved, markAsPaid runs, candidatures
        // transition (selected → Accepted, others → Rejected), a Conversation is
        // provisioned per accepted candidature, and candidature_rejected notifications
        // are dispatched.
        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('initiatePaymentForMission')
                ->once()
                ->andReturn([
                    'fedapay_transaction_id' => 555666,
                    'checkout_url' => 'https://checkout.fedapay.com/mission-token',
                ]);
        });

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->uuid}/confirm-selection", [
                'candidature_ids' => [
                    $this->selectedFirst->uuid,
                    $this->selectedSecond->uuid,
                ],
            ])
            ->assertOk();

        // Sanity check: pre-webhook state per AC #1/#2.
        $this->assertSame(CandidatureStatus::Pending, $this->selectedFirst->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $this->selectedSecond->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $this->rejectedCandidate->fresh()->status);
        $this->assertSame(0, Conversation::query()->count());

        /** @var MissionPayment $payment */
        $payment = MissionPayment::query()->where('mission_id', $this->mission->id)->firstOrFail();

        app(MissionPaymentService::class)->markAsPaid($payment, 'fedapay-approved-ref');

        $this->assertSame(MissionStatus::Closed, $this->mission->fresh()->status);
        $this->assertSame(CandidatureStatus::Accepted, $this->selectedFirst->fresh()->status);
        $this->assertSame(CandidatureStatus::Accepted, $this->selectedSecond->fresh()->status);
        $this->assertSame(CandidatureStatus::Rejected, $this->rejectedCandidate->fresh()->status);

        // AC #4: Conversation::firstOrCreate fires for each accepted candidature.
        $this->assertDatabaseHas('conversations', ['candidature_id' => $this->selectedFirst->id]);
        $this->assertDatabaseHas('conversations', ['candidature_id' => $this->selectedSecond->id]);
        $this->assertSame(2, Conversation::query()->count());

        // AC #5: candidature_rejected notification for each rejected Face + the
        // existing mission_participation_confirmation_required for each selected Face
        // (replaces the redundant candidature_accepted notification).
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->getFaceUserId($this->rejectedCandidate),
            'type' => 'candidature_rejected',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->getFaceUserId($this->selectedFirst),
            'type' => 'mission_participation_confirmation_required',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->getFaceUserId($this->selectedSecond),
            'type' => 'mission_participation_confirmation_required',
        ]);
        $this->assertDatabaseMissing('notifications', ['type' => 'candidature_accepted']);
    }

    public function test_webhook_replay_is_idempotent_and_does_not_double_mutate_or_notify(): void
    {
        // FIX-20.1 (AC #6): a duplicate transaction.approved webhook must be a no-op:
        // no second Conversation row, no second candidature_rejected notification,
        // no re-transition of already-Accepted candidatures.
        $payment = $this->createPendingMissionPayment('777888');

        $service = app(MissionPaymentService::class);

        $service->markAsPaid($payment->fresh(), 'fedapay-approved-ref');

        $notificationCountAfterFirst = \App\Models\Notification::query()->count();
        $conversationCountAfterFirst = Conversation::query()->count();

        // Second webhook lands — markAsPaid early-returns on Paid status, so
        // applySelectionOutcomesOnPaid never runs a second time.
        $service->markAsPaid($payment->fresh(), 'fedapay-approved-ref');

        $this->assertSame($notificationCountAfterFirst, \App\Models\Notification::query()->count());
        $this->assertSame($conversationCountAfterFirst, Conversation::query()->count());

        // Sanity: the accepted candidatures stay Accepted (not flipped back to
        // Pending nor mutated again).
        $this->assertSame(CandidatureStatus::Accepted, $this->selectedFirst->fresh()->status);
        $this->assertSame(CandidatureStatus::Accepted, $this->selectedSecond->fresh()->status);
        $this->assertSame(CandidatureStatus::Rejected, $this->rejectedCandidate->fresh()->status);
    }

    public function test_abandoned_checkout_leaves_all_candidatures_pending_and_no_conversation(): void
    {
        // FIX-20.1 (AC #7): the producer starts the checkout flow but never completes
        // payment. No webhook fires. All candidatures must stay Pending and no
        // Conversation row should exist.
        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('initiatePaymentForMission')
                ->once()
                ->andReturn([
                    'fedapay_transaction_id' => 999000,
                    'checkout_url' => 'https://checkout.fedapay.com/abandoned-token',
                ]);
        });

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->uuid}/confirm-selection", [
                'candidature_ids' => [
                    $this->selectedFirst->uuid,
                    $this->selectedSecond->uuid,
                ],
            ])
            ->assertOk();

        // No webhook fired — no outcomes applied.
        $this->assertSame(CandidatureStatus::Pending, $this->selectedFirst->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $this->selectedSecond->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $this->rejectedCandidate->fresh()->status);
        $this->assertSame(0, Conversation::query()->count());
        $this->assertDatabaseCount('notifications', 0);
    }

    private function createPendingCandidature(): Candidature
    {
        $face = Face::factory()->create();

        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        return Candidature::factory()->pending()->create([
            'face_id' => $face->id,
            'mission_id' => $this->mission->id,
        ]);
    }

    private function getFaceUserId(Candidature $candidature): int
    {
        return (int) User::query()
            ->where('userable_type', Face::class)
            ->where('userable_id', $candidature->face_id)
            ->value('id');
    }

    private function createPendingMissionPayment(string $fedapayTransactionId): MissionPayment
    {
        $selectedCandidatures = [$this->selectedFirst, $this->selectedSecond];
        $pricing = new MissionPricing($this->mission->budget, count($selectedCandidatures));

        $payment = MissionPayment::query()->create([
            'mission_id' => $this->mission->id,
            'producer_id' => $this->producer->id,
            'nombre_faces_retenues' => count($selectedCandidatures),
            'budget_par_face' => $pricing->budgetParFace,
            'montant_sous_total' => $pricing->sousTotal,
            'commission_producteur' => $pricing->commissionProducteur,
            'montant_total_producteur' => $pricing->montantTotalProducteur,
            'commission_faces_total' => $pricing->commissionFacesTotal,
            'montant_total_faces' => $pricing->montantTotalFaces,
            'fedapay_transaction_id' => $fedapayTransactionId,
            'status' => 'pending',
        ]);

        foreach ($selectedCandidatures as $candidature) {
            MissionPaymentCandidature::query()->create([
                'mission_payment_id' => $payment->id,
                'candidature_id' => $candidature->id,
                'face_id' => $candidature->face_id,
                'montant_face_recoit' => $pricing->montantParFace,
                'escrow_status' => EscrowStatus::Pending,
            ]);
        }

        // FIX-20.1: candidatures stay Pending until the FedaPay webhook fires
        // `markAsPaid` + `applySelectionOutcomesOnPaid`. The helper only reproduces
        // the post-prepare state (payment row + escrow stubs + mission pending).
        $this->mission->update(['status' => MissionStatus::PendingPayment]);

        return $payment->fresh();
    }

    private function makeTransactionStub(
        string $status,
        string $checkoutUrl,
        bool $expectsGenerateToken = true,
        ?string $reference = null,
    ): \FedaPay\Transaction {
        /** @var \FedaPay\Transaction $transaction */
        $transaction = \Mockery::mock(\FedaPay\Transaction::class);
        $transaction->status = $status;
        $transaction->reference = $reference;

        if ($expectsGenerateToken) {
            $transaction->shouldReceive('generateToken')
                ->once()
                ->andReturn((object) ['url' => $checkoutUrl]);
        }

        return $transaction;
    }
}
