<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Events\BookingCreated;
use App\Listeners\Booking\NotifyFaceOnBookingReceived;
use App\Mail\BookingReceivedMail;
use App\Mail\BookingResponseReminderMail;
use App\Models\Booking;
use App\Models\Face;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Lot B10 — la Face répond à une demande de booking cash sous 72 h après sa création,
 * ou avant le début du jour de tournage s'il arrive plus tôt (fuseau métier). Relance
 * unique quand il reste 24 h (soit à 48 h), échéance affichée partout.
 */
class FaceBookingResponseDeadlineTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private User $faceUser;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        // Mardi 20/10/2026 12:00 UTC = 13:00 à Porto-Novo (UTC+1).
        $this->travelTo(Carbon::parse('2026-10-20 12:00:00', 'UTC'));

        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => Producer::factory()->create()->id,
            'email' => 'producer@test.bj',
        ]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => Face::factory()->create(['prenom' => 'Awa'])->id,
            'email' => 'face@test.bj',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function pendingCash(array $overrides = []): Booking
    {
        return Booking::factory()->pending()->create(array_merge([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'type_contenu' => 'Publicité',
            'date_debut' => '2026-11-10 00:00:00',
            'date_fin' => '2026-11-10 00:00:00',
            'created_at' => now(),
        ], $overrides));
    }

    // --- Échéance ---

    public function test_deadline_is_72_hours_after_creation_when_the_shooting_day_is_later(): void
    {
        $booking = $this->pendingCash();

        $this->assertTrue($booking->faceResponseDeadline()->equalTo(Carbon::parse('2026-10-23 12:00:00', 'UTC')));
    }

    public function test_deadline_is_the_start_of_the_shooting_day_in_business_time_when_earlier(): void
    {
        $booking = $this->pendingCash(['date_debut' => '2026-10-22 00:00:00', 'date_fin' => '2026-10-22 00:00:00']);

        // 22/10 00:00 à Porto-Novo = 21/10 23:00 UTC.
        $this->assertTrue($booking->faceResponseDeadline()->equalTo(Carbon::parse('2026-10-21 23:00:00', 'UTC')));
    }

    public function test_ugc_bookings_have_no_face_response_deadline(): void
    {
        $booking = $this->pendingCash(['type_contenu' => 'UGC', 'date_debut' => null, 'date_fin' => null]);

        $this->assertNull($booking->faceResponseDeadline());
    }

    public function test_the_api_exposes_the_deadline_only_while_the_request_is_pending(): void
    {
        $booking = $this->pendingCash();

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}")
            ->assertOk()
            ->assertJsonPath('data.face_response_deadline', '2026-10-23T12:00:00+00:00');

        $booking->update(['status' => BookingStatus::Accepted, 'accepted_at' => now()]);

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}")
            ->assertOk()
            ->assertJsonPath('data.face_response_deadline', null);
    }

    // --- Expiration ---

    public function test_a_request_left_unanswered_for_72_hours_expires(): void
    {
        $booking = $this->pendingCash();

        $this->travelTo(Carbon::parse('2026-10-23 11:59:00', 'UTC'));
        $this->artisan('bookings:expire-unaccepted')->assertExitCode(Command::SUCCESS);
        $this->assertSame(BookingStatus::Pending, $booking->refresh()->status);

        $this->travelTo(Carbon::parse('2026-10-23 12:00:00', 'UTC'));
        $this->artisan('bookings:expire-unaccepted')
            ->expectsOutput('Found 1 booking(s) to expire.')
            ->expectsOutput("Expired booking #{$booking->id}")
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame(BookingStatus::Expired, $booking->refresh()->status);
    }

    public function test_a_request_expires_at_the_start_of_the_shooting_day_when_earlier(): void
    {
        $booking = $this->pendingCash(['date_debut' => '2026-10-21 00:00:00', 'date_fin' => '2026-10-21 00:00:00']);

        $this->travelTo(Carbon::parse('2026-10-20 22:59:00', 'UTC'));
        $this->artisan('bookings:expire-unaccepted')->expectsOutput('Found 0 booking(s) to expire.');
        $this->assertSame(BookingStatus::Pending, $booking->refresh()->status);

        // 21/10 00:00 à Porto-Novo.
        $this->travelTo(Carbon::parse('2026-10-20 23:00:00', 'UTC'));
        $this->artisan('bookings:expire-unaccepted')->expectsOutput('Found 1 booking(s) to expire.');
        $this->assertSame(BookingStatus::Expired, $booking->refresh()->status);
    }

    public function test_the_cash_expiry_command_never_touches_ugc_requests(): void
    {
        $ugc = $this->pendingCash([
            'type_contenu' => 'UGC',
            'date_debut' => null,
            'date_fin' => null,
            'created_at' => now()->subDays(5),
        ]);

        $this->artisan('bookings:expire-unaccepted')->expectsOutput('Found 0 booking(s) to expire.');

        $this->assertSame(BookingStatus::Pending, $ugc->refresh()->status);
    }

    public function test_both_parties_are_told_the_face_did_not_answer_in_time(): void
    {
        $booking = $this->pendingCash(['created_at' => now()->subHours(73)]);

        $this->artisan('bookings:expire-unaccepted')->assertExitCode(Command::SUCCESS);

        foreach ([$this->producerUser, $this->faceUser] as $user) {
            $message = Notification::query()
                ->where('user_id', $user->id)
                ->where('type', 'booking_expired')
                ->sole()
                ->data['message'];
            $this->assertStringContainsString("la Face n'a pas répondu dans le délai", $message);
        }
        $this->assertSame(BookingStatus::Expired, $booking->refresh()->status);
    }

    public function test_the_face_cannot_accept_after_the_deadline_even_before_the_cron_ran(): void
    {
        $booking = $this->pendingCash();

        // Une minute avant l'échéance (23/10 12:00 UTC) : acceptable, bouton actif.
        $this->travelTo(Carbon::parse('2026-10-23 11:59:00', 'UTC'));
        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}")
            ->assertJsonPath('data.can_accept', true);

        // Une minute après : le cron n'est pas passé (statut encore Pending) mais l'accept est refusé.
        $this->travelTo(Carbon::parse('2026-10-23 12:01:00', 'UTC'));

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}")
            ->assertJsonPath('data.can_accept', false);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/accept")
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'Le délai de réponse est dépassé : cette demande a expiré.');

        $this->assertSame(BookingStatus::Pending, $booking->refresh()->status);
        $this->assertNull($booking->accepted_at);
    }

    public function test_the_service_itself_refuses_an_accept_past_the_deadline(): void
    {
        $booking = $this->pendingCash();
        $this->travelTo(Carbon::parse('2026-10-23 12:00:00', 'UTC'));

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('Le délai de réponse est dépassé : cette demande a expiré.');

        app(\App\Services\BookingService::class)->accept($booking);
    }

    // --- Relance à 48 h ---

    public function test_the_face_is_reminded_once_when_24_hours_remain(): void
    {
        $booking = $this->pendingCash();

        $this->travelTo(Carbon::parse('2026-10-22 11:59:00', 'UTC'));
        $this->artisan('bookings:remind-face-response')->expectsOutput('Found 0 booking(s) requiring a response reminder.');

        $this->travelTo(Carbon::parse('2026-10-22 12:00:00', 'UTC'));
        $this->artisan('bookings:remind-face-response')
            ->expectsOutput('Found 1 booking(s) requiring a response reminder.')
            ->assertExitCode(Command::SUCCESS);
        $this->artisan('bookings:remind-face-response')->expectsOutput('Found 0 booking(s) requiring a response reminder.');

        $notification = Notification::query()
            ->where('user_id', $this->faceUser->id)
            ->where('type', 'booking_response_reminder')
            ->sole();
        // 23/10 12:00 UTC = 13:00 à Porto-Novo.
        $this->assertStringContainsString('Répondez avant le 23/10 à 13:00', $notification->data['message']);
        $this->assertSame("/face/bookings/{$booking->uuid}", $notification->data['url']);

        Mail::assertQueued(BookingResponseReminderMail::class, 1);
        Mail::assertQueued(BookingResponseReminderMail::class, fn (BookingResponseReminderMail $mail): bool => $mail->hasTo('face@test.bj'));
        $this->assertNotNull($booking->refresh()->response_reminder_sent_at);
    }

    public function test_no_reminder_when_the_whole_window_is_24_hours_or_less(): void
    {
        // Tournage le 21/10 : échéance 20/10 23:00 UTC, onze heures après la demande.
        $this->pendingCash(['date_debut' => '2026-10-21 00:00:00', 'date_fin' => '2026-10-21 00:00:00']);

        $this->travelTo(Carbon::parse('2026-10-20 22:00:00', 'UTC'));
        $this->artisan('bookings:remind-face-response')->expectsOutput('Found 0 booking(s) requiring a response reminder.');

        Mail::assertNothingQueued();
    }

    public function test_no_reminder_for_answered_or_expired_requests(): void
    {
        $this->pendingCash(['status' => BookingStatus::Accepted, 'accepted_at' => now()]);
        $this->pendingCash(['status' => BookingStatus::Refused]);

        $this->travelTo(Carbon::parse('2026-10-22 13:00:00', 'UTC'));
        $this->artisan('bookings:remind-face-response')->expectsOutput('Found 0 booking(s) requiring a response reminder.');

        $this->travelTo(Carbon::parse('2026-10-23 13:00:00', 'UTC'));
        $this->artisan('bookings:remind-face-response')->expectsOutput('Found 0 booking(s) requiring a response reminder.');
    }

    public function test_reminder_email_states_the_deadline(): void
    {
        $booking = $this->pendingCash();

        $html = (new BookingResponseReminderMail($booking->fresh(['producer.userable', 'face.userable'])))->render();

        $this->assertStringContainsString('Répondez avant le 23/10 à 13:00', $html);
    }

    // --- Échéance annoncée à la réception ---

    public function test_the_booking_received_notification_states_the_deadline(): void
    {
        $booking = $this->pendingCash();

        (new NotifyFaceOnBookingReceived)->handle(new BookingCreated($booking));

        $message = Notification::query()
            ->where('user_id', $this->faceUser->id)
            ->where('type', 'booking_received')
            ->sole()
            ->data['message'];
        $this->assertStringContainsString('Répondez avant le 23/10 à 13:00.', $message);
    }

    public function test_the_booking_received_email_states_the_deadline(): void
    {
        $booking = $this->pendingCash()->fresh(['producer.userable', 'face.userable']);

        $html = (new BookingReceivedMail($booking->face->userable, $booking->producer->userable, $booking))->render();

        $this->assertStringContainsString('Répondez avant le 23/10 à 13:00', $html);
    }
}
