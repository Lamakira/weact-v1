<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Events\BookingCreated;
use App\Listeners\Booking\SendBookingReceivedEmail;
use App\Mail\BookingReceivedMail;
use App\Models\Booking;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendBookingReceivedEmailListenerTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    private User $faceUser;

    private Face $face;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup miroir BookingNotificationTest L44-73 — same fixture, same fields.
        $this->producer = Producer::factory()->create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
        ]);
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);

        $this->face = Face::factory()->create([
            'prenom' => 'Awa',
            'nom' => 'Diallo',
        ]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
            'email' => 'awa@example.test',
        ]);

        $this->booking = Booking::factory()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => BookingStatus::Pending,
            'type_contenu' => 'Publicité',
            'lieu' => 'Cotonou',
            'duree_heures' => 8,
            'montant_face_recoit' => 45000,
        ]);
    }

    public function test_listener_queues_booking_received_mail_to_face(): void
    {
        Mail::fake();

        (new SendBookingReceivedEmail)->handle(new BookingCreated($this->booking));

        Mail::assertQueuedCount(1);
        Mail::assertQueued(
            BookingReceivedMail::class,
            fn (BookingReceivedMail $mail): bool => $mail->hasTo($this->faceUser->email)
                && $mail->face->is($this->face)
                && $mail->producer->is($this->producer)
                && $mail->booking->is($this->booking),
        );
    }

    public function test_listener_skips_when_face_userable_is_missing(): void
    {
        Mail::fake();

        // Point userable_id at a non-existent Face profile.
        $this->faceUser->update(['userable_id' => 999_999]);

        (new SendBookingReceivedEmail)->handle(new BookingCreated($this->booking));

        Mail::assertNothingQueued();
    }

    public function test_listener_skips_when_face_email_is_empty(): void
    {
        Mail::fake();

        $this->faceUser->update(['email' => '']);

        (new SendBookingReceivedEmail)->handle(new BookingCreated($this->booking));

        Mail::assertNothingQueued();
    }
}
