<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Enums\EscrowStatus;
use App\Enums\WalletCreditMotif;
use App\Events\BookingCancelled;
use App\Listeners\Booking\SendWalletCreditedEmailOnBookingCancelled;
use App\Mail\WalletCreditedMail;
use App\Models\Booking;
use App\Models\EscrowTransaction;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendWalletCreditedEmailOnBookingCancelledListenerTest extends TestCase
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

        $this->producer = Producer::factory()->create([
            'first_name' => 'Studio',
            'last_name' => 'Alpha',
        ]);
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
            'email' => 'studio@example.test',
            'balance' => 27000, // post-crédit 90% de 30000
        ]);

        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);

        $this->booking = Booking::factory()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => BookingStatus::CancelledByProducer,
            'montant_total_producteur' => 30000,
            'montant_face_recoit' => 27000,
        ]);
    }

    public function test_listener_queues_wallet_credited_mail_on_paid_booking_cancellation_refund(): void
    {
        Mail::fake();

        EscrowTransaction::factory()->create([
            'booking_id' => $this->booking->id,
            'amount' => $this->booking->montant_face_recoit,
            'status' => EscrowStatus::Refunded->value,
            'refunded_at' => now(),
        ]);

        (new SendWalletCreditedEmailOnBookingCancelled)->handle(new BookingCancelled($this->booking));

        Mail::assertQueuedCount(1);
        Mail::assertQueued(
            WalletCreditedMail::class,
            fn (WalletCreditedMail $mail): bool => $mail->hasTo($this->producerUser->email)
                && $mail->producer->is($this->producer)
                && $mail->amount === 27_000
                && $mail->motif === WalletCreditMotif::BookingCancellationRefund
                && $mail->newBalance === 27_000,
        );
    }

    public function test_listener_queues_wallet_credited_mail_when_face_cancels_paid_booking(): void
    {
        Mail::fake();

        $this->booking->update(['status' => BookingStatus::CancelledByFace]);

        EscrowTransaction::factory()->create([
            'booking_id' => $this->booking->id,
            'amount' => $this->booking->montant_face_recoit,
            'status' => EscrowStatus::Refunded->value,
        ]);

        (new SendWalletCreditedEmailOnBookingCancelled)->handle(new BookingCancelled($this->booking->fresh()));

        Mail::assertQueuedCount(1);
        Mail::assertQueued(WalletCreditedMail::class);
    }

    public function test_listener_skips_when_no_escrow_transaction_exists(): void
    {
        Mail::fake();

        // No EscrowTransaction created — booking cancelled while Pending/Accepted.

        (new SendWalletCreditedEmailOnBookingCancelled)->handle(new BookingCancelled($this->booking));

        Mail::assertNothingQueued();
    }

    public function test_listener_skips_when_escrow_status_is_not_refunded(): void
    {
        Mail::fake();

        EscrowTransaction::factory()->create([
            'booking_id' => $this->booking->id,
            'status' => EscrowStatus::Locked->value,
        ]);

        (new SendWalletCreditedEmailOnBookingCancelled)->handle(new BookingCancelled($this->booking));

        Mail::assertNothingQueued();
    }

    public function test_listener_skips_when_producer_email_is_empty(): void
    {
        Mail::fake();

        EscrowTransaction::factory()->create([
            'booking_id' => $this->booking->id,
            'status' => EscrowStatus::Refunded->value,
        ]);
        $this->producerUser->update(['email' => '']);

        (new SendWalletCreditedEmailOnBookingCancelled)->handle(new BookingCancelled($this->booking->fresh()));

        Mail::assertNothingQueued();
    }
}
