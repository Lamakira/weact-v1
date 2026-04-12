<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use App\Services\FedapayService;
use App\Services\MissionPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertJsonPath('message', 'Le paiement de la mission n\'a pas pu être initialisé. Veuillez réessayer.');

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

    public function test_successful_payment_initiation_finalizes_selection_and_creates_notifications(): void
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
        $this->assertSame(CandidatureStatus::Accepted, $this->selectedFirst->fresh()->status);
        $this->assertSame(CandidatureStatus::Accepted, $this->selectedSecond->fresh()->status);
        $this->assertSame(CandidatureStatus::Rejected, $this->rejectedCandidate->fresh()->status);

        $this->assertDatabaseHas('mission_payments', [
            'mission_id' => $this->mission->id,
            'fedapay_transaction_id' => '123456',
            'status' => 'pending',
        ]);
        $this->assertDatabaseCount('mission_payment_candidatures', 2);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->getFaceUserId($this->selectedFirst),
            'type' => 'candidature_accepted',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->getFaceUserId($this->selectedSecond),
            'type' => 'candidature_accepted',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->getFaceUserId($this->rejectedCandidate),
            'type' => 'candidature_rejected',
        ]);
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
            ->assertJsonPath('message', 'Le paiement de la mission n\'a pas pu être initialisé. Veuillez réessayer.');

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
                    && ($context['remote_transaction_id'] ?? null) === 654321
                    && ($context['needs_compensation'] ?? null) === true
                    && ($context['compensation_attempted'] ?? null) === true
                    && ($context['compensation_failed'] ?? null) === false
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
            ->assertJsonPath('message', 'Le paiement de la mission n\'a pas pu être initialisé. Veuillez réessayer.');

        // Compensation was attempted but threw, so prepared state is left in place
        // and the operator must rely on the logged context for manual recovery.
        $this->assertSame(MissionStatus::PendingPayment, $this->mission->fresh()->status);
        $this->assertSame(CandidatureStatus::Accepted, $this->selectedFirst->fresh()->status);
        $this->assertSame(CandidatureStatus::Accepted, $this->selectedSecond->fresh()->status);
        $this->assertSame(CandidatureStatus::Rejected, $this->rejectedCandidate->fresh()->status);

        Log::shouldHaveReceived('error')->twice();

        Log::shouldHaveReceived('error')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Mission payment initiation failed'
                    && ($context['remote_transaction_id'] ?? null) === 987654
                    && ($context['compensation_attempted'] ?? null) === true
                    && ($context['compensation_failed'] ?? null) === true
                    && ($context['manual_recovery_required'] ?? null) === true;
            });

        Log::shouldHaveReceived('error')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Mission payment compensation failed after initiation error'
                    && ($context['remote_transaction_id'] ?? null) === 987654
                    && ($context['compensation_error_message'] ?? null) === 'Compensation transaction deadlocked.';
            });
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
}
