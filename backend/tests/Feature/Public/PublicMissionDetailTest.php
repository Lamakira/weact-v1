<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\MissionGender;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicMissionDetailTest extends TestCase
{
    use RefreshDatabase;

    private function createProducerWithUser(): Producer
    {
        $producer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        return $producer;
    }

    private function createPublishedMission(array $attributes = []): Mission
    {
        $producer = $attributes['producer'] ?? $this->createProducerWithUser();
        unset($attributes['producer']);

        return Mission::factory()->published()->create(array_merge(
            ['producer_id' => $producer->id],
            $attributes
        ));
    }

    // ─── Basic Detail Tests ──────────────────────────────────────────

    public function test_returns_published_mission_with_all_expected_fields(): void
    {
        $producer = $this->createProducerWithUser();

        $mission = $this->createPublishedMission([
            'producer' => $producer,
            'titre' => 'Casting publicité MTN',
            'description' => 'Recherche comédien(ne) pour spot TV',
            'budget' => 150000,
            'lieu' => 'Cotonou, Bénin',
            'nombre_faces_voulu' => 3,
            'type_mission' => MissionType::Publicite,
            'genre_voulu' => MissionGender::Femme,
            'duree' => '2 jours',
            'profil_recherche' => 'Jeune femme 20-30 ans',
        ]);

        $response = $this->getJson("/api/v1/public/missions/{$mission->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'titre',
                    'description',
                    'date_tournage',
                    'profil_recherche',
                    'budget',
                    'date_limite_candidature',
                    'nombre_faces_voulu',
                    'type_mission',
                    'type_mission_label',
                    'genre_voulu',
                    'genre_voulu_label',
                    'lieu',
                    'duree',
                    'status',
                    'status_label',
                    'created_at',
                    'producer' => [
                        'id',
                        'display_name',
                        'profile_photo_thumbnail_url',
                        'average_rating',
                        'ratings_count',
                    ],
                ],
                'message',
            ]);

        $data = $response->json('data');
        $this->assertEquals($mission->id, $data['id']);
        $this->assertEquals('Casting publicité MTN', $data['titre']);
        $this->assertEquals('Recherche comédien(ne) pour spot TV', $data['description']);
        $this->assertEquals(150000, $data['budget']);
        $this->assertEquals('Cotonou, Bénin', $data['lieu']);
        $this->assertEquals(3, $data['nombre_faces_voulu']);
        $this->assertEquals('publicite', $data['type_mission']);
        $this->assertEquals('Publicité', $data['type_mission_label']);
        $this->assertEquals('femme', $data['genre_voulu']);
        $this->assertEquals('Femme', $data['genre_voulu_label']);
        $this->assertEquals('2 jours', $data['duree']);
        $this->assertEquals('Jeune femme 20-30 ans', $data['profil_recherche']);
        $this->assertEquals('published', $data['status']);
        $this->assertEquals('Publiée', $data['status_label']);
    }

    public function test_returns_producer_data_with_ratings(): void
    {
        $producer = $this->createProducerWithUser();
        $mission = $this->createPublishedMission(['producer' => $producer]);

        // Create rating context
        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $completedMission1 = Mission::factory()->completed()->create(['producer_id' => $producer->id]);
        $completedMission2 = Mission::factory()->completed()->create(['producer_id' => $producer->id]);

        $candidature1 = Candidature::factory()->create([
            'face_id' => $face->id,
            'mission_id' => $completedMission1->id,
        ]);
        $candidature2 = Candidature::factory()->create([
            'face_id' => $face->id,
            'mission_id' => $completedMission2->id,
        ]);

        Rating::create([
            'candidature_id' => $candidature1->id,
            'rated_type' => Producer::class,
            'rated_id' => $producer->id,
            'rater_id' => $faceUser->id,
            'score' => 4,
            'comment' => 'Good producer',
        ]);

        Rating::create([
            'candidature_id' => $candidature2->id,
            'rated_type' => Producer::class,
            'rated_id' => $producer->id,
            'rater_id' => $faceUser->id,
            'score' => 5,
            'comment' => 'Excellent',
        ]);

        $response = $this->getJson("/api/v1/public/missions/{$mission->id}");

        $response->assertOk();

        $producerData = $response->json('data.producer');
        $this->assertEquals($producer->id, $producerData['id']);
        $this->assertEquals($producer->display_name, $producerData['display_name']);
        $this->assertArrayHasKey('profile_photo_thumbnail_url', $producerData);
        $this->assertEquals(4.5, $producerData['average_rating']);
        $this->assertEquals(2, $producerData['ratings_count']);
    }

    // ─── 404 Tests ───────────────────────────────────────────────────

    public function test_returns_404_for_non_existent_mission_id(): void
    {
        $response = $this->getJson('/api/v1/public/missions/99999');

        $response->assertNotFound()
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                ],
            ])
            ->assertJson([
                'error' => [
                    'code' => 'MISSION_NOT_FOUND',
                    'message' => 'Mission non trouvée',
                ],
            ]);
    }

    public function test_returns_404_for_draft_mission(): void
    {
        $producer = $this->createProducerWithUser();
        $mission = Mission::factory()->draft()->create(['producer_id' => $producer->id]);

        $response = $this->getJson("/api/v1/public/missions/{$mission->id}");

        $response->assertNotFound()
            ->assertJson([
                'error' => [
                    'code' => 'MISSION_NOT_FOUND',
                ],
            ]);
    }

    public function test_returns_404_for_closed_mission(): void
    {
        $producer = $this->createProducerWithUser();
        $mission = Mission::factory()->closed()->create(['producer_id' => $producer->id]);

        $response = $this->getJson("/api/v1/public/missions/{$mission->id}");

        $response->assertNotFound()
            ->assertJson([
                'error' => [
                    'code' => 'MISSION_NOT_FOUND',
                ],
            ]);
    }

    public function test_returns_404_for_completed_mission(): void
    {
        $producer = $this->createProducerWithUser();
        $mission = Mission::factory()->completed()->create(['producer_id' => $producer->id]);

        $response = $this->getJson("/api/v1/public/missions/{$mission->id}");

        $response->assertNotFound()
            ->assertJson([
                'error' => [
                    'code' => 'MISSION_NOT_FOUND',
                ],
            ]);
    }

    // ─── Auth & Access Tests ─────────────────────────────────────────

    public function test_does_not_require_authentication(): void
    {
        $mission = $this->createPublishedMission();

        // No auth token, no actingAs
        $response = $this->getJson("/api/v1/public/missions/{$mission->id}");

        $response->assertOk();
    }

    // ─── Data Type Tests ─────────────────────────────────────────────

    public function test_response_has_correct_data_types(): void
    {
        $producer = $this->createProducerWithUser();
        $mission = $this->createPublishedMission([
            'producer' => $producer,
            'budget' => 100000,
            'nombre_faces_voulu' => 2,
        ]);

        $response = $this->getJson("/api/v1/public/missions/{$mission->id}");

        $response->assertOk();

        $data = $response->json('data');

        $this->assertIsInt($data['id']);
        $this->assertIsString($data['titre']);
        $this->assertIsString($data['description']);
        $this->assertIsInt($data['budget']);
        $this->assertIsInt($data['nombre_faces_voulu']);
        $this->assertIsString($data['type_mission']);
        $this->assertIsString($data['type_mission_label']);
        $this->assertIsString($data['genre_voulu']);
        $this->assertIsString($data['genre_voulu_label']);
        $this->assertIsString($data['lieu']);
        $this->assertIsString($data['status']);
        $this->assertIsString($data['status_label']);
        $this->assertIsString($data['created_at']);

        // Producer nested object
        $this->assertIsInt($data['producer']['id']);
        $this->assertIsString($data['producer']['display_name']);
        $this->assertIsInt($data['producer']['ratings_count']);
    }

    // ─── Date Formatting Tests ───────────────────────────────────────

    public function test_date_fields_are_formatted_correctly(): void
    {
        $producer = $this->createProducerWithUser();
        $mission = $this->createPublishedMission([
            'producer' => $producer,
            'date_tournage' => '2026-03-15',
            'date_limite_candidature' => '2026-02-28',
        ]);

        $response = $this->getJson("/api/v1/public/missions/{$mission->id}");

        $response->assertOk();

        $data = $response->json('data');

        // Date fields should be ISO date strings (YYYY-MM-DD)
        $this->assertEquals('2026-03-15', $data['date_tournage']);
        $this->assertEquals('2026-02-28', $data['date_limite_candidature']);
    }

    // ─── Budget Tests ────────────────────────────────────────────────

    public function test_budget_is_integer_xof(): void
    {
        $producer = $this->createProducerWithUser();
        $mission = $this->createPublishedMission([
            'producer' => $producer,
            'budget' => 250000,
        ]);

        $response = $this->getJson("/api/v1/public/missions/{$mission->id}");

        $response->assertOk();
        $this->assertIsInt($response->json('data.budget'));
        $this->assertEquals(250000, $response->json('data.budget'));
    }

    // ─── Response Format Tests ───────────────────────────────────────

    public function test_response_does_not_include_meta_key(): void
    {
        $mission = $this->createPublishedMission();

        $response = $this->getJson("/api/v1/public/missions/{$mission->id}");

        $response->assertOk();

        // Single resource: no meta pagination
        $this->assertArrayNotHasKey('meta', $response->json());
    }

    public function test_success_message_is_returned(): void
    {
        $mission = $this->createPublishedMission();

        $response = $this->getJson("/api/v1/public/missions/{$mission->id}");

        $response->assertOk();
        $this->assertEquals('Mission retrieved successfully', $response->json('message'));
    }
}
