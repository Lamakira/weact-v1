<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lot B12 : le filtre de coordonnées s'applique aux motifs de refus / d'annulation lus par
 * l'autre partie AVANT paiement (chat fermé) ; après paiement la messagerie est ouverte.
 */
class BookingReasonContactFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private User $faceUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => Producer::factory()->create()->id,
        ]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => Face::factory()->create()->id,
        ]);
    }

    private function booking(string $state, array $attributes = []): Booking
    {
        return Booking::factory()->{$state}()->create($attributes + [
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);
    }

    public function test_refusal_reason_with_phone_number_is_rejected_and_status_unchanged(): void
    {
        $booking = $this->booking('pending');

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/refuse", [
                'cancellation_reason' => 'Appelez-moi au 97 12 34 56 pour en discuter',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cancellation_reason');

        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
    }

    public function test_clean_refusal_reason_is_allowed(): void
    {
        $booking = $this->booking('pending');

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/refuse", [
                'cancellation_reason' => 'Je ne suis pas disponible à ces dates.',
            ])
            ->assertOk();

        $this->assertSame(BookingStatus::Refused, $booking->fresh()->status);
    }

    public function test_ugc_commission_paid_refusal_reason_with_contact_is_rejected(): void
    {
        $booking = $this->booking('pending', [
            'type_contenu' => 'UGC',
            'status' => BookingStatus::CommissionPaid,
        ]);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/refuse", [
                'cancellation_reason' => 'Écrivez-moi sur instagram.com/moi',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cancellation_reason');

        $this->assertSame(BookingStatus::CommissionPaid, $booking->fresh()->status);
    }

    public function test_custom_cancellation_reason_with_instagram_link_is_rejected_on_accepted_booking(): void
    {
        $booking = $this->booking('accepted');

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/cancel", [
                'cancellation_reason' => 'other',
                'custom_cancellation_reason' => 'Contactez-moi : https://instagram.com/moi',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('custom_cancellation_reason');

        $this->assertSame(BookingStatus::Accepted, $booking->fresh()->status);
    }

    public function test_custom_cancellation_reason_with_contact_is_allowed_on_paid_booking(): void
    {
        $booking = $this->booking('paid');

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/cancel", [
                'cancellation_reason' => 'other',
                'custom_cancellation_reason' => 'Contactez-moi : https://instagram.com/moi',
            ])
            ->assertOk();

        $this->assertSame(BookingStatus::CancelledByProducer, $booking->fresh()->status);
    }

    public function test_clean_custom_cancellation_reason_is_allowed_on_pending_booking(): void
    {
        $booking = $this->booking('pending');

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/cancel", [
                'cancellation_reason' => 'other',
                'custom_cancellation_reason' => 'Le projet est reporté.',
            ])
            ->assertOk();

        $this->assertSame(BookingStatus::CancelledByProducer, $booking->fresh()->status);
    }
}
