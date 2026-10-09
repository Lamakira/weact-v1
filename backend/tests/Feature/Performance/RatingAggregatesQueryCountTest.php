<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Models\Booking;
use App\Models\BookingRating;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PERF-B1: rating aggregates are computed in the listing query, not per row.
 */
class RatingAggregatesQueryCountTest extends TestCase
{
    use RefreshDatabase;

    private User $raterUser;

    protected function setUp(): void
    {
        parent::setUp();

        $producer = Producer::factory()->create();
        $this->raterUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
    }

    /**
     * @param  list<int>  $candidatureScores
     * @param  list<int>  $bookingScores
     * @return array{0: Face, 1: User}
     */
    private function faceWithRatings(array $candidatureScores, array $bookingScores, float $penalty = 0.0): array
    {
        $face = Face::factory()->create(['rating_penalty' => $penalty]);
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        foreach ($candidatureScores as $score) {
            $mission = Mission::factory()->create(['producer_id' => $this->raterUser->userable_id]);
            $candidature = Candidature::factory()->completed()->create([
                'face_id' => $face->id,
                'mission_id' => $mission->id,
            ]);
            Rating::create([
                'candidature_id' => $candidature->id,
                'rater_id' => $this->raterUser->id,
                'rated_id' => $face->id,
                'rated_type' => Face::class,
                'score' => $score,
            ]);
        }

        foreach ($bookingScores as $score) {
            $booking = Booking::factory()->completed()->create([
                'face_id' => $faceUser->id,
                'producer_id' => $this->raterUser->id,
            ]);
            BookingRating::create([
                'booking_id' => $booking->id,
                'rater_id' => $this->raterUser->id,
                'rated_id' => $faceUser->id,
                'score' => $score,
            ]);
        }

        return [$face, $faceUser];
    }

    private function listingQueryCount(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/public/faces')->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_aggregate_scope_yields_same_values_as_lazy_accessors(): void
    {
        [$mixed] = $this->faceWithRatings([5, 4], [3], penalty: 0.5);
        [$none] = $this->faceWithRatings([], []);
        [$bookingOnly] = $this->faceWithRatings([], [2, 5]);

        foreach ([$mixed, $none, $bookingOnly] as $face) {
            $lazy = Face::query()->findOrFail($face->id);
            $eager = Face::query()->withRatingAggregates()->findOrFail($face->id);

            $this->assertSame($lazy->average_rating, $eager->average_rating);
            $this->assertSame($lazy->ratings_count, $eager->ratings_count);
        }

        $this->assertEqualsWithDelta(3.5, Face::query()->withRatingAggregates()->findOrFail($mixed->id)->average_rating, 0.0001);
        $this->assertSame(3, Face::query()->withRatingAggregates()->findOrFail($mixed->id)->ratings_count);
        $this->assertNull(Face::query()->withRatingAggregates()->findOrFail($none->id)->average_rating);
        $this->assertSame(0, Face::query()->withRatingAggregates()->findOrFail($none->id)->ratings_count);
        $this->assertEqualsWithDelta(3.5, Face::query()->withRatingAggregates()->findOrFail($bookingOnly->id)->average_rating, 0.0001);
    }

    public function test_aggregate_scope_yields_same_values_for_producers(): void
    {
        $producer = $this->raterUser->userable;
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => Face::factory()->create()->id,
        ]);
        $booking = Booking::factory()->completed()->create([
            'face_id' => $faceUser->id,
            'producer_id' => $this->raterUser->id,
        ]);
        BookingRating::create([
            'booking_id' => $booking->id,
            'rater_id' => $faceUser->id,
            'rated_id' => $this->raterUser->id,
            'score' => 4,
        ]);

        $lazy = Producer::query()->findOrFail($producer->id);
        $eager = Producer::query()->withRatingAggregates()->findOrFail($producer->id);

        $this->assertSame($lazy->average_rating, $eager->average_rating);
        $this->assertSame(4.0, $eager->average_rating);
        $this->assertSame($lazy->ratings_count, $eager->ratings_count);
        $this->assertSame(1, $eager->ratings_count);
    }

    public function test_public_faces_listing_query_count_does_not_grow_with_rows(): void
    {
        $this->faceWithRatings([5], [3]);
        $this->faceWithRatings([4], []);
        $this->faceWithRatings([], [2]);
        $small = $this->listingQueryCount();

        for ($i = 0; $i < 7; $i++) {
            $this->faceWithRatings([5, 3], [4]);
        }
        $large = $this->listingQueryCount();

        $this->assertSame($small, $large, "Listing queries grew with rows: {$small} -> {$large}");
    }

    public function test_public_faces_listing_keeps_average_rating_values(): void
    {
        [$face] = $this->faceWithRatings([5, 4], [3], penalty: 0.5);

        $row = collect($this->getJson('/api/v1/public/faces')->assertOk()->json('data'))
            ->firstWhere('username', $face->username);

        $this->assertEqualsWithDelta(3.5, $row['average_rating'], 0.0001);
    }
}
