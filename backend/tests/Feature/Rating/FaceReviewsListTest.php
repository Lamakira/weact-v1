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

class FaceReviewsListTest extends TestCase
{
    use RefreshDatabase;

    private Face $face;

    private Producer $producer;

    private User $producerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->face = Face::factory()->create();
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
    }

    // ========================================================================
    // Basic endpoint tests
    // ========================================================================

    public function test_can_get_face_reviews_list(): void
    {
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
            'comment' => 'Excellent travail!',
        ]);

        $response = $this->getJson("/api/v1/public/faces/{$this->face->username}/reviews");

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

    public function test_face_reviews_include_correct_data(): void
    {
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
            'score' => 4,
            'comment' => 'Très bon travail',
        ]);

        $response = $this->getJson("/api/v1/public/faces/{$this->face->username}/reviews");

        $response->assertStatus(200)
            ->assertJsonPath('data.0.score', 4)
            ->assertJsonPath('data.0.comment', 'Très bon travail')
            ->assertJsonPath('data.0.rater.display_name', $this->producer->display_name);
    }

    public function test_face_with_no_reviews_returns_empty_array(): void
    {
        $response = $this->getJson("/api/v1/public/faces/{$this->face->username}/reviews");

        $response->assertStatus(200)
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }

    public function test_nonexistent_face_returns_404(): void
    {
        $response = $this->getJson('/api/v1/public/faces/nonexistentuser/reviews');

        $response->assertStatus(404);
    }

    // ========================================================================
    // Pagination tests
    // ========================================================================

    public function test_face_reviews_are_paginated_10_per_page(): void
    {
        // Create 15 reviews
        for ($i = 0; $i < 15; $i++) {
            $producer = Producer::factory()->create();
            $producerUser = User::factory()->create([
                'userable_type' => Producer::class,
                'userable_id' => $producer->id,
            ]);

            $mission = Mission::factory()->create(['producer_id' => $producer->id]);
            $candidature = Candidature::factory()->completed()->create([
                'face_id' => $this->face->id,
                'mission_id' => $mission->id,
            ]);

            Rating::create([
                'candidature_id' => $candidature->id,
                'rater_id' => $producerUser->id,
                'rated_id' => $this->face->id,
                'rated_type' => Face::class,
                'score' => rand(3, 5),
            ]);
        }

        $response = $this->getJson("/api/v1/public/faces/{$this->face->username}/reviews");

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
            $producer = Producer::factory()->create();
            $producerUser = User::factory()->create([
                'userable_type' => Producer::class,
                'userable_id' => $producer->id,
            ]);

            $mission = Mission::factory()->create(['producer_id' => $producer->id]);
            $candidature = Candidature::factory()->completed()->create([
                'face_id' => $this->face->id,
                'mission_id' => $mission->id,
            ]);

            Rating::create([
                'candidature_id' => $candidature->id,
                'rater_id' => $producerUser->id,
                'rated_id' => $this->face->id,
                'rated_type' => Face::class,
                'score' => rand(3, 5),
            ]);
        }

        $response = $this->getJson("/api/v1/public/faces/{$this->face->username}/reviews?page=2");

        $response->assertStatus(200)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonCount(5, 'data');
    }

    // ========================================================================
    // Ordering tests
    // ========================================================================

    public function test_face_reviews_ordered_by_most_recent_first(): void
    {
        // Create first review (older)
        $mission1 = Mission::factory()->create(['producer_id' => $this->producer->id]);
        $candidature1 = Candidature::factory()->completed()->create([
            'face_id' => $this->face->id,
            'mission_id' => $mission1->id,
        ]);

        $olderRating = Rating::create([
            'candidature_id' => $candidature1->id,
            'rater_id' => $this->producerUser->id,
            'rated_id' => $this->face->id,
            'rated_type' => Face::class,
            'score' => 3,
            'comment' => 'Older review',
        ]);
        $olderRating->created_at = now()->subDays(5);
        $olderRating->save();

        // Create second review (newer)
        $producer2 = Producer::factory()->create();
        $producerUser2 = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer2->id,
        ]);

        $mission2 = Mission::factory()->create(['producer_id' => $producer2->id]);
        $candidature2 = Candidature::factory()->completed()->create([
            'face_id' => $this->face->id,
            'mission_id' => $mission2->id,
        ]);

        Rating::create([
            'candidature_id' => $candidature2->id,
            'rater_id' => $producerUser2->id,
            'rated_id' => $this->face->id,
            'rated_type' => Face::class,
            'score' => 5,
            'comment' => 'Newer review',
        ]);

        $response = $this->getJson("/api/v1/public/faces/{$this->face->username}/reviews");

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
            'rater_id' => $this->producerUser->id,
            'rated_id' => $this->face->id,
            'rated_type' => Face::class,
            'score' => 5,
            'comment' => 'Super professionnel!',
        ]);

        $response = $this->getJson("/api/v1/public/faces/{$this->face->username}/reviews");

        $response->assertStatus(200)
            ->assertJsonPath('data.0.comment', 'Super professionnel!');
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
            'rater_id' => $this->producerUser->id,
            'rated_id' => $this->face->id,
            'rated_type' => Face::class,
            'score' => 4,
            'comment' => null,
        ]);

        $response = $this->getJson("/api/v1/public/faces/{$this->face->username}/reviews");

        $response->assertStatus(200)
            ->assertJsonPath('data.0.comment', null);
    }

    // ========================================================================
    // Rater info tests
    // ========================================================================

    public function test_review_includes_rater_display_name(): void
    {
        $this->producer->update(['first_name' => 'Jean', 'last_name' => 'Dupont']);

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

        $response = $this->getJson("/api/v1/public/faces/{$this->face->username}/reviews");

        $response->assertStatus(200);
        $this->assertNotNull($response->json('data.0.rater.display_name'));
    }

    public function test_review_formatted_date_is_in_french(): void
    {
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

        $response = $this->getJson("/api/v1/public/faces/{$this->face->username}/reviews");

        $response->assertStatus(200);

        $formattedDate = $response->json('data.0.formatted_date');
        $this->assertNotNull($formattedDate);
        // Should contain a French month name
        $frenchMonths = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        $containsFrenchMonth = false;
        foreach ($frenchMonths as $month) {
            if (stripos($formattedDate, $month) !== false) {
                $containsFrenchMonth = true;
                break;
            }
        }
        $this->assertTrue($containsFrenchMonth, "formatted_date '{$formattedDate}' should contain a French month name");
    }

    // ========================================================================
    // Edge case tests for pagination
    // ========================================================================

    public function test_invalid_page_zero_returns_first_page(): void
    {
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

        $response = $this->getJson("/api/v1/public/faces/{$this->face->username}/reviews?page=0");

        $response->assertStatus(200)
            ->assertJsonPath('meta.current_page', 1);
    }

    public function test_negative_page_returns_first_page(): void
    {
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

        $response = $this->getJson("/api/v1/public/faces/{$this->face->username}/reviews?page=-1");

        $response->assertStatus(200)
            ->assertJsonPath('meta.current_page', 1);
    }

    public function test_page_beyond_last_returns_empty_data(): void
    {
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

        $response = $this->getJson("/api/v1/public/faces/{$this->face->username}/reviews?page=999");

        $response->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    public function test_non_numeric_page_returns_first_page(): void
    {
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

        $response = $this->getJson("/api/v1/public/faces/{$this->face->username}/reviews?page=abc");

        $response->assertStatus(200)
            ->assertJsonPath('meta.current_page', 1);
    }
}
