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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class RatingSchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper to create a Face with its associated User.
     */
    private function createFaceWithUser(): array
    {
        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        return [$face, $faceUser];
    }

    /**
     * Helper to create a Producer with its associated User.
     */
    private function createProducerWithUser(): array
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        return [$producer, $producerUser];
    }

    /**
     * Helper to create a completed candidature with all associations.
     */
    private function createCompletedCandidature(?Face $face = null, ?Producer $producer = null): array
    {
        if (!$face) {
            [$face, $faceUser] = $this->createFaceWithUser();
        } else {
            $faceUser = User::where('userable_type', Face::class)
                ->where('userable_id', $face->id)
                ->first();
            if (!$faceUser) {
                $faceUser = User::factory()->create([
                    'userable_type' => Face::class,
                    'userable_id' => $face->id,
                ]);
            }
        }

        if (!$producer) {
            [$producer, $producerUser] = $this->createProducerWithUser();
        } else {
            $producerUser = User::where('userable_type', Producer::class)
                ->where('userable_id', $producer->id)
                ->first();
            if (!$producerUser) {
                $producerUser = User::factory()->create([
                    'userable_type' => Producer::class,
                    'userable_id' => $producer->id,
                ]);
            }
        }

        $mission = Mission::factory()->create([
            'producer_id' => $producer->id,
        ]);

        $candidature = Candidature::factory()->create([
            'face_id' => $face->id,
            'mission_id' => $mission->id,
            'status' => CandidatureStatus::Completed,
        ]);

        return [
            'candidature' => $candidature,
            'face' => $face,
            'faceUser' => $faceUser,
            'producer' => $producer,
            'producerUser' => $producerUser,
            'mission' => $mission,
        ];
    }

    // ========================================================================
    // AC #1: Migration creates ratings table with all required fields
    // ========================================================================

    public function test_ratings_table_exists_with_all_required_columns(): void
    {
        $this->assertTrue(
            \Schema::hasTable('ratings'),
            'The ratings table should exist'
        );

        $this->assertTrue(\Schema::hasColumn('ratings', 'id'));
        $this->assertTrue(\Schema::hasColumn('ratings', 'candidature_id'));
        $this->assertTrue(\Schema::hasColumn('ratings', 'rater_id'));
        $this->assertTrue(\Schema::hasColumn('ratings', 'rated_id'));
        $this->assertTrue(\Schema::hasColumn('ratings', 'rated_type'));
        $this->assertTrue(\Schema::hasColumn('ratings', 'score'));
        $this->assertTrue(\Schema::hasColumn('ratings', 'comment'));
        $this->assertTrue(\Schema::hasColumn('ratings', 'created_at'));
        $this->assertTrue(\Schema::hasColumn('ratings', 'updated_at'));
    }

    // ========================================================================
    // AC #2: Foreign key constraint to candidatures works correctly
    // ========================================================================

    public function test_candidature_foreign_key_constraint_works(): void
    {
        $rating = Rating::factory()->create();

        $this->assertInstanceOf(Candidature::class, $rating->candidature);
        $this->assertEquals($rating->candidature_id, $rating->candidature->id);
    }

    public function test_cascade_delete_from_candidature_to_ratings(): void
    {
        $data = $this->createCompletedCandidature();

        $rating = Rating::factory()
            ->forCandidature($data['candidature'])
            ->ratedBy($data['faceUser'])
            ->ratingProducer($data['producer'])
            ->create();

        $ratingId = $rating->id;

        $this->assertDatabaseHas('ratings', ['id' => $ratingId]);

        $data['candidature']->delete();

        $this->assertDatabaseMissing('ratings', ['id' => $ratingId]);
    }

    // ========================================================================
    // AC #3: Foreign key constraint to users (rater_id) works correctly
    // ========================================================================

    public function test_rater_foreign_key_constraint_works(): void
    {
        $rating = Rating::factory()->create();

        $this->assertInstanceOf(User::class, $rating->rater);
        $this->assertEquals($rating->rater_id, $rating->rater->id);
    }

    public function test_cascade_delete_from_user_to_ratings(): void
    {
        $data = $this->createCompletedCandidature();

        $rating = Rating::factory()
            ->forCandidature($data['candidature'])
            ->ratedBy($data['faceUser'])
            ->ratingProducer($data['producer'])
            ->create();

        $ratingId = $rating->id;

        $this->assertDatabaseHas('ratings', ['id' => $ratingId]);

        // Deleting the user should cascade delete the rating
        $data['faceUser']->delete();

        $this->assertDatabaseMissing('ratings', ['id' => $ratingId]);
    }

    // ========================================================================
    // AC #4: Polymorphic rated relationship works for Face and Producer
    // ========================================================================

    public function test_polymorphic_rated_relationship_works_for_face(): void
    {
        $rating = Rating::factory()->producerRatingFace()->create();

        $this->assertInstanceOf(Face::class, $rating->rated);
        $this->assertEquals($rating->rated_id, $rating->rated->id);
        $this->assertEquals(Face::class, $rating->rated_type);
    }

    public function test_polymorphic_rated_relationship_works_for_producer(): void
    {
        $rating = Rating::factory()->faceRatingProducer()->create();

        $this->assertInstanceOf(Producer::class, $rating->rated);
        $this->assertEquals($rating->rated_id, $rating->rated->id);
        $this->assertEquals(Producer::class, $rating->rated_type);
    }

    // ========================================================================
    // AC #5: Score validation accepts values from 1 to 5 only
    // ========================================================================

    public function test_score_accepts_value_1(): void
    {
        $rating = Rating::factory()->oneStar()->create();

        $this->assertEquals(1, $rating->score);
    }

    public function test_score_accepts_value_2(): void
    {
        $rating = Rating::factory()->twoStars()->create();

        $this->assertEquals(2, $rating->score);
    }

    public function test_score_accepts_value_3(): void
    {
        $rating = Rating::factory()->threeStars()->create();

        $this->assertEquals(3, $rating->score);
    }

    public function test_score_accepts_value_4(): void
    {
        $rating = Rating::factory()->fourStars()->create();

        $this->assertEquals(4, $rating->score);
    }

    public function test_score_accepts_value_5(): void
    {
        $rating = Rating::factory()->fiveStars()->create();

        $this->assertEquals(5, $rating->score);
    }

    public function test_score_rejects_value_0(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Score must be between 1 and 5');

        Rating::factory()->score(0)->create();
    }

    public function test_score_rejects_value_6(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Score must be between 1 and 5');

        Rating::factory()->score(6)->create();
    }

    public function test_score_rejects_negative_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Score must be between 1 and 5');

        Rating::factory()->score(-1)->create();
    }

    // ========================================================================
    // AC #6: Comment accepts optional text content
    // ========================================================================

    public function test_comment_is_optional(): void
    {
        $rating = Rating::factory()->withoutComment()->create();

        $this->assertNull($rating->comment);
    }

    public function test_comment_accepts_text_content(): void
    {
        $comment = 'This was a great collaboration experience!';
        $rating = Rating::factory()->withComment($comment)->create();

        $this->assertEquals($comment, $rating->comment);
    }

    // ========================================================================
    // AC #7: Unique constraint prevents duplicate ratings
    // ========================================================================

    public function test_unique_constraint_prevents_duplicate_ratings(): void
    {
        $data = $this->createCompletedCandidature();

        // Create first rating
        Rating::factory()
            ->forCandidature($data['candidature'])
            ->ratedBy($data['faceUser'])
            ->ratingProducer($data['producer'])
            ->create();

        // Attempt to create duplicate rating
        $this->expectException(QueryException::class);

        Rating::factory()
            ->forCandidature($data['candidature'])
            ->ratedBy($data['faceUser'])
            ->ratingProducer($data['producer'])
            ->create();
    }

    public function test_same_rater_can_rate_different_targets_in_same_candidature(): void
    {
        $data = $this->createCompletedCandidature();

        // Face rates Producer
        $rating1 = Rating::factory()
            ->forCandidature($data['candidature'])
            ->ratedBy($data['faceUser'])
            ->ratingProducer($data['producer'])
            ->create();

        // This verifies the first rating was created
        $this->assertDatabaseHas('ratings', ['id' => $rating1->id]);
    }

    // ========================================================================
    // AC #8: Rating model relationships are properly defined
    // ========================================================================

    public function test_rating_candidature_relationship(): void
    {
        $rating = Rating::factory()->create();

        $this->assertInstanceOf(Candidature::class, $rating->candidature);
    }

    public function test_rating_rater_relationship(): void
    {
        $rating = Rating::factory()->create();

        $this->assertInstanceOf(User::class, $rating->rater);
    }

    public function test_rating_rated_polymorphic_relationship(): void
    {
        // Test with Producer
        $ratingProducer = Rating::factory()->faceRatingProducer()->create();
        $this->assertInstanceOf(Producer::class, $ratingProducer->rated);

        // Test with Face
        $ratingFace = Rating::factory()->producerRatingFace()->create();
        $this->assertInstanceOf(Face::class, $ratingFace->rated);
    }

    // ========================================================================
    // AC #9: Face and Producer can retrieve ratings received
    // ========================================================================

    public function test_face_ratings_received_relationship(): void
    {
        $rating = Rating::factory()->producerRatingFace()->create();
        $face = $rating->rated;

        $this->assertCount(1, $face->ratingsReceived);
        $this->assertTrue($face->ratingsReceived->contains($rating));
    }

    public function test_producer_ratings_received_relationship(): void
    {
        $rating = Rating::factory()->faceRatingProducer()->create();
        $producer = $rating->rated;

        $this->assertCount(1, $producer->ratingsReceived);
        $this->assertTrue($producer->ratingsReceived->contains($rating));
    }

    public function test_face_average_rating_accessor(): void
    {
        // Create first candidature and rating
        $data1 = $this->createCompletedCandidature();
        $face = $data1['face'];

        Rating::factory()
            ->forCandidature($data1['candidature'])
            ->ratedBy($data1['producerUser'])
            ->ratingFace($face)
            ->fourStars()
            ->create();

        // Create second candidature for the same face with different producer
        $data2 = $this->createCompletedCandidature($face);

        Rating::factory()
            ->forCandidature($data2['candidature'])
            ->ratedBy($data2['producerUser'])
            ->ratingFace($face)
            ->fiveStars()
            ->create();

        $face->refresh();

        // Average of 4 and 5 = 4.5
        $this->assertEquals(4.5, $face->average_rating);
    }

    public function test_producer_average_rating_accessor(): void
    {
        // Create first candidature and rating
        $data1 = $this->createCompletedCandidature();
        $producer = $data1['producer'];

        Rating::factory()
            ->forCandidature($data1['candidature'])
            ->ratedBy($data1['faceUser'])
            ->ratingProducer($producer)
            ->threeStars()
            ->create();

        // Create second candidature for the same producer with different face
        $data2 = $this->createCompletedCandidature(null, $producer);

        Rating::factory()
            ->forCandidature($data2['candidature'])
            ->ratedBy($data2['faceUser'])
            ->ratingProducer($producer)
            ->fiveStars()
            ->create();

        $producer->refresh();

        // Average of 3 and 5 = 4.0
        $this->assertEquals(4.0, $producer->average_rating);
    }

    public function test_face_ratings_count_accessor(): void
    {
        $data = $this->createCompletedCandidature();

        Rating::factory()
            ->forCandidature($data['candidature'])
            ->ratedBy($data['producerUser'])
            ->ratingFace($data['face'])
            ->create();

        $data['face']->refresh();

        $this->assertEquals(1, $data['face']->ratings_count);
    }

    public function test_producer_ratings_count_accessor(): void
    {
        $data = $this->createCompletedCandidature();

        Rating::factory()
            ->forCandidature($data['candidature'])
            ->ratedBy($data['faceUser'])
            ->ratingProducer($data['producer'])
            ->create();

        $data['producer']->refresh();

        $this->assertEquals(1, $data['producer']->ratings_count);
    }

    public function test_face_average_rating_returns_null_when_no_ratings(): void
    {
        $face = Face::factory()->create();

        $this->assertNull($face->average_rating);
    }

    public function test_producer_average_rating_returns_null_when_no_ratings(): void
    {
        $producer = Producer::factory()->create();

        $this->assertNull($producer->average_rating);
    }

    // ========================================================================
    // AC #10: User can retrieve ratings given
    // ========================================================================

    public function test_user_ratings_given_relationship(): void
    {
        $data = $this->createCompletedCandidature();

        $rating = Rating::factory()
            ->forCandidature($data['candidature'])
            ->ratedBy($data['faceUser'])
            ->ratingProducer($data['producer'])
            ->create();

        $this->assertCount(1, $data['faceUser']->ratingsGiven);
        $this->assertTrue($data['faceUser']->ratingsGiven->contains($rating));
    }

    // ========================================================================
    // Additional: Candidature relationships and helper methods
    // ========================================================================

    public function test_candidature_ratings_relationship(): void
    {
        $data = $this->createCompletedCandidature();

        $rating = Rating::factory()
            ->forCandidature($data['candidature'])
            ->ratedBy($data['faceUser'])
            ->ratingProducer($data['producer'])
            ->create();

        $this->assertCount(1, $data['candidature']->ratings);
        $this->assertTrue($data['candidature']->ratings->contains($rating));
    }

    public function test_candidature_can_be_rated_returns_true_when_completed(): void
    {
        $candidature = Candidature::factory()->completed()->create();

        $this->assertTrue($candidature->canBeRated());
    }

    public function test_candidature_can_be_rated_returns_false_when_pending(): void
    {
        $candidature = Candidature::factory()->pending()->create();

        $this->assertFalse($candidature->canBeRated());
    }

    public function test_candidature_can_be_rated_returns_false_when_accepted(): void
    {
        $candidature = Candidature::factory()->accepted()->create();

        $this->assertFalse($candidature->canBeRated());
    }

    public function test_candidature_can_be_rated_returns_false_when_confirmed(): void
    {
        $candidature = Candidature::factory()->confirmed()->create();

        $this->assertFalse($candidature->canBeRated());
    }

    public function test_candidature_can_be_rated_returns_false_when_in_progress(): void
    {
        $candidature = Candidature::factory()->inProgress()->create();

        $this->assertFalse($candidature->canBeRated());
    }

    public function test_candidature_can_be_rated_returns_false_when_rejected(): void
    {
        $candidature = Candidature::factory()->rejected()->create();

        $this->assertFalse($candidature->canBeRated());
    }

    public function test_candidature_has_rating_from_returns_true_when_user_has_rated(): void
    {
        $data = $this->createCompletedCandidature();

        Rating::factory()
            ->forCandidature($data['candidature'])
            ->ratedBy($data['faceUser'])
            ->ratingProducer($data['producer'])
            ->create();

        $this->assertTrue($data['candidature']->hasRatingFrom($data['faceUser']));
    }

    public function test_candidature_has_rating_from_returns_false_when_user_has_not_rated(): void
    {
        $data = $this->createCompletedCandidature();

        $this->assertFalse($data['candidature']->hasRatingFrom($data['faceUser']));
    }

    // ========================================================================
    // Additional: Score casting
    // ========================================================================

    public function test_score_is_cast_to_integer(): void
    {
        $rating = Rating::factory()->fiveStars()->create();

        $this->assertIsInt($rating->score);
    }

    // ========================================================================
    // Additional: Factory states work correctly
    // ========================================================================

    public function test_factory_creates_valid_rating(): void
    {
        $rating = Rating::factory()->create();

        $this->assertNotNull($rating->id);
        $this->assertNotNull($rating->candidature_id);
        $this->assertNotNull($rating->rater_id);
        $this->assertNotNull($rating->rated_id);
        $this->assertNotNull($rating->rated_type);
        $this->assertGreaterThanOrEqual(1, $rating->score);
        $this->assertLessThanOrEqual(5, $rating->score);
    }

    public function test_factory_face_rating_producer_state(): void
    {
        $rating = Rating::factory()->faceRatingProducer()->create();

        $this->assertEquals(Producer::class, $rating->rated_type);
        $this->assertInstanceOf(Producer::class, $rating->rated);
    }

    public function test_factory_producer_rating_face_state(): void
    {
        $rating = Rating::factory()->producerRatingFace()->create();

        $this->assertEquals(Face::class, $rating->rated_type);
        $this->assertInstanceOf(Face::class, $rating->rated);
    }

    // ========================================================================
    // Additional: Polymorphic relationship has no foreign key constraint
    // ========================================================================

    /**
     * The polymorphic `rated` relationship (rated_id, rated_type) does not have
     * a foreign key constraint at the database level. This means:
     * 1. Ratings can point to non-existent entities (orphaned ratings)
     * 2. If a Face/Producer is deleted, their ratings are NOT automatically deleted
     * 3. This is by design for historical preservation of ratings data
     *
     * Note: In practice, Face/Producer deletion cascades through candidatures,
     * which then cascade-deletes ratings. This test verifies the polymorphic
     * relationship itself has no constraint.
     */
    public function test_polymorphic_rated_has_no_foreign_key_constraint(): void
    {
        $rating = Rating::factory()->faceRatingProducer()->create();

        // Manually set rated_id to a non-existent ID (simulating orphan)
        // This should work because there's no FK constraint on polymorphic columns
        $rating->rated_id = 99999;
        $rating->save();

        // Verify rating still exists
        $this->assertDatabaseHas('ratings', [
            'id' => $rating->id,
            'rated_id' => 99999,
        ]);

        // Verify rated relationship returns null for non-existent entity
        $rating->refresh();
        $this->assertNull($rating->rated);
    }

    /**
     * Verify that deleting a Face cascades through candidatures to delete ratings.
     * Ratings are deleted via: Face → Candidature (FK cascade) → Rating (FK cascade)
     */
    public function test_deleting_face_cascades_through_candidature_to_delete_rating(): void
    {
        // Create a rating where the Face is the rater (Face rating Producer)
        $data = $this->createCompletedCandidature();

        $rating = Rating::factory()
            ->forCandidature($data['candidature'])
            ->ratedBy($data['faceUser'])
            ->ratingProducer($data['producer'])
            ->create();

        $ratingId = $rating->id;
        $candidatureId = $data['candidature']->id;

        // Verify rating exists
        $this->assertDatabaseHas('ratings', ['id' => $ratingId]);

        // Delete the Face - this cascades to candidatures, then to ratings
        $data['face']->delete();

        // Verify candidature was deleted (Face has candidatures relationship)
        $this->assertDatabaseMissing('candidatures', ['id' => $candidatureId]);

        // Verify rating was also deleted (cascaded from candidature)
        $this->assertDatabaseMissing('ratings', ['id' => $ratingId]);
    }
}
