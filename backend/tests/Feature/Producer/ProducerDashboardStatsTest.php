<?php

declare(strict_types=1);

namespace Tests\Feature\Producer;

use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProducerDashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
    }

    public function test_authenticated_producer_can_access_dashboard_stats(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'published',
                    'in_progress',
                    'closed',
                    'completed',
                    'total_candidatures',
                    'unique_collaborators',
                    'average_rating',
                    'ratings_count',
                    'acceptance_rate',
                    'average_response_time_hours',
                    'completed_missions_count',
                ],
                'message',
            ]);
    }

    public function test_returns_correct_counts_for_each_status(): void
    {
        // Create missions with various statuses for this Producer
        $publishedMissions = Mission::factory()->count(3)->published()->create(['producer_id' => $this->producer->id]);
        $closedMissions = Mission::factory()->count(2)->closed()->create(['producer_id' => $this->producer->id]);
        Mission::factory()->count(5)->completed()->create(['producer_id' => $this->producer->id]);
        // Draft missions should not be counted in display stats
        Mission::factory()->count(1)->draft()->create(['producer_id' => $this->producer->id]);

        // Create a Face and candidatures for in_progress count
        $face = Face::factory()->create();

        // Add confirmed candidature to one closed mission (makes it "in progress")
        Candidature::factory()->confirmed()->create([
            'face_id' => $face->id,
            'mission_id' => $closedMissions[0]->id,
        ]);

        // Add in_progress candidature to one published mission (also counts as "in progress")
        Candidature::factory()->inProgress()->create([
            'face_id' => $face->id,
            'mission_id' => $publishedMissions[0]->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.published', 3)
            ->assertJsonPath('data.in_progress', 2) // 2 missions have confirmed/in_progress candidatures
            ->assertJsonPath('data.closed', 2)
            ->assertJsonPath('data.completed', 5);
    }

    public function test_returns_zeros_for_statuses_with_no_missions(): void
    {
        // Producer has no missions at all
        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.published', 0)
            ->assertJsonPath('data.in_progress', 0)
            ->assertJsonPath('data.closed', 0)
            ->assertJsonPath('data.completed', 0);
    }

    public function test_only_counts_missions_belonging_to_authenticated_producer(): void
    {
        // Create missions for this Producer
        Mission::factory()->count(2)->published()->create(['producer_id' => $this->producer->id]);

        // Create missions for another Producer (should not be counted)
        $otherProducer = Producer::factory()->create();
        Mission::factory()->count(5)->published()->create(['producer_id' => $otherProducer->id]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.published', 2); // Only this Producer's missions
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $response = $this->getJson('/api/v1/producer/dashboard/stats');

        $response->assertUnauthorized();
    }

    public function test_face_user_gets_403(): void
    {
        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $response = $this->actingAs($faceUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', 'Accès réservé aux Producteurs');
    }

    public function test_draft_missions_are_excluded_from_stats(): void
    {
        // Create only draft missions
        Mission::factory()->count(5)->draft()->create(['producer_id' => $this->producer->id]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.published', 0)
            ->assertJsonPath('data.in_progress', 0)
            ->assertJsonPath('data.closed', 0)
            ->assertJsonPath('data.completed', 0);
    }

    public function test_response_includes_success_message(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('message', 'Dashboard stats retrieved successfully');
    }

    public function test_in_progress_counts_missions_with_active_candidatures(): void
    {
        $face = Face::factory()->create();

        // Create missions without active candidatures
        $mission1 = Mission::factory()->published()->create(['producer_id' => $this->producer->id]);
        $mission2 = Mission::factory()->closed()->create(['producer_id' => $this->producer->id]);

        // No active candidatures yet - in_progress should be 0
        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.in_progress', 0);

        // Add confirmed candidature - now in_progress should be 1
        Candidature::factory()->confirmed()->create([
            'face_id' => $face->id,
            'mission_id' => $mission1->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.in_progress', 1);

        // Add in_progress candidature to another mission - now in_progress should be 2
        Candidature::factory()->inProgress()->create([
            'face_id' => $face->id,
            'mission_id' => $mission2->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.in_progress', 2);
    }

    public function test_in_progress_does_not_count_pending_or_accepted_candidatures(): void
    {
        $face = Face::factory()->create();
        $mission = Mission::factory()->published()->create(['producer_id' => $this->producer->id]);

        // Pending candidature should not count as in_progress
        Candidature::factory()->pending()->create([
            'face_id' => $face->id,
            'mission_id' => $mission->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.in_progress', 0);

        // Accepted candidature should not count as in_progress either
        $face2 = Face::factory()->create();
        Candidature::factory()->accepted()->create([
            'face_id' => $face2->id,
            'mission_id' => $mission->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.in_progress', 0);
    }

    // ============================================
    // Tests for total_candidatures (FR56)
    // ============================================

    public function test_stats_includes_total_candidatures_count(): void
    {
        // Create missions for this Producer
        $mission1 = Mission::factory()->published()->create(['producer_id' => $this->producer->id]);
        $mission2 = Mission::factory()->closed()->create(['producer_id' => $this->producer->id]);

        // Create candidatures with various statuses - ALL should be counted
        // Note: unique constraint on (face_id, mission_id) means one face per mission
        $face1 = Face::factory()->create();
        $face2 = Face::factory()->create();
        $face3 = Face::factory()->create();
        Candidature::factory()->pending()->create(['face_id' => $face1->id, 'mission_id' => $mission1->id]);
        Candidature::factory()->accepted()->create(['face_id' => $face2->id, 'mission_id' => $mission1->id]);
        Candidature::factory()->rejected()->create(['face_id' => $face3->id, 'mission_id' => $mission2->id]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.total_candidatures', 3); // All 3 candidatures counted
    }

    public function test_total_candidatures_includes_all_statuses(): void
    {
        $mission = Mission::factory()->published()->create(['producer_id' => $this->producer->id]);

        // Create candidatures with ALL possible statuses
        $faces = Face::factory()->count(6)->create();

        Candidature::factory()->pending()->create(['face_id' => $faces[0]->id, 'mission_id' => $mission->id]);
        Candidature::factory()->accepted()->create(['face_id' => $faces[1]->id, 'mission_id' => $mission->id]);
        Candidature::factory()->confirmed()->create(['face_id' => $faces[2]->id, 'mission_id' => $mission->id]);
        Candidature::factory()->inProgress()->create(['face_id' => $faces[3]->id, 'mission_id' => $mission->id]);
        Candidature::factory()->completed()->create(['face_id' => $faces[4]->id, 'mission_id' => $mission->id]);
        Candidature::factory()->rejected()->create(['face_id' => $faces[5]->id, 'mission_id' => $mission->id]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.total_candidatures', 6); // All 6 statuses counted
    }

    public function test_total_candidatures_returns_zero_when_no_candidatures(): void
    {
        // Create missions but no candidatures
        Mission::factory()->published()->create(['producer_id' => $this->producer->id]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.total_candidatures', 0);
    }

    public function test_total_candidatures_only_counts_own_missions(): void
    {
        // Create candidature for this Producer's mission
        $myMission = Mission::factory()->published()->create(['producer_id' => $this->producer->id]);
        $face = Face::factory()->create();
        Candidature::factory()->pending()->create(['face_id' => $face->id, 'mission_id' => $myMission->id]);

        // Create candidatures for another Producer's mission (should NOT be counted)
        $otherProducer = Producer::factory()->create();
        $otherMission = Mission::factory()->published()->create(['producer_id' => $otherProducer->id]);
        Candidature::factory()->count(5)->create(['mission_id' => $otherMission->id]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.total_candidatures', 1); // Only my candidature
    }

    public function test_total_candidatures_aggregates_across_multiple_missions(): void
    {
        // Create multiple missions for this Producer
        $mission1 = Mission::factory()->published()->create(['producer_id' => $this->producer->id]);
        $mission2 = Mission::factory()->closed()->create(['producer_id' => $this->producer->id]);
        $mission3 = Mission::factory()->completed()->create(['producer_id' => $this->producer->id]);

        // Create candidatures across all missions (factory creates unique faces automatically)
        Candidature::factory()->count(3)->create(['mission_id' => $mission1->id]);
        Candidature::factory()->count(2)->create(['mission_id' => $mission2->id]);
        Candidature::factory()->count(4)->create(['mission_id' => $mission3->id]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.total_candidatures', 9); // 3 + 2 + 4 = 9
    }

    // ============================================
    // Tests for unique_collaborators (FR57)
    // ============================================

    public function test_stats_includes_unique_collaborators_count(): void
    {
        $mission = Mission::factory()->completed()->create(['producer_id' => $this->producer->id]);
        $face = Face::factory()->create();

        // Create a completed candidature
        Candidature::factory()->completed()->create([
            'face_id' => $face->id,
            'mission_id' => $mission->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.unique_collaborators', 1);
    }

    public function test_unique_collaborators_only_counts_completed_candidatures(): void
    {
        $mission = Mission::factory()->published()->create(['producer_id' => $this->producer->id]);
        $faces = Face::factory()->count(6)->create();

        // Create candidatures with ALL possible statuses
        Candidature::factory()->pending()->create(['face_id' => $faces[0]->id, 'mission_id' => $mission->id]);
        Candidature::factory()->accepted()->create(['face_id' => $faces[1]->id, 'mission_id' => $mission->id]);
        Candidature::factory()->confirmed()->create(['face_id' => $faces[2]->id, 'mission_id' => $mission->id]);
        Candidature::factory()->inProgress()->create(['face_id' => $faces[3]->id, 'mission_id' => $mission->id]);
        Candidature::factory()->completed()->create(['face_id' => $faces[4]->id, 'mission_id' => $mission->id]); // Only this counts
        Candidature::factory()->rejected()->create(['face_id' => $faces[5]->id, 'mission_id' => $mission->id]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.unique_collaborators', 1); // Only the completed one
    }

    public function test_unique_collaborators_counts_same_face_only_once(): void
    {
        // Create multiple missions
        $mission1 = Mission::factory()->completed()->create(['producer_id' => $this->producer->id]);
        $mission2 = Mission::factory()->completed()->create(['producer_id' => $this->producer->id]);
        $mission3 = Mission::factory()->completed()->create(['producer_id' => $this->producer->id]);

        // Same Face worked on all 3 missions
        $face = Face::factory()->create();
        Candidature::factory()->completed()->create(['face_id' => $face->id, 'mission_id' => $mission1->id]);
        Candidature::factory()->completed()->create(['face_id' => $face->id, 'mission_id' => $mission2->id]);
        Candidature::factory()->completed()->create(['face_id' => $face->id, 'mission_id' => $mission3->id]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.unique_collaborators', 1); // Same Face = 1 collaborator
    }

    public function test_unique_collaborators_counts_multiple_distinct_faces(): void
    {
        $mission1 = Mission::factory()->completed()->create(['producer_id' => $this->producer->id]);
        $mission2 = Mission::factory()->completed()->create(['producer_id' => $this->producer->id]);

        // 3 different Faces worked with the Producer
        $face1 = Face::factory()->create();
        $face2 = Face::factory()->create();
        $face3 = Face::factory()->create();

        Candidature::factory()->completed()->create(['face_id' => $face1->id, 'mission_id' => $mission1->id]);
        Candidature::factory()->completed()->create(['face_id' => $face2->id, 'mission_id' => $mission1->id]);
        Candidature::factory()->completed()->create(['face_id' => $face3->id, 'mission_id' => $mission2->id]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.unique_collaborators', 3); // 3 unique Faces
    }

    public function test_unique_collaborators_returns_zero_when_no_completed_candidatures(): void
    {
        $mission = Mission::factory()->published()->create(['producer_id' => $this->producer->id]);
        $face = Face::factory()->create();

        // Create non-completed candidatures
        Candidature::factory()->pending()->create(['face_id' => $face->id, 'mission_id' => $mission->id]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.unique_collaborators', 0);
    }

    public function test_unique_collaborators_only_counts_own_missions(): void
    {
        // Create completed candidature for this Producer's mission
        $myMission = Mission::factory()->completed()->create(['producer_id' => $this->producer->id]);
        $face = Face::factory()->create();
        Candidature::factory()->completed()->create(['face_id' => $face->id, 'mission_id' => $myMission->id]);

        // Create completed candidatures for another Producer's mission (should NOT be counted)
        $otherProducer = Producer::factory()->create();
        $otherMission = Mission::factory()->completed()->create(['producer_id' => $otherProducer->id]);
        $otherFaces = Face::factory()->count(5)->create();
        foreach ($otherFaces as $otherFace) {
            Candidature::factory()->completed()->create(['face_id' => $otherFace->id, 'mission_id' => $otherMission->id]);
        }

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.unique_collaborators', 1); // Only my collaborator
    }

    public function test_unique_collaborators_aggregates_across_multiple_missions(): void
    {
        // Create multiple missions
        $mission1 = Mission::factory()->completed()->create(['producer_id' => $this->producer->id]);
        $mission2 = Mission::factory()->completed()->create(['producer_id' => $this->producer->id]);

        // Face1 worked on mission1
        $face1 = Face::factory()->create();
        Candidature::factory()->completed()->create(['face_id' => $face1->id, 'mission_id' => $mission1->id]);

        // Face2 worked on both missions (should count only once)
        $face2 = Face::factory()->create();
        Candidature::factory()->completed()->create(['face_id' => $face2->id, 'mission_id' => $mission1->id]);
        Candidature::factory()->completed()->create(['face_id' => $face2->id, 'mission_id' => $mission2->id]);

        // Face3 worked only on mission2
        $face3 = Face::factory()->create();
        Candidature::factory()->completed()->create(['face_id' => $face3->id, 'mission_id' => $mission2->id]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.unique_collaborators', 3); // Face1 + Face2 + Face3 = 3 unique
    }

    // ============================================
    // Tests for average_rating and ratings_count (FR58)
    // ============================================

    public function test_stats_includes_average_rating_and_ratings_count(): void
    {
        // Create a Face with User to rate the Producer
        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        // Create a completed mission and candidature
        $mission = Mission::factory()->completed()->create(['producer_id' => $this->producer->id]);
        $candidature = Candidature::factory()->completed()->create([
            'face_id' => $face->id,
            'mission_id' => $mission->id,
        ]);

        // Create a rating from Face to Producer
        Rating::factory()->create([
            'candidature_id' => $candidature->id,
            'rater_id' => $faceUser->id,
            'rated_id' => $this->producer->id,
            'rated_type' => Producer::class,
            'score' => 4,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.ratings_count', 1);

        // Check average_rating is 4 (integer or float comparison)
        $this->assertEquals(4, $response->json('data.average_rating'));
    }

    public function test_stats_returns_null_average_rating_when_no_ratings(): void
    {
        // Producer has no ratings
        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.average_rating', null)
            ->assertJsonPath('data.ratings_count', 0);
    }

    public function test_average_rating_is_calculated_correctly(): void
    {
        // Create 2 Faces to rate the Producer
        $face1 = Face::factory()->create();
        $faceUser1 = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face1->id,
        ]);
        $face2 = Face::factory()->create();
        $faceUser2 = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face2->id,
        ]);

        // Create a completed mission with 2 candidatures
        $mission = Mission::factory()->completed()->create(['producer_id' => $this->producer->id]);
        $candidature1 = Candidature::factory()->completed()->create([
            'face_id' => $face1->id,
            'mission_id' => $mission->id,
        ]);
        $candidature2 = Candidature::factory()->completed()->create([
            'face_id' => $face2->id,
            'mission_id' => $mission->id,
        ]);

        // Create ratings: 3 stars + 5 stars = average 4.0
        Rating::factory()->create([
            'candidature_id' => $candidature1->id,
            'rater_id' => $faceUser1->id,
            'rated_id' => $this->producer->id,
            'rated_type' => Producer::class,
            'score' => 3,
        ]);
        Rating::factory()->create([
            'candidature_id' => $candidature2->id,
            'rater_id' => $faceUser2->id,
            'rated_id' => $this->producer->id,
            'rated_type' => Producer::class,
            'score' => 5,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.ratings_count', 2);

        // Check average_rating is 4 (3+5)/2 = 4
        $this->assertEquals(4, $response->json('data.average_rating'));
    }

    public function test_stats_excludes_ratings_for_other_producers(): void
    {
        // Create a Face with User to rate the Producer
        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        // Create a rating for this Producer (5 stars)
        $mission = Mission::factory()->completed()->create(['producer_id' => $this->producer->id]);
        $candidature = Candidature::factory()->completed()->create([
            'face_id' => $face->id,
            'mission_id' => $mission->id,
        ]);
        Rating::factory()->create([
            'candidature_id' => $candidature->id,
            'rater_id' => $faceUser->id,
            'rated_id' => $this->producer->id,
            'rated_type' => Producer::class,
            'score' => 5,
        ]);

        // Create a rating for ANOTHER Producer (1 star - should NOT be counted)
        $otherProducer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $otherProducer->id,
        ]);
        $otherMission = Mission::factory()->completed()->create(['producer_id' => $otherProducer->id]);
        $face2 = Face::factory()->create();
        $faceUser2 = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face2->id,
        ]);
        $otherCandidature = Candidature::factory()->completed()->create([
            'face_id' => $face2->id,
            'mission_id' => $otherMission->id,
        ]);
        Rating::factory()->create([
            'candidature_id' => $otherCandidature->id,
            'rater_id' => $faceUser2->id,
            'rated_id' => $otherProducer->id,
            'rated_type' => Producer::class,
            'score' => 1,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.ratings_count', 1);

        // Check average_rating is 5 (only my 5-star rating)
        $this->assertEquals(5, $response->json('data.average_rating'));
    }

    public function test_average_rating_with_multiple_ratings(): void
    {
        // Create 4 Faces to rate the Producer with different scores
        $mission = Mission::factory()->completed()->create(['producer_id' => $this->producer->id]);
        $scores = [2, 3, 4, 5]; // Average = 3.5

        foreach ($scores as $score) {
            $face = Face::factory()->create();
            $faceUser = User::factory()->create([
                'userable_type' => Face::class,
                'userable_id' => $face->id,
            ]);
            $candidature = Candidature::factory()->completed()->create([
                'face_id' => $face->id,
                'mission_id' => $mission->id,
            ]);
            Rating::factory()->create([
                'candidature_id' => $candidature->id,
                'rater_id' => $faceUser->id,
                'rated_id' => $this->producer->id,
                'rated_type' => Producer::class,
                'score' => $score,
            ]);
        }

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.average_rating', 3.5) // (2+3+4+5)/4 = 3.5
            ->assertJsonPath('data.ratings_count', 4);
    }

    // ============================================
    // Tests for acceptance_rate and average_response_time_hours (FR59)
    // ============================================

    public function test_stats_includes_acceptance_rate_field(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'published',
                    'in_progress',
                    'closed',
                    'completed',
                    'total_candidatures',
                    'unique_collaborators',
                    'average_rating',
                    'ratings_count',
                    'acceptance_rate',
                    'average_response_time_hours',
                    'completed_missions_count', // AC #5 explicit field requirement
                ],
            ]);
    }

    public function test_completed_missions_count_matches_completed_field(): void
    {
        // Create 5 completed missions
        Mission::factory()->count(5)->completed()->create(['producer_id' => $this->producer->id]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.completed', 5)
            ->assertJsonPath('data.completed_missions_count', 5); // Must match completed
    }

    public function test_acceptance_rate_is_fifty_percent_with_two_accepted_out_of_four(): void
    {
        $mission = Mission::factory()->published()->create(['producer_id' => $this->producer->id]);

        // Create 4 candidatures: 2 accepted, 2 rejected = 50% acceptance rate
        $faces = Face::factory()->count(4)->create();
        Candidature::factory()->accepted()->create(['face_id' => $faces[0]->id, 'mission_id' => $mission->id]);
        Candidature::factory()->completed()->create(['face_id' => $faces[1]->id, 'mission_id' => $mission->id]);
        Candidature::factory()->rejected()->create(['face_id' => $faces[2]->id, 'mission_id' => $mission->id]);
        Candidature::factory()->rejected()->create(['face_id' => $faces[3]->id, 'mission_id' => $mission->id]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk();
        $this->assertEquals(50.0, $response->json('data.acceptance_rate'));
    }

    public function test_acceptance_rate_is_zero_when_all_candidatures_rejected(): void
    {
        $mission = Mission::factory()->published()->create(['producer_id' => $this->producer->id]);

        // Create 3 rejected candidatures = 0% acceptance rate
        $faces = Face::factory()->count(3)->create();
        Candidature::factory()->rejected()->create(['face_id' => $faces[0]->id, 'mission_id' => $mission->id]);
        Candidature::factory()->rejected()->create(['face_id' => $faces[1]->id, 'mission_id' => $mission->id]);
        Candidature::factory()->rejected()->create(['face_id' => $faces[2]->id, 'mission_id' => $mission->id]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk();
        $this->assertEquals(0.0, $response->json('data.acceptance_rate'));
    }

    public function test_acceptance_rate_is_zero_not_null_when_no_candidatures(): void
    {
        // Producer has missions but no candidatures
        Mission::factory()->published()->create(['producer_id' => $this->producer->id]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk();
        // Must be 0 (numeric), not null
        $acceptanceRate = $response->json('data.acceptance_rate');
        $this->assertNotNull($acceptanceRate);
        $this->assertEquals(0, $acceptanceRate);
    }

    public function test_acceptance_rate_is_hundred_percent_when_all_accepted(): void
    {
        $mission = Mission::factory()->published()->create(['producer_id' => $this->producer->id]);

        // Create 3 accepted candidatures (various accepted statuses) = 100%
        $faces = Face::factory()->count(3)->create();
        Candidature::factory()->accepted()->create(['face_id' => $faces[0]->id, 'mission_id' => $mission->id]);
        Candidature::factory()->confirmed()->create(['face_id' => $faces[1]->id, 'mission_id' => $mission->id]);
        Candidature::factory()->completed()->create(['face_id' => $faces[2]->id, 'mission_id' => $mission->id]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk();
        $this->assertEquals(100.0, $response->json('data.acceptance_rate'));
    }

    public function test_acceptance_rate_includes_all_accepted_statuses(): void
    {
        $mission = Mission::factory()->published()->create(['producer_id' => $this->producer->id]);

        // Create 5 candidatures: 4 "accepted" statuses + 1 pending = 80%
        $faces = Face::factory()->count(5)->create();
        Candidature::factory()->accepted()->create(['face_id' => $faces[0]->id, 'mission_id' => $mission->id]);
        Candidature::factory()->confirmed()->create(['face_id' => $faces[1]->id, 'mission_id' => $mission->id]);
        Candidature::factory()->inProgress()->create(['face_id' => $faces[2]->id, 'mission_id' => $mission->id]);
        Candidature::factory()->completed()->create(['face_id' => $faces[3]->id, 'mission_id' => $mission->id]);
        Candidature::factory()->pending()->create(['face_id' => $faces[4]->id, 'mission_id' => $mission->id]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk();
        $this->assertEquals(80.0, $response->json('data.acceptance_rate'));
    }

    public function test_acceptance_rate_only_counts_own_producer_candidatures(): void
    {
        // Create candidature for this Producer (rejected)
        $myMission = Mission::factory()->published()->create(['producer_id' => $this->producer->id]);
        $face = Face::factory()->create();
        Candidature::factory()->rejected()->create(['face_id' => $face->id, 'mission_id' => $myMission->id]);

        // Create candidatures for another Producer (all accepted - should NOT affect our rate)
        $otherProducer = Producer::factory()->create();
        $otherMission = Mission::factory()->published()->create(['producer_id' => $otherProducer->id]);
        $otherFaces = Face::factory()->count(5)->create();
        foreach ($otherFaces as $otherFace) {
            Candidature::factory()->accepted()->create(['face_id' => $otherFace->id, 'mission_id' => $otherMission->id]);
        }

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk();
        // Only my 1 rejected candidature = 0%
        $this->assertEquals(0.0, $response->json('data.acceptance_rate'));
    }

    public function test_average_response_time_is_calculated_correctly(): void
    {
        $mission = Mission::factory()->published()->create(['producer_id' => $this->producer->id]);
        $face = Face::factory()->create();

        // Create candidature and manually set timestamps using raw DB update
        // Created 2 hours ago, updated (decision made) now = 2 hours response time
        $candidature = Candidature::factory()->accepted()->create([
            'face_id' => $face->id,
            'mission_id' => $mission->id,
        ]);

        // Use raw DB update to set exact timestamps without model events
        $now = now();
        $twoHoursAgo = $now->copy()->subHours(2);
        \DB::table('candidatures')
            ->where('id', $candidature->id)
            ->update([
                'created_at' => $twoHoursAgo,
                'updated_at' => $now,
            ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk();
        // Should be approximately 2.0 hours (may have slight variance due to test execution time)
        $responseTime = $response->json('data.average_response_time_hours');
        $this->assertNotNull($responseTime);
        $this->assertGreaterThanOrEqual(1.9, $responseTime);
        $this->assertLessThanOrEqual(2.1, $responseTime);
    }

    public function test_average_response_time_is_null_when_no_decisions_made(): void
    {
        $mission = Mission::factory()->published()->create(['producer_id' => $this->producer->id]);
        $face = Face::factory()->create();

        // Only pending candidatures - no decisions made yet
        Candidature::factory()->pending()->create([
            'face_id' => $face->id,
            'mission_id' => $mission->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.average_response_time_hours', null);
    }

    public function test_average_response_time_includes_both_accepted_and_rejected(): void
    {
        $mission = Mission::factory()->published()->create(['producer_id' => $this->producer->id]);
        $now = now();

        // Create 2 candidatures: 1 accepted (1 hour), 1 rejected (3 hours) = avg 2 hours
        $face1 = Face::factory()->create();
        $candidature1 = Candidature::factory()->accepted()->create([
            'face_id' => $face1->id,
            'mission_id' => $mission->id,
        ]);
        \DB::table('candidatures')
            ->where('id', $candidature1->id)
            ->update([
                'created_at' => $now->copy()->subHour(),
                'updated_at' => $now,
            ]);

        $face2 = Face::factory()->create();
        $candidature2 = Candidature::factory()->rejected()->create([
            'face_id' => $face2->id,
            'mission_id' => $mission->id,
        ]);
        \DB::table('candidatures')
            ->where('id', $candidature2->id)
            ->update([
                'created_at' => $now->copy()->subHours(3),
                'updated_at' => $now,
            ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk();
        // Average of 1 hour and 3 hours = 2 hours
        $responseTime = $response->json('data.average_response_time_hours');
        $this->assertNotNull($responseTime);
        $this->assertGreaterThanOrEqual(1.9, $responseTime);
        $this->assertLessThanOrEqual(2.1, $responseTime);
    }

    public function test_average_response_time_only_counts_own_producer(): void
    {
        $now = now();

        // Create candidature for this Producer (1 hour response)
        $myMission = Mission::factory()->published()->create(['producer_id' => $this->producer->id]);
        $face = Face::factory()->create();
        $candidature = Candidature::factory()->accepted()->create([
            'face_id' => $face->id,
            'mission_id' => $myMission->id,
        ]);
        \DB::table('candidatures')
            ->where('id', $candidature->id)
            ->update([
                'created_at' => $now->copy()->subHour(),
                'updated_at' => $now,
            ]);

        // Create candidatures for another Producer (10 hours response - should NOT affect our time)
        $otherProducer = Producer::factory()->create();
        $otherMission = Mission::factory()->published()->create(['producer_id' => $otherProducer->id]);
        $otherFace = Face::factory()->create();
        $otherCandidature = Candidature::factory()->accepted()->create([
            'face_id' => $otherFace->id,
            'mission_id' => $otherMission->id,
        ]);
        \DB::table('candidatures')
            ->where('id', $otherCandidature->id)
            ->update([
                'created_at' => $now->copy()->subHours(10),
                'updated_at' => $now,
            ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk();
        // Only my 1 hour response time
        $responseTime = $response->json('data.average_response_time_hours');
        $this->assertNotNull($responseTime);
        $this->assertGreaterThanOrEqual(0.9, $responseTime);
        $this->assertLessThanOrEqual(1.1, $responseTime);
    }

    public function test_acceptance_rate_has_one_decimal_precision(): void
    {
        $mission = Mission::factory()->published()->create(['producer_id' => $this->producer->id]);

        // Create 3 candidatures: 1 accepted, 2 rejected = 33.3%
        $faces = Face::factory()->count(3)->create();
        Candidature::factory()->accepted()->create(['face_id' => $faces[0]->id, 'mission_id' => $mission->id]);
        Candidature::factory()->rejected()->create(['face_id' => $faces[1]->id, 'mission_id' => $mission->id]);
        Candidature::factory()->rejected()->create(['face_id' => $faces[2]->id, 'mission_id' => $mission->id]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk();
        // 1/3 = 33.333... rounded to 1 decimal = 33.3
        $this->assertEquals(33.3, $response->json('data.acceptance_rate'));
    }
}
