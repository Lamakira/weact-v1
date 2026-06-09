<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Enums\MissionStatus;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaceViewMissionDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    private Face $face;

    private User $producerUser;

    private Producer $producer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a Face user
        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);

        // Create a Producer user
        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
    }

    public function test_face_can_view_published_mission_detail(): void
    {
        $mission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'titre' => 'Test Mission',
            'description' => 'Full description of the mission',
            'date_tournage' => '2026-02-15',
            'budget' => 150000,
            'lieu' => 'Cotonou',
            'duree' => '2 jours',
            'type_mission' => 'publicite',
            'genre_voulu' => 'femme',
            'profil_recherche' => 'Femme 25-35 ans, peau noire',
            'nombre_faces_voulu' => 3,
            'date_limite_candidature' => '2026-02-01',
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/missions/{$mission->uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'titre',
                    'description',
                    'date_tournage',
                    'budget',
                    'lieu',
                    'duree',
                    'type_mission',
                    'type_mission_label',
                    'genre_voulu',
                    'genre_voulu_label',
                    'profil_recherche',
                    'nombre_faces_voulu',
                    'date_limite_candidature',
                    'status',
                    'status_label',
                    'is_accepting_candidatures',
                    'producer' => [
                        'id',
                        'type',
                        'display_name',
                    ],
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJsonPath('data.id', $mission->uuid)
            ->assertJsonPath('data.titre', 'Test Mission')
            ->assertJsonPath('data.description', 'Full description of the mission')
            ->assertJsonPath('data.budget', 150000)
            ->assertJsonPath('data.lieu', 'Cotonou')
            ->assertJsonPath('data.duree', '2 jours')
            ->assertJsonPath('data.type_mission', 'publicite')
            ->assertJsonPath('data.genre_voulu', 'femme')
            ->assertJsonPath('data.profil_recherche', 'Femme 25-35 ans, peau noire')
            ->assertJsonPath('data.nombre_faces_voulu', 3)
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.status_label', 'Publiée');
    }

    public function test_face_cannot_view_draft_mission(): void
    {
        $mission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Draft,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/missions/{$mission->uuid}");

        $response->assertStatus(404);
    }

    public function test_face_cannot_view_closed_mission(): void
    {
        $mission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Closed,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/missions/{$mission->uuid}");

        $response->assertStatus(404);
    }

    public function test_face_cannot_view_completed_mission(): void
    {
        $mission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Completed,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/missions/{$mission->uuid}");

        $response->assertStatus(404);
    }

    public function test_nonexistent_mission_returns_404(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404);
    }

    public function test_producer_cannot_access_face_mission_detail_endpoint(): void
    {
        $mission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/face/missions/{$mission->uuid}");

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', 'Cette action n\'est pas autorisée');
    }

    public function test_unauthenticated_user_cannot_access(): void
    {
        $mission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
        ]);

        $response = $this->getJson("/api/v1/face/missions/{$mission->uuid}");

        $response->assertStatus(401);
    }

    public function test_response_includes_producer_data(): void
    {
        $producer = Producer::factory()->create([
            'type' => 'agency',
            'agency_name' => 'Studio XYZ',
        ]);

        $mission = Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Published,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/missions/{$mission->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('data.producer.agency_name', 'Studio XYZ')
            ->assertJsonPath('data.producer.type', 'agency')
            ->assertJsonPath('data.producer.display_name', 'Studio XYZ');
    }

    public function test_is_accepting_candidatures_true_when_mission_open(): void
    {
        $mission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'date_limite_candidature' => now()->addDays(10),
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/missions/{$mission->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_accepting_candidatures', true);
    }

    public function test_is_accepting_candidatures_false_when_deadline_passed(): void
    {
        $mission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'date_limite_candidature' => now()->subDays(1),
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/missions/{$mission->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_accepting_candidatures', false);
    }

    public function test_response_includes_all_required_fields(): void
    {
        $mission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'titre' => 'Publicité Savon',
            'description' => 'Recherche visages pour campagne publicitaire',
            'date_tournage' => '2026-02-15',
            'profil_recherche' => 'Femme 25-35 ans',
            'budget' => 150000,
            'date_limite_candidature' => '2026-02-01',
            'nombre_faces_voulu' => 3,
            'type_mission' => 'publicite',
            'genre_voulu' => 'femme',
            'lieu' => 'Cotonou',
            'duree' => '1 jour',
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/missions/{$mission->uuid}");

        $response->assertStatus(200);

        $data = $response->json('data');

        // Check all required fields are present
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('titre', $data);
        $this->assertArrayHasKey('description', $data);
        $this->assertArrayHasKey('date_tournage', $data);
        $this->assertArrayHasKey('profil_recherche', $data);
        $this->assertArrayHasKey('budget', $data);
        $this->assertArrayHasKey('date_limite_candidature', $data);
        $this->assertArrayHasKey('nombre_faces_voulu', $data);
        $this->assertArrayHasKey('type_mission', $data);
        $this->assertArrayHasKey('type_mission_label', $data);
        $this->assertArrayHasKey('genre_voulu', $data);
        $this->assertArrayHasKey('genre_voulu_label', $data);
        $this->assertArrayHasKey('lieu', $data);
        $this->assertArrayHasKey('duree', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('status_label', $data);
        $this->assertArrayHasKey('is_accepting_candidatures', $data);
        $this->assertArrayHasKey('producer', $data);
        $this->assertArrayHasKey('created_at', $data);
        $this->assertArrayHasKey('updated_at', $data);

        // Verify label values
        $this->assertEquals('Publicité', $data['type_mission_label']);
        $this->assertEquals('Femme', $data['genre_voulu_label']);
    }

    // ========================================
    // Producer Rating Exposure Tests (Story 8-7)
    // ========================================

    public function test_producer_rating_included_in_mission_detail(): void
    {
        $producer = Producer::factory()->create([
            'type' => 'agency',
            'agency_name' => 'Rated Studio',
        ]);

        $mission = Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Published,
        ]);

        // Create ratings for the producer (average should be 4.5)
        \App\Models\Rating::factory()
            ->faceRatingProducer()
            ->ratingProducer($producer)
            ->score(5)
            ->create();
        \App\Models\Rating::factory()
            ->faceRatingProducer()
            ->ratingProducer($producer)
            ->score(4)
            ->create();

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/missions/{$mission->uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'producer' => [
                        'id',
                        'type',
                        'display_name',
                        'average_rating',
                        'ratings_count',
                    ],
                ],
            ])
            ->assertJsonPath('data.producer.ratings_count', 2);

        // Check average rating value (4.5 = average of 5 and 4)
        $this->assertEquals(4.5, $response->json('data.producer.average_rating'));
    }

    public function test_producer_with_no_ratings_returns_null_in_detail(): void
    {
        $producer = Producer::factory()->create();

        $mission = Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Published,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/missions/{$mission->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('data.producer.average_rating', null)
            ->assertJsonPath('data.producer.ratings_count', 0);
    }

    // ===================================================================
    // Gate UGC sur le détail (FR5, UGC 2.1)
    // ===================================================================

    /**
     * La factory Mission ne tire jamais `ugc` — attributs explicites obligatoires.
     */
    private function makePublishedUgcMission(): Mission
    {
        return $this->producer->missions()->create([
            'titre' => 'Appel UGC — Unboxing',
            'description' => 'desc',
            'date_tournage' => now()->addMonth(),
            'profil_recherche' => 'Créatrices',
            'budget' => 0,
            'date_limite_candidature' => now()->addWeeks(2),
            'nombre_faces_voulu' => 3,
            'type_mission' => 'ugc',
            'genre_voulu' => 'tous',
            'lieu' => 'Cotonou',
            'duree' => 'Livrables vidéo',
            'status' => MissionStatus::Published,
            'commission_paid_at' => now(),
            'type_compensation' => 'product',
            'nom_produit' => 'Tenue Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'montant_remuneration' => null,
            'commission_ugc' => 2500,
        ]);
    }

    public function test_free_face_cannot_view_ugc_mission_detail(): void
    {
        $mission = $this->makePublishedUgcMission();

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/missions/{$mission->uuid}");

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'UGC_SUBSCRIPTION_REQUIRED');
        $this->assertNull($response->json('data'));
    }

    public function test_subscribed_face_can_view_ugc_mission_detail(): void
    {
        FaceSubscription::factory()->starter()->active()->create(['face_id' => $this->face->id]);
        $mission = $this->makePublishedUgcMission();

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/missions/{$mission->uuid}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $mission->uuid)
            ->assertJsonPath('data.type_mission', 'ugc');

        // Internals de facturation Producer : masqués pour les Faces, même abonnées
        $this->assertArrayNotHasKey('commission_ugc', $response->json('data'));
        $this->assertArrayNotHasKey('commission_paid_at', $response->json('data'));
    }

    public function test_free_face_with_existing_candidature_can_view_ugc_mission_detail(): void
    {
        $mission = $this->makePublishedUgcMission();
        Candidature::create([
            'face_id' => $this->face->id,
            'mission_id' => $mission->id,
            'message_motivation' => 'Très motivée',
        ]);

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/missions/{$mission->uuid}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $mission->uuid);
    }
}
