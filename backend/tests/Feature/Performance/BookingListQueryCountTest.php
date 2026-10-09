<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingRating;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * PERF-B2: the booking list does not render full party profiles with per-row queries.
 */
class BookingListQueryCountTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
    }

    private function addBooking(): Booking
    {
        $face = Face::factory()->create(['rating_penalty' => 0.25]);
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $booking = Booking::factory()->create([
            'face_id' => $faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => BookingStatus::Pending,
        ]);

        $rated = Booking::factory()->completed()->create([
            'face_id' => $faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);
        BookingRating::create([
            'booking_id' => $rated->id,
            'rater_id' => $this->producerUser->id,
            'rated_id' => $faceUser->id,
            'score' => 4,
        ]);

        return $booking;
    }

    private function queryCount(string $url): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->producerUser)->getJson($url)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_bookings_index_query_count_is_constant(): void
    {
        $this->addBooking();
        $this->addBooking();
        $small = $this->queryCount('/api/v1/bookings');

        for ($i = 0; $i < 6; $i++) {
            $this->addBooking();
        }
        $large = $this->queryCount('/api/v1/bookings');

        $this->assertSame($small, $large, "Booking index queries grew: {$small} -> {$large}");
    }

    public function test_bookings_index_keeps_party_fields(): void
    {
        $booking = $this->addBooking();

        $row = collect($this->actingAs($this->producerUser)->getJson('/api/v1/bookings')->assertOk()->json('data'))
            ->firstWhere('id', $booking->uuid);

        $this->assertSame('Face', $row['face']['userable_type']);
        $this->assertSame(3.75, $row['face']['userable']['average_rating']);
        $this->assertSame(1, $row['face']['userable']['ratings_count']);
        $this->assertArrayHasKey('profile_completion_percentage', $row['face']['userable']);
        $this->assertArrayHasKey('has_elite_badge', $row['face']['userable']);
        $this->assertSame('Producer', $row['producer']['userable_type']);
        $this->assertArrayHasKey('display_name', $row['producer']['userable']);
        $this->assertArrayHasKey('average_rating', $row['producer']['userable']);
    }

    public function test_bookings_index_can_rate_flag_follows_viewer_rating(): void
    {
        $this->addBooking(); // contains one completed booking already rated by the viewer
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => Face::factory()->create()->id,
        ]);
        $unrated = Booking::factory()->completed()->create([
            'face_id' => $faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $rows = collect($this->actingAs($this->producerUser)->getJson('/api/v1/bookings')->assertOk()->json('data'));

        $this->assertTrue($rows->firstWhere('id', $unrated->uuid)['can_rate']);
        $completed = $rows->where('status', 'completed')->where('id', '!=', $unrated->uuid);
        $this->assertCount(1, $completed);
        $this->assertFalse($completed->first()['can_rate']);
    }

    public function test_booking_show_and_polled_status_endpoints_stay_bounded(): void
    {
        $booking = $this->addBooking();

        // Warm-up not needed (actingAs), the count is an upper bound on the whole request.
        foreach (["/api/v1/bookings/{$booking->uuid}", "/api/v1/bookings/{$booking->uuid}/payment-status"] as $url) {
            $this->assertLessThanOrEqual(30, $this->queryCount($url), $url);
        }
    }

    public function test_rate_policy_ignores_a_preloaded_or_forged_attribute(): void
    {
        $booking = $this->addBooking();
        $faceUser = User::query()->findOrFail($booking->face_id);
        $completed = Booking::query()
            ->where('face_id', $faceUser->id)
            ->where('status', BookingStatus::Completed)
            ->firstOrFail();

        // The viewer already rated this booking (see addBooking()).
        $completed->setAttribute('viewer_has_rated', false);

        $this->assertFalse(Gate::forUser($this->producerUser)->allows('rate', $completed));
    }
}
