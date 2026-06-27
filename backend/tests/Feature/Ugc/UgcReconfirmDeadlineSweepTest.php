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
use App\Services\MissionPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ugc-9-1 (D-9.1.g) — sweep `ugc:expire-unreconfirmed-candidatures`.
 *
 * Une candidature mission UGC `accepted` jamais reconfirmée après 48h (ancrage
 * `accepted_at`) est dénouée : refund de l'escrow hybride au Producteur (net),
 * candidature → Cancelled, slot libéré + réouverture `Closed → Published`. Couvre
 * l'hybride (refund) ET le produit-seul (libère le slot, 0 mouvement d'argent),
 * la borne 48h, l'idempotence et la garde deadline-dépassée de la réouverture.
 *
 * Élite (5 %) : cash 15000 → net escrow 14250.
 */
class UgcReconfirmDeadlineSweepTest extends TestCase
{
    use RefreshDatabase;

    public function test_sweep_refunds_and_reopens_hybrid_candidature_past_48h(): void
    {
        [$producer, $producerUser] = $this->makeProducerWithUser();
        [$face, $faceUser] = $this->makeSubscribedFace('elite');
        $mission = $this->makePublishedHybridMission($producer, ['nombre_faces_voulu' => 1]);
        $candidature = $this->makeAcceptedCandidaturePast48h($mission, $face);
        $this->lockHybridEscrow($candidature);
        $mission->update(['status' => MissionStatus::Closed]); // capacité atteinte (1/1)

        $before = (int) $producerUser->fresh()->balance;

        $this->artisan('ugc:expire-unreconfirmed-candidatures')->assertExitCode(0);

        // Escrow Refunded + Producteur crédité du net + candidature Cancelled + mission réouverte.
        $this->assertSame(EscrowStatus::Refunded, $candidature->fresh()->paymentEntry?->escrow_status);
        $this->assertSame($before + 14250, (int) $producerUser->fresh()->balance);
        $this->assertSame(CandidatureStatus::Cancelled, $candidature->fresh()->status);
        $this->assertSame(MissionStatus::Published, $mission->fresh()->status);
        $this->assertDatabaseHas('financial_events', ['type' => 'refund', 'amount' => 14250]);

        // Notifs Face (place libérée) + Producteur (remboursement).
        $this->assertDatabaseHas('notifications', [
            'user_id' => $faceUser->id,
            'type' => 'mission_candidature_slot_released',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $producerUser->id,
            'type' => 'mission_candidature_refunded',
        ]);
    }

    public function test_sweep_ignores_candidature_within_48h(): void
    {
        [$producer, $producerUser] = $this->makeProducerWithUser();
        [$face] = $this->makeSubscribedFace('elite');
        $mission = $this->makePublishedHybridMission($producer, ['nombre_faces_voulu' => 1]);
        $candidature = $this->makePendingCandidature($mission, $face);
        // accepted_at il y a 47h (< 48h) — borné par accepted_at.
        $candidature->update(['status' => CandidatureStatus::Accepted, 'accepted_at' => now()->subHours(47)]);
        $this->lockHybridEscrow($candidature);
        $mission->update(['status' => MissionStatus::Closed]);

        $before = (int) $producerUser->fresh()->balance;

        $this->artisan('ugc:expire-unreconfirmed-candidatures')->assertExitCode(0);

        // Intact : escrow Locked, candidature Accepted, mission Closed, aucun crédit.
        $this->assertSame(EscrowStatus::Locked, $candidature->fresh()->paymentEntry?->escrow_status);
        $this->assertSame(CandidatureStatus::Accepted, $candidature->fresh()->status);
        $this->assertSame(MissionStatus::Closed, $mission->fresh()->status);
        $this->assertSame($before, (int) $producerUser->fresh()->balance);
    }

    public function test_sweep_frees_product_only_slot_without_money(): void
    {
        [$producer, $producerUser] = $this->makeProducerWithUser();
        [$face, $faceUser] = $this->makeSubscribedFace('elite');
        // Mission UGC produit-seul : pas d'escrow par-Candidature.
        $mission = $this->makePublishedHybridMission($producer, [
            'nombre_faces_voulu' => 1,
            'type_compensation' => CompensationType::Product->value,
        ]);
        $candidature = $this->makeAcceptedCandidaturePast48h($mission, $face);
        // PAS d'entry escrow (produit-seul).
        $mission->update(['status' => MissionStatus::Closed]);

        $this->artisan('ugc:expire-unreconfirmed-candidatures')->assertExitCode(0);

        // Slot libéré + mission réouverte ; AUCUN mouvement d'argent.
        $this->assertSame(CandidatureStatus::Cancelled, $candidature->fresh()->status);
        $this->assertSame(MissionStatus::Published, $mission->fresh()->status);
        $this->assertSame(0, MissionPaymentCandidature::count());
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseMissing('financial_events', ['type' => 'refund']);

        // Notif Face (place libérée) ; PAS de notif refund Producteur (rien remboursé).
        $this->assertDatabaseHas('notifications', [
            'user_id' => $faceUser->id,
            'type' => 'mission_candidature_slot_released',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $producerUser->id,
            'type' => 'mission_candidature_refunded',
        ]);
    }

    public function test_sweep_is_idempotent_and_noop_on_cancelled(): void
    {
        [$producer, $producerUser] = $this->makeProducerWithUser();
        [$face] = $this->makeSubscribedFace('elite');
        $mission = $this->makePublishedHybridMission($producer, ['nombre_faces_voulu' => 1]);
        $candidature = $this->makeAcceptedCandidaturePast48h($mission, $face);
        $this->lockHybridEscrow($candidature);
        $mission->update(['status' => MissionStatus::Closed]);

        $before = (int) $producerUser->fresh()->balance;

        // 1er run → refund. 2e run → la candidature est Cancelled, plus sélectionnée.
        $this->artisan('ugc:expire-unreconfirmed-candidatures')->assertExitCode(0);
        $this->artisan('ugc:expire-unreconfirmed-candidatures')->assertExitCode(0);

        // Appel direct sur une candidature déjà terminale → garde idempotente → false, 0 crédit.
        $this->assertFalse(
            app(MissionPaymentService::class)->unwindUgcCandidatureSlot($candidature->fresh(), 'reconfirm_window_expired')
        );

        // Un seul crédit Producteur au total.
        $this->assertSame($before + 14250, (int) $producerUser->fresh()->balance);
        $this->assertSame(1, WalletTransaction::where('user_id', $producerUser->id)->count());
    }

    public function test_sweep_does_not_reopen_when_deadline_passed(): void
    {
        [$producer, $producerUser] = $this->makeProducerWithUser();
        [$face] = $this->makeSubscribedFace('elite');
        // Deadline de candidature déjà dépassée.
        $mission = $this->makePublishedHybridMission($producer, [
            'nombre_faces_voulu' => 1,
            'date_limite_candidature' => now()->subDay(),
        ]);
        $candidature = $this->makeAcceptedCandidaturePast48h($mission, $face);
        $this->lockHybridEscrow($candidature);
        $mission->update(['status' => MissionStatus::Closed]);

        $before = (int) $producerUser->fresh()->balance;

        $this->artisan('ugc:expire-unreconfirmed-candidatures')->assertExitCode(0);

        // L'escrow est tout de même dénoué (refund + Cancelled)...
        $this->assertSame(EscrowStatus::Refunded, $candidature->fresh()->paymentEntry?->escrow_status);
        $this->assertSame($before + 14250, (int) $producerUser->fresh()->balance);
        $this->assertSame(CandidatureStatus::Cancelled, $candidature->fresh()->status);
        // ...mais la mission reste Closed (deadline dépassée, garde conservatrice).
        $this->assertSame(MissionStatus::Closed, $mission->fresh()->status);
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

    /** Candidature acceptée dont l'ancrage `accepted_at` dépasse la fenêtre 48h. */
    private function makeAcceptedCandidaturePast48h(Mission $mission, Face $face): Candidature
    {
        $candidature = $this->makePendingCandidature($mission, $face);
        $candidature->update(['status' => CandidatureStatus::Accepted, 'accepted_at' => now()->subHours(49)]);

        return $candidature;
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
