<?php

declare(strict_types=1);

namespace Tests\Feature\Candidature;

use App\Enums\CandidatureStatus;
use App\Enums\FaceCategory;
use App\Enums\FaceGender;
use App\Enums\FaceNiche;
use App\Enums\MissionStatus;
use App\Models\Candidature;
use App\Models\Experience;
use App\Models\Face;
use App\Models\FacePhoto;
use App\Models\FaceSubscription;
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

    /** @return array<string, string> */
    private function authHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken,
        ];
    }

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
        $response = $this->getJson(
            "/api/v1/producer/candidates/{$this->face->uuid}",
            $this->authHeaders($this->producerUser),
        );

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
            ->assertJsonPath('data.id', $this->face->uuid)
            ->assertJsonPath('data.nom', 'Dupont')
            ->assertJsonPath('data.prenom', 'Marie');
    }

    public function test_response_includes_all_profile_fields(): void
    {
        $response = $this->getJson(
            "/api/v1/producer/candidates/{$this->face->uuid}",
            $this->authHeaders($this->producerUser),
        );

        $response->assertOk()
            ->assertJsonPath('data.bio', 'Je suis une actrice passionnée avec 5 ans d\'expérience.')
            ->assertJsonPath('data.ville', 'Cotonou')
            ->assertJsonPath('data.pays', 'Bénin')
            ->assertJsonPath('data.formatted_location', 'Cotonou, Bénin')
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
        // Producer view of acting_video is masked for free Faces; this test asserts
        // the legacy "URL contains marie_acting.mp4" semantic, so we promote the
        // Face to premium so the URL stays exposed. Free-Face masking is covered
        // by PremiumActingVideoMaskingTest::test_producer_response_masks_acting_video_for_free_face.
        FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);

        $response = $this->getJson(
            "/api/v1/producer/candidates/{$this->face->uuid}",
            $this->authHeaders($this->producerUser),
        );

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

        $response = $this->getJson(
            "/api/v1/producer/candidates/{$this->face->uuid}",
            $this->authHeaders($this->producerUser),
        );

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

        $response = $this->getJson(
            "/api/v1/producer/candidates/{$this->face->uuid}",
            $this->authHeaders($this->producerUser),
        );

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
        $response = $this->getJson(
            "/api/v1/producer/candidates/{$this->face->uuid}",
            $this->authHeaders($this->faceUser),
        );

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', 'Accès réservé aux Producteurs');
    }

    public function test_producer_can_view_any_face_profile(): void
    {
        // Any producer can view any Face's profile (needed for direct bookings)
        $otherFace = Face::factory()->create([
            'nom' => 'Martin',
            'prenom' => 'Jean',
        ]);

        $response = $this->getJson(
            "/api/v1/producer/candidates/{$otherFace->uuid}",
            $this->authHeaders($this->producerUser),
        );

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'nom', 'prenom'],
            ]);
    }

    public function test_returns_404_for_non_existent_face(): void
    {
        $response = $this->getJson(
            '/api/v1/producer/candidates/00000000-0000-0000-0000-000000000000',
            $this->authHeaders($this->producerUser),
        );

        $response->assertNotFound();
    }

    public function test_returns_401_when_unauthenticated(): void
    {
        $response = $this->getJson("/api/v1/producer/candidates/{$this->face->uuid}");

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

        $response = $this->getJson(
            "/api/v1/producer/candidates/{$minimalFace->uuid}",
            $this->authHeaders($this->producerUser),
        );

        $response->assertOk()
            ->assertJsonPath('data.id', $minimalFace->uuid)
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
        $response = $this->getJson(
            "/api/v1/producer/candidates/{$otherFace->uuid}",
            $this->authHeaders($this->producerUser),
        );

        $response->assertOk()
            ->assertJsonPath('data.id', $otherFace->uuid);
    }

    public function test_any_producer_can_view_any_face_profile(): void
    {
        // Create another Producer
        $otherProducer = Producer::factory()->create();
        $otherProducerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $otherProducer->id,
        ]);

        // Any producer can view any Face (needed for direct bookings)
        $response = $this->getJson(
            "/api/v1/producer/candidates/{$this->face->uuid}",
            $this->authHeaders($otherProducerUser),
        );

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'nom', 'prenom'],
            ]);
    }

    public function test_candidate_profile_includes_personal_info_fields(): void
    {
        // Update face with personal info
        $this->face->update([
            'sexe' => FaceGender::HOMME->value,
            'date_naissance' => '1995-06-15',
            'nationalite' => 'Béninoise',
        ]);

        $response = $this->getJson(
            "/api/v1/producer/candidates/{$this->face->uuid}",
            $this->authHeaders($this->producerUser),
        );

        $response->assertOk()
            ->assertJsonPath('data.sexe', 'homme')
            ->assertJsonPath('data.sexe_label', 'Homme')
            ->assertJsonPath('data.age', \Carbon\Carbon::parse('1995-06-15')->age)
            ->assertJsonPath('data.nationalite', 'Béninoise')
            ->assertJsonPath('data.pays', 'Bénin')
            ->assertJsonPath('data.langues', null);
    }

    public function test_candidate_profile_includes_langues(): void
    {
        $this->face->update([
            'langues' => ['Français', 'Anglais', 'Fon'],
        ]);

        $response = $this->getJson(
            "/api/v1/producer/candidates/{$this->face->uuid}",
            $this->authHeaders($this->producerUser),
        );

        $response->assertOk()
            ->assertJsonPath('data.langues', ['Français', 'Anglais', 'Fon']);
    }

    public function test_candidate_profile_returns_null_for_missing_personal_info(): void
    {
        // Face without personal info (existing data)
        $minimalFace = Face::factory()->create([
            'nom' => 'NoPersInfo',
            'prenom' => 'Face',
        ]);

        Candidature::factory()->create([
            'mission_id' => $this->mission->id,
            'face_id' => $minimalFace->id,
        ]);

        $response = $this->getJson(
            "/api/v1/producer/candidates/{$minimalFace->uuid}",
            $this->authHeaders($this->producerUser),
        );

        $response->assertOk()
            ->assertJsonPath('data.sexe', null)
            ->assertJsonPath('data.sexe_label', null)
            ->assertJsonPath('data.age', null)
            ->assertJsonPath('data.nationalite', null);
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

        $response = $this->getJson(
            "/api/v1/producer/candidates/{$faceWithoutExtras->uuid}",
            $this->authHeaders($this->producerUser),
        );

        $response->assertOk()
            ->assertJsonPath('data.photos', [])
            ->assertJsonPath('data.experiences', []);
    }
}
