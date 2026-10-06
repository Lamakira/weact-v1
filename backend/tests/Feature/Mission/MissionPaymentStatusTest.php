<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use App\Models\User;
use App\Services\FaceEntitlementService;
use App\Services\FedapayService;
use App\ValueObjects\MissionPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FIX-19.3 — Guard false pending payment UI.
 *
 * The /payment-status endpoint must expose a boolean `is_trackable` flag so the
 * producer mission candidature page can tell "pending with a real FedaPay
 * transaction I can still poll" from "pending but no trackable transaction"
 * (e.g. stuck after a failed initiation). The frontend uses this flag to
 * decide whether to start polling and whether to render the "Paiement en
 * attente de confirmation..." banner.
 */
class MissionPaymentStatusTest extends TestCase
{
    use RefreshDatabase;

    private Producer $producer;

    private User $producerUser;

    private Mission $mission;

    private Candidature $firstCandidature;

    private Candidature $secondCandidature;

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

        $this->firstCandidature = $this->createPendingCandidature();
        $this->secondCandidature = $this->createPendingCandidature();
    }

    public function test_payment_status_reports_is_trackable_false_when_mission_has_no_payment(): void
    {
        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldNotReceive('retrieveTransaction');
        });

        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$this->mission->uuid}/payment-status");

        $response->assertOk()
            ->assertJsonPath('data.has_payment', false)
            ->assertJsonPath('data.status', null)
            ->assertJsonPath('data.is_trackable', false)
            ->assertJsonPath('data.mission_status', MissionStatus::Published->value);
    }

    public function test_payment_status_reports_is_trackable_false_when_pending_payment_has_no_fedapay_transaction(): void
    {
        // Simulates the stuck-pending state: mission moved to PendingPayment
        // but compensation failed (or was skipped) and no fedapay_transaction_id
        // was ever persisted. This is the "false pending" scenario the producer
        // UI must NOT start polling on.
        $this->createPendingMissionPayment(fedapayTransactionId: null);

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldNotReceive('retrieveTransaction');
        });

        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$this->mission->uuid}/payment-status");

        $response->assertOk()
            ->assertJsonPath('data.has_payment', true)
            ->assertJsonPath('data.status', MissionPaymentStatus::Pending->value)
            ->assertJsonPath('data.is_trackable', false)
            ->assertJsonPath('data.mission_status', MissionStatus::PendingPayment->value);
    }

    public function test_payment_status_reports_is_trackable_true_when_pending_payment_has_fedapay_transaction(): void
    {
        $this->createPendingMissionPayment(fedapayTransactionId: '123456');

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('retrieveTransaction')
                ->once()
                ->with(123456)
                ->andReturn($this->makeTransactionStub('pending'));
        });

        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$this->mission->uuid}/payment-status");

        $response->assertOk()
            ->assertJsonPath('data.has_payment', true)
            ->assertJsonPath('data.status', MissionPaymentStatus::Pending->value)
            ->assertJsonPath('data.is_trackable', true)
            ->assertJsonPath('data.mission_status', MissionStatus::PendingPayment->value);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function terminalFedapayStatuses(): array
    {
        return [
            'canceled' => ['canceled'],
            'declined' => ['declined'],
            'expired' => ['expired'],
            'refunded' => ['refunded'],
        ];
    }

    /**
     * Annulation côté FedaPay : le paiement cash resterait Pending avec son id de transaction,
     * « traçable » pour toujours (poll sans fin, jamais de nouvelle sélection possible). Le
     * self-heal serveur (lookup FedaPay, jamais l'indice navigateur) libère la transaction morte.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('terminalFedapayStatuses')]
    public function test_payment_status_releases_a_dead_transaction_so_the_selection_can_be_retried(string $remoteStatus): void
    {
        $payment = $this->createPendingMissionPayment(fedapayTransactionId: '123456');

        $this->mock(FedapayService::class, function ($mock) use ($remoteStatus): void {
            $mock->shouldReceive('retrieveTransaction')
                ->once()
                ->with(123456)
                ->andReturn($this->makeTransactionStub($remoteStatus));
        });

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$this->mission->uuid}/payment-status")
            ->assertOk()
            ->assertJsonPath('data.status', MissionPaymentStatus::Pending->value)
            ->assertJsonPath('data.is_trackable', false)
            ->assertJsonPath('data.mission_status', MissionStatus::PendingPayment->value);

        $this->assertNull($payment->fresh()->fedapay_transaction_id);
        $this->assertSame(MissionPaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_releasing_a_dead_transaction_is_idempotent(): void
    {
        $payment = $this->createPendingMissionPayment(fedapayTransactionId: '123456');
        $service = app(\App\Services\MissionPaymentService::class);

        $first = $service->releaseTerminalTransaction($payment, 123456);
        $second = $service->releaseTerminalTransaction($first, 123456);

        $this->assertNull($second->fedapay_transaction_id);
        $this->assertSame(MissionPaymentStatus::Pending, $second->status);
    }

    public function test_releasing_never_touches_a_payment_that_already_moved_to_another_transaction(): void
    {
        $payment = $this->createPendingMissionPayment(fedapayTransactionId: '777777');

        app(\App\Services\MissionPaymentService::class)->releaseTerminalTransaction($payment, 123456);

        $this->assertSame('777777', $payment->fresh()->fedapay_transaction_id);
    }

    public function test_a_paid_payment_is_never_released(): void
    {
        $payment = $this->createPendingMissionPayment(fedapayTransactionId: '123456');
        $payment->update(['status' => MissionPaymentStatus::Paid, 'paid_at' => now(), 'fedapay_ref' => 'ref']);

        app(\App\Services\MissionPaymentService::class)->releaseTerminalTransaction($payment, 123456);

        $this->assertSame('123456', $payment->fresh()->fedapay_transaction_id);
        $this->assertSame(MissionPaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_a_pending_remote_transaction_is_left_untouched(): void
    {
        $payment = $this->createPendingMissionPayment(fedapayTransactionId: '123456');

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('retrieveTransaction')->once()->andReturn($this->makeTransactionStub('pending'));
        });

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$this->mission->uuid}/payment-status")
            ->assertOk()
            ->assertJsonPath('data.is_trackable', true);

        $this->assertSame('123456', $payment->fresh()->fedapay_transaction_id);
    }

    public function test_payment_status_reports_is_trackable_false_once_payment_is_paid(): void
    {
        $payment = $this->createPendingMissionPayment(fedapayTransactionId: '123456');
        $payment->update([
            'status' => MissionPaymentStatus::Paid,
            'paid_at' => now(),
            'fedapay_ref' => 'fedapay-ref',
        ]);
        $this->mission->update(['status' => MissionStatus::Closed]);

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldNotReceive('retrieveTransaction');
        });

        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$this->mission->uuid}/payment-status");

        $response->assertOk()
            ->assertJsonPath('data.has_payment', true)
            ->assertJsonPath('data.status', MissionPaymentStatus::Paid->value)
            ->assertJsonPath('data.is_trackable', false)
            ->assertJsonPath('data.mission_status', MissionStatus::Closed->value);
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

    private function createPendingMissionPayment(?string $fedapayTransactionId): MissionPayment
    {
        $selectedCandidatures = [$this->firstCandidature, $this->secondCandidature];
        $pricing = new MissionPricing($this->mission->budget, count($selectedCandidatures));
        $entitlements = app(FaceEntitlementService::class);

        $commissionFacesTotal = 0;
        $montantTotalFaces = 0;
        $entries = [];

        foreach ($selectedCandidatures as $candidature) {
            $rate = $entitlements->capabilities($candidature->face)->commissionRate;
            $montantFaceRecoit = $pricing->budgetParFace - (int) round($pricing->budgetParFace * $rate);
            $commissionFacesTotal += $pricing->budgetParFace - $montantFaceRecoit;
            $montantTotalFaces += $montantFaceRecoit;
            $entries[] = ['candidature' => $candidature, 'montant_face_recoit' => $montantFaceRecoit];
        }

        $payment = MissionPayment::query()->create([
            'mission_id' => $this->mission->id,
            'producer_id' => $this->producer->id,
            'nombre_faces_retenues' => count($selectedCandidatures),
            'budget_par_face' => $pricing->budgetParFace,
            'montant_sous_total' => $pricing->sousTotal,
            'commission_producteur' => $pricing->commissionProducteur,
            'montant_total_producteur' => $pricing->montantTotalProducteur,
            'commission_faces_total' => $commissionFacesTotal,
            'montant_total_faces' => $montantTotalFaces,
            'fedapay_transaction_id' => $fedapayTransactionId,
            'status' => MissionPaymentStatus::Pending,
        ]);

        foreach ($entries as $entry) {
            MissionPaymentCandidature::query()->create([
                'mission_payment_id' => $payment->id,
                'candidature_id' => $entry['candidature']->id,
                'face_id' => $entry['candidature']->face_id,
                'montant_face_recoit' => $entry['montant_face_recoit'],
                'escrow_status' => EscrowStatus::Pending,
            ]);

            $entry['candidature']->update(['status' => CandidatureStatus::Accepted]);
        }

        $this->mission->update(['status' => MissionStatus::PendingPayment]);

        return $payment->fresh();
    }

    private function makeTransactionStub(string $status): \FedaPay\Transaction
    {
        /** @var \FedaPay\Transaction $transaction */
        $transaction = \Mockery::mock(\FedaPay\Transaction::class);
        $transaction->status = $status;

        return $transaction;
    }
}
