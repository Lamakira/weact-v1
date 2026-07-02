<?php

declare(strict_types=1);

namespace Tests\Feature\Candidature;

use App\Enums\CandidatureStatus;
use App\Enums\CompensationType;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Mail\UgcDealAcceptedMail;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * ugc-8-2 — la Face reconfirme (2ᵉ oui) sa participation après l'acceptation
 * Producteur : accepted→confirmed (D-8.2.d), chemin SÉPARÉ du confirm cash (qui
 * exige un MissionPayment Paid). La reconfirmation arme le tunnel en dispatchant
 * l'event REPURPOSÉ UgcMissionDealAccepted (D-8.2.e) ⇒ le Producteur reçoit la
 * notif in-app + l'email « préparez l'expédition ».
 */
class FaceReconfirmUgcCandidatureTest extends TestCase
{
    use RefreshDatabase;

    // ===================================================================
    // Happy path (AC7)
    // ===================================================================

    public function test_face_reconfirms_accepted_candidature_and_arms_tunnel(): void
    {
        Mail::fake();

        [$producer, $producerUser] = $this->makeProducerWithUser();
        [$face, $faceUser] = $this->makeFaceWithUser();
        $mission = $this->makePublishedUgcMission($producer);
        $candidature = $this->makeAcceptedCandidature($mission, $face);

        $response = $this->actingAs($faceUser)
            ->postJson("/api/v1/face/candidatures/{$candidature->uuid}/reconfirm");

        $response->assertOk()->assertJsonPath('data.status', 'confirmed');

        // accepted → confirmed (tunnel-ready : UgcShipmentService exige Confirmed).
        $this->assertDatabaseHas('candidatures', [
            'id' => $candidature->id,
            'status' => 'confirmed',
        ]);

        // Event REPURPOSÉ → le Producteur est notifié d'expédier (in-app + email).
        $this->assertDatabaseHas('notifications', [
            'user_id' => $producerUser->id,
            'type' => 'ugc_deal_accepted',
        ]);
        Mail::assertQueued(UgcDealAcceptedMail::class);
    }

    // ===================================================================
    // Gardes (AC7)
    // ===================================================================

    public function test_reconfirm_on_non_accepted_candidature_is_rejected(): void
    {
        [$producer] = $this->makeProducerWithUser();
        [$face, $faceUser] = $this->makeFaceWithUser();
        $mission = $this->makePublishedUgcMission($producer);
        // pending (pas encore acceptée par le Producteur) → reconfirm impossible.
        $candidature = $this->makeCandidature($mission, $face, CandidatureStatus::Pending);

        $this->actingAs($faceUser)
            ->postJson("/api/v1/face/candidatures/{$candidature->uuid}/reconfirm")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    public function test_reconfirm_on_non_ugc_mission_is_rejected(): void
    {
        [$producer] = $this->makeProducerWithUser();
        [$face, $faceUser] = $this->makeFaceWithUser();
        // Mission STANDARD : la reconfirmation directe est réservée à l'UGC
        // (le standard passe par confirm). Garde UGC AVANT la garde de statut.
        $mission = Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Published,
        ]);
        $candidature = $this->makeAcceptedCandidature($mission, $face);

        $this->actingAs($faceUser)
            ->postJson("/api/v1/face/candidatures/{$candidature->uuid}/reconfirm")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    public function test_other_face_cannot_reconfirm_candidature(): void
    {
        [$producer] = $this->makeProducerWithUser();
        [$face] = $this->makeFaceWithUser();
        [, $otherFaceUser] = $this->makeFaceWithUser();
        $mission = $this->makePublishedUgcMission($producer);
        $candidature = $this->makeAcceptedCandidature($mission, $face);

        $this->actingAs($otherFaceUser)
            ->postJson("/api/v1/face/candidatures/{$candidature->uuid}/reconfirm")
            ->assertForbidden();
    }

    public function test_non_face_cannot_reconfirm_candidature(): void
    {
        [$producer, $producerUser] = $this->makeProducerWithUser();
        [$face] = $this->makeFaceWithUser();
        $mission = $this->makePublishedUgcMission($producer);
        $candidature = $this->makeAcceptedCandidature($mission, $face);

        $this->actingAs($producerUser)
            ->postJson("/api/v1/face/candidatures/{$candidature->uuid}/reconfirm")
            ->assertForbidden();
    }

    // ===================================================================
    // Blast-radius confirm cash (revue 2026-06-26) — anti faux-canary
    // ===================================================================

    public function test_ugc_accepted_candidature_routed_to_cash_confirm_is_rejected_without_canary(): void
    {
        // ugc-8-2 (revue) : `Accepted` devient atteignable en UGC. Une candidature UGC
        // postée par erreur sur le endpoint cash /confirm doit être rejetée proprement
        // (→ reconfirm) SANS déclencher le canary ops INVARIANT_VIOLATION (faux positif :
        // l'UGC produit-seul n'a jamais de MissionPayment).
        Log::spy();

        [$producer] = $this->makeProducerWithUser();
        [$face, $faceUser] = $this->makeFaceWithUser();
        $mission = $this->makePublishedUgcMission($producer);
        $candidature = $this->makeAcceptedCandidature($mission, $face);

        $this->actingAs($faceUser)
            ->postJson("/api/v1/face/candidatures/{$candidature->uuid}/confirm")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        // Inchangée : le confirm cash n'a pas touché la candidature UGC.
        $this->assertDatabaseHas('candidatures', [
            'id' => $candidature->id,
            'status' => 'accepted',
        ]);

        // Le canary cash ne doit PAS être émis pour une candidature UGC mal-routée.
        Log::shouldNotHaveReceived('warning', [
            \Mockery::on(fn ($message): bool => is_string($message) && str_contains($message, 'INVARIANT_VIOLATION')),
            \Mockery::any(),
        ]);
    }

    // ===================================================================
    // Fixtures (S8) — User userable construit à la main (factories ne le font pas).
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
    private function makeFaceWithUser(): array
    {
        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        return [$face, $user];
    }

    private function makePublishedUgcMission(Producer $producer): Mission
    {
        return $producer->missions()->create([
            'titre' => 'Appel UGC — Unboxing',
            'description' => 'Brief',
            'date_tournage' => null,
            'lieu' => null,
            'duree' => null,
            'profil_recherche' => 'Créatrices lifestyle',
            'budget' => 0,
            'date_limite_candidature' => now()->addWeeks(2),
            'nombre_faces_voulu' => 3,
            'type_mission' => MissionType::Ugc->value,
            'genre_voulu' => 'tous',
            'status' => MissionStatus::Published,
            'commission_paid_at' => now(),
            'type_compensation' => CompensationType::Product->value,
            'nom_produit' => 'Sneakers',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'montant_remuneration' => null,
            'commission_ugc' => 2500,
        ]);
    }

    private function makeAcceptedCandidature(Mission $mission, Face $face): Candidature
    {
        return $this->makeCandidature($mission, $face, CandidatureStatus::Accepted);
    }

    private function makeCandidature(Mission $mission, Face $face, CandidatureStatus $status): Candidature
    {
        return Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $face->id,
            'status' => $status,
        ]);
    }
}
