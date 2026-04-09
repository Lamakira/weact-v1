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

class ProducerReviewsListTest extends TestCase
{
    use RefreshDatabase;

    private Face $face;

    private User $faceUser;

    private Producer $producer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);

        $this->producer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
    }

    // ========================================================================
    // Basic endpoint tests
    // ========================================================================

    public function test_can_get_producer_reviews_list(): void
    {
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
            'comment' => 'Super producteur!',
        ]);

        $response = $this->getJson("/api/v1/public/producers/{$this->producer->slug}/reviews");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'score',
                        'comment',
                        'created_at',
                        'formatted_date',
                        'rater' => [
                            'display_name',
                            'profile_photo_url',
                        ],
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

    public function test_producer_reviews_include_correct_data(): void
    {
        $this->face->update(['nom' => 'Martin', 'prenom' => 'Marie']);

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
            'comment' => 'Très bonne collaboration',
        ]);

        $response = $this->getJson("/api/v1/public/producers/{$this->producer->slug}/reviews");

        $response->assertStatus(200)
            ->assertJsonPath('data.0.score', 4)
            ->assertJsonPath('data.0.comment', 'Très bonne collaboration');

        // Rater should be the Face
        $this->assertNotNull($response->json('data.0.rater.display_name'));
    }

    public function test_producer_with_no_reviews_returns_empty_array(): void
    {
        $response = $this->getJson("/api/v1/public/producers/{$this->producer->slug}/reviews");

        $response->assertStatus(200)
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }

    public function test_nonexistent_producer_returns_404(): void
    {
        $response = $this->getJson('/api/v1/public/producers/non-existent-slug/reviews');

        $response->assertStatus(404);
    }

    // ========================================================================
    // Pagination tests
    // ========================================================================

    public function test_producer_reviews_are_paginated_10_per_page(): void
    {
        // Create 15 reviews from different faces
        for ($i = 0; $i < 15; $i++) {
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
                'score' => rand(3, 5),
            ]);
        }

        $response = $this->getJson("/api/v1/public/producers/{$this->producer->slug}/reviews");

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 15)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(10, 'data');
    }

    public function test_can_navigate_to_second_page(): void
    {
        // Create 15 reviews
        for ($i = 0; $i < 15; $i++) {
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
                'score' => rand(3, 5),
            ]);
        }

        $response = $this->getJson("/api/v1/public/producers/{$this->producer->slug}/reviews?page=2");

        $response->assertStatus(200)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonCount(5, 'data');
    }

    // ========================================================================
    // Ordering tests
    // ========================================================================

    public function test_producer_reviews_ordered_by_most_recent_first(): void
    {
        // Create first review (older)
        $mission1 = Mission::factory()->create(['producer_id' => $this->producer->id]);
        $candidature1 = Candidature::factory()->completed()->create([
            'face_id' => $this->face->id,
            'mission_id' => $mission1->id,
        ]);

        $olderRating = Rating::create([
            'candidature_id' => $candidature1->id,
            'rater_id' => $this->faceUser->id,
            'rated_id' => $this->producer->id,
            'rated_type' => Producer::class,
            'score' => 3,
            'comment' => 'Older review',
        ]);
        $olderRating->created_at = now()->subDays(5);
        $olderRating->save();

        // Create second review (newer)
        $face2 = Face::factory()->create();
        $faceUser2 = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face2->id,
        ]);

        $mission2 = Mission::factory()->create(['producer_id' => $this->producer->id]);
        $candidature2 = Candidature::factory()->completed()->create([
            'face_id' => $face2->id,
            'mission_id' => $mission2->id,
        ]);

        Rating::create([
            'candidature_id' => $candidature2->id,
            'rater_id' => $faceUser2->id,
            'rated_id' => $this->producer->id,
            'rated_type' => Producer::class,
            'score' => 5,
            'comment' => 'Newer review',
        ]);

        $response = $this->getJson("/api/v1/public/producers/{$this->producer->slug}/reviews");

        $response->assertStatus(200);

        // Most recent should be first
        $this->assertEquals('Newer review', $response->json('data.0.comment'));
        $this->assertEquals('Older review', $response->json('data.1.comment'));
    }

    // ========================================================================
    // Comment handling tests
    // ========================================================================

    public function test_review_with_comment_shows_comment(): void
    {
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
            'comment' => 'Producteur très professionnel!',
        ]);

        $response = $this->getJson("/api/v1/public/producers/{$this->producer->slug}/reviews");

        $response->assertStatus(200)
            ->assertJsonPath('data.0.comment', 'Producteur très professionnel!');
    }

    public function test_review_without_comment_shows_null(): void
    {
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
            'comment' => null,
        ]);

        $response = $this->getJson("/api/v1/public/producers/{$this->producer->slug}/reviews");

        $response->assertStatus(200)
            ->assertJsonPath('data.0.comment', null);
    }
}
