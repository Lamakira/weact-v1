<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc;

use App\Enums\CandidatureStatus;
use App\Enums\CompensationType;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Mission;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use App\Models\User;
use App\Services\MissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ugc-9-1 (D-9.1.i) — suppression d'une mission UGC hybride dénoue l'escrow AVANT le
 * hard-delete (cascade FK) : un escrow `Locked` est remboursé au Producteur, une entry
 * `Pending` in-flight est markFailed (supprimée), le tout avant que la cascade n'efface
 * candidatures + entries. 0 argent orphelin.
 *
 * Élite (5 %) : net escrow 14250.
 */
class MissionDeleteHybridEscrowUnwindTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_mission_refunds_locked_hybrid_escrows(): void
    {
        [$producer, $producerUser] = $this->makeProducerWithUser();
        [$face] = $this->makeSubscribedFace('elite');
        $mission = $this->makePublishedHybridMission($producer, ['nombre_faces_voulu' => 1]);
        $candidature = $this->makePendingCandidature($mission, $face);
        $candidature->update(['status' => CandidatureStatus::Accepted, 'accepted_at' => now()]);
        $this->lockHybridEscrow($candidature);

        $before = (int) $producerUser->fresh()->balance;

        app(MissionService::class)->deleteMission($mission);

        // Producteur remboursé du net AVANT la cascade ; trace financière conservée.
        $this->assertSame($before + 14250, (int) $producerUser->fresh()->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $producerUser->id,
            'type' => 'credit',
            'amount' => 14250,
        ]);
        $this->assertDatabaseHas('financial_events', ['type' => 'refund', 'amount' => 14250]);

        // Mission + candidature + entry escrow effacées par le hard-delete (0 entry orpheline).
        $this->assertDatabaseMissing('missions', ['id' => $mission->id]);
        $this->assertDatabaseMissing('candidatures', ['id' => $candidature->id]);
        $this->assertSame(0, MissionPaymentCandidature::count());
    }

    public function test_deleting_mission_markfails_inflight_pending_entries(): void
    {
        [$producer, $producerUser] = $this->makeProducerWithUser();
        [$face] = $this->makeSubscribedFace('elite');
        $mission = $this->makePublishedHybridMission($producer, ['nombre_faces_voulu' => 1]);
        // Candidature Pending portant une entry escrow Pending in-flight (paiement initié).
        $candidature = $this->makePendingCandidature($mission, $face);
        $this->pendingHybridEscrow($candidature);

        $before = (int) $producerUser->fresh()->balance;

        app(MissionService::class)->deleteMission($mission);

        // Entry in-flight supprimée + cascade ; AUCUN mouvement d'argent (Pending → markFailed).
        $this->assertSame(0, MissionPaymentCandidature::count());
        $this->assertDatabaseMissing('missions', ['id' => $mission->id]);
        $this->assertDatabaseMissing('candidatures', ['id' => $candidature->id]);
        $this->assertSame($before, (int) $producerUser->fresh()->balance);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseMissing('financial_events', ['type' => 'refund']);

        // ugc-9-1 (code-review) : à la suppression de mission, le Producteur initie l'action
        // → PAS de notif « paiement échoué — réessayez de l'accepter » (mission disparue).
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $producerUser->id,
            'type' => 'mission_candidature_payment_failed',
        ]);
    }

    // ===================================================================
    // Fixtures (calquées UgcMissionHybridSettlementTest:546-619)
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

    /** Entry hybride LOCKÉE (Élite : net 14250). */
    private function lockHybridEscrow(Candidature $candidature, int $net = 14250): MissionPaymentCandidature
    {
        return MissionPaymentCandidature::create([
            'mission_payment_id' => null,
            'candidature_id' => $candidature->id,
            'face_id' => $candidature->face_id,
            'montant_face_recoit' => $net,
            'escrow_status' => \App\Enums\EscrowStatus::Locked,
            'locked_at' => now(),
            'fedapay_transaction_id' => '99'.$candidature->id,
        ]);
    }

    /** Entry hybride in-flight (Pending) — paiement initié, pas encore confirmé. */
    private function pendingHybridEscrow(Candidature $candidature, int $net = 14250): MissionPaymentCandidature
    {
        return MissionPaymentCandidature::create([
            'mission_payment_id' => null,
            'candidature_id' => $candidature->id,
            'face_id' => $candidature->face_id,
            'montant_face_recoit' => $net,
            'escrow_status' => \App\Enums\EscrowStatus::Pending,
            'fedapay_transaction_id' => '70'.$candidature->id,
        ]);
    }
}
