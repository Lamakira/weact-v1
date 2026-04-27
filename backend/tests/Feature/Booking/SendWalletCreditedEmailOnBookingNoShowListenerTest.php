<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Enums\WalletCreditMotif;
use App\Events\BookingNoShowReported;
use App\Listeners\Booking\SendWalletCreditedEmailOnBookingNoShow;
use App\Mail\WalletCreditedMail;
use App\Models\Booking;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendWalletCreditedEmailOnBookingNoShowListenerTest extends TestCase
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
            'balance' => 50000,
        ]);

        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);

        $this->booking = Booking::factory()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => BookingStatus::NoShow,
            'montant_total_producteur' => 50000,
        ]);
    }

    public function test_listener_queues_wallet_credited_mail_on_no_show_report(): void
    {
        Mail::fake();

        (new SendWalletCreditedEmailOnBookingNoShow)->handle(new BookingNoShowReported($this->booking));

        Mail::assertQueuedCount(1);
        Mail::assertQueued(
            WalletCreditedMail::class,
            fn (WalletCreditedMail $mail): bool => $mail->hasTo($this->producerUser->email)
                && $mail->amount === 50_000
                && $mail->motif === WalletCreditMotif::BookingNoShowRefund
                && $mail->newBalance === 50_000,
        );
    }

    public function test_listener_skips_when_producer_userable_is_missing(): void
    {
        Mail::fake();

        $this->producerUser->update(['userable_id' => 999_999]);

        (new SendWalletCreditedEmailOnBookingNoShow)->handle(new BookingNoShowReported($this->booking));

        Mail::assertNothingQueued();
    }

    public function test_listener_skips_when_producer_email_is_empty(): void
    {
        Mail::fake();

        $this->producerUser->update(['email' => '']);

        (new SendWalletCreditedEmailOnBookingNoShow)->handle(new BookingNoShowReported($this->booking));

        Mail::assertNothingQueued();
    }

    public function test_listener_does_not_skip_when_escrow_is_absent(): void
    {
        Mail::fake();

        // No EscrowTransaction at all — for no-show, listener does NOT read escrow.
        (new SendWalletCreditedEmailOnBookingNoShow)->handle(new BookingNoShowReported($this->booking));

        Mail::assertQueued(WalletCreditedMail::class);
    }
}
