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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ugc-9-1 (D-9.1.h) — endpoint release Producteur
 * `POST /api/v1/producer/candidatures/{candidature}/release`.
 *
 * Le Producteur libère manuellement une candidature acceptée jamais reconfirmée :
 * refund de l'escrow hybride (net) + candidature Cancelled + slot libéré + réouverture.
 * Gardes : ownership (403 non-propriétaire), statut (400 INVALID_STATUS si non-accepted).
 *
 * Élite (5 %) : net escrow 14250.
 */
class ProducerReleaseCandidatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_producer_release_refunds_and_frees_slot(): void
    {
        [$producer, $producerUser] = $this->makeProducerWithUser();
        [$face] = $this->makeSubscribedFace('elite');
        $mission = $this->makePublishedHybridMission($producer, ['nombre_faces_voulu' => 1]);
        $candidature = $this->makePendingCandidature($mission, $face);
        $candidature->update(['status' => CandidatureStatus::Accepted, 'accepted_at' => now()]);
        $this->lockHybridEscrow($candidature);
        $mission->update(['status' => MissionStatus::Closed]); // capacité atteinte (1/1)

        $before = (int) $producerUser->fresh()->balance;

        $this->actingAs($producerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/release")
            ->assertOk()
            ->assertJsonPath('data.candidature_status', 'cancelled');

        // Escrow Refunded + Producteur crédité du net + candidature Cancelled + mission réouverte.
        $this->assertSame(EscrowStatus::Refunded, $candidature->fresh()->paymentEntry?->escrow_status);
        $this->assertSame($before + 14250, (int) $producerUser->fresh()->balance);
        $this->assertSame(CandidatureStatus::Cancelled, $candidature->fresh()->status);
        $this->assertSame(MissionStatus::Published, $mission->fresh()->status);
        $this->assertDatabaseHas('financial_events', ['type' => 'refund', 'amount' => 14250]);
    }

    public function test_release_forbidden_for_non_owner(): void
    {
        [$producer] = $this->makeProducerWithUser();
        [, $otherProducerUser] = $this->makeProducerWithUser(); // un autre Producteur
        [$face] = $this->makeSubscribedFace('elite');
        $mission = $this->makePublishedHybridMission($producer, ['nombre_faces_voulu' => 1]);
        $candidature = $this->makePendingCandidature($mission, $face);
        $candidature->update(['status' => CandidatureStatus::Accepted, 'accepted_at' => now()]);
        $this->lockHybridEscrow($candidature);

        $this->actingAs($otherProducerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/release")
            ->assertStatus(403);

        // Escrow et candidature intacts.
        $this->assertSame(EscrowStatus::Locked, $candidature->fresh()->paymentEntry?->escrow_status);
        $this->assertSame(CandidatureStatus::Accepted, $candidature->fresh()->status);
    }

    public function test_release_rejects_non_accepted_candidature(): void
    {
        [$producer, $producerUser] = $this->makeProducerWithUser();
        [$face] = $this->makeSubscribedFace('elite');
        $mission = $this->makePublishedHybridMission($producer, ['nombre_faces_voulu' => 1]);
        $candidature = $this->makePendingCandidature($mission, $face);
        // Confirmed (en tunnel) avec escrow Locked : non libérable par release.
        $candidature->update(['status' => CandidatureStatus::Confirmed]);
        $this->lockHybridEscrow($candidature);

        $before = (int) $producerUser->fresh()->balance;

        $this->actingAs($producerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/release")
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        // Escrow intact, candidature inchangée, aucun crédit.
        $this->assertSame(EscrowStatus::Locked, $candidature->fresh()->paymentEntry?->escrow_status);
        $this->assertSame(CandidatureStatus::Confirmed, $candidature->fresh()->status);
        $this->assertSame($before, (int) $producerUser->fresh()->balance);
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
            'escrow_status' => EscrowStatus::Locked,
            'locked_at' => now(),
            'fedapay_transaction_id' => '99'.$candidature->id,
        ]);
    }
}
