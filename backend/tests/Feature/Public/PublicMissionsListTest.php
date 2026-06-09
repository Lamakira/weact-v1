<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\MissionGender;
use App\Enums\MissionType;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicMissionsListTest extends TestCase
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

    // ─── Basic List Tests ────────────────────────────────────────────

    public function test_returns_paginated_list_of_published_missions_without_authentication(): void
    {
        $producer = $this->createProducerWithUser();

        for ($i = 0; $i < 20; $i++) {
            $this->createPublishedMission(['producer' => $producer]);
        }

        $response = $this->getJson('/api/v1/public/missions');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'slug',
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
                            'slug',
                            'display_name',
                            'profile_photo_thumbnail_url',
                            'average_rating',
                            'ratings_count',
                        ],
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
                'message',
            ]);

        $this->assertEquals(15, $response->json('meta.per_page'));
        $this->assertCount(15, $response->json('data'));
        $this->assertEquals(20, $response->json('meta.total'));
    }

    public function test_only_returns_published_missions(): void
    {
        $producer = $this->createProducerWithUser();

        // Create missions with different statuses
        $published = $this->createPublishedMission(['producer' => $producer, 'titre' => 'Published Mission']);
        Mission::factory()->draft()->create(['producer_id' => $producer->id, 'titre' => 'Draft Mission']);
        Mission::factory()->closed()->create(['producer_id' => $producer->id, 'titre' => 'Closed Mission']);
        Mission::factory()->completed()->create(['producer_id' => $producer->id, 'titre' => 'Completed Mission']);

        $response = $this->getJson('/api/v1/public/missions');

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals($published->uuid, $response->json('data.0.id'));
        $this->assertEquals('Published Mission', $response->json('data.0.titre'));
    }

    public function test_response_includes_correct_mission_fields(): void
    {
        $producer = $this->createProducerWithUser();

        $mission = $this->createPublishedMission([
            'producer' => $producer,
            'titre' => 'Casting publicité MTN',
            'description' => 'Recherche comédien(ne) pour spot TV',
            'budget' => 75000,
            'lieu' => 'Cotonou, Bénin',
            'nombre_faces_voulu' => 3,
            'type_mission' => MissionType::Publicite,
            'genre_voulu' => MissionGender::Femme,
            'duree' => '1 journée',
        ]);

        $response = $this->getJson('/api/v1/public/missions');

        $response->assertOk();

        $data = $response->json('data.0');

        $this->assertEquals($mission->uuid, $data['id']);
        $this->assertEquals('Casting publicité MTN', $data['titre']);
        $this->assertEquals('Recherche comédien(ne) pour spot TV', $data['description']);
        $this->assertEquals(75000, $data['budget']);
        $this->assertEquals('Cotonou, Bénin', $data['lieu']);
        $this->assertEquals(3, $data['nombre_faces_voulu']);
        $this->assertEquals('publicite', $data['type_mission']);
        $this->assertEquals('Publicité', $data['type_mission_label']);
        $this->assertEquals('femme', $data['genre_voulu']);
        $this->assertEquals('Femme', $data['genre_voulu_label']);
        $this->assertEquals('1 journée', $data['duree']);
        $this->assertEquals('published', $data['status']);
        $this->assertEquals('Publiée', $data['status_label']);
    }

    public function test_response_includes_producer_info(): void
    {
        $producer = $this->createProducerWithUser();
        $this->createPublishedMission(['producer' => $producer]);

        $response = $this->getJson('/api/v1/public/missions');

        $response->assertOk();

        $producerData = $response->json('data.0.producer');

        $this->assertEquals($producer->uuid, $producerData['id']);
        $this->assertEquals($producer->slug, $producerData['slug']);
        $this->assertArrayHasKey('display_name', $producerData);
        $this->assertArrayHasKey('profile_photo_thumbnail_url', $producerData);
        $this->assertArrayHasKey('average_rating', $producerData);
        $this->assertArrayHasKey('ratings_count', $producerData);
    }

    public function test_producer_does_not_expose_sensitive_fields(): void
    {
        $producer = $this->createProducerWithUser();
        $this->createPublishedMission(['producer' => $producer]);

        $response = $this->getJson('/api/v1/public/missions');

        $response->assertOk();

        $producerData = $response->json('data.0.producer');

        // Should NOT include sensitive producer fields
        $this->assertArrayNotHasKey('email', $producerData);
        $this->assertArrayNotHasKey('phone', $producerData);
        $this->assertArrayNotHasKey('bio', $producerData);
        $this->assertArrayNotHasKey('agency_name', $producerData);
        $this->assertArrayNotHasKey('agency_logo_url', $producerData);
    }

    // ─── Pagination Tests ────────────────────────────────────────────

    public function test_supports_custom_per_page_parameter_with_max_30(): void
    {
        $producer = $this->createProducerWithUser();

        for ($i = 0; $i < 35; $i++) {
            $this->createPublishedMission(['producer' => $producer]);
        }

        // Request 30 items (max allowed)
        $response = $this->getJson('/api/v1/public/missions?per_page=30');

        $response->assertOk();
        $this->assertEquals(30, $response->json('meta.per_page'));
        $this->assertCount(30, $response->json('data'));

        // Request 50 items (exceeds max 30, returns 422)
        $response = $this->getJson('/api/v1/public/missions?per_page=50');

        $response->assertUnprocessable();
    }

    public function test_supports_page_parameter_for_pagination(): void
    {
        $producer = $this->createProducerWithUser();

        for ($i = 0; $i < 20; $i++) {
            $this->createPublishedMission(['producer' => $producer]);
        }

        $response = $this->getJson('/api/v1/public/missions?page=2&per_page=15');

        $response->assertOk();
        $this->assertEquals(2, $response->json('meta.current_page'));
        $this->assertCount(5, $response->json('data'));
    }

    public function test_returns_empty_data_array_when_no_published_missions_exist(): void
    {
        $response = $this->getJson('/api/v1/public/missions');

        $response->assertOk()
            ->assertJson([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'total' => 0,
                ],
            ]);
    }

    // ─── Validation Tests ────────────────────────────────────────────

    public function test_returns_422_for_invalid_page_parameter(): void
    {
        $response = $this->getJson('/api/v1/public/missions?page=0');
        $response->assertUnprocessable();

        $response = $this->getJson('/api/v1/public/missions?page=-1');
        $response->assertUnprocessable();

        $response = $this->getJson('/api/v1/public/missions?page=abc');
        $response->assertUnprocessable();
    }

    public function test_returns_422_for_invalid_per_page_parameter(): void
    {
        $response = $this->getJson('/api/v1/public/missions?per_page=0');
        $response->assertUnprocessable();

        $response = $this->getJson('/api/v1/public/missions?per_page=-1');
        $response->assertUnprocessable();

        $response = $this->getJson('/api/v1/public/missions?per_page=abc');
        $response->assertUnprocessable();
    }

    // ─── Auth & Rate Limiting Tests ──────────────────────────────────

    public function test_does_not_require_authentication(): void
    {
        $producer = $this->createProducerWithUser();
        $this->createPublishedMission(['producer' => $producer]);

        // No auth token, no actingAs
        $response = $this->getJson('/api/v1/public/missions');

        $response->assertOk();
    }

    public function test_rate_limiting_is_applied(): void
    {
        $producer = $this->createProducerWithUser();
        $this->createPublishedMission(['producer' => $producer]);

        // Make 61 requests (limit is 60/min)
        for ($i = 0; $i < 60; $i++) {
            $response = $this->getJson('/api/v1/public/missions');
            $response->assertOk();
        }

        $response = $this->getJson('/api/v1/public/missions');
        $response->assertStatus(429);
    }

    // ─── Data Ordering Tests ─────────────────────────────────────────

    public function test_missions_are_ordered_by_created_at_desc(): void
    {
        $producer = $this->createProducerWithUser();

        $older = $this->createPublishedMission(['producer' => $producer, 'titre' => 'Older Mission']);
        Mission::where('id', $older->id)->update(['created_at' => now()->subDays(2)]);

        $newer = $this->createPublishedMission(['producer' => $producer, 'titre' => 'Newer Mission']);
        Mission::where('id', $newer->id)->update(['created_at' => now()->subDay()]);

        $newest = $this->createPublishedMission(['producer' => $producer, 'titre' => 'Newest Mission']);

        $response = $this->getJson('/api/v1/public/missions');

        $response->assertOk();
        $this->assertEquals($newest->uuid, $response->json('data.0.id'));
        $this->assertEquals($newer->uuid, $response->json('data.1.id'));
        $this->assertEquals($older->uuid, $response->json('data.2.id'));
    }

    // ─── Eager Loading / N+1 Prevention Tests ────────────────────────

    public function test_producer_rating_is_included_when_available(): void
    {
        $producer = $this->createProducerWithUser();
        $publishedMission = $this->createPublishedMission(['producer' => $producer]);

        // Create a face user for rating
        $face = \App\Models\Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => \App\Models\Face::class,
            'userable_id' => $face->id,
        ]);

        // Create completed missions with candidatures for rating context
        $completedMission1 = Mission::factory()->completed()->create(['producer_id' => $producer->id]);
        $completedMission2 = Mission::factory()->completed()->create(['producer_id' => $producer->id]);
        $candidature1 = \App\Models\Candidature::factory()->create([
            'face_id' => $face->id,
            'mission_id' => $completedMission1->id,
        ]);
        $candidature2 = \App\Models\Candidature::factory()->create([
            'face_id' => $face->id,
            'mission_id' => $completedMission2->id,
        ]);

        Rating::create([
            'candidature_id' => $candidature1->id,
            'rated_type' => Producer::class,
            'rated_id' => $producer->id,
            'rater_id' => $faceUser->id,
            'score' => 4,
            'comment' => 'Great producer',
        ]);

        Rating::create([
            'candidature_id' => $candidature2->id,
            'rated_type' => Producer::class,
            'rated_id' => $producer->id,
            'rater_id' => $faceUser->id,
            'score' => 5,
            'comment' => 'Excellent',
        ]);

        $response = $this->getJson('/api/v1/public/missions');

        $response->assertOk();
        $producerData = $response->json('data.0.producer');

        $this->assertEquals(4.5, $producerData['average_rating']);
        $this->assertEquals(2, $producerData['ratings_count']);
    }

    public function test_producer_rating_is_null_when_no_ratings(): void
    {
        $producer = $this->createProducerWithUser();
        $this->createPublishedMission(['producer' => $producer]);

        $response = $this->getJson('/api/v1/public/missions');

        $response->assertOk();
        $producerData = $response->json('data.0.producer');

        $this->assertNull($producerData['average_rating']);
        $this->assertEquals(0, $producerData['ratings_count']);
    }

    public function test_success_message_is_returned(): void
    {
        $response = $this->getJson('/api/v1/public/missions');

        $response->assertOk();
        $this->assertEquals('Missions retrieved successfully', $response->json('message'));
    }

    // ─── Exclusion des missions UGC (FR5, UGC 2.1) ───────────────────

    public function test_ugc_missions_are_excluded_from_public_list(): void
    {
        $producer = $this->createProducerWithUser();
        $this->createPublishedMission(['producer' => $producer, 'titre' => 'Mission standard']);
        // La factory Mission ne tire jamais `ugc` — attributs explicites obligatoires.
        $producer->missions()->create([
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
            'status' => 'published',
            'commission_paid_at' => now(),
            'type_compensation' => 'product',
            'nom_produit' => 'Tenue Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'montant_remuneration' => null,
            'commission_ugc' => 2500,
        ]);

        $response = $this->getJson('/api/v1/public/missions');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.titre', 'Mission standard')
            ->assertJsonPath('meta.total', 1);
    }
}
