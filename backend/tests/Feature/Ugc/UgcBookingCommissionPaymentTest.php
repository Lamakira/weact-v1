<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc;

use App\Enums\BookingStatus;
use App\Enums\FinancialEventType;
use App\Events\BookingCommissionPaid;
use App\Jobs\HandleFedapayWebhook;
use App\Models\Booking;
use App\Models\Face;
use App\Models\FedapayWebhookEvent;
use App\Models\FinancialEvent;
use App\Models\Producer;
use App\Models\User;
use App\Services\BookingService;
use App\Services\FaceSubscriptionPaymentService;
use App\Services\FedapayService;
use App\Services\MissionPaymentService;
use App\Services\Ugc\UgcCommissionPaymentService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class UgcBookingCommissionPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    private User $faceUser;

    private Face $face;

    private int $webhookSeq = 0;

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
    }

    public function test_producer_can_initiate_ugc_commission_payment(): void
    {
        $booking = $this->makePendingUgcBooking();

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('initiatePaymentForUgcBooking')
                ->once()
                ->andReturn(['fedapay_transaction_id' => 901, 'checkout_url' => 'https://fedapay.test/x']);
        });

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/pay-commission")
            ->assertOk()
            ->assertJsonPath('checkout_url', 'https://fedapay.test/x');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'fedapay_transaction_id' => 901,
        ]);
    }

    public function test_ugc_payment_charges_commission_only(): void
    {
        // Hybride : commission 5000, rémunération 15000, total producteur 20000.
        // WeAct ne facture QUE la commission (D-1.5.a) → l'event PaymentInitiated
        // doit porter 5000, jamais 20000.
        $booking = $this->makePendingUgcBooking(commission: 5000, compensation: 'hybrid');

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('initiatePaymentForUgcBooking')
                ->once()
                ->andReturn(['fedapay_transaction_id' => 902, 'checkout_url' => 'https://fedapay.test/y']);
        });

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/pay-commission")
            ->assertOk();

        $this->assertDatabaseHas('financial_events', [
            'booking_id' => $booking->id,
            'type' => FinancialEventType::PaymentInitiated->value,
            'amount' => 5000,
        ]);
        $this->assertDatabaseMissing('financial_events', [
            'booking_id' => $booking->id,
            'amount' => 20000,
        ]);
    }

    public function test_initiate_is_idempotent_and_reuses_existing_transaction(): void
    {
        $booking = $this->makePendingUgcBooking();

        $transactionStub = \Mockery::mock(\FedaPay\Transaction::class);
        $transactionStub->status = 'pending';

        $this->mock(FedapayService::class, function ($mock) use ($transactionStub): void {
            $mock->shouldReceive('initiatePaymentForUgcBooking')
                ->once()
                ->andReturn(['fedapay_transaction_id' => 903, 'checkout_url' => 'https://fedapay.test/first']);
            $mock->shouldReceive('retrieveTransaction')
                ->once()
                ->with(903)
                ->andReturn($transactionStub);
            $mock->shouldReceive('regenerateTokenFromTransaction')
                ->once()
                ->with($transactionStub)
                ->andReturn(['checkout_url' => 'https://fedapay.test/reused', 'fedapay_status' => 'pending']);
        });

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/pay-commission")
            ->assertOk()
            ->assertJsonPath('checkout_url', 'https://fedapay.test/first');

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/pay-commission")
            ->assertOk()
            ->assertJsonPath('checkout_url', 'https://fedapay.test/reused');

        $this->assertSame(903, (int) $booking->fresh()->fedapay_transaction_id);
        $this->assertSame(1, FinancialEvent::query()
            ->where('booking_id', $booking->id)
            ->where('type', FinancialEventType::PaymentInitiated->value)
            ->count());
    }

    public function test_webhook_approved_marks_commission_paid(): void
    {
        // Fake ONLY the domain event — a bare Event::fake() would also stub the
        // Eloquent `creating` hook (HasRouteUuid) and leave uuid null.
        Event::fake([BookingCommissionPaid::class]);

        $booking = $this->makePendingUgcBooking(commission: 2500);
        $booking->update(['fedapay_transaction_id' => 911]);

        $this->dispatchWebhook('transaction.approved', 911, 'ref_ok');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::CommissionPaid->value,
        ]);
        $this->assertDatabaseHas('financial_events', [
            'booking_id' => $booking->id,
            'type' => FinancialEventType::PaymentConfirmed->value,
            'amount' => 2500,
            'fedapay_ref' => 'ref_ok',
        ]);
        // Pas d'escrow pour l'UGC (D-1.5.b).
        $this->assertDatabaseMissing('escrow_transactions', ['booking_id' => $booking->id]);

        Event::assertDispatched(BookingCommissionPaid::class);
    }

    public function test_webhook_approved_is_idempotent(): void
    {
        $booking = $this->makePendingUgcBooking(commission: 2500);
        $booking->update(['fedapay_transaction_id' => 912]);

        // Deux events webhook distincts pour la même transaction → le settlement
        // doit rester idempotent (1 seul PaymentConfirmed, statut stable).
        $this->dispatchWebhook('transaction.approved', 912, 'ref_ok');
        $this->dispatchWebhook('transaction.approved', 912, 'ref_ok');

        $this->assertSame(BookingStatus::CommissionPaid, $booking->fresh()->status);
        $this->assertSame(1, FinancialEvent::query()
            ->where('booking_id', $booking->id)
            ->where('type', FinancialEventType::PaymentConfirmed->value)
            ->count());
    }

    public function test_webhook_declined_keeps_booking_pending(): void
    {
        $booking = $this->makePendingUgcBooking(commission: 2500);
        $booking->update(['fedapay_transaction_id' => 913]);

        $this->dispatchWebhook('transaction.declined', 913, 'ref_ko');

        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
        $this->assertDatabaseHas('financial_events', [
            'booking_id' => $booking->id,
            'type' => FinancialEventType::PaymentFailed->value,
            'fedapay_ref' => 'ref_ko',
        ]);
    }

    public function test_cash_mark_as_paid_is_noop_for_ugc_booking(): void
    {
        // AC5 / defer 572 : le chemin cash markAsPaid ignore explicitement l'UGC.
        $booking = $this->makePendingUgcBooking(commission: 2500);

        app(BookingService::class)->markAsPaid($booking, 'ref_cash');

        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
        $this->assertDatabaseMissing('escrow_transactions', ['booking_id' => $booking->id]);
        $this->assertDatabaseMissing('financial_events', [
            'booking_id' => $booking->id,
            'type' => FinancialEventType::PaymentConfirmed->value,
        ]);
    }

    public function test_commission_status_polling_settles_when_approved(): void
    {
        $booking = $this->makePendingUgcBooking(commission: 2500);
        $booking->update(['fedapay_transaction_id' => 914]);

        $transactionStub = \Mockery::mock(\FedaPay\Transaction::class);
        $transactionStub->status = 'approved';
        $transactionStub->reference = 'ref_poll';

        $this->mock(FedapayService::class, function ($mock) use ($transactionStub): void {
            $mock->shouldReceive('retrieveTransaction')
                ->once()
                ->with(914)
                ->andReturn($transactionStub);
        });

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}/commission-status")
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::CommissionPaid->value);

        $this->assertSame(BookingStatus::CommissionPaid, $booking->fresh()->status);
    }

    public function test_non_owner_cannot_pay_commission(): void
    {
        $booking = $this->makePendingUgcBooking();

        $otherProducer = Producer::factory()->create();
        $otherUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $otherProducer->id,
        ]);

        $this->actingAs($otherUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/pay-commission")
            ->assertForbidden();
    }

    public function test_cannot_pay_commission_on_non_ugc_booking(): void
    {
        $cashBooking = Booking::factory()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => BookingStatus::Pending,
            'type_contenu' => 'Publicité',
        ]);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$cashBooking->uuid}/pay-commission")
            ->assertForbidden();
    }

    public function test_webhook_approved_on_cancelled_booking_is_noop_and_marks_processed(): void
    {
        // Régression revue 1.5 : un booking UGC pending avec un paiement en vol peut
        // être annulé (cancel autorise pending et ne vide pas fedapay_transaction_id).
        // Si le webhook approved arrive ensuite, markBookingCommissionPaid NE DOIT PAS
        // throw — sinon l'exception s'échappe de handle() avant markProcessed() et le
        // job part en retry storm. Il journalise (ops/refund 2.5) et no-op.
        $booking = $this->makePendingUgcBooking(commission: 2500);
        $booking->update([
            'fedapay_transaction_id' => 915,
            'status' => BookingStatus::CancelledByProducer,
        ]);

        $this->dispatchWebhook('transaction.approved', 915, 'ref_late');

        // Statut inchangé, aucun settlement.
        $this->assertSame(BookingStatus::CancelledByProducer, $booking->fresh()->status);
        $this->assertDatabaseMissing('financial_events', [
            'booking_id' => $booking->id,
            'type' => FinancialEventType::PaymentConfirmed->value,
        ]);
        // Le webhook est traité (queue non empoisonnée) : aucun event ne reste 'received'.
        $this->assertDatabaseMissing('fedapay_webhook_events', ['status' => 'received']);
    }

    public function test_webhook_declined_after_settlement_records_no_failed_event(): void
    {
        // Patch revue 1.5 : un webhook declined/canceled tardif (ref différente)
        // arrivant après le settlement ne doit pas écrire un PaymentFailed
        // contradictoire sur un booking déjà commission_paid.
        $booking = $this->makePendingUgcBooking(commission: 2500);
        $booking->update(['fedapay_transaction_id' => 916]);

        $this->dispatchWebhook('transaction.approved', 916, 'ref_ok');
        $this->assertSame(BookingStatus::CommissionPaid, $booking->fresh()->status);

        $this->dispatchWebhook('transaction.declined', 916, 'ref_late_decline');

        $this->assertSame(BookingStatus::CommissionPaid, $booking->fresh()->status);
        $this->assertDatabaseMissing('financial_events', [
            'booking_id' => $booking->id,
            'type' => FinancialEventType::PaymentFailed->value,
        ]);
    }

    private function makePendingUgcBooking(int $commission = 2500, string $compensation = 'product'): Booking
    {
        return Booking::create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => BookingStatus::Pending,
            'date_debut' => now()->addWeek(),
            'date_fin' => now()->addWeeks(2),
            'duree_heures' => 8,
            'type_contenu' => 'UGC',
            'lieu' => 'Cotonou',
            'tarif_base' => 0,
            'montant_face_recoit' => $compensation === 'hybrid' ? 15000 : 0,
            'montant_total_producteur' => $compensation === 'hybrid' ? $commission + 15000 : $commission,
            'type_compensation' => $compensation,
            'nom_produit' => 'Tenue Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'montant_remuneration' => $compensation === 'hybrid' ? 15000 : null,
            'commission_ugc' => $commission,
        ]);
    }

    private function dispatchWebhook(string $eventName, int $transactionId, string $reference): void
    {
        $this->webhookSeq++;
        $payload = ['entity' => ['id' => $transactionId, 'reference' => $reference]];

        $webhookEvent = FedapayWebhookEvent::create([
            'fedapay_event_id' => "evt_{$transactionId}_{$this->webhookSeq}",
            'event_name' => $eventName,
            'payload' => $payload,
            'status' => 'received',
        ]);

        (new HandleFedapayWebhook($webhookEvent->id, $eventName, $payload))->handle(
            app(BookingService::class),
            app(MissionPaymentService::class),
            app(WalletService::class),
            app(FaceSubscriptionPaymentService::class),
            app(UgcCommissionPaymentService::class),
        );
    }
}
