<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc;

use App\Enums\CandidatureStatus;
use App\Enums\CompensationType;
use App\Enums\EscrowStatus;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Mission;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\FedapayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * ugc-8-5 — endpoint self-heal du paiement hybride par-Face (D-8.5.a→d).
 *
 * GET /api/v1/producer/candidatures/{candidature}/payment-status re-vérifie
 * activement FedaPay (résilience webhook, miroir de commission-status /
 * missions payment-status) et settle via les markUgc* de 8-4 : approved →
 * candidature accepted (escrow Locked, payment_status 'paid') ; declined →
 * entry supprimée (slot libéré, candidature reste pending, payment_status
 * 'failed') ; pending → is_trackable true. Garde ownership 403. Idempotent.
 */
class UgcMissionHybridCandidaturePaymentStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_status_settles_candidature_when_fedapay_approved(): void
    {
        Mail::fake();

        [$producer, $producerUser] = $this->makeProducerWithUser();
        [$face] = $this->makeSubscribedFace('elite');
        $mission = $this->makePublishedHybridMission($producer);
        $candidature = $this->makePendingCandidature($mission, $face);
        $this->pendingHybridEscrow($candidature, txn: '777001');

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('retrieveTransaction')
                ->once()
                ->with(777001)
                ->andReturn($this->makeTransactionStub('approved', 'ref_poll'));
        });

        $this->actingAs($producerUser)
            ->getJson("/api/v1/producer/candidatures/{$candidature->uuid}/payment-status")
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.candidature_status', 'accepted')
            ->assertJsonPath('data.is_trackable', false);

        // Settled : escrow séquestré (Locked) + candidature accepted (calque webhook 8-4).
        $this->assertSame(EscrowStatus::Locked, $candidature->fresh()->paymentEntry?->escrow_status);
        $this->assertSame(CandidatureStatus::Accepted, $candidature->fresh()->status);
    }

    public function test_payment_status_reports_failed_and_frees_slot_when_fedapay_declined(): void
    {
        [$producer, $producerUser] = $this->makeProducerWithUser();
        [$face] = $this->makeSubscribedFace('elite');
        $mission = $this->makePublishedHybridMission($producer);
        $candidature = $this->makePendingCandidature($mission, $face);
        $this->pendingHybridEscrow($candidature, txn: '777002');

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('retrieveTransaction')
                ->once()
                ->with(777002)
                ->andReturn($this->makeTransactionStub('declined', 'ref_ko'));
        });

        $this->actingAs($producerUser)
            ->getJson("/api/v1/producer/candidatures/{$candidature->uuid}/payment-status")
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'failed')
            ->assertJsonPath('data.candidature_status', 'pending')
            ->assertJsonPath('data.is_trackable', false);

        // Entry supprimée (slot in-flight libéré) ; candidature reste pending → réessai possible.
        $this->assertDatabaseMissing('mission_payment_candidatures', ['candidature_id' => $candidature->id]);
        $this->assertSame(CandidatureStatus::Pending, $candidature->fresh()->status);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $producerUser->id,
            'type' => 'mission_candidature_payment_failed',
        ]);
    }

    public function test_payment_status_reports_pending_trackable_while_in_flight(): void
    {
        [$producer, $producerUser] = $this->makeProducerWithUser();
        [$face] = $this->makeSubscribedFace('elite');
        $mission = $this->makePublishedHybridMission($producer);
        $candidature = $this->makePendingCandidature($mission, $face);
        $this->pendingHybridEscrow($candidature, txn: '777003');

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('retrieveTransaction')
                ->once()
                ->with(777003)
                ->andReturn($this->makeTransactionStub('pending', 'ref_wait'));
        });

        $this->actingAs($producerUser)
            ->getJson("/api/v1/producer/candidatures/{$candidature->uuid}/payment-status")
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.candidature_status', 'pending')
            ->assertJsonPath('data.is_trackable', true);

        // Toujours in-flight : entry Pending intacte, candidature pending.
        $this->assertSame(EscrowStatus::Pending, $candidature->fresh()->paymentEntry?->escrow_status);
        $this->assertSame(CandidatureStatus::Pending, $candidature->fresh()->status);
    }

    public function test_payment_status_is_idempotent_on_already_accepted(): void
    {
        [$producer, $producerUser] = $this->makeProducerWithUser();
        [$face] = $this->makeSubscribedFace('elite');
        $mission = $this->makePublishedHybridMission($producer);
        $candidature = $this->makePendingCandidature($mission, $face);
        $candidature->update(['status' => CandidatureStatus::Accepted]);
        $this->lockHybridEscrow($candidature);

        // Candidature déjà réglée → court-circuit : FedaPay n'est jamais rappelé.
        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldNotReceive('retrieveTransaction');
        });

        $this->actingAs($producerUser)
            ->getJson("/api/v1/producer/candidatures/{$candidature->uuid}/payment-status")
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.candidature_status', 'accepted')
            ->assertJsonPath('data.is_trackable', false);

        // Aucun double traitement : escrow reste Locked (pas re-Released), pas de crédit wallet.
        $this->assertSame(EscrowStatus::Locked, $candidature->fresh()->paymentEntry?->escrow_status);
        $this->assertSame(0, WalletTransaction::count());
    }

    public function test_payment_status_forbidden_for_non_owner_producer(): void
    {
        [$producer] = $this->makeProducerWithUser();
        [, $otherProducerUser] = $this->makeProducerWithUser();
        [$face] = $this->makeSubscribedFace('elite');
        $mission = $this->makePublishedHybridMission($producer);
        $candidature = $this->makePendingCandidature($mission, $face);
        $this->pendingHybridEscrow($candidature, txn: '777004');

        // Un autre Producteur ne doit jamais poller le paiement d'une candidature qui n'est pas la sienne.
        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldNotReceive('retrieveTransaction');
        });

        $this->actingAs($otherProducerUser)
            ->getJson("/api/v1/producer/candidatures/{$candidature->uuid}/payment-status")
            ->assertStatus(403);
    }

    // ===================================================================
    // Fixtures (calque UgcMissionHybridSettlementTest, 8-4)
    // ===================================================================

    /** @return array{0: Producer, 1: User} */
    private function makeProducerWithUser(): array
    {
        $producer = Producer::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        return [$producer, $user];
    }

    /** @return array{0: Face, 1: User} */
    private function makeSubscribedFace(string $tier = 'elite'): array
    {
        $face = Face::factory()->create(['sexe' => 'femme']);
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        FaceSubscription::factory()->{$tier}()->active()->create(['face_id' => $face->id]);

        return [$face, $user];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makePublishedHybridMission(Producer $producer, array $overrides = []): Mission
    {
        return $producer->missions()->create(array_merge([
            'titre' => 'Appel UGC hybride',
            'description' => 'Brief',
            'date_tournage' => null,
            'lieu' => null,
            'duree' => null,
            'profil_recherche' => 'Créatrices',
            'budget' => 15000,
            'date_limite_candidature' => now()->addWeeks(2),
            'nombre_faces_voulu' => 1,
            'type_mission' => MissionType::Ugc->value,
            'genre_voulu' => 'tous',
            'status' => MissionStatus::Published,
            'commission_paid_at' => null,
            'type_compensation' => CompensationType::Hybrid->value,
            'nom_produit' => 'Sneakers',
            'valeur_produit' => 50000,
            'nombre_videos' => 3,
            'montant_remuneration' => 15000,
            'commission_ugc' => null,
        ], $overrides));
    }

    private function makePendingCandidature(Mission $mission, Face $face): Candidature
    {
        return Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $face->id,
            'status' => CandidatureStatus::Pending,
        ]);
    }

    /** Entry hybride in-flight (Pending) — paiement initié, pas encore confirmé. */
    private function pendingHybridEscrow(Candidature $candidature, int $net = 14250, ?string $txn = null): MissionPaymentCandidature
    {
        return MissionPaymentCandidature::create([
            'mission_payment_id' => null, // PARENTLESS (D-8.4.a)
            'candidature_id' => $candidature->id,
            'face_id' => $candidature->face_id,
            'montant_face_recoit' => $net,
            'escrow_status' => EscrowStatus::Pending,
            'fedapay_transaction_id' => $txn ?? ('70'.$candidature->id),
        ]);
    }

    /** Entry hybride LOCKÉE (Élite : net 14250). */
    private function lockHybridEscrow(Candidature $candidature, int $net = 14250): MissionPaymentCandidature
    {
        return MissionPaymentCandidature::create([
            'mission_payment_id' => null,
            'candidature_id' => $candidature->id,
            'face_id' => $candidature->face_id,
            'montant_face_recoit' => $net,
            'escrow_status' => EscrowStatus::Locked,
            'locked_at' => now(),
            'fedapay_transaction_id' => '99'.$candidature->id,
        ]);
    }

    private function makeTransactionStub(string $status, string $reference): \FedaPay\Transaction
    {
        /** @var \FedaPay\Transaction $transaction */
        $transaction = \Mockery::mock(\FedaPay\Transaction::class);
        $transaction->status = $status;
        $transaction->reference = $reference;

        return $transaction;
    }
}
