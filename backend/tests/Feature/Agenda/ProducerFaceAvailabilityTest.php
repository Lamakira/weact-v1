<?php

declare(strict_types=1);

namespace Tests\Feature\Agenda;

use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Models\FaceUnavailableDay;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Tests\Feature\DateChange\DateChangeTestCase;

/**
 * GET /api/v1/producer/candidates/{face}/availability : « Libre » ou « Indisponible », sans motif.
 */
class ProducerFaceAvailabilityTest extends DateChangeTestCase
{
    private function availability(User $as, string $faceUuid, string $from = '2026-11-01', string $to = '2026-11-30'): TestResponse
    {
        return $this->actingAs($as)->getJson("/api/v1/producer/candidates/{$faceUuid}/availability?from={$from}&to={$to}");
    }

    private function faceUuid(User $faceUser): string
    {
        return \App\Models\Face::query()->findOrFail($faceUser->userable_id)->uuid;
    }

    public function test_it_returns_the_union_of_blocked_booked_and_mission_days(): void
    {
        $faceUser = $this->faceUser();

        FaceUnavailableDay::create(['face_id' => $faceUser->userable_id, 'day' => '2026-11-12']);
        FaceUnavailableDay::create(['face_id' => $faceUser->userable_id, 'day' => '2026-11-19']);
        $this->cashBooking(['status' => BookingStatus::Paid, 'date_debut' => '2026-11-18 09:00:00', 'date_fin' => '2026-11-20 17:00:00'], $faceUser);
        [$mission, $payment] = $this->paidMission([], '2026-11-24');
        $this->selectedFaceFor($faceUser, $mission, $payment, CandidatureStatus::Accepted);

        $response = $this->availability($this->producerUser, $this->faceUuid($faceUser))->assertOk();

        $response->assertExactJson(['data' => [
            'from' => '2026-11-01',
            'to' => '2026-11-30',
            'today' => '2026-11-10',
            'unavailable_days' => ['2026-11-12', '2026-11-18', '2026-11-19', '2026-11-20', '2026-11-24'],
        ]]);
    }

    public function test_a_pending_booking_does_not_make_a_day_unavailable(): void
    {
        $faceUser = $this->faceUser();
        $this->cashBooking(['status' => BookingStatus::Pending, 'date_debut' => '2026-11-18 09:00:00', 'date_fin' => '2026-11-18 17:00:00'], $faceUser);

        $this->availability($this->producerUser, $this->faceUuid($faceUser))
            ->assertOk()
            ->assertJsonPath('data.unavailable_days', []);
    }

    public function test_the_window_clamps_multi_day_bookings_and_ignores_blocked_days_outside(): void
    {
        $faceUser = $this->faceUser();
        FaceUnavailableDay::create(['face_id' => $faceUser->userable_id, 'day' => '2026-12-12']);
        $this->cashBooking(['date_debut' => '2026-10-30 09:00:00', 'date_fin' => '2026-11-02 17:00:00'], $faceUser);

        $this->availability($this->producerUser, $this->faceUuid($faceUser))
            ->assertOk()
            ->assertJsonPath('data.unavailable_days', ['2026-11-01', '2026-11-02']);
    }

    public function test_a_booking_at_2330_utc_counts_on_the_business_day(): void
    {
        $faceUser = $this->faceUser();
        $this->cashBooking(['date_debut' => '2026-11-19 23:30:00', 'date_fin' => '2026-11-20 22:30:00'], $faceUser);

        $this->availability($this->producerUser, $this->faceUuid($faceUser))
            ->assertOk()
            ->assertJsonPath('data.unavailable_days', ['2026-11-20']);
    }

    public function test_the_range_is_validated(): void
    {
        $uuid = $this->faceUuid($this->faceUser());

        $this->availability($this->producerUser, $uuid, '2026-01-01', '2026-04-04')->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->availability($this->producerUser, $uuid, '2026-11-10', '2026-11-09')->assertUnprocessable()->assertJsonValidationErrors('to');
    }

    public function test_a_face_cannot_use_the_producer_endpoint(): void
    {
        $faceUser = $this->faceUser();

        $this->availability($faceUser, $this->faceUuid($faceUser))->assertForbidden();
    }

    public function test_a_deactivated_face_is_not_found(): void
    {
        $faceUser = $this->faceUser();
        $faceUser->update(['is_active' => false]);

        $this->availability($this->producerUser, $this->faceUuid($faceUser))->assertNotFound();
    }
}
