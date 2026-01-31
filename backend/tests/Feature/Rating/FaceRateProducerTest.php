<?php

declare(strict_types=1);

namespace Tests\Feature\Rating;

use App\Enums\CandidatureStatus;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaceRateProducerTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    private Face $face;

    private User $producerUser;

    private Producer $producer;

    private Mission $mission;

    private Candidature $completedCandidature;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Face with User
        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);

        // Create Producer with User
        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);

        // Create Mission for the Producer
        $this->mission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
        ]);

        // Create completed Candidature
        $this->completedCandidature = Candidature::factory()->create([
            'face_id' => $this->face->id,
            'mission_id' => $this->mission->id,
            'status' => CandidatureStatus::Completed,
        ]);
    }

    // ========================================================================
    // AC #1: Face can rate Producer after completed candidature
    // ========================================================================

    public function test_face_can_rate_producer_after_completed_candidature(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->completedCandidature->id}/rate", [
                'score' => 5,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'score',
                    'comment',
                    'created_at',
                    'rater' => ['id', 'name', 'photo_url'],
                    'rated' => ['id', 'name', 'photo_url'],
                    'candidature_id',
                ],
                'message',
            ]);

        $this->assertDatabaseHas('ratings', [
            'candidature_id' => $this->completedCandidature->id,
            'rater_id' => $this->faceUser->id,
            'rated_id' => $this->producer->id,
            'rated_type' => Producer::class,
            'score' => 5,
        ]);
    }

    // ========================================================================
    // AC #2: Rating with valid score (1-5) succeeds
    // ========================================================================

    public function test_rating_with_score_1_succeeds(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->completedCandidature->id}/rate", [
                'score' => 1,
            ]);

        $response->assertStatus(201);
        $this->assertEquals(1, $response->json('data.score'));
    }

    public function test_rating_with_score_3_succeeds(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->completedCandidature->id}/rate", [
                'score' => 3,
            ]);

        $response->assertStatus(201);
        $this->assertEquals(3, $response->json('data.score'));
    }

    public function test_rating_with_score_5_succeeds(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->completedCandidature->id}/rate", [
                'score' => 5,
            ]);

        $response->assertStatus(201);
        $this->assertEquals(5, $response->json('data.score'));
    }

    // ========================================================================
    // AC #3: Rating with optional comment saves correctly
    // ========================================================================

    public function test_rating_with_comment_saves_correctly(): void
    {
        $comment = 'Excellent producteur, très professionnel !';

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->completedCandidature->id}/rate", [
                'score' => 5,
                'comment' => $comment,
            ]);

        $response->assertStatus(201);
        $this->assertEquals($comment, $response->json('data.comment'));

        $this->assertDatabaseHas('ratings', [
            'candidature_id' => $this->completedCandidature->id,
            'comment' => $comment,
        ]);
    }

    public function test_rating_without_comment_saves_correctly(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->completedCandidature->id}/rate", [
                'score' => 4,
            ]);

        $response->assertStatus(201);
        $this->assertNull($response->json('data.comment'));
    }

    // ========================================================================
    // AC #4: Duplicate rating returns 422 error
    // ========================================================================

    public function test_duplicate_rating_returns_422_error(): void
    {
        // Create first rating
        Rating::create([
            'candidature_id' => $this->completedCandidature->id,
            'rater_id' => $this->faceUser->id,
            'rated_id' => $this->producer->id,
            'rated_type' => Producer::class,
            'score' => 4,
        ]);

        // Try to rate again
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->completedCandidature->id}/rate", [
                'score' => 5,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'error' => [
                    'code' => 'ALREADY_RATED',
                    'message' => 'Vous avez déjà évalué ce producteur pour cette mission',
                ],
            ]);
    }

    // ========================================================================
    // AC #5: Rating non-completed candidature returns 403
    // ========================================================================

    public function test_rating_pending_candidature_returns_403(): void
    {
        // Create a new mission for this candidature
        $newMission = Mission::factory()->create(['producer_id' => $this->producer->id]);
        $pendingCandidature = Candidature::factory()->create([
            'face_id' => $this->face->id,
            'mission_id' => $newMission->id,
            'status' => CandidatureStatus::Pending,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$pendingCandidature->id}/rate", [
                'score' => 5,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => [
                    'code' => 'RATING_NOT_ALLOWED',
                    'message' => "Les évaluations ne sont possibles qu'après la fin de la mission",
                ],
            ]);
    }

    public function test_rating_accepted_candidature_returns_403(): void
    {
        // Create a new mission for this candidature
        $newMission = Mission::factory()->create(['producer_id' => $this->producer->id]);
        $acceptedCandidature = Candidature::factory()->create([
            'face_id' => $this->face->id,
            'mission_id' => $newMission->id,
            'status' => CandidatureStatus::Accepted,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$acceptedCandidature->id}/rate", [
                'score' => 5,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => [
                    'code' => 'RATING_NOT_ALLOWED',
                ],
            ]);
    }

    public function test_rating_confirmed_candidature_returns_403(): void
    {
        // Create a new mission for this candidature
        $newMission = Mission::factory()->create(['producer_id' => $this->producer->id]);
        $confirmedCandidature = Candidature::factory()->create([
            'face_id' => $this->face->id,
            'mission_id' => $newMission->id,
            'status' => CandidatureStatus::Confirmed,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$confirmedCandidature->id}/rate", [
                'score' => 5,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => [
                    'code' => 'RATING_NOT_ALLOWED',
                ],
            ]);
    }

    public function test_rating_in_progress_candidature_returns_403(): void
    {
        // Create a new mission for this candidature
        $newMission = Mission::factory()->create(['producer_id' => $this->producer->id]);
        $inProgressCandidature = Candidature::factory()->create([
            'face_id' => $this->face->id,
            'mission_id' => $newMission->id,
            'status' => CandidatureStatus::InProgress,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$inProgressCandidature->id}/rate", [
                'score' => 5,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => [
                    'code' => 'RATING_NOT_ALLOWED',
                ],
            ]);
    }

    public function test_rating_rejected_candidature_returns_403(): void
    {
        // Create a new mission for this candidature
        $newMission = Mission::factory()->create(['producer_id' => $this->producer->id]);
        $rejectedCandidature = Candidature::factory()->create([
            'face_id' => $this->face->id,
            'mission_id' => $newMission->id,
            'status' => CandidatureStatus::Rejected,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$rejectedCandidature->id}/rate", [
                'score' => 5,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => [
                    'code' => 'RATING_NOT_ALLOWED',
                ],
            ]);
    }

    // ========================================================================
    // AC #6: Rating another Face's candidature returns 403
    // ========================================================================

    public function test_rating_another_face_candidature_returns_403(): void
    {
        // Create another Face
        $otherFace = Face::factory()->create();
        $otherFaceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $otherFace->id,
        ]);

        // Create candidature for the other Face
        $otherCandidature = Candidature::factory()->create([
            'face_id' => $otherFace->id,
            'mission_id' => $this->mission->id,
            'status' => CandidatureStatus::Completed,
        ]);

        // Try to rate with original Face user
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$otherCandidature->id}/rate", [
                'score' => 5,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Cette candidature ne vous appartient pas',
                ],
            ]);
    }

    // ========================================================================
    // AC #7: Response includes French message
    // ========================================================================

    public function test_response_includes_french_success_message(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->completedCandidature->id}/rate", [
                'score' => 5,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Évaluation envoyée avec succès',
            ]);
    }

    // ========================================================================
    // Additional: Validation errors
    // ========================================================================

    public function test_invalid_score_0_returns_validation_error(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->completedCandidature->id}/rate", [
                'score' => 0,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['score']);
    }

    public function test_invalid_score_6_returns_validation_error(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->completedCandidature->id}/rate", [
                'score' => 6,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['score']);
    }

    public function test_missing_score_returns_validation_error(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->completedCandidature->id}/rate", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['score']);
    }

    public function test_comment_exceeding_1000_chars_returns_validation_error(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->completedCandidature->id}/rate", [
                'score' => 5,
                'comment' => str_repeat('a', 1001),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['comment']);
    }

    public function test_comment_html_tags_are_stripped(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->completedCandidature->id}/rate", [
                'score' => 5,
                'comment' => '<script>alert("xss")</script>Great producer!',
            ]);

        $response->assertStatus(201);
        $this->assertEquals('alert("xss")Great producer!', $response->json('data.comment'));

        $this->assertDatabaseHas('ratings', [
            'candidature_id' => $this->completedCandidature->id,
            'comment' => 'alert("xss")Great producer!',
        ]);
    }

    // ========================================================================
    // Additional: Authentication
    // ========================================================================

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->postJson("/api/v1/face/candidatures/{$this->completedCandidature->id}/rate", [
            'score' => 5,
        ]);

        $response->assertStatus(401);
    }

    public function test_producer_cannot_use_face_rating_endpoint(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/face/candidatures/{$this->completedCandidature->id}/rate", [
                'score' => 5,
            ]);

        $response->assertStatus(403);
    }

    public function test_rating_nonexistent_candidature_returns_404(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/candidatures/999999/rate', [
                'score' => 5,
            ]);

        $response->assertStatus(404);
    }

    // ========================================================================
    // Additional: Response structure
    // ========================================================================

    public function test_rating_response_includes_rater_and_rated_info(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->completedCandidature->id}/rate", [
                'score' => 4,
                'comment' => 'Good experience',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'score',
                    'comment',
                    'created_at',
                    'rater' => [
                        'id',
                        'name',
                        'photo_url',
                    ],
                    'rated' => [
                        'id',
                        'name',
                        'photo_url',
                    ],
                    'candidature_id',
                ],
                'message',
            ]);

        // Verify rater is the Face user
        $this->assertEquals($this->faceUser->id, $response->json('data.rater.id'));

        // Verify rated is the Producer
        $this->assertEquals($this->producer->id, $response->json('data.rated.id'));
    }
}
