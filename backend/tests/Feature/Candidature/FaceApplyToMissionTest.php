<?php

declare(strict_types=1);

namespace Tests\Feature\Candidature;

use App\Enums\CandidatureStatus;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaceApplyToMissionTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    private Face $face;

    private Mission $publishedMission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->face = Face::factory()->create(['sexe' => 'homme']);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);

        $this->publishedMission = Mission::factory()->published()->forAll()->create([
            'date_limite_candidature' => now()->addDays(7),
        ]);
        // is_active (3.0) : la garde apply exige un producteur avec User actif
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->publishedMission->producer_id,
        ]);
    }

    public function test_face_can_apply_to_published_mission_with_motivation(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$this->publishedMission->uuid}/apply", [
                'message_motivation' => 'Je suis très motivé pour cette mission!',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'mission_id',
                    'face_id',
                    'status',
                    'status_label',
                    'message_motivation',
                    'created_at',
                    'updated_at',
                ],
                'message',
            ])
            ->assertJson([
                'data' => [
                    'mission_id' => $this->publishedMission->id,
                    'face_id' => $this->face->id,
                    'status' => 'pending',
                    'status_label' => 'En attente',
                    'message_motivation' => 'Je suis très motivé pour cette mission!',
                ],
                'message' => 'Candidature envoyée avec succès',
            ]);

        $this->assertDatabaseHas('candidatures', [
            'face_id' => $this->face->id,
            'mission_id' => $this->publishedMission->id,
            'message_motivation' => 'Je suis très motivé pour cette mission!',
            'status' => 'pending',
        ]);
    }

    public function test_face_can_apply_to_published_mission_without_motivation(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$this->publishedMission->uuid}/apply");

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'mission_id' => $this->publishedMission->id,
                    'face_id' => $this->face->id,
                    'status' => 'pending',
                    'message_motivation' => null,
                ],
                'message' => 'Candidature envoyée avec succès',
            ]);

        $this->assertDatabaseHas('candidatures', [
            'face_id' => $this->face->id,
            'mission_id' => $this->publishedMission->id,
            'message_motivation' => null,
            'status' => 'pending',
        ]);
    }

    public function test_candidature_is_created_with_pending_status(): void
    {
        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$this->publishedMission->uuid}/apply");

        $candidature = Candidature::where('face_id', $this->face->id)
            ->where('mission_id', $this->publishedMission->id)
            ->first();

        $this->assertNotNull($candidature);
        $this->assertEquals(CandidatureStatus::Pending, $candidature->status);
    }

    public function test_face_cannot_apply_to_draft_mission(): void
    {
        $draftMission = Mission::factory()->draft()->create();

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$draftMission->uuid}/apply");

        $response->assertStatus(404);

        $this->assertDatabaseMissing('candidatures', [
            'face_id' => $this->face->id,
            'mission_id' => $draftMission->id,
        ]);
    }

    public function test_face_cannot_apply_to_closed_mission(): void
    {
        $closedMission = Mission::factory()->closed()->create([
            'date_limite_candidature' => now()->addDays(7),
        ]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$closedMission->uuid}/apply");

        $response->assertStatus(404);

        $this->assertDatabaseMissing('candidatures', [
            'face_id' => $this->face->id,
            'mission_id' => $closedMission->id,
        ]);
    }

    public function test_face_cannot_apply_to_completed_mission(): void
    {
        $completedMission = Mission::factory()->completed()->create([
            'date_limite_candidature' => now()->subDays(1),
        ]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$completedMission->uuid}/apply");

        $response->assertStatus(404);

        $this->assertDatabaseMissing('candidatures', [
            'face_id' => $this->face->id,
            'mission_id' => $completedMission->id,
        ]);
    }

    public function test_face_cannot_apply_to_mission_past_deadline(): void
    {
        $expiredMission = Mission::factory()->published()->create([
            'date_limite_candidature' => now()->subDays(1),
        ]);
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $expiredMission->producer_id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$expiredMission->uuid}/apply");

        $response->assertStatus(422)
            ->assertJson([
                'error' => [
                    'code' => 'MISSION_CLOSED',
                    'message' => "Cette mission n'accepte plus de candidatures",
                ],
            ]);

        $this->assertDatabaseMissing('candidatures', [
            'face_id' => $this->face->id,
            'mission_id' => $expiredMission->id,
        ]);
    }

    public function test_face_cannot_apply_twice_to_same_mission(): void
    {
        // First application
        Candidature::factory()->create([
            'face_id' => $this->face->id,
            'mission_id' => $this->publishedMission->id,
        ]);

        // Second application attempt
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$this->publishedMission->uuid}/apply");

        $response->assertStatus(422)
            ->assertJson([
                'error' => [
                    'code' => 'ALREADY_APPLIED',
                    'message' => 'Vous avez déjà postulé à cette mission',
                ],
            ]);

        // Ensure only one candidature exists
        $this->assertEquals(1, Candidature::where('face_id', $this->face->id)
            ->where('mission_id', $this->publishedMission->id)
            ->count());
    }

    public function test_producer_cannot_apply_to_mission(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $response = $this->actingAs($producerUser)
            ->postJson("/api/v1/face/missions/{$this->publishedMission->uuid}/apply");

        $response->assertStatus(403);

        $this->assertDatabaseMissing('candidatures', [
            'mission_id' => $this->publishedMission->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_apply_to_mission(): void
    {
        $response = $this->postJson("/api/v1/face/missions/{$this->publishedMission->uuid}/apply");

        $response->assertStatus(401);

        $this->assertDatabaseMissing('candidatures', [
            'mission_id' => $this->publishedMission->id,
        ]);
    }

    public function test_motivation_message_max_length_validation(): void
    {
        $longMessage = str_repeat('a', 2001);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$this->publishedMission->uuid}/apply", [
                'message_motivation' => $longMessage,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message_motivation']);

        $this->assertDatabaseMissing('candidatures', [
            'face_id' => $this->face->id,
            'mission_id' => $this->publishedMission->id,
        ]);
    }

    public function test_motivation_message_exactly_2000_chars_is_valid(): void
    {
        $maxMessage = str_repeat('a', 2000);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$this->publishedMission->uuid}/apply", [
                'message_motivation' => $maxMessage,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('candidatures', [
            'face_id' => $this->face->id,
            'mission_id' => $this->publishedMission->id,
        ]);
    }

    public function test_empty_string_motivation_is_stored_as_null(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$this->publishedMission->uuid}/apply", [
                'message_motivation' => '',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'message_motivation' => null,
                ],
            ]);

        $this->assertDatabaseHas('candidatures', [
            'face_id' => $this->face->id,
            'mission_id' => $this->publishedMission->id,
            'message_motivation' => null,
        ]);
    }

    public function test_mission_detail_includes_candidature_when_applied(): void
    {
        // Apply first
        Candidature::factory()->create([
            'face_id' => $this->face->id,
            'mission_id' => $this->publishedMission->id,
            'status' => CandidatureStatus::Pending,
        ]);

        // View mission detail
        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/missions/{$this->publishedMission->uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'candidature' => [
                    'id',
                    'mission_id',
                    'face_id',
                    'status',
                    'status_label',
                ],
            ])
            ->assertJson([
                'candidature' => [
                    'mission_id' => $this->publishedMission->id,
                    'face_id' => $this->face->id,
                    'status' => 'pending',
                ],
            ]);
    }

    public function test_mission_detail_returns_null_candidature_when_not_applied(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/missions/{$this->publishedMission->uuid}");

        $response->assertStatus(200)
            ->assertJson([
                'candidature' => null,
            ]);
    }

    public function test_apply_to_non_existent_mission_returns_404(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/missions/00000000-0000-0000-0000-000000000000/apply');

        $response->assertStatus(404);
    }

    // ===================================================================
    // Garde UGC sur le apply (FR5, UGC 2.1)
    // ===================================================================

    /**
     * La factory Mission ne tire jamais `ugc` — attributs explicites obligatoires.
     */
    private function makePublishedUgcMission(): Mission
    {
        $producer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        return $producer->missions()->create([
            'titre' => 'Appel UGC — Unboxing',
            'description' => 'desc',
            'date_tournage' => now()->addMonth(),
            'profil_recherche' => 'Créatrices',
            'budget' => 0,
            'date_limite_candidature' => now()->addWeeks(2),
            'nombre_faces_voulu' => 3,
            'type_mission' => 'ugc',
            'genre_voulu' => 'tous', // évite le guard gender_mismatch
            'lieu' => 'Cotonou',
            'duree' => 'Livrables vidéo',
            'status' => 'published',
            'commission_paid_at' => now(),
            'type_compensation' => 'product',
            'nom_produit' => 'Tenue Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'montant_remuneration' => null,
            'commission_ugc' => 2500,
        ]);
    }

    public function test_free_face_cannot_apply_to_ugc_mission(): void
    {
        $ugcMission = $this->makePublishedUgcMission();

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$ugcMission->uuid}/apply", [
                'message_motivation' => 'Très motivé',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'UGC_SUBSCRIPTION_REQUIRED');

        $this->assertDatabaseCount('candidatures', 0);
        $this->assertDatabaseMissing('candidatures', [
            'face_id' => $this->face->id,
            'mission_id' => $ugcMission->id,
        ]);
    }

    public function test_free_face_with_existing_candidature_on_ugc_mission_gets_already_applied(): void
    {
        // Précédence des gardes (review 2.1) : le check duplicate passe avant le
        // gate UGC — une Face détentrice d'une candidature (abonnée au moment du
        // apply, expirée depuis) reçoit ALREADY_APPLIED, pas le paywall.
        $ugcMission = $this->makePublishedUgcMission();
        Candidature::factory()->create([
            'face_id' => $this->face->id,
            'mission_id' => $ugcMission->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$ugcMission->uuid}/apply", [
                'message_motivation' => 'Très motivé',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'ALREADY_APPLIED');
    }

    public function test_subscribed_face_can_apply_to_ugc_mission(): void
    {
        FaceSubscription::factory()->starter()->active()->create(['face_id' => $this->face->id]);
        $ugcMission = $this->makePublishedUgcMission();

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$ugcMission->uuid}/apply", [
                'message_motivation' => 'Très motivé',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('candidatures', [
            'face_id' => $this->face->id,
            'mission_id' => $ugcMission->id,
            'status' => 'pending',
        ]);
    }

    // ===================================================================
    // Garde producteur is_active (story 3.0)
    // ===================================================================

    public function test_face_cannot_apply_to_mission_of_inactive_producer(): void
    {
        $inactiveProducer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $inactiveProducer->id,
            'is_active' => false,
        ]);
        $hiddenMission = Mission::factory()->published()->create([
            'producer_id' => $inactiveProducer->id,
            'date_limite_candidature' => now()->addDays(7),
        ]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$hiddenMission->uuid}/apply", [
                'message_motivation' => 'Très motivé',
            ]);

        $response->assertStatus(404);
        $this->assertSame(0, Candidature::count());
    }
}
