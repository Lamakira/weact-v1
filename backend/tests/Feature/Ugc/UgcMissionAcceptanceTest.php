<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UGC 2.4 — POST /api/v1/face/missions/{mission}/accept : acceptation
 * DIRECTE d'une mission UGC par la Face (D-2.4.a). La candidature atterrit
 * `confirmed` (créée ou transitionnée depuis pending), une Conversation est
 * provisionnée, la capacité est garantie sous lock et la mission se clôt à
 * capacité atteinte (D-2.4.d).
 */
class UgcMissionAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    private Face $face;

    private User $producerUser;

    private Producer $producer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->face = Face::factory()->create(['sexe' => 'femme']);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
    }

    private function subscribeFace(Face $face): void
    {
        FaceSubscription::factory()->starter()->active()->create(['face_id' => $face->id]);
    }

    /**
     * La factory Mission ne tire jamais `ugc` — attributs explicites obligatoires.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function makePublishedUgcMission(array $overrides = []): Mission
    {
        return $this->producer->missions()->create(array_merge([
            'titre' => 'Appel UGC — Unboxing sneakers',
            'description' => 'Brief détaillé',
            'date_tournage' => now()->addMonth(),
            'profil_recherche' => 'Créatrices lifestyle',
            'budget' => 0,
            'date_limite_candidature' => now()->addWeeks(2),
            'nombre_faces_voulu' => 2,
            'type_mission' => 'ugc',
            'genre_voulu' => 'tous',
            'lieu' => 'Cotonou',
            'duree' => 'Livrables vidéo',
            'status' => MissionStatus::Published,
            'commission_paid_at' => now(),
            'type_compensation' => 'product',
            'nom_produit' => 'Sneakers Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'montant_remuneration' => null,
            'commission_ugc' => 2500,
        ], $overrides));
    }

    /**
     * @return array{0: Face, 1: User}
     */
    private function makeSubscribedFace(): array
    {
        $face = Face::factory()->create(['sexe' => 'femme']);
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $this->subscribeFace($face);

        return [$face, $user];
    }

    // ===================================================================
    // Happy paths (AC4)
    // ===================================================================

    public function test_eligible_face_accepts_product_ugc_mission(): void
    {
        $this->subscribeFace($this->face);
        $mission = $this->makePublishedUgcMission();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', CandidatureStatus::Confirmed->value)
            ->assertJsonPath('message', 'Mission acceptée — votre engagement est enregistré');

        $candidature = Candidature::where('face_id', $this->face->id)
            ->where('mission_id', $mission->id)
            ->first();

        $this->assertNotNull($candidature);
        $this->assertSame(CandidatureStatus::Confirmed, $candidature->status);
        $this->assertDatabaseHas('conversations', ['candidature_id' => $candidature->id]);
    }

    public function test_eligible_face_accepts_hybrid_ugc_mission(): void
    {
        $this->subscribeFace($this->face);
        $mission = $this->makePublishedUgcMission([
            'type_compensation' => 'hybrid',
            'montant_remuneration' => 15000,
        ]);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', CandidatureStatus::Confirmed->value);
    }

    public function test_pending_candidature_transitions_to_confirmed_without_duplicate(): void
    {
        $this->subscribeFace($this->face);
        $mission = $this->makePublishedUgcMission();

        $existing = Candidature::create([
            'face_id' => $this->face->id,
            'mission_id' => $mission->id,
        ]);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/accept")
            ->assertOk();

        $this->assertSame(1, Candidature::where('face_id', $this->face->id)
            ->where('mission_id', $mission->id)
            ->count());
        $this->assertSame(CandidatureStatus::Confirmed, $existing->fresh()->status);
    }

    public function test_acceptance_notifies_producer(): void
    {
        $this->subscribeFace($this->face);
        $mission = $this->makePublishedUgcMission();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/accept")
            ->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->producerUser->id,
            'type' => 'ugc_deal_accepted',
        ]);
    }

    // ===================================================================
    // Capacité & auto-clôture (AC5)
    // ===================================================================

    public function test_mission_closes_when_capacity_one_is_reached(): void
    {
        $this->subscribeFace($this->face);
        $mission = $this->makePublishedUgcMission(['nombre_faces_voulu' => 1]);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/accept")
            ->assertOk();

        $this->assertSame(MissionStatus::Closed, $mission->fresh()->status);
    }

    public function test_capacity_two_closes_on_second_accept_and_rejects_third(): void
    {
        $mission = $this->makePublishedUgcMission(['nombre_faces_voulu' => 2]);

        [, $u1] = $this->makeSubscribedFace();
        [, $u2] = $this->makeSubscribedFace();
        [$f3, $u3] = $this->makeSubscribedFace();

        // f3 postule AVANT la clôture — sa candidature pending lui garde l'accès.
        Candidature::create(['face_id' => $f3->id, 'mission_id' => $mission->id]);

        $this->actingAs($u1)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/accept")
            ->assertOk();
        $this->assertSame(MissionStatus::Published, $mission->fresh()->status);

        $this->actingAs($u2)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/accept")
            ->assertOk();
        $this->assertSame(MissionStatus::Closed, $mission->fresh()->status);

        // Mission close : la garde isAcceptingCandidatures répond MISSION_CLOSED.
        $this->actingAs($u3)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/accept")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'MISSION_CLOSED');
    }

    public function test_face_without_candidature_gets_404_on_closed_mission(): void
    {
        [, $u1] = $this->makeSubscribedFace();
        $mission = $this->makePublishedUgcMission(['nombre_faces_voulu' => 1]);

        $this->actingAs($u1)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/accept")
            ->assertOk();

        // Mission closed + pas de candidature → 404 (garde visibilité) AVANT la capacité.
        $this->subscribeFace($this->face);
        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/accept")
            ->assertNotFound();
    }

    public function test_full_mission_still_published_returns_mission_full(): void
    {
        // MISSION_FULL pur : capacité 1 + candidature confirmed pré-seedée
        // sans passer par l'endpoint (la mission reste published).
        [$f1] = $this->makeSubscribedFace();
        $mission = $this->makePublishedUgcMission(['nombre_faces_voulu' => 1]);

        Candidature::create([
            'face_id' => $f1->id,
            'mission_id' => $mission->id,
            'status' => CandidatureStatus::Confirmed,
        ]);

        $this->subscribeFace($this->face);
        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/accept")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'MISSION_FULL')
            ->assertJsonPath('error.message', 'Toutes les places de cette mission sont déjà pourvues.');
    }

    public function test_pending_and_cancelled_candidatures_do_not_consume_capacity(): void
    {
        [$f1] = $this->makeSubscribedFace();
        [$f2] = $this->makeSubscribedFace();
        $mission = $this->makePublishedUgcMission(['nombre_faces_voulu' => 1]);

        Candidature::create(['face_id' => $f1->id, 'mission_id' => $mission->id]);
        Candidature::create([
            'face_id' => $f2->id,
            'mission_id' => $mission->id,
            'status' => CandidatureStatus::Cancelled,
        ]);

        $this->subscribeFace($this->face);
        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', CandidatureStatus::Confirmed->value);

        $this->assertSame(MissionStatus::Closed, $mission->fresh()->status);
    }

    // ===================================================================
    // Gates : paywall, genre, fenêtre, type (AC4)
    // ===================================================================

    public function test_free_face_gets_paywall(): void
    {
        $mission = $this->makePublishedUgcMission();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/accept")
            ->assertForbidden()
            ->assertJsonPath('error.code', 'UGC_SUBSCRIPTION_REQUIRED');
    }

    public function test_free_face_with_pending_candidature_still_gets_paywall(): void
    {
        // Pas d'exception candidature sur l'accept : voir ≠ s'engager (D-2.4.c).
        $mission = $this->makePublishedUgcMission();
        Candidature::create(['face_id' => $this->face->id, 'mission_id' => $mission->id]);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/accept")
            ->assertForbidden()
            ->assertJsonPath('error.code', 'UGC_SUBSCRIPTION_REQUIRED');
    }

    public function test_gender_mismatch_is_rejected(): void
    {
        $this->subscribeFace($this->face);
        $mission = $this->makePublishedUgcMission(['genre_voulu' => 'homme']);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/accept")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'gender_mismatch');
    }

    public function test_past_deadline_returns_mission_closed(): void
    {
        $this->subscribeFace($this->face);
        $mission = $this->makePublishedUgcMission([
            'date_limite_candidature' => now()->subDay(),
        ]);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/accept")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'MISSION_CLOSED')
            ->assertJsonPath('error.message', "Cette mission n'accepte plus de candidatures");
    }

    public function test_standard_mission_returns_invalid_status(): void
    {
        $this->subscribeFace($this->face);
        $mission = $this->producer->missions()->create([
            'titre' => 'Shooting photo standard',
            'description' => 'desc',
            'date_tournage' => now()->addMonth(),
            'profil_recherche' => 'Modèles',
            'budget' => 100000,
            'date_limite_candidature' => now()->addWeeks(2),
            'nombre_faces_voulu' => 2,
            'type_mission' => 'shooting_photo',
            'genre_voulu' => 'tous',
            'lieu' => 'Cotonou',
            'duree' => '1 jour',
            'status' => MissionStatus::Published,
        ]);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/accept")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_STATUS')
            ->assertJsonPath('error.message', "L'acceptation directe est réservée aux missions UGC.");
    }

    public function test_unpublished_mission_without_candidature_returns_404(): void
    {
        $this->subscribeFace($this->face);
        $mission = $this->makePublishedUgcMission([
            'status' => MissionStatus::PendingPayment,
            'commission_paid_at' => null,
        ]);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/accept")
            ->assertNotFound();
    }

    // ===================================================================
    // États de candidature (AC4)
    // ===================================================================

    public function test_double_accept_returns_already_accepted(): void
    {
        $this->subscribeFace($this->face);
        $mission = $this->makePublishedUgcMission();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/accept")
            ->assertOk();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/accept")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'ALREADY_ACCEPTED')
            ->assertJsonPath('error.message', 'Vous avez déjà accepté cette mission.');
    }

    public function test_cancelled_candidature_returns_invalid_status(): void
    {
        $this->subscribeFace($this->face);
        $mission = $this->makePublishedUgcMission();

        Candidature::create([
            'face_id' => $this->face->id,
            'mission_id' => $mission->id,
            'status' => CandidatureStatus::Cancelled,
        ]);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/accept")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_STATUS')
            ->assertJsonPath('error.message', 'Votre candidature pour cette mission a été retirée ou refusée.');
    }

    // ===================================================================
    // Auth (AC4)
    // ===================================================================

    public function test_producer_cannot_accept_a_mission(): void
    {
        $mission = $this->makePublishedUgcMission();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/accept")
            ->assertForbidden();
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $mission = $this->makePublishedUgcMission();

        $this->postJson("/api/v1/face/missions/{$mission->uuid}/accept")
            ->assertUnauthorized();
    }
}
