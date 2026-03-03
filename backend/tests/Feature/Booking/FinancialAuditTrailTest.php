<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Enums\FinancialEventType;
use App\Events\BookingPaid;
use App\Models\Booking;
use App\Models\Face;
use App\Models\FinancialEvent;
use App\Models\Producer;
use App\Models\User;
use App\Services\BookingService;
use App\Services\FedapayService;
use FedaPay\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class FinancialAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private User $faceUser;

    private Booking $acceptedBooking;

    private array $phoneData = ['number' => '64000001', 'country' => 'bj'];

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
        ]);
    }

    // === AC1: FinancialEvent created atomically ===

    public function test_payment_initiation_creates_financial_event_atomically(): void
    {
        $this->mock(FedapayService::class, function ($mock) {
            $mock->shouldReceive('initiatePayment')
                ->once()
                ->andReturn([
                    'fedapay_transaction_id' => 12345,
                    'status' => 'pending',
                ]);
        });

        $service = app(BookingService::class);
        $service->initiatePayment($this->acceptedBooking, 'mtn_open', $this->phoneData);

        $this->assertDatabaseHas('financial_events', [
            'booking_id' => $this->acceptedBooking->id,
            'type' => FinancialEventType::PaymentInitiated->value,
            'amount' => $this->acceptedBooking->montant_total_producteur,
            'status' => 'pending',
        ]);
    }

    public function test_mark_as_paid_creates_payment_confirmed_financial_event(): void
    {
        Event::fake();

        $service = app(BookingService::class);
        $service->markAsPaid($this->acceptedBooking, 'ref_test_123');

        $this->assertDatabaseHas('financial_events', [
            'booking_id' => $this->acceptedBooking->id,
            'type' => FinancialEventType::PaymentConfirmed->value,
            'fedapay_ref' => 'ref_test_123',
            'status' => 'confirmed',
        ]);
    }

    public function test_mark_payment_failed_creates_payment_failed_financial_event(): void
    {
        $service = app(BookingService::class);
        $service->markPaymentFailed($this->acceptedBooking, 'ref_fail_456', 'Insufficient funds');

        $event = FinancialEvent::where('booking_id', $this->acceptedBooking->id)
            ->where('type', FinancialEventType::PaymentFailed->value)
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals('ref_fail_456', $event->fedapay_ref);
        $this->assertEquals('failed', $event->status);
        $this->assertEquals(['reason' => 'Insufficient funds'], $event->metadata);
    }

    // === AC2: Idempotency ===

    public function test_initiate_payment_twice_returns_same_result_without_duplicate_financial_event(): void
    {
        $this->mock(FedapayService::class, function ($mock) {
            $mock->shouldReceive('initiatePayment')
                ->once() // Only called ONCE despite two calls
                ->andReturn([
                    'fedapay_transaction_id' => 77777,
                    'status' => 'pending',
                ]);
            // Second call triggers retrieveTransaction to check existing transaction status
            $transactionMock = \Mockery::mock(Transaction::class);
            $transactionMock->status = 'pending';
            $mock->shouldReceive('retrieveTransaction')
                ->once()
                ->andReturn($transactionMock);
        });

        $service = app(BookingService::class);

        $result1 = $service->initiatePayment($this->acceptedBooking, 'mtn_open', $this->phoneData);
        $result2 = $service->initiatePayment($this->acceptedBooking->fresh(), 'mtn_open', $this->phoneData);

        $this->assertEquals($result1->fedapay_transaction_id, $result2->fedapay_transaction_id);

        $count = FinancialEvent::where('booking_id', $this->acceptedBooking->id)
            ->where('type', FinancialEventType::PaymentInitiated->value)
            ->count();
        $this->assertEquals(1, $count);
    }

    public function test_mark_as_paid_twice_creates_only_one_payment_confirmed_event(): void
    {
        Event::fake();

        $service = app(BookingService::class);

        $service->markAsPaid($this->acceptedBooking, 'ref_once');
        $service->markAsPaid($this->acceptedBooking->fresh(), 'ref_once');

        $count = FinancialEvent::where('booking_id', $this->acceptedBooking->id)
            ->where('type', FinancialEventType::PaymentConfirmed->value)
            ->count();
        $this->assertEquals(1, $count);
    }

    public function test_mark_payment_failed_twice_with_same_ref_creates_only_one_event(): void
    {
        $service = app(BookingService::class);

        $service->markPaymentFailed($this->acceptedBooking, 'ref_dup', 'Declined');
        $service->markPaymentFailed($this->acceptedBooking, 'ref_dup', 'Declined');

        $count = FinancialEvent::where('booking_id', $this->acceptedBooking->id)
            ->where('type', FinancialEventType::PaymentFailed->value)
            ->where('fedapay_ref', 'ref_dup')
            ->count();
        $this->assertEquals(1, $count);
    }

    public function test_mark_payment_failed_with_different_refs_creates_separate_events(): void
    {
        $service = app(BookingService::class);

        $service->markPaymentFailed($this->acceptedBooking, 'ref_attempt_1', 'Timeout');
        $service->markPaymentFailed($this->acceptedBooking, 'ref_attempt_2', 'Insufficient funds');

        $count = FinancialEvent::where('booking_id', $this->acceptedBooking->id)
            ->where('type', FinancialEventType::PaymentFailed->value)
            ->count();
        $this->assertEquals(2, $count);
    }

    // === AC3: Atomic rollback ===

    public function test_no_financial_event_left_if_fedapay_call_throws(): void
    {
        $this->mock(FedapayService::class, function ($mock) {
            $mock->shouldReceive('initiatePayment')
                ->once()
                ->andThrow(new \RuntimeException('Fedapay API error'));
        });

        $service = app(BookingService::class);

        try {
            $service->initiatePayment($this->acceptedBooking, 'mtn_open', $this->phoneData);
        } catch (\RuntimeException) {
            // Expected
        }

        $this->assertDatabaseMissing('financial_events', [
            'booking_id' => $this->acceptedBooking->id,
        ]);
    }

    // === AC5: Immutability ===

    public function test_financial_event_cannot_be_updated(): void
    {
        $event = FinancialEvent::create([
            'type' => FinancialEventType::PaymentInitiated,
            'booking_id' => $this->acceptedBooking->id,
            'amount' => $this->acceptedBooking->montant_total_producteur,
            'idempotency_key' => 'test-update-key',
            'status' => 'pending',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('immutable');
        $event->update(['status' => 'tampered']);
    }

    public function test_financial_event_cannot_be_deleted(): void
    {
        $event = FinancialEvent::create([
            'type' => FinancialEventType::PaymentInitiated,
            'booking_id' => $this->acceptedBooking->id,
            'amount' => $this->acceptedBooking->montant_total_producteur,
            'idempotency_key' => 'test-delete-key',
            'status' => 'pending',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('immutable');
        $event->delete();
    }

    // === Query scopes ===

    public function test_for_booking_scope_returns_correct_events(): void
    {
        Event::fake();

        $service = app(BookingService::class);
        $service->markAsPaid($this->acceptedBooking, 'ref_scope');

        // Create another booking with its own event
        $otherBooking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);
        $service->markAsPaid($otherBooking, 'ref_other');

        $events = FinancialEvent::forBooking($this->acceptedBooking->id)->get();

        $this->assertCount(1, $events);
        $this->assertEquals($this->acceptedBooking->id, $events->first()->booking_id);
    }

    public function test_exists_for_key_returns_correct_result(): void
    {
        $this->mock(FedapayService::class, function ($mock) {
            $mock->shouldReceive('initiatePayment')
                ->once()
                ->andReturn([
                    'fedapay_transaction_id' => 33333,
                    'status' => 'pending',
                ]);
        });

        $service = app(BookingService::class);
        $service->initiatePayment($this->acceptedBooking, 'mtn_open', $this->phoneData);

        $event = FinancialEvent::where('booking_id', $this->acceptedBooking->id)->first();

        $this->assertTrue(FinancialEvent::existsForKey($event->idempotency_key));
        $this->assertFalse(FinancialEvent::existsForKey('nonexistent-key'));
    }

    // === Enum completeness ===

    public function test_financial_event_type_enum_has_all_expected_cases(): void
    {
        $expected = [
            'PaymentInitiated',
            'PaymentConfirmed',
            'PaymentFailed',
            'EscrowLock',
            'EscrowRelease',
            'Refund',
            'Withdrawal',
            'Commission',
        ];

        $actual = array_map(fn ($case) => $case->name, FinancialEventType::cases());

        foreach ($expected as $case) {
            $this->assertContains($case, $actual, "Missing enum case: {$case}");
        }
    }

    public function test_financial_event_type_label_returns_french_labels(): void
    {
        $this->assertEquals('Paiement initié', FinancialEventType::PaymentInitiated->label());
        $this->assertEquals('Paiement confirmé', FinancialEventType::PaymentConfirmed->label());
        $this->assertEquals('Paiement échoué', FinancialEventType::PaymentFailed->label());
        $this->assertEquals('Escrow verrouillé', FinancialEventType::EscrowLock->label());
        $this->assertEquals('Escrow libéré', FinancialEventType::EscrowRelease->label());
        $this->assertEquals('Remboursement', FinancialEventType::Refund->label());
        $this->assertEquals('Retrait', FinancialEventType::Withdrawal->label());
        $this->assertEquals('Commission', FinancialEventType::Commission->label());
    }
}
