<?php

declare(strict_types=1);

namespace Tests\Feature\Candidature;

use App\Enums\CandidatureStatus;
use App\Enums\FaceCategory;
use App\Enums\FaceNiche;
use App\Enums\MissionStatus;
use App\Models\Candidature;
use App\Models\Experience;
use App\Models\Face;
use App\Models\FacePhoto;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProducerViewCandidateProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    private User $faceUser;

    private Face $face;

    private Mission $mission;

    private Candidature $candidature;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a Producer with User
        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);

        // Create a published mission owned by this Producer
        $this->mission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
        ]);

        // Create a Face with complete profile
        $this->face = Face::factory()->create([
            'nom' => 'Dupont',
            'prenom' => 'Marie',
            'username' => 'marie_dupont',
            'bio' => 'Je suis une actrice passionnée avec 5 ans d\'expérience.',
            'ville' => 'Cotonou',
            'quartier' => 'Akpakpa',
            'pays' => 'Bénin',
            'taille' => 175,
            'poids' => 65,
            'categories' => [FaceCategory::ACTEUR->value],
            'niches' => [FaceNiche::BEAUTE->value],
            'tarif_horaire' => 25000,
            'tarif_journalier' => 150000,
            'is_available' => true,
            'profile_photo' => 'marie_photo.jpg',
            'presentation_video' => 'marie_presentation.mp4',
            'acting_video' => 'marie_acting.mp4',
        ]);

        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);

        // Create a candidature linking Face to Producer's mission
        $this->candidature = Candidature::factory()->create([
            'mission_id' => $this->mission->id,
            'face_id' => $this->face->id,
            'status' => CandidatureStatus::Pending,
        ]);
    }

    public function test_producer_can_view_profile_of_face_who_applied_to_their_mission(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/candidates/{$this->face->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'nom',
                    'prenom',
                    'username',
                    'profile_photo_url',
                    'bio',
                    'ville',
                    'quartier',
                    'pays',
                    'formatted_location',
                    'taille',
                    'poids',
                    'categories',
                    'niches',
                    'tarif_horaire',
                    'tarif_journalier',
                    'formatted_tarif_horaire',
                    'formatted_tarif_journalier',
                    'is_available',
                    'availability_badge',
                    'availability_badge_color',
                    'presentation_video_url',
                    'acting_video_url',
                    'experiences',
                    'photos',
                ],
            ])
            ->assertJsonPath('data.id', $this->face->id)
            ->assertJsonPath('data.nom', 'Dupont')
            ->assertJsonPath('data.prenom', 'Marie');
    }

    public function test_response_includes_all_profile_fields(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/candidates/{$this->face->id}");

        $response->assertOk()
            ->assertJsonPath('data.bio', 'Je suis une actrice passionnée avec 5 ans d\'expérience.')
            ->assertJsonPath('data.ville', 'Cotonou')
            ->assertJsonPath('data.quartier', 'Akpakpa')
            ->assertJsonPath('data.pays', 'Bénin')
            ->assertJsonPath('data.formatted_location', 'Cotonou, Akpakpa, Bénin')
            ->assertJsonPath('data.taille', 175)
            ->assertJsonPath('data.poids', 65)
            ->assertJsonPath('data.categories.0.value', 'acteur')
            ->assertJsonPath('data.categories.0.label', 'Acteur')
            ->assertJsonPath('data.niches.0.value', 'beaute')
            ->assertJsonPath('data.niches.0.label', 'Beauté')
            ->assertJsonPath('data.tarif_horaire', 25000)
            ->assertJsonPath('data.tarif_journalier', 150000)
            ->assertJsonPath('data.is_available', true)
            ->assertJsonPath('data.availability_badge', 'Disponible');
    }

    public function test_response_includes_video_urls(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/candidates/{$this->face->id}");

        $response->assertOk();

        $data = $response->json('data');

        // Video URLs should be present (based on profile_photo, presentation_video, acting_video fields)
        $this->assertArrayHasKey('presentation_video_url', $data);
        $this->assertArrayHasKey('presentation_video_thumbnail_url', $data);
        $this->assertArrayHasKey('acting_video_url', $data);
        $this->assertArrayHasKey('acting_video_thumbnail_url', $data);

        // URLs should contain the expected path
        $this->assertStringContainsString('marie_presentation.mp4', $data['presentation_video_url']);
        $this->assertStringContainsString('marie_acting.mp4', $data['acting_video_url']);
    }

    public function test_response_includes_photo_album(): void
    {
        // Add photos to the Face
        FacePhoto::factory()->create([
            'face_id' => $this->face->id,
            'filename' => 'photo1.jpg',
            'position' => 1,
        ]);
        FacePhoto::factory()->create([
            'face_id' => $this->face->id,
            'filename' => 'photo2.jpg',
            'position' => 2,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/candidates/{$this->face->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data.photos')
            ->assertJsonStructure([
                'data' => [
                    'photos' => [
                        '*' => [
                            'id',
                            'photo_url',
                            'thumbnail_url',
                            'position',
                        ],
                    ],
                ],
            ]);
    }

    public function test_response_includes_experiences(): void
    {
        // Add experiences to the Face
        Experience::factory()->create([
            'face_id' => $this->face->id,
            'titre' => 'Publicité Moov Africa',
            'description' => 'Actrice principale dans le spot TV',
        ]);
        Experience::factory()->create([
            'face_id' => $this->face->id,
            'titre' => 'Court-métrage "Les Rêves"',
            'description' => 'Rôle secondaire',
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/candidates/{$this->face->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data.experiences')
            ->assertJsonStructure([
                'data' => [
                    'experiences' => [
                        '*' => [
                            'id',
                            'titre',
                            'description',
                        ],
                    ],
                ],
            ]);
    }

    public function test_face_cannot_access_producer_candidates_endpoint(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/producer/candidates/{$this->face->id}");

        $response->assertForbidden()
            ->assertJson([
                'message' => 'Accès réservé aux Producteurs',
            ]);
    }

    public function test_producer_cannot_view_face_who_did_not_apply_to_their_missions(): void
    {
        // Create another Face who hasn't applied to any of this Producer's missions
        $otherFace = Face::factory()->create([
            'nom' => 'Martin',
            'prenom' => 'Jean',
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/candidates/{$otherFace->id}");

        $response->assertForbidden()
            ->assertJson([
                'message' => 'Vous ne pouvez consulter que les profils des candidats ayant postulé à vos missions',
            ]);
    }

    public function test_returns_404_for_non_existent_face(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/candidates/99999');

        $response->assertNotFound();
    }

    public function test_returns_401_when_unauthenticated(): void
    {
        $response = $this->getJson("/api/v1/producer/candidates/{$this->face->id}");

        $response->assertUnauthorized();
    }

    public function test_returns_partial_profile_when_face_has_missing_fields(): void
    {
        // Create a Face with minimal data
        $minimalFace = Face::factory()->create([
            'nom' => 'Test',
            'prenom' => 'User',
            'bio' => null,
            'taille' => null,
            'poids' => null,
            'presentation_video' => null,
            'acting_video' => null,
        ]);

        // Create candidature to allow Producer to view this Face
        Candidature::factory()->create([
            'mission_id' => $this->mission->id,
            'face_id' => $minimalFace->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/candidates/{$minimalFace->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $minimalFace->id)
            ->assertJsonPath('data.bio', null)
            ->assertJsonPath('data.taille', null)
            ->assertJsonPath('data.poids', null)
            ->assertJsonPath('data.presentation_video_url', null)
            ->assertJsonPath('data.acting_video_url', null);
    }

    public function test_producer_can_view_face_who_applied_to_any_of_their_missions(): void
    {
        // Create another mission for the same Producer
        $otherMission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
        ]);

        // Create another Face who applied to the other mission
        $otherFace = Face::factory()->create([
            'nom' => 'Other',
            'prenom' => 'Face',
        ]);

        Candidature::factory()->create([
            'mission_id' => $otherMission->id,
            'face_id' => $otherFace->id,
        ]);

        // Producer should be able to view this Face
        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/candidates/{$otherFace->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $otherFace->id);
    }

    public function test_other_producer_cannot_view_candidate_who_applied_to_different_producer(): void
    {
        // Create another Producer
        $otherProducer = Producer::factory()->create();
        $otherProducerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $otherProducer->id,
        ]);

        // The Face applied only to the first Producer's mission
        // Other Producer should not be able to view this Face
        $response = $this->actingAs($otherProducerUser)
            ->getJson("/api/v1/producer/candidates/{$this->face->id}");

        $response->assertForbidden()
            ->assertJson([
                'message' => 'Vous ne pouvez consulter que les profils des candidats ayant postulé à vos missions',
            ]);
    }

    public function test_empty_photos_and_experiences_return_empty_arrays(): void
    {
        // Create Face without photos or experiences
        $faceWithoutExtras = Face::factory()->create([
            'nom' => 'Simple',
            'prenom' => 'Face',
        ]);

        Candidature::factory()->create([
            'mission_id' => $this->mission->id,
            'face_id' => $faceWithoutExtras->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/candidates/{$faceWithoutExtras->id}");

        $response->assertOk()
            ->assertJsonPath('data.photos', [])
            ->assertJsonPath('data.experiences', []);
    }
}
