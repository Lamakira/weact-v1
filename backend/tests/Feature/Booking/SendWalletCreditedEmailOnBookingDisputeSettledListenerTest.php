<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Enums\DisputeResolutionOutcome;
use App\Enums\WalletCreditMotif;
use App\Events\BookingDisputeSettled;
use App\Listeners\Booking\SendWalletCreditedEmailOnBookingDisputeSettled;
use App\Mail\WalletCreditedFaceMail;
use App\Mail\WalletCreditedMail;
use App\Models\Booking;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendWalletCreditedEmailOnBookingDisputeSettledListenerTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private User $faceUser;

    protected function setUp(): void
    {
        parent::setUp();

        $producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
            'email' => 'studio@example.test',
            'balance' => 50000,
        ]);

        $face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email' => 'face@example.test',
            'balance' => 90000,
        ]);
    }

    private function booking(BookingStatus $status): Booking
    {
        return Booking::factory()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => $status,
        ]);
    }

    public function test_favor_producer_on_no_show_queues_the_no_show_refund_mail(): void
    {
        Mail::fake();

        (new SendWalletCreditedEmailOnBookingDisputeSettled)->handle(
            new BookingDisputeSettled($this->booking(BookingStatus::NoShow), DisputeResolutionOutcome::FavorProducer, 50000)
        );

        Mail::assertQueuedCount(1);
        Mail::assertQueued(
            WalletCreditedMail::class,
            fn (WalletCreditedMail $mail): bool => $mail->hasTo('studio@example.test')
                && $mail->amount === 50000
                && $mail->motif === WalletCreditMotif::BookingNoShowRefund
                && $mail->newBalance === 50000,
        );
    }

    public function test_favor_producer_on_late_cancellation_uses_the_cancellation_motif(): void
    {
        Mail::fake();

        (new SendWalletCreditedEmailOnBookingDisputeSettled)->handle(
            new BookingDisputeSettled($this->booking(BookingStatus::CancelledByProducer), DisputeResolutionOutcome::FavorProducer, 45000)
        );

        Mail::assertQueued(
            WalletCreditedMail::class,
            fn (WalletCreditedMail $mail): bool => $mail->motif === WalletCreditMotif::BookingCancellationRefund,
        );
    }

    public function test_favor_face_on_late_cancellation_queues_the_face_mail(): void
    {
        Mail::fake();

        (new SendWalletCreditedEmailOnBookingDisputeSettled)->handle(
            new BookingDisputeSettled($this->booking(BookingStatus::CancelledByProducer), DisputeResolutionOutcome::FavorFace, 90000)
        );

        Mail::assertQueuedCount(1);
        Mail::assertQueued(
            WalletCreditedFaceMail::class,
            fn (WalletCreditedFaceMail $mail): bool => $mail->hasTo('face@example.test') && $mail->amount === 90000,
        );
    }

    public function test_favor_face_on_completed_no_show_sends_nothing_here(): void
    {
        Mail::fake();

        (new SendWalletCreditedEmailOnBookingDisputeSettled)->handle(
            new BookingDisputeSettled($this->booking(BookingStatus::Completed), DisputeResolutionOutcome::FavorFace, 90000)
        );

        Mail::assertNothingQueued();
    }

    public function test_listener_skips_when_producer_email_is_empty(): void
    {
        Mail::fake();

        $this->producerUser->update(['email' => '']);

        (new SendWalletCreditedEmailOnBookingDisputeSettled)->handle(
            new BookingDisputeSettled($this->booking(BookingStatus::NoShow), DisputeResolutionOutcome::FavorProducer, 50000)
        );

        Mail::assertNothingQueued();
    }
}
