<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Enums\MissionStatus;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaceBrowseMissionsTest extends TestCase
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

    public function test_face_can_list_published_missions(): void
    {
        // Create published missions
        Mission::factory()->count(3)->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'titre',
                        'description',
                        'date_tournage',
                        'budget',
                        'lieu',
                        'type_mission',
                        'nombre_faces_voulu',
                        'status',
                        'status_label',
                        'producer',
                    ],
                ],
                'links',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    public function test_pagination_returns_12_per_page(): void
    {
        // Create 15 published missions
        Mission::factory()->count(15)->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions');

        $response->assertStatus(200)
            ->assertJsonCount(12, 'data')
            ->assertJsonPath('meta.per_page', 12)
            ->assertJsonPath('meta.total', 15)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_missions_ordered_by_newest_first(): void
    {
        // Create missions with different dates
        $oldMission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'titre' => 'Old Mission',
            'created_at' => now()->subDays(5),
        ]);

        $newMission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'titre' => 'New Mission',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals('New Mission', $data[0]['titre']);
        $this->assertEquals('Old Mission', $data[1]['titre']);
    }

    public function test_only_published_missions_are_returned(): void
    {
        // Create missions with different statuses
        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'titre' => 'Published Mission',
        ]);

        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Draft,
            'titre' => 'Draft Mission',
        ]);

        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Closed,
            'titre' => 'Closed Mission',
        ]);

        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Completed,
            'titre' => 'Completed Mission',
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.titre', 'Published Mission');
    }

    public function test_producer_cannot_access_face_missions_endpoint(): void
    {
        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/face/missions');

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', 'Cette action n\'est pas autorisée');
    }

    public function test_unauthenticated_user_cannot_access(): void
    {
        $response = $this->getJson('/api/v1/face/missions');

        $response->assertStatus(401);
    }

    public function test_response_includes_producer_data(): void
    {
        $producer = Producer::factory()->create([
            'type' => 'agency',
            'agency_name' => 'Studio XYZ',
        ]);
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Published,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.producer.agency_name', 'Studio XYZ')
            ->assertJsonPath('data.0.producer.type', 'agency');
    }

    public function test_empty_list_returns_empty_array_with_message(): void
    {
        // No missions created

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('message', 'Aucune mission disponible pour le moment')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_pagination_second_page(): void
    {
        // Create 15 published missions
        Mission::factory()->count(15)->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions?page=2');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_mission_card_has_required_fields(): void
    {
        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'titre' => 'Test Mission',
            'description' => 'Test Description',
            'date_tournage' => '2026-02-15',
            'budget' => 150000,
            'lieu' => 'Cotonou',
            'type_mission' => 'publicite',
            'nombre_faces_voulu' => 3,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.titre', 'Test Mission')
            ->assertJsonPath('data.0.description', 'Test Description')
            ->assertJsonPath('data.0.budget', 150000)
            ->assertJsonPath('data.0.lieu', 'Cotonou')
            ->assertJsonPath('data.0.type_mission', 'publicite')
            ->assertJsonPath('data.0.nombre_faces_voulu', 3)
            ->assertJsonPath('data.0.status', 'published')
            ->assertJsonPath('data.0.status_label', 'Publiée');

        // Check date_tournage exists and is in ISO 8601 format
        $data = $response->json('data.0');
        $this->assertNotNull($data['date_tournage']);
        $this->assertStringContainsString('2026-02-15', $data['date_tournage']);
    }

    // ========================================
    // Filter Tests (Story 5-9)
    // ========================================

    public function test_filter_by_lieu_returns_matching_missions(): void
    {
        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'lieu' => 'Cotonou',
            'titre' => 'Cotonou Mission',
        ]);

        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'lieu' => 'Porto-Novo',
            'titre' => 'Porto-Novo Mission',
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions?lieu=Cotonou');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.lieu', 'Cotonou')
            ->assertJsonPath('data.0.titre', 'Cotonou Mission');
    }

    public function test_filter_by_lieu_is_case_insensitive_partial_match(): void
    {
        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'lieu' => 'Cotonou Centre',
            'titre' => 'Centre Mission',
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions?lieu=cotonou');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.titre', 'Centre Mission');
    }

    public function test_filter_by_budget_min_returns_missions_above_minimum(): void
    {
        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'budget' => 50000,
            'titre' => 'Low Budget',
        ]);

        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'budget' => 100000,
            'titre' => 'Medium Budget',
        ]);

        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'budget' => 200000,
            'titre' => 'High Budget',
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions?budget_min=100000');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $budgets = collect($response->json('data'))->pluck('budget')->all();
        $this->assertContains(100000, $budgets);
        $this->assertContains(200000, $budgets);
        $this->assertNotContains(50000, $budgets);
    }

    public function test_filter_by_budget_max_returns_missions_below_maximum(): void
    {
        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'budget' => 50000,
            'titre' => 'Low Budget',
        ]);

        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'budget' => 100000,
            'titre' => 'Medium Budget',
        ]);

        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'budget' => 200000,
            'titre' => 'High Budget',
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions?budget_max=100000');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $budgets = collect($response->json('data'))->pluck('budget')->all();
        $this->assertContains(50000, $budgets);
        $this->assertContains(100000, $budgets);
        $this->assertNotContains(200000, $budgets);
    }

    public function test_filter_by_budget_range_returns_missions_within_range(): void
    {
        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'budget' => 50000,
            'titre' => 'Low Budget',
        ]);

        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'budget' => 100000,
            'titre' => 'Medium Budget',
        ]);

        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'budget' => 200000,
            'titre' => 'High Budget',
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions?budget_min=75000&budget_max=150000');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.budget', 100000);
    }

    public function test_filter_by_date_tournage_returns_missions_on_or_after_date(): void
    {
        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'date_tournage' => '2026-01-15',
            'titre' => 'January Mission',
        ]);

        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'date_tournage' => '2026-02-15',
            'titre' => 'February Mission',
        ]);

        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'date_tournage' => '2026-03-15',
            'titre' => 'March Mission',
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions?date_tournage=2026-02-01');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $titles = collect($response->json('data'))->pluck('titre')->all();
        $this->assertContains('February Mission', $titles);
        $this->assertContains('March Mission', $titles);
        $this->assertNotContains('January Mission', $titles);
    }

    public function test_filter_by_type_mission_returns_matching_type(): void
    {
        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'type_mission' => 'publicite',
            'titre' => 'Publicite Mission',
        ]);

        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'type_mission' => 'film',
            'titre' => 'Film Mission',
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions?type_mission=publicite');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type_mission', 'publicite')
            ->assertJsonPath('data.0.titre', 'Publicite Mission');
    }

    public function test_multiple_filters_combined_with_and_logic(): void
    {
        // Mission that matches all filters
        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'lieu' => 'Cotonou',
            'budget' => 100000,
            'type_mission' => 'publicite',
            'titre' => 'Perfect Match',
        ]);

        // Mission that matches only lieu
        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'lieu' => 'Cotonou',
            'budget' => 50000,
            'type_mission' => 'film',
            'titre' => 'Only Lieu Match',
        ]);

        // Mission that matches only type
        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'lieu' => 'Porto-Novo',
            'budget' => 100000,
            'type_mission' => 'publicite',
            'titre' => 'Only Type Match',
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions?lieu=Cotonou&budget_min=75000&type_mission=publicite');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.titre', 'Perfect Match');
    }

    public function test_no_matching_filters_returns_empty_with_message(): void
    {
        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'lieu' => 'Cotonou',
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions?lieu=NonexistentCity');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('message', 'Aucune mission ne correspond à vos critères');
    }

    public function test_filter_validation_rejects_invalid_date_format(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions?date_tournage=15-02-2026');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_tournage']);
    }

    public function test_filter_validation_rejects_invalid_type_mission(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions?type_mission=invalid_type');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type_mission']);
    }

    public function test_filter_validation_rejects_negative_budget(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions?budget_min=-1000');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['budget_min']);
    }

    public function test_filter_validation_rejects_budget_max_less_than_min(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions?budget_min=100000&budget_max=50000');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['budget_max']);
    }

    public function test_filters_work_with_pagination(): void
    {
        // Create 15 Cotonou missions
        Mission::factory()->count(15)->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'lieu' => 'Cotonou',
        ]);

        // Create 5 Porto-Novo missions (should not be in filtered results)
        Mission::factory()->count(5)->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'lieu' => 'Porto-Novo',
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions?lieu=Cotonou');

        $response->assertStatus(200)
            ->assertJsonCount(12, 'data')
            ->assertJsonPath('meta.total', 15)
            ->assertJsonPath('meta.last_page', 2);

        // Check second page
        $responsePage2 = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions?lieu=Cotonou&page=2');

        $responsePage2->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }

    // ========================================
    // Producer Rating Exposure Tests (Story 8-7)
    // ========================================

    public function test_producer_rating_included_in_mission_list(): void
    {
        $producer = Producer::factory()->create([
            'type' => 'agency',
            'agency_name' => 'Rated Studio',
        ]);
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Published,
        ]);

        // Create ratings for the producer (average should be 4.0)
        \App\Models\Rating::factory()
            ->faceRatingProducer()
            ->ratingProducer($producer)
            ->score(5)
            ->create();
        \App\Models\Rating::factory()
            ->faceRatingProducer()
            ->ratingProducer($producer)
            ->score(3)
            ->create();

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'producer' => [
                            'id',
                            'average_rating',
                            'ratings_count',
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.0.producer.ratings_count', 2);

        // Check average rating value (4.0 = average of 5 and 3)
        $this->assertEquals(4.0, $response->json('data.0.producer.average_rating'));
    }

    public function test_producer_with_no_ratings_returns_null_average(): void
    {
        $producer = Producer::factory()->create([
            'type' => 'particulier',
        ]);
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Published,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.producer.average_rating', null)
            ->assertJsonPath('data.0.producer.ratings_count', 0);
    }

    public function test_producer_with_single_rating_returns_correct_values(): void
    {
        $producer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Published,
        ]);

        // Create single 5-star rating
        \App\Models\Rating::factory()
            ->faceRatingProducer()
            ->ratingProducer($producer)
            ->score(5)
            ->create();

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.producer.ratings_count', 1);

        // Check average rating value
        $this->assertEquals(5.0, $response->json('data.0.producer.average_rating'));
    }

    // ===================================================================
    // Exclusion des missions UGC de la liste standard (FR5, UGC 2.1)
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

    public function test_ugc_missions_are_excluded_from_standard_listing(): void
    {
        $this->makePublishedUgcMission();
        $standard = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $standard->uuid);
    }

    public function test_type_mission_ugc_filter_returns_empty_list(): void
    {
        $this->makePublishedUgcMission();

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions?type_mission=ugc');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    // ─── Filtre producteur is_active (story 3.0) ─────────────────────

    public function test_missions_from_inactive_producer_are_excluded(): void
    {
        $visibleMission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
        ]);

        $inactiveProducer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $inactiveProducer->id,
            'is_active' => false,
        ]);
        Mission::factory()->create([
            'producer_id' => $inactiveProducer->id,
            'status' => MissionStatus::Published,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visibleMission->uuid);
    }

    public function test_missions_from_producer_without_user_are_excluded(): void
    {
        // Témoin (review 3.0) : le scope exclut aussi les producteurs sans
        // AUCUNE ligne User — l'invariant prod « tout producteur a un User »
        // n'est pas garanti par la factory (Producer::factory() seul = orphelin).
        $visibleMission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
        ]);

        $orphanProducer = Producer::factory()->create();
        Mission::factory()->create([
            'producer_id' => $orphanProducer->id,
            'status' => MissionStatus::Published,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visibleMission->uuid);
    }
}
