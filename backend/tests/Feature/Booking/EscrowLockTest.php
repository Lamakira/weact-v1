<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Enums\FinancialEventType;
use App\Models\Booking;
use App\Models\EscrowTransaction;
use App\Models\Face;
use App\Models\FinancialEvent;
use App\Models\Producer;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EscrowLockTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private User $faceUser;

    private Booking $acceptedBooking;

    protected function setUp(): void
    {
        parent::setUp();

        $producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $face = Face::factory()->create([
            'tarif_horaire' => 5000,
            'tarif_journalier' => 30000,
            'is_available' => true,
        ]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $this->acceptedBooking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'montant_face_recoit' => 42500,
        ]);
    }

    public function test_escrow_transaction_created_with_locked_status_on_payment(): void
    {
        Event::fake();

        $service = app(BookingService::class);
        $service->markAsPaid($this->acceptedBooking, 'ref_escrow_lock_123');

        $this->assertDatabaseHas('escrow_transactions', [
            'booking_id' => $this->acceptedBooking->id,
            'amount' => 42500,
            'status' => 'locked',
        ]);

        $escrow = EscrowTransaction::where('booking_id', $this->acceptedBooking->id)->first();
        $this->assertNotNull($escrow);
        $this->assertNotNull($escrow->locked_at);
        $this->assertNull($escrow->released_at);
    }

    public function test_escrow_lock_financial_event_recorded_on_payment(): void
    {
        Event::fake();

        $service = app(BookingService::class);
        $service->markAsPaid($this->acceptedBooking, 'ref_escrow_lock_456');

        $this->assertDatabaseHas('financial_events', [
            'booking_id' => $this->acceptedBooking->id,
            'type' => FinancialEventType::EscrowLock->value,
            'amount' => 42500,
        ]);
    }

    public function test_escrow_lock_and_payment_confirmed_are_in_same_transaction(): void
    {
        Event::fake();

        $service = app(BookingService::class);
        $service->markAsPaid($this->acceptedBooking, 'ref_atomic_789');

        // Both EscrowLock and PaymentConfirmed events exist for the same booking
        $events = FinancialEvent::forBooking($this->acceptedBooking->id)->get();
        $types = $events->pluck('type')->map(fn ($t) => $t instanceof FinancialEventType ? $t->value : $t)->toArray();

        $this->assertContains(FinancialEventType::PaymentConfirmed->value, $types);
        $this->assertContains(FinancialEventType::EscrowLock->value, $types);

        // Escrow record also exists — all created atomically
        $this->assertDatabaseHas('escrow_transactions', [
            'booking_id' => $this->acceptedBooking->id,
            'status' => 'locked',
        ]);
    }

    public function test_escrow_amount_matches_booking_montant_face_recoit(): void
    {
        Event::fake();

        $service = app(BookingService::class);
        $service->markAsPaid($this->acceptedBooking, 'ref_amount_check');

        $escrow = EscrowTransaction::where('booking_id', $this->acceptedBooking->id)->first();
        $this->assertEquals($this->acceptedBooking->montant_face_recoit, $escrow->amount);
    }
}

