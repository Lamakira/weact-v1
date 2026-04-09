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

class ProducerRatingDisplayTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    private Face $face;

    private Producer $producer;

    private User $producerUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Face with User (will rate the Producer)
        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);

        // Create Producer with User (will receive ratings)
        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
    }

    // ========================================================================
    // Task 1: Producer model includes average_rating and ratings_count
    // ========================================================================

    public function test_producer_model_includes_average_rating_in_array(): void
    {
        // Create a rating for the producer
        $mission = Mission::factory()->create(['producer_id' => $this->producer->id]);
        $candidature = Candidature::factory()->completed()->create([
            'face_id' => $this->face->id,
            'mission_id' => $mission->id,
        ]);

        Rating::create([
            'candidature_id' => $candidature->id,
            'rater_id' => $this->faceUser->id,
            'rated_id' => $this->producer->id,
            'rated_type' => Producer::class,
            'score' => 5,
        ]);

        $producerArray = $this->producer->fresh()->toArray();

        $this->assertArrayHasKey('average_rating', $producerArray);
        $this->assertEquals(5.0, $producerArray['average_rating']);
    }

    public function test_producer_model_includes_ratings_count_in_array(): void
    {
        // Create multiple ratings for the producer from different faces
        for ($i = 0; $i < 3; $i++) {
            $face = Face::factory()->create();
            $faceUser = User::factory()->create([
                'userable_type' => Face::class,
                'userable_id' => $face->id,
            ]);

            $mission = Mission::factory()->create(['producer_id' => $this->producer->id]);
            $candidature = Candidature::factory()->completed()->create([
                'face_id' => $face->id,
                'mission_id' => $mission->id,
            ]);

            Rating::create([
                'candidature_id' => $candidature->id,
                'rater_id' => $faceUser->id,
                'rated_id' => $this->producer->id,
                'rated_type' => Producer::class,
                'score' => 4,
            ]);
        }

        $producerArray = $this->producer->fresh()->toArray();

        $this->assertArrayHasKey('ratings_count', $producerArray);
        $this->assertEquals(3, $producerArray['ratings_count']);
    }

    public function test_producer_with_no_ratings_returns_null_for_average(): void
    {
        $producerArray = $this->producer->toArray();

        $this->assertArrayHasKey('average_rating', $producerArray);
        $this->assertNull($producerArray['average_rating']);
    }

    public function test_producer_with_no_ratings_returns_zero_for_count(): void
    {
        $producerArray = $this->producer->toArray();

        $this->assertArrayHasKey('ratings_count', $producerArray);
        $this->assertEquals(0, $producerArray['ratings_count']);
    }

    public function test_producer_average_rating_calculation_is_accurate(): void
    {
        // Create ratings with scores 3, 4, 5 → average should be 4.0
        $scores = [3, 4, 5];

        foreach ($scores as $score) {
            $face = Face::factory()->create();
            $faceUser = User::factory()->create([
                'userable_type' => Face::class,
                'userable_id' => $face->id,
            ]);

            $mission = Mission::factory()->create(['producer_id' => $this->producer->id]);
            $candidature = Candidature::factory()->completed()->create([
                'face_id' => $face->id,
                'mission_id' => $mission->id,
            ]);

            Rating::create([
                'candidature_id' => $candidature->id,
                'rater_id' => $faceUser->id,
                'rated_id' => $this->producer->id,
                'rated_type' => Producer::class,
                'score' => $score,
            ]);
        }

        $producer = $this->producer->fresh();

        $this->assertEquals(4.0, $producer->average_rating);
        $this->assertEquals(3, $producer->ratings_count);
    }

    public function test_producer_average_rating_with_decimal_result(): void
    {
        // Create ratings with scores 4, 5 → average should be 4.5
        $scores = [4, 5];

        foreach ($scores as $score) {
            $face = Face::factory()->create();
            $faceUser = User::factory()->create([
                'userable_type' => Face::class,
                'userable_id' => $face->id,
            ]);

            $mission = Mission::factory()->create(['producer_id' => $this->producer->id]);
            $candidature = Candidature::factory()->completed()->create([
                'face_id' => $face->id,
                'mission_id' => $mission->id,
            ]);

            Rating::create([
                'candidature_id' => $candidature->id,
                'rater_id' => $faceUser->id,
                'rated_id' => $this->producer->id,
                'rated_type' => Producer::class,
                'score' => $score,
            ]);
        }

        $producer = $this->producer->fresh();

        $this->assertEquals(4.5, $producer->average_rating);
    }

    // ========================================================================
    // Public API Response includes rating data
    // ========================================================================

    public function test_public_producer_api_includes_rating_data(): void
    {
        // Create a rating for the producer
        $mission = Mission::factory()->create(['producer_id' => $this->producer->id]);
        $candidature = Candidature::factory()->completed()->create([
            'face_id' => $this->face->id,
            'mission_id' => $mission->id,
        ]);

        Rating::create([
            'candidature_id' => $candidature->id,
            'rater_id' => $this->faceUser->id,
            'rated_id' => $this->producer->id,
            'rated_type' => Producer::class,
            'score' => 4,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/public/producers/{$this->producer->slug}");

        $response->assertStatus(200)
            ->assertJsonPath('data.ratings_count', 1);

        // Average rating may be int or float depending on value
        $this->assertEquals(4, $response->json('data.average_rating'));
    }

    public function test_public_producer_without_ratings_shows_null_average(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/public/producers/{$this->producer->slug}");

        $response->assertStatus(200)
            ->assertJsonPath('data.average_rating', null)
            ->assertJsonPath('data.ratings_count', 0);
    }

    public function test_producer_viewing_own_public_profile_sees_rating(): void
    {
        // Create a rating for the producer
        $mission = Mission::factory()->create(['producer_id' => $this->producer->id]);
        $candidature = Candidature::factory()->completed()->create([
            'face_id' => $this->face->id,
            'mission_id' => $mission->id,
        ]);

        Rating::create([
            'candidature_id' => $candidature->id,
            'rater_id' => $this->faceUser->id,
            'rated_id' => $this->producer->id,
            'rated_type' => Producer::class,
            'score' => 5,
        ]);

        // Producer viewing their own public profile
        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/public/producers/{$this->producer->slug}");

        $response->assertStatus(200)
            ->assertJsonPath('data.ratings_count', 1);

        $this->assertEquals(5, $response->json('data.average_rating'));
    }
}
