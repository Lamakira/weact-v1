<?php

declare(strict_types=1);

namespace Tests\Feature\Rating;

use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaceRatingDisplayTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    private Face $face;

    private User $faceUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Producer with User
        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);

        // Create Face with User
        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    // ========================================================================
    // Task 1 & 6: Face API includes average_rating and ratings_count
    // ========================================================================

    public function test_face_model_includes_average_rating_in_array(): void
    {
        // Create some ratings for the face
        $mission = Mission::factory()->create(['producer_id' => $this->producer->id]);
        $candidature = Candidature::factory()->completed()->create([
            'face_id' => $this->face->id,
            'mission_id' => $mission->id,
        ]);

        Rating::create([
            'candidature_id' => $candidature->id,
            'rater_id' => $this->producerUser->id,
            'rated_id' => $this->face->id,
            'rated_type' => Face::class,
            'score' => 5,
        ]);

        $faceArray = $this->face->fresh()->toArray();

        $this->assertArrayHasKey('average_rating', $faceArray);
        $this->assertEquals(5.0, $faceArray['average_rating']);
    }

    public function test_face_model_includes_ratings_count_in_array(): void
    {
        // Create multiple ratings for the face
        $mission = Mission::factory()->create(['producer_id' => $this->producer->id]);

        // Create 3 candidatures with ratings
        for ($i = 0; $i < 3; $i++) {
            $candidature = Candidature::factory()->completed()->create([
                'face_id' => $this->face->id,
                'mission_id' => Mission::factory()->create(['producer_id' => $this->producer->id])->id,
            ]);

            Rating::create([
                'candidature_id' => $candidature->id,
                'rater_id' => $this->producerUser->id,
                'rated_id' => $this->face->id,
                'rated_type' => Face::class,
                'score' => 4,
            ]);
        }

        $faceArray = $this->face->fresh()->toArray();

        $this->assertArrayHasKey('ratings_count', $faceArray);
        $this->assertEquals(3, $faceArray['ratings_count']);
    }

    public function test_face_with_no_ratings_returns_null_for_average(): void
    {
        $faceArray = $this->face->toArray();

        $this->assertArrayHasKey('average_rating', $faceArray);
        $this->assertNull($faceArray['average_rating']);
    }

    public function test_face_with_no_ratings_returns_zero_for_count(): void
    {
        $faceArray = $this->face->toArray();

        $this->assertArrayHasKey('ratings_count', $faceArray);
        $this->assertEquals(0, $faceArray['ratings_count']);
    }

    public function test_face_average_rating_calculation_is_accurate(): void
    {
        // Create ratings with scores 3, 4, 5 → average should be 4.0
        $scores = [3, 4, 5];

        foreach ($scores as $score) {
            $mission = Mission::factory()->create(['producer_id' => $this->producer->id]);
            $candidature = Candidature::factory()->completed()->create([
                'face_id' => $this->face->id,
                'mission_id' => $mission->id,
            ]);

            Rating::create([
                'candidature_id' => $candidature->id,
                'rater_id' => $this->producerUser->id,
                'rated_id' => $this->face->id,
                'rated_type' => Face::class,
                'score' => $score,
            ]);
        }

        $face = $this->face->fresh();

        $this->assertEquals(4.0, $face->average_rating);
        $this->assertEquals(3, $face->ratings_count);
    }

    public function test_face_average_rating_with_decimal_result(): void
    {
        // Create ratings with scores 4, 5 → average should be 4.5
        $scores = [4, 5];

        foreach ($scores as $score) {
            $mission = Mission::factory()->create(['producer_id' => $this->producer->id]);
            $candidature = Candidature::factory()->completed()->create([
                'face_id' => $this->face->id,
                'mission_id' => $mission->id,
            ]);

            Rating::create([
                'candidature_id' => $candidature->id,
                'rater_id' => $this->producerUser->id,
                'rated_id' => $this->face->id,
                'rated_type' => Face::class,
                'score' => $score,
            ]);
        }

        $face = $this->face->fresh();

        $this->assertEquals(4.5, $face->average_rating);
    }

    // ========================================================================
    // API Response includes rating data
    // ========================================================================

    public function test_producer_viewing_candidate_api_includes_rating_data(): void
    {
        // Create a candidature so producer can view the face
        $mission = Mission::factory()->create(['producer_id' => $this->producer->id]);
        $candidature = Candidature::factory()->completed()->create([
            'face_id' => $this->face->id,
            'mission_id' => $mission->id,
        ]);

        // Create a rating
        Rating::create([
            'candidature_id' => $candidature->id,
            'rater_id' => $this->producerUser->id,
            'rated_id' => $this->face->id,
            'rated_type' => Face::class,
            'score' => 4,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/candidates/{$this->face->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.ratings_count', 1);

        // Average rating may be int or float depending on value
        $this->assertEquals(4, $response->json('data.average_rating'));
    }

    public function test_producer_viewing_candidate_without_ratings_shows_null_average(): void
    {
        // Create a candidature so producer can view the face (required for authorization)
        $mission = Mission::factory()->create(['producer_id' => $this->producer->id]);
        Candidature::factory()->create([
            'face_id' => $this->face->id,
            'mission_id' => $mission->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/candidates/{$this->face->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.average_rating', null)
            ->assertJsonPath('data.ratings_count', 0);
    }

    public function test_face_profile_api_includes_rating_data(): void
    {
        // Create a rating for the face
        $mission = Mission::factory()->create(['producer_id' => $this->producer->id]);
        $candidature = Candidature::factory()->completed()->create([
            'face_id' => $this->face->id,
            'mission_id' => $mission->id,
        ]);

        Rating::create([
            'candidature_id' => $candidature->id,
            'rater_id' => $this->producerUser->id,
            'rated_id' => $this->face->id,
            'rated_type' => Face::class,
            'score' => 5,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/profile');

        $response->assertStatus(200)
            ->assertJsonPath('data.ratings_count', 1);

        // Average rating may be int or float depending on value
        $this->assertEquals(5, $response->json('data.average_rating'));
    }
}
