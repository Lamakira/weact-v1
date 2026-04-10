<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\EscrowStatus;
use App\Enums\FinancialEventType;
use App\Models\Booking;
use App\Models\EscrowTransaction;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillCancelledBookingWalletRefundsCommandTest extends TestCase
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
            'balance' => 0,
        ]);

        $face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
    }

    public function test_command_backfills_missing_wallet_refund_for_cancelled_paid_booking(): void
    {
        $booking = Booking::factory()->cancelledByProducer()->withFedapayTransaction()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        EscrowTransaction::factory()->create([
            'booking_id' => $booking->id,
            'amount' => $booking->montant_face_recoit,
            'status' => EscrowStatus::Locked->value,
        ]);

        $expectedRefund = (int) round($booking->montant_total_producteur * 0.90);

        $this->artisan('bookings:backfill-cancelled-wallet-refunds', [
            '--booking-id' => $booking->id,
        ])->assertSuccessful();

        $this->producerUser->refresh();
        $this->assertSame($expectedRefund, $this->producerUser->balance);

        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $this->producerUser->id,
            'booking_id' => $booking->id,
            'type' => 'credit',
            'amount' => $expectedRefund,
            'description' => "Booking #{$booking->id} — rattrapage remboursement annulation",
        ]);

        $this->assertDatabaseHas('escrow_transactions', [
            'booking_id' => $booking->id,
            'status' => EscrowStatus::Refunded->value,
        ]);

        $this->assertDatabaseHas('financial_events', [
            'booking_id' => $booking->id,
            'type' => FinancialEventType::Refund->value,
            'amount' => $expectedRefund,
            'status' => 'completed',
            'idempotency_key' => "backfill-refund-booking-{$booking->id}",
        ]);
    }

    public function test_command_is_idempotent_when_run_twice(): void
    {
        $booking = Booking::factory()->cancelledByFace()->withFedapayTransaction()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        EscrowTransaction::factory()->create([
            'booking_id' => $booking->id,
            'amount' => $booking->montant_face_recoit,
            'status' => EscrowStatus::Locked->value,
        ]);

        $expectedRefund = (int) round($booking->montant_total_producteur * 0.90);

        $this->artisan('bookings:backfill-cancelled-wallet-refunds', [
            '--booking-id' => $booking->id,
        ])->assertSuccessful();

        $this->artisan('bookings:backfill-cancelled-wallet-refunds', [
            '--booking-id' => $booking->id,
        ])->assertSuccessful();

        $this->producerUser->refresh();
        $this->assertSame($expectedRefund, $this->producerUser->balance);

        $this->assertSame(
            1,
            WalletTransaction::query()
                ->where('user_id', $this->producerUser->id)
                ->where('booking_id', $booking->id)
                ->where('type', 'credit')
                ->count()
        );
    }
}
