<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Events\BookingRefused;
use App\Mail\BookingRefusedMail;
use App\Models\Booking;
use App\Models\Face;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use App\Services\BookingService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Lot B9 — demandes concurrentes : quand la Face accepte une demande, ses autres
 * demandes en attente sur des dates qui se chevauchent sont refusées automatiquement,
 * avec un motif clair pour leurs Producteurs. Le motif de refus libre (1000 caractères)
 * tient en base (colonne TEXT).
 */
class ConcurrentBookingRequestsTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $face = Face::factory()->create([
            'prenom' => 'Awa',
            'nom' => 'Diallo',
            'tarif_journalier' => 30000,
            'is_available' => true,
        ]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
    }

    private function producerUser(string $email): User
    {
        return User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => Producer::factory()->create()->id,
            'email' => $email,
        ]);
    }

    private function pendingFor(User $producer, CarbonInterface $start, CarbonInterface $end, ?User $face = null): Booking
    {
        return Booking::factory()->pending()->create([
            'face_id' => ($face ?? $this->faceUser)->id,
            'producer_id' => $producer->id,
            'type_contenu' => 'Publicité',
            'date_debut' => $start,
            'date_fin' => $end,
        ]);
    }

    public function test_accepting_a_request_auto_refuses_the_faces_other_overlapping_pending_requests(): void
    {
        $start = now()->addDays(10)->startOfDay();
        $accepted = $this->pendingFor($this->producerUser('a@test.bj'), $start, $start->copy()->addDays(2));
        $overlapping = $this->pendingFor($this->producerUser('b@test.bj'), $start->copy()->addDay(), $start->copy()->addDays(4));
        // Commence le jour où la demande acceptée se termine : plage inclusive, donc chevauchement.
        $sameDayBoundary = $this->pendingFor($this->producerUser('c@test.bj'), $start->copy()->addDays(2), $start->copy()->addDays(2));
        $later = $this->pendingFor($this->producerUser('d@test.bj'), $start->copy()->addDays(5), $start->copy()->addDays(6));

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$accepted->uuid}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::Accepted->value);

        foreach ([$overlapping, $sameDayBoundary] as $booking) {
            $booking->refresh();
            $this->assertSame(BookingStatus::Refused, $booking->status);
            $this->assertSame(BookingService::OVERLAP_AUTO_REFUSAL_REASON, $booking->cancellation_reason);
        }

        $this->assertSame(BookingStatus::Pending, $later->refresh()->status);
        $this->assertSame(BookingStatus::Accepted, $accepted->refresh()->status);
    }

    public function test_other_faces_and_ugc_requests_are_never_auto_refused(): void
    {
        $start = now()->addDays(10)->startOfDay();
        $accepted = $this->pendingFor($this->producerUser('a@test.bj'), $start, $start->copy()->addDays(2));

        $otherFaceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => Face::factory()->create()->id,
        ]);
        $otherFacePending = $this->pendingFor($this->producerUser('b@test.bj'), $start, $start->copy()->addDays(2), $otherFaceUser);

        // Dotation UGC : sans dates, jamais concernée par le chevauchement.
        $ugcPending = Booking::factory()->pending()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser('c@test.bj')->id,
            'type_contenu' => 'UGC',
            'date_debut' => null,
            'date_fin' => null,
            'duree_heures' => null,
        ]);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$accepted->uuid}/accept")
            ->assertOk();

        $this->assertSame(BookingStatus::Pending, $otherFacePending->refresh()->status);
        $this->assertSame(BookingStatus::Pending, $ugcPending->refresh()->status);
    }

    public function test_auto_refused_producers_get_a_clear_in_app_notification_and_email(): void
    {
        $start = now()->addDays(10)->startOfDay();
        $acceptedProducer = $this->producerUser('a@test.bj');
        $refusedProducer = $this->producerUser('b@test.bj');
        $accepted = $this->pendingFor($acceptedProducer, $start, $start->copy()->addDays(2));
        $overlapping = $this->pendingFor($refusedProducer, $start->copy()->addDay(), $start->copy()->addDays(3));

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$accepted->uuid}/accept")
            ->assertOk();

        $notification = Notification::query()
            ->where('user_id', $refusedProducer->id)
            ->where('type', 'booking_refused')
            ->sole();
        $this->assertStringContainsString("n'est plus disponible sur ces dates", $notification->data['message']);
        $this->assertSame("/producer/bookings/{$overlapping->uuid}", $notification->data['url']);

        $this->assertFalse(Notification::query()
            ->where('user_id', $acceptedProducer->id)
            ->where('type', 'booking_refused')
            ->exists());

        Mail::assertQueued(BookingRefusedMail::class, function (BookingRefusedMail $mail) use ($overlapping): bool {
            return $mail->hasTo('b@test.bj')
                && $mail->automatic
                && $mail->booking->is($overlapping);
        });
        Mail::assertNotQueued(BookingRefusedMail::class, fn (BookingRefusedMail $mail): bool => $mail->hasTo('a@test.bj'));
    }

    public function test_auto_refusal_dispatches_an_automatic_refused_event_and_manual_refusal_does_not(): void
    {
        Event::fake([BookingRefused::class]);

        $start = now()->addDays(10)->startOfDay();
        $accepted = $this->pendingFor($this->producerUser('a@test.bj'), $start, $start->copy()->addDays(2));
        $overlapping = $this->pendingFor($this->producerUser('b@test.bj'), $start, $start);
        $manual = $this->pendingFor($this->producerUser('c@test.bj'), $start->copy()->addDays(20), $start->copy()->addDays(21));

        $this->actingAs($this->faceUser)->postJson("/api/v1/bookings/{$accepted->uuid}/accept")->assertOk();
        $this->actingAs($this->faceUser)->postJson("/api/v1/bookings/{$manual->uuid}/refuse")->assertOk();

        Event::assertDispatched(BookingRefused::class, fn (BookingRefused $e): bool => $e->booking->is($overlapping) && $e->automatic);
        Event::assertDispatched(BookingRefused::class, fn (BookingRefused $e): bool => $e->booking->is($manual) && ! $e->automatic);
        Event::assertDispatchedTimes(BookingRefused::class, 2);
    }

    public function test_auto_refused_email_explains_the_face_accepted_another_request(): void
    {
        $start = now()->addDays(10)->startOfDay();
        $booking = $this->pendingFor($this->producerUser('b@test.bj'), $start, $start);
        $booking->update([
            'status' => BookingStatus::Refused,
            'cancellation_reason' => BookingService::OVERLAP_AUTO_REFUSAL_REASON,
        ]);

        $html = (new BookingRefusedMail($booking->fresh(), automatic: true))->render();

        $this->assertStringContainsString('autre demande sur ces dates', $html);
        $this->assertStringNotContainsString('a refusé votre booking', $html);
        $this->assertSame("Votre demande de booking n'a pas abouti", (new BookingRefusedMail($booking, automatic: true))->envelope()->subject);
    }

    public function test_a_refusal_reason_of_1000_characters_is_stored_in_full(): void
    {
        $booking = $this->pendingFor($this->producerUser('a@test.bj'), now()->addDays(10), now()->addDays(10));
        $reason = str_repeat('é', 1000);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/refuse", ['cancellation_reason' => $reason])
            ->assertOk();

        $this->assertSame($reason, $booking->refresh()->cancellation_reason);
    }

    public function test_a_refusal_reason_over_1000_characters_is_rejected(): void
    {
        $booking = $this->pendingFor($this->producerUser('a@test.bj'), now()->addDays(10), now()->addDays(10));

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/refuse", ['cancellation_reason' => str_repeat('a', 1001)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cancellation_reason']);

        $this->assertSame(BookingStatus::Pending, $booking->refresh()->status);
    }
}
