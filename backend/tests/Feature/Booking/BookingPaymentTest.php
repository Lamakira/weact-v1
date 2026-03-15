<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Enums\FinancialEventType;
use App\Events\BookingPaid;
use App\Jobs\HandleFedapayWebhook;
use App\Models\Booking;
use App\Models\Face;
use App\Models\FedapayWebhookEvent;
use App\Models\FinancialEvent;
use App\Models\Producer;
use App\Models\User;
use App\Services\BookingService;
use App\Services\FedapayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BookingPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    private User $faceUser;

    private Face $face;

    private Booking $acceptedBooking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);

        $this->face = Face::factory()->create([
            'tarif_horaire' => 5000,
            'tarif_journalier' => 30000,
            'is_available' => true,
        ]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);

        $this->acceptedBooking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);
    }

    // === PAYMENT INITIATION TESTS ===

    public function test_producer_can_initiate_payment_on_accepted_booking(): void
    {
        $this->mock(FedapayService::class, function ($mock) {
            $mock->shouldReceive('initiatePayment')
                ->once()
                ->andReturn([
                    'fedapay_transaction_id' => 12345678,
                    'checkout_url' => 'https://checkout.fedapay.com/test-token',
                ]);
        });

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$this->acceptedBooking->id}/pay");

        $response->assertOk()
            ->assertJsonPath('data.fedapay_transaction_id', 12345678)
            ->assertJsonPath('checkout_url', 'https://checkout.fedapay.com/test-token');

        $this->assertDatabaseHas('bookings', [
            'id' => $this->acceptedBooking->id,
            'fedapay_transaction_id' => 12345678,
            'status' => BookingStatus::Accepted->value, // Still accepted until webhook confirms
        ]);
    }

    public function test_producer_cannot_pay_non_accepted_booking(): void
    {
        $pendingBooking = Booking::factory()->pending()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$pendingBooking->id}/pay");

        $response->assertForbidden();
    }

    public function test_face_cannot_initiate_payment(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$this->acceptedBooking->id}/pay");

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_gets_401_on_pay(): void
    {
        $this->postJson("/api/v1/bookings/{$this->acceptedBooking->id}/pay")
            ->assertUnauthorized();
    }

    public function test_payment_initiation_creates_financial_event(): void
    {
        $this->mock(FedapayService::class, function ($mock) {
            $mock->shouldReceive('initiatePayment')
                ->once()
                ->andReturn([
                    'fedapay_transaction_id' => 99999,
                    'checkout_url' => 'https://checkout.fedapay.com/test-token',
                ]);
        });

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$this->acceptedBooking->id}/pay")
            ->assertOk();

        $this->assertDatabaseHas('financial_events', [
            'booking_id' => $this->acceptedBooking->id,
            'type' => FinancialEventType::PaymentInitiated->value,
            'status' => 'pending',
        ]);
    }

    // === WEBHOOK TESTS ===

    public function test_webhook_with_valid_signature_dispatches_job(): void
    {
        Queue::fake();

        $booking = Booking::factory()->accepted()->withFedapayTransaction()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'fedapay_transaction_id' => 55555,
        ]);

        $payload = json_encode([
            'id' => 'evt_123',
            'name' => 'transaction.approved',
            'entity' => [
                'id' => 55555,
                'reference' => 'ref_abc',
                'status' => 'approved',
            ],
        ]);

        $this->mock(FedapayService::class, function ($mock) {
            $mock->shouldReceive('verifyWebhookSignature')
                ->once()
                ->andReturn((object) [
                    'id' => 'evt_123',
                    'name' => 'transaction.approved',
                    'data' => (object) ['id' => 55555],
                ]);
        });

        $response = $this->postJson('/api/v1/webhooks/fedapay', json_decode($payload, true), [
            'X-FEDAPAY-SIGNATURE' => 'valid_sig',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'received');

        Queue::assertPushed(HandleFedapayWebhook::class);

        $this->assertDatabaseHas('fedapay_webhook_events', [
            'fedapay_event_id' => 'evt_123',
            'event_name' => 'transaction.approved',
            'status' => 'received',
        ]);
    }

    public function test_webhook_with_invalid_signature_returns_403(): void
    {
        $this->mock(FedapayService::class, function ($mock) {
            $mock->shouldReceive('verifyWebhookSignature')
                ->once()
                ->andThrow(new \Exception('Invalid signature'));
        });

        $response = $this->postJson('/api/v1/webhooks/fedapay', ['test' => 'data'], [
            'X-FEDAPAY-SIGNATURE' => 'invalid_sig',
        ]);

        $response->assertStatus(403);
    }

    public function test_webhook_without_signature_header_returns_403(): void
    {
        $this->mock(FedapayService::class, function ($mock) {
            $mock->shouldReceive('verifyWebhookSignature')
                ->once()
                ->with(\Mockery::any(), '')
                ->andThrow(new \Exception('Missing signature'));
        });

        $response = $this->postJson('/api/v1/webhooks/fedapay', ['test' => 'data']);

        $response->assertStatus(403);
    }

    public function test_duplicate_webhook_returns_200_without_reprocessing(): void
    {
        Queue::fake();

        // Create an already-processed webhook event
        FedapayWebhookEvent::create([
            'fedapay_event_id' => 'evt_duplicate',
            'event_name' => 'transaction.approved',
            'payload' => ['test' => true],
            'status' => 'processed',
            'processed_at' => now(),
        ]);

        $this->mock(FedapayService::class, function ($mock) {
            $mock->shouldReceive('verifyWebhookSignature')
                ->once()
                ->andReturn((object) [
                    'id' => 'evt_duplicate',
                    'name' => 'transaction.approved',
                    'data' => (object) ['id' => 11111],
                ]);
        });

        $response = $this->postJson('/api/v1/webhooks/fedapay', ['entity' => ['id' => 11111]], [
            'X-FEDAPAY-SIGNATURE' => 'valid_sig',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'already_processed');

        Queue::assertNotPushed(HandleFedapayWebhook::class);
    }

    public function test_webhook_declined_creates_payment_failed_event(): void
    {
        $booking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'fedapay_transaction_id' => 77777,
        ]);

        $webhookEvent = FedapayWebhookEvent::create([
            'fedapay_event_id' => 'evt_decline',
            'event_name' => 'transaction.declined',
            'payload' => [
                'entity' => [
                    'id' => 77777,
                    'reference' => 'ref_fail',
                ],
            ],
            'status' => 'received',
        ]);

        $job = new HandleFedapayWebhook(
            $webhookEvent->id,
            'transaction.declined',
            [
                'entity' => [
                    'id' => 77777,
                    'reference' => 'ref_fail',
                ],
            ]
        );

        $job->handle(app(BookingService::class));

        // Booking stays accepted (can retry)
        $booking->refresh();
        $this->assertEquals(BookingStatus::Accepted, $booking->status);

        // FinancialEvent created with PaymentFailed type
        $this->assertDatabaseHas('financial_events', [
            'booking_id' => $booking->id,
            'type' => FinancialEventType::PaymentFailed->value,
            'status' => 'failed',
        ]);

        // Webhook event marked processed
        $webhookEvent->refresh();
        $this->assertEquals('processed', $webhookEvent->status);
        $this->assertNotNull($webhookEvent->processed_at);
    }

    public function test_financial_event_is_immutable(): void
    {
        $this->mock(FedapayService::class, function ($mock) {
            $mock->shouldReceive('initiatePayment')
                ->once()
                ->andReturn([
                    'fedapay_transaction_id' => 11111,
                    'checkout_url' => 'https://checkout.fedapay.com/test-token',
                ]);
        });

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$this->acceptedBooking->id}/pay")
            ->assertOk();

        $event = FinancialEvent::where('booking_id', $this->acceptedBooking->id)->first();
        $this->assertNotNull($event);

        $this->assertEquals(FinancialEventType::PaymentInitiated, $event->type);
        $this->assertEquals($this->acceptedBooking->montant_total_producteur, $event->amount);

        // Verify update is rejected
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('immutable');
        $event->update(['status' => 'tampered']);
    }

    public function test_financial_event_cannot_be_deleted(): void
    {
        $this->mock(FedapayService::class, function ($mock) {
            $mock->shouldReceive('initiatePayment')
                ->once()
                ->andReturn([
                    'fedapay_transaction_id' => 22222,
                    'checkout_url' => 'https://checkout.fedapay.com/test-token',
                ]);
        });

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$this->acceptedBooking->id}/pay")
            ->assertOk();

        $event = FinancialEvent::where('booking_id', $this->acceptedBooking->id)->first();
        $this->assertNotNull($event);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('immutable');
        $event->delete();
    }

    public function test_mark_as_paid_is_idempotent(): void
    {
        Event::fake();

        $paidBooking = Booking::factory()->paid()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'fedapay_transaction_id' => 88888,
        ]);

        $bookingService = app(BookingService::class);
        $result = $bookingService->markAsPaid($paidBooking, 'ref_idempotent');

        // Should return the booking without error
        $this->assertEquals(BookingStatus::Paid, $result->status);

        // Should NOT dispatch BookingPaid again (idempotent)
        Event::assertNotDispatched(BookingPaid::class);
    }

    public function test_webhook_approved_marks_booking_as_paid(): void
    {
        Event::fake();

        $booking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'fedapay_transaction_id' => 66666,
        ]);

        $webhookEvent = FedapayWebhookEvent::create([
            'fedapay_event_id' => 'evt_approved',
            'event_name' => 'transaction.approved',
            'payload' => [
                'entity' => [
                    'id' => 66666,
                    'reference' => 'ref_success',
                ],
            ],
            'status' => 'received',
        ]);

        $job = new HandleFedapayWebhook(
            $webhookEvent->id,
            'transaction.approved',
            [
                'entity' => [
                    'id' => 66666,
                    'reference' => 'ref_success',
                ],
            ]
        );

        $job->handle(app(BookingService::class));

        $booking->refresh();
        $this->assertEquals(BookingStatus::Paid, $booking->status);

        $this->assertDatabaseHas('financial_events', [
            'booking_id' => $booking->id,
            'type' => FinancialEventType::PaymentConfirmed->value,
            'fedapay_ref' => 'ref_success',
        ]);

        Event::assertDispatched(BookingPaid::class);
    }
}
