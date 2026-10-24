<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Mail\UnreadMessagesMail;
use App\Models\Booking;
use App\Models\BookingMessage;
use App\Models\Candidature;
use App\Models\Conversation;
use App\Models\Face;
use App\Models\Message;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Commande messages:remind-unread : e-mail si un message reste non lu plus d'1 h,
 * au plus un par fil et par destinataire sur 24 h.
 */
class RemindUnreadMessagesCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    private User $producerUser;

    private Face $face;

    private Producer $producer;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Carbon::setTestNow('2030-10-10 12:00:00');

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function conversation(?string $dateTournage = null): Conversation
    {
        $mission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'titre' => 'Lookbook Wax',
        ] + ($dateTournage !== null ? ['date_tournage' => $dateTournage] : []));
        $candidature = Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $this->face->id,
            'status' => CandidatureStatus::Accepted,
        ]);

        return Conversation::factory()->create(['candidature_id' => $candidature->id]);
    }

    private function message(Conversation $conversation, User $sender, int $minutesAgo, bool $read = false): Message
    {
        return Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'sender_type' => User::class,
            'content' => 'Mon numéro est le 0197000000',
            'read_at' => $read ? now() : null,
            'created_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    private function booking(BookingStatus $status = BookingStatus::Paid, string $dateDebut = '2030-12-05 10:00:00'): Booking
    {
        return Booking::factory()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => $status,
            'type_contenu' => 'Clip musical',
            'date_debut' => Carbon::parse($dateDebut, 'UTC'),
        ]);
    }

    private function bookingMessage(Booking $booking, User $sender, int $minutesAgo, bool $read = false): BookingMessage
    {
        return BookingMessage::factory()->create([
            'booking_id' => $booking->id,
            'sender_id' => $sender->id,
            'read_at' => $read ? now() : null,
            'created_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    private function runCommand(): void
    {
        $this->artisan('messages:remind-unread')->assertSuccessful();
    }

    private function queuedFor(User $user): int
    {
        return Mail::queued(UnreadMessagesMail::class, fn (UnreadMessagesMail $m) => $m->hasTo($user->email))->count();
    }

    public function test_conversation_email_is_sent_after_one_hour_but_not_before(): void
    {
        $conversation = $this->conversation();
        $this->message($conversation, $this->producerUser, 59);

        $this->runCommand();
        Mail::assertNothingQueued();

        $this->message($conversation, $this->producerUser, 61);
        $this->runCommand();

        Mail::assertQueued(UnreadMessagesMail::class, 1);
        Mail::assertQueued(UnreadMessagesMail::class, function (UnreadMessagesMail $mail) use ($conversation): bool {
            return $mail->hasTo($this->faceUser->email)
                && $mail->unreadCount === 2 // tous les non lus du fil, y compris le message de 59 min
                && $mail->contextLabel === 'Lookbook Wax'
                && $mail->senderName === $this->producer->display_name
                && $mail->threadUrl === rtrim((string) config('app.frontend_url'), '/')."/face/conversations/{$conversation->uuid}";
        });
    }

    public function test_email_never_contains_the_message_text(): void
    {
        $conversation = $this->conversation();
        $this->message($conversation, $this->producerUser, 90);

        $this->runCommand();

        Mail::assertQueued(UnreadMessagesMail::class, function (UnreadMessagesMail $mail): bool {
            $html = $mail->render();

            return ! str_contains($html, '0197000000')
                && str_contains($html, 'Lookbook Wax')
                && str_contains($html, $mail->threadUrl);
        });
    }

    public function test_booking_email_is_sent_for_a_readable_booking_thread(): void
    {
        $booking = $this->booking();
        $this->bookingMessage($booking, $this->faceUser, 120);

        $this->runCommand();

        Mail::assertQueued(UnreadMessagesMail::class, 1);
        Mail::assertQueued(UnreadMessagesMail::class, fn (UnreadMessagesMail $mail): bool => $mail->hasTo($this->producerUser->email)
            && $mail->unreadCount === 1
            && str_starts_with($mail->contextLabel, 'Booking · Clip musical · ')
            && $mail->threadUrl === rtrim((string) config('app.frontend_url'), '/')."/producer/conversations/{$booking->uuid}?kind=booking");
    }

    public function test_at_most_one_email_per_thread_and_recipient_per_24_hours(): void
    {
        $conversation = $this->conversation();
        $this->message($conversation, $this->producerUser, 90);

        $this->runCommand();
        $this->runCommand(); // idempotence : même créneau, rien de plus
        $this->assertSame(1, $this->queuedFor($this->faceUser));

        // 23 h plus tard : toujours dans la fenêtre de 24 h, même avec un nouveau message ancien
        Carbon::setTestNow(now()->addHours(23));
        $this->message($conversation, $this->producerUser, 70);
        $this->runCommand();
        $this->assertSame(1, $this->queuedFor($this->faceUser));

        // Au-delà de 24 h après le premier envoi : un nouvel e-mail, puis de nouveau plus rien
        Carbon::setTestNow(now()->addHours(2));
        $this->runCommand();
        $this->runCommand();
        $this->assertSame(2, $this->queuedFor($this->faceUser));
    }

    public function test_a_thread_never_opened_does_not_trigger_endless_reminders(): void
    {
        $conversation = $this->conversation();
        $this->message($conversation, $this->producerUser, 90);

        $this->runCommand();
        $this->assertSame(1, $this->queuedFor($this->faceUser));

        // Aucun nouveau message : ni à +25 h, ni à +49 h
        Carbon::setTestNow(now()->addHours(25));
        $this->runCommand();
        Carbon::setTestNow(now()->addHours(24));
        $this->runCommand();
        $this->assertSame(1, $this->queuedFor($this->faceUser));

        // Nouveau message à +30 h (depuis le premier envoi) : un e-mail une fois qu'il a plus d'1 h (+31 h)
        Carbon::setTestNow(Carbon::parse('2030-10-10 12:00:00')->addHours(30));
        $this->message($conversation, $this->producerUser, 0);
        $this->runCommand();
        $this->assertSame(1, $this->queuedFor($this->faceUser));

        Carbon::setTestNow(Carbon::parse('2030-10-10 12:00:00')->addHours(31)->addMinute());
        $this->runCommand();
        $this->assertSame(2, $this->queuedFor($this->faceUser));
    }

    public function test_unread_messages_older_than_72_hours_are_ignored(): void
    {
        $conversation = $this->conversation();
        $this->message($conversation, $this->producerUser, 4 * 24 * 60);
        $booking = $this->booking();
        $this->bookingMessage($booking, $this->faceUser, 4 * 24 * 60);

        $this->runCommand();

        Mail::assertNothingQueued();
    }

    public function test_no_email_to_an_inactive_account(): void
    {
        $this->faceUser->forceFill(['is_active' => false])->save();
        $this->message($this->conversation(), $this->producerUser, 120);

        $this->runCommand();

        Mail::assertNothingQueued();
    }

    public function test_cooldown_is_per_thread_and_per_recipient(): void
    {
        $a = $this->conversation();
        $b = $this->conversation();
        $this->message($a, $this->producerUser, 90);
        $this->message($b, $this->producerUser, 90);
        // Autre sens sur le fil A : le Producteur a lui aussi un message non lu de la Face
        $this->message($a, $this->faceUser, 90);

        $this->runCommand();

        // Face : fils A et B ; Producteur : fil A uniquement
        $this->assertSame(2, $this->queuedFor($this->faceUser));
        $this->assertSame(1, $this->queuedFor($this->producerUser));
    }

    public function test_nothing_is_sent_for_read_messages(): void
    {
        $conversation = $this->conversation();
        $this->message($conversation, $this->producerUser, 120, true);
        $booking = $this->booking();
        $this->bookingMessage($booking, $this->faceUser, 120, true);

        $this->runCommand();

        Mail::assertNothingQueued();
    }

    public function test_nothing_is_sent_if_the_thread_is_read_before_the_run(): void
    {
        $conversation = $this->conversation();
        $message = $this->message($conversation, $this->producerUser, 120);
        $message->update(['read_at' => now()]);

        $this->runCommand();

        Mail::assertNothingQueued();
    }

    public function test_the_sender_is_never_notified_of_their_own_messages(): void
    {
        $conversation = $this->conversation();
        $this->message($conversation, $this->producerUser, 120);
        $booking = $this->booking();
        $this->bookingMessage($booking, $this->producerUser, 120);

        $this->runCommand();

        $this->assertSame(2, $this->queuedFor($this->faceUser));
        $this->assertSame(0, $this->queuedFor($this->producerUser));
    }

    public function test_no_email_when_the_user_cannot_access_the_booking_thread(): void
    {
        // Chat verrouillé (statuts non lisibles selon BookingPolicy::viewMessages)
        $this->bookingMessage($this->booking(BookingStatus::Pending), $this->producerUser, 120);
        $this->bookingMessage($this->booking(BookingStatus::Accepted), $this->producerUser, 120);
        $this->bookingMessage($this->booking(BookingStatus::CancelledByProducer), $this->producerUser, 120);

        $this->runCommand();

        Mail::assertNothingQueued();
    }

    public function test_no_email_to_an_unverified_address(): void
    {
        $this->faceUser->forceFill(['email_verified_at' => null])->save();
        $this->message($this->conversation(), $this->producerUser, 120);

        $this->runCommand();

        Mail::assertNothingQueued();
    }

    public function test_urgent_conversation_email_is_sent_after_fifteen_minutes(): void
    {
        // Tournage le 2030-10-11 (00:00 Porto-Novo = 10-10 23:00 UTC) : dans les 48 h
        $conversation = $this->conversation('2030-10-11');
        $this->message($conversation, $this->producerUser, 14);

        $this->runCommand();
        Mail::assertNothingQueued();

        $this->message($conversation, $this->producerUser, 16);
        $this->runCommand();

        Mail::assertQueued(UnreadMessagesMail::class, 1);
        $this->assertSame(1, $this->queuedFor($this->faceUser));
    }

    public function test_non_urgent_conversation_email_is_not_sent_after_twenty_minutes(): void
    {
        // Date de tournage figée loin dans le futur : fenêtre « 1 h » classique
        $conversation = $this->conversation('2030-12-05');
        $this->message($conversation, $this->producerUser, 20);

        $this->runCommand();

        Mail::assertNothingQueued();
    }

    public function test_urgent_booking_email_is_sent_after_fifteen_minutes(): void
    {
        $booking = $this->booking(BookingStatus::Paid, '2030-10-11 10:00:00');
        $this->bookingMessage($booking, $this->producerUser, 14);

        $this->runCommand();
        Mail::assertNothingQueued();

        $this->bookingMessage($booking, $this->producerUser, 16);
        $this->runCommand();

        Mail::assertQueued(UnreadMessagesMail::class, 1);
        $this->assertSame(1, $this->queuedFor($this->faceUser));
    }

    public function test_finished_shoot_day_is_not_urgent(): void
    {
        // Jour de tournage terminé depuis plus de 24 h : retour au délai d'1 h
        $conversation = $this->conversation('2030-10-08');
        $this->message($conversation, $this->producerUser, 20);
        $booking = $this->booking(BookingStatus::Paid, '2030-10-08 10:00:00');
        $this->bookingMessage($booking, $this->producerUser, 20);

        $this->runCommand();

        Mail::assertNothingQueued();
    }

    public function test_command_is_scheduled_every_five_minutes_without_overlapping(): void
    {

        $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'messages:remind-unread'));

        $this->assertNotNull($event);
        $this->assertSame('*/5 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }
}
