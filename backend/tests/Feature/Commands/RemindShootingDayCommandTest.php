<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Mail\BookingShootingReminderMail;
use App\Mail\MissionShootingReminderMail;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RemindShootingDayCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_command_notifies_producer_and_only_confirmed_or_in_progress_faces(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $mission = Mission::factory()->published()->create([
            'producer_id' => $producer->id,
            'date_tournage' => now()->addDay()->format('Y-m-d'),
            'shooting_reminder_sent_at' => null,
        ]);

        $confirmedFace = Face::factory()->create();
        $confirmedUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $confirmedFace->id,
        ]);

        $acceptedFace = Face::factory()->create();
        $acceptedUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $acceptedFace->id,
        ]);

        $confirmedCandidature = Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $confirmedFace->id,
            'status' => CandidatureStatus::Confirmed,
        ]);

        $acceptedCandidature = Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $acceptedFace->id,
            'status' => CandidatureStatus::Accepted,
        ]);

        $this->artisan('missions:remind-shooting')
            ->assertExitCode(0);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $producerUser->id,
            'type' => 'shooting_day_reminder',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $confirmedUser->id,
            'type' => 'shooting_day_reminder',
        ]);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $acceptedUser->id,
            'type' => 'shooting_day_reminder',
        ]);

        $this->assertDatabaseHas('missions', [
            'id' => $mission->id,
        ]);

        $mission->refresh();
        $this->assertNotNull($mission->shooting_reminder_sent_at);

        // Lot C4 : le Producteur arrive directement sur l'onglet Présences de la page détail.
        $producerNotification = Notification::where('user_id', $producerUser->id)->where('type', 'shooting_day_reminder')->firstOrFail();
        $this->assertSame("/producer/missions/{$mission->uuid}?tab=presences", $producerNotification->data['url']);

        $confirmedNotification = Notification::where('user_id', $confirmedUser->id)->first();
        $this->assertSame($confirmedCandidature->id, $confirmedNotification->data['candidature_id']);

        $this->assertNull(Notification::where('user_id', $acceptedUser->id)->first());
        $this->assertSame(CandidatureStatus::Accepted, $acceptedCandidature->fresh()->status);

        // Lot D6 : l'e-mail suit exactement les destinataires de l'in-app.
        Mail::assertQueued(MissionShootingReminderMail::class, 2);
        Mail::assertQueued(MissionShootingReminderMail::class, fn (MissionShootingReminderMail $mail): bool => $mail->forProducer && $mail->hasTo($producerUser->email));
        Mail::assertQueued(MissionShootingReminderMail::class, fn (MissionShootingReminderMail $mail): bool => ! $mail->forProducer && $mail->hasTo($confirmedUser->email));
        Mail::assertNotQueued(MissionShootingReminderMail::class, fn (MissionShootingReminderMail $mail): bool => $mail->hasTo($acceptedUser->email));

        // Second passage : marqueur posé, aucun nouvel e-mail.
        $this->artisan('missions:remind-shooting')->assertExitCode(0);
        Mail::assertQueued(MissionShootingReminderMail::class, 2);
    }

    public function test_mission_reminder_email_failure_does_not_block_the_in_app_reminder_nor_the_marker(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));

        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
        $mission = Mission::factory()->published()->create([
            'producer_id' => $producer->id,
            'date_tournage' => now()->addDay()->format('Y-m-d'),
            'shooting_reminder_sent_at' => null,
        ]);

        $this->artisan('missions:remind-shooting')->assertExitCode(0);

        $this->assertDatabaseHas('notifications', ['user_id' => $producerUser->id, 'type' => 'shooting_day_reminder']);
        $this->assertNotNull($mission->fresh()->shooting_reminder_sent_at);
    }

    // ------------------------------------------------------------------
    // Lot D6 : bookings cash payés, rappel la veille du tournage
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function paidBooking(array $attributes = []): Booking
    {
        return Booking::factory()->create(array_merge([
            'status' => BookingStatus::Paid,
            'type_contenu' => 'Publicité',
            'lieu' => 'Cotonou, Haie Vive',
            'duree_heures' => 6,
            // Forme réelle : date calendaire sans heure (minuit UTC), demain.
            'date_debut' => now()->addDay()->startOfDay(),
        ], $attributes));
    }

    public function test_booking_reminder_notifies_and_emails_both_parties_the_day_before(): void
    {
        $booking = $this->paidBooking();

        $this->artisan('missions:remind-shooting')->assertExitCode(0);

        foreach ([$booking->producer_id => '/producer', $booking->face_id => '/face'] as $userId => $prefix) {
            $notification = Notification::where('user_id', $userId)->where('type', 'shooting_day_reminder')->firstOrFail();
            $this->assertSame("{$prefix}/bookings/{$booking->uuid}", $notification->data['url']);
            $this->assertSame($booking->id, $notification->data['booking_id']);
        }

        Mail::assertQueued(BookingShootingReminderMail::class, 2);
        Mail::assertQueued(BookingShootingReminderMail::class, fn (BookingShootingReminderMail $mail): bool => $mail->forProducer && $mail->hasTo($booking->producer->email));
        Mail::assertQueued(BookingShootingReminderMail::class, fn (BookingShootingReminderMail $mail): bool => ! $mail->forProducer && $mail->hasTo($booking->face->email));
        $this->assertNotNull($booking->fresh()->shooting_reminder_sent_at);

        // Contenu : date, lieu, durée.
        $html = (new BookingShootingReminderMail($booking->load('face.userable', 'producer.userable'), true))->render();
        $this->assertStringContainsString('Cotonou, Haie Vive', $html);
        $this->assertStringContainsString('6 heures', $html);
    }

    public function test_booking_reminder_is_idempotent(): void
    {
        $booking = $this->paidBooking();

        $this->artisan('missions:remind-shooting')->assertExitCode(0);
        $this->artisan('missions:remind-shooting')->assertExitCode(0);

        Mail::assertQueued(BookingShootingReminderMail::class, 2);
        $this->assertSame(2, Notification::where('type', 'shooting_day_reminder')->where('data->booking_id', $booking->id)->count());
    }

    public function test_booking_reminder_targets_the_right_day_with_midnight_dates_and_shows_no_hour(): void
    {
        // date_debut est stocké à 00:00 UTC : aucune conversion de fuseau, aucune heure affichée.
        $tomorrow = $this->paidBooking();
        $today = $this->paidBooking(['date_debut' => now()->startOfDay()]);
        $dayAfter = $this->paidBooking(['date_debut' => now()->addDays(2)->startOfDay()]);

        $this->artisan('missions:remind-shooting')->assertExitCode(0);

        $this->assertNotNull($tomorrow->fresh()->shooting_reminder_sent_at);
        $this->assertNull($today->fresh()->shooting_reminder_sent_at);
        $this->assertNull($dayAfter->fresh()->shooting_reminder_sent_at);

        foreach ([true, false] as $forProducer) {
            $html = (new BookingShootingReminderMail($tomorrow->load('face.userable', 'producer.userable'), $forProducer))->render();
            $this->assertStringNotContainsString('01h00', $html);
            $this->assertStringNotContainsString('Heure de début', $html);
            $this->assertStringContainsString(now()->addDay()->locale('fr')->translatedFormat('l d F Y'), $html);
        }
    }

    public function test_evening_catch_up_reminds_a_booking_paid_after_the_morning_run_without_duplicates(): void
    {
        $booking = $this->paidBooking(['status' => BookingStatus::Accepted]);

        // 08:00 : pas encore payé, rien à rappeler.
        $this->artisan('missions:remind-shooting')->assertExitCode(0);
        Mail::assertNothingQueued();

        // Payé dans la journée ; passage de 18:00.
        $booking->update(['status' => BookingStatus::Paid]);
        $this->artisan('missions:remind-shooting')->assertExitCode(0);
        Mail::assertQueued(BookingShootingReminderMail::class, 2);

        // Le lendemain matin / soir suivant : aucun doublon.
        $this->artisan('missions:remind-shooting')->assertExitCode(0);
        Mail::assertQueued(BookingShootingReminderMail::class, 2);
    }

    public function test_reminder_is_scheduled_at_08_and_18_without_overlapping(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->filter(fn ($event): bool => str_contains((string) $event->command, 'missions:remind-shooting'));

        $this->assertEqualsCanonicalizing(['0 8 * * *', '0 18 * * *'], $events->pluck('expression')->all());
        $this->assertTrue($events->every(fn ($event): bool => $event->withoutOverlapping));
    }

    public function test_booking_reminder_skips_unpaid_cancelled_and_ugc_bookings(): void
    {
        $skipped = [
            $this->paidBooking(['status' => BookingStatus::Accepted]),
            $this->paidBooking(['status' => BookingStatus::CancelledByFace]),
            $this->paidBooking(['status' => BookingStatus::CancelledByProducer]),
            $this->paidBooking(['status' => BookingStatus::Expired]),
            $this->paidBooking(['status' => BookingStatus::CommissionPaid, 'type_contenu' => 'UGC']),
            $this->paidBooking(['type_contenu' => 'UGC']),
        ];

        $this->artisan('missions:remind-shooting')->assertExitCode(0);

        Mail::assertNothingQueued();
        foreach ($skipped as $booking) {
            $this->assertNull($booking->fresh()->shooting_reminder_sent_at);
        }
    }

    public function test_booking_reminder_email_failure_does_not_block_in_app_nor_marker(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));

        $booking = $this->paidBooking();

        $this->artisan('missions:remind-shooting')->assertExitCode(0);

        $this->assertSame(2, Notification::where('type', 'shooting_day_reminder')->where('data->booking_id', $booking->id)->count());
        $this->assertNotNull($booking->fresh()->shooting_reminder_sent_at);
    }

    public function test_command_ignores_ugc_missions(): void
    {
        // UGC 2.4 : pas de tournage en UGC (livrables à distance) — le rappel J-1
        // ne doit notifier ni le Producteur ni les Faces engagées d'une mission UGC.
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $mission = Mission::factory()->published()->create([
            'producer_id' => $producer->id,
            'date_tournage' => now()->addDay()->format('Y-m-d'),
            'shooting_reminder_sent_at' => null,
            'type_mission' => 'ugc',
            'type_compensation' => 'product',
            'nom_produit' => 'Sneakers Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'commission_ugc' => 2500,
            'commission_paid_at' => now(),
        ]);

        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $face->id,
            'status' => CandidatureStatus::Confirmed,
        ]);

        $this->artisan('missions:remind-shooting')
            ->expectsOutput('Found 0 mission(s) with shooting tomorrow.')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('notifications', ['user_id' => $producerUser->id, 'type' => 'shooting_day_reminder']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $faceUser->id, 'type' => 'shooting_day_reminder']);
        $this->assertNull($mission->fresh()->shooting_reminder_sent_at);
    }
}
