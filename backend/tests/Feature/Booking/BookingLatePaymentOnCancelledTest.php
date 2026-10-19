<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Enums\FinancialEventType;
use App\Enums\WalletCreditMotif;
use App\Jobs\HandleFedapayWebhook;
use App\Mail\WalletCreditedMail;
use App\Models\Booking;
use App\Models\Face;
use App\Models\FedapayWebhookEvent;
use App\Models\FinancialEvent;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use App\Services\BookingService;
use App\Services\FaceSubscriptionPaymentService;
use App\Services\FedapayService;
use App\Services\MissionPaymentService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Lot B11b — un paiement FedaPay approuvé APRÈS l'annulation d'un booking jamais payé (checkout
 * resté ouvert pendant l'annulation) est crédité automatiquement au wallet du Producteur,
 * exactement une fois, sans toucher au statut ni créer d'escrow. Un booking payé puis annulé
 * garde son propre flux de remboursement : jamais de double crédit.
 */
class BookingLatePaymentOnCancelledTest extends TestCase
{
    use RefreshDatabase;

    private const TRANSACTION_ID = 9191;

    private User $producerUser;

    private User $faceUser;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->travelTo(Carbon::parse('2026-10-20 12:00:00', 'UTC'));

        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => Producer::factory()->create()->id,
            'email' => 'producer@test.bj',
            'balance' => 0,
        ]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => Face::factory()->create()->id,
        ]);

        $this->booking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'type_contenu' => 'Publicité',
            'accepted_at' => now(),
            'date_debut' => '2026-12-10',
            'date_fin' => '2026-12-10',
            'tarif_base' => 100000,
            'montant_total_producteur' => 110000,
            'montant_face_recoit' => 90000,
            'fedapay_transaction_id' => self::TRANSACTION_ID,
        ]);
    }

    public function test_webhook_approved_after_face_cancellation_credits_the_producer_wallet_once(): void
    {
        app(BookingService::class)->cancelByFace($this->booking, 'schedule_conflict');
        $this->assertSame(BookingStatus::CancelledByFace, $this->booking->refresh()->status);

        $this->dispatchApprovedWebhook('evt_cancel_1');
        $this->dispatchApprovedWebhook('evt_cancel_2');

        $this->booking->refresh();
        $this->assertSame(BookingStatus::CancelledByFace, $this->booking->status);
        $this->assertSame(110000, (int) $this->producerUser->refresh()->balance);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $this->producerUser->id,
            'booking_id' => $this->booking->id,
            'amount' => 110000,
            'description' => WalletCreditMotif::BookingLatePaymentAfterCancellation->label(),
        ]);
        $this->assertDatabaseMissing('escrow_transactions', ['booking_id' => $this->booking->id]);

        $this->assertSame(1, $this->eventCount(FinancialEventType::PaymentConfirmed));
        $this->assertSame(1, $this->eventCount(FinancialEventType::Refund));
        $confirmed = FinancialEvent::query()
            ->where('booking_id', $this->booking->id)
            ->where('type', FinancialEventType::PaymentConfirmed->value)
            ->sole();
        $this->assertTrue((bool) ($confirmed->metadata['late_after_cancellation'] ?? false));

        $expectedMessage = "Paiement reçu après l'annulation du booking : le montant a été crédité sur votre wallet WeAct.";
        $this->assertSame(1, Notification::query()
            ->where('user_id', $this->producerUser->id)
            ->where('type', 'booking_wallet_credited')
            ->where('data->message', $expectedMessage)
            ->count());
        Mail::assertQueued(WalletCreditedMail::class, 1);
        Mail::assertQueued(WalletCreditedMail::class, fn (WalletCreditedMail $mail): bool => $mail->hasTo('producer@test.bj')
            && $mail->amount === 110000
            && $mail->motif === WalletCreditMotif::BookingLatePaymentAfterCancellation);

        $this->assertSame(0, FedapayWebhookEvent::query()->where('status', 'received')->count());
        $this->assertSame(2, FedapayWebhookEvent::query()->where('status', 'processed')->count());
    }

    public function test_webhook_approved_after_producer_cancellation_credits_the_producer_wallet(): void
    {
        app(BookingService::class)->cancel($this->booking, 'schedule_conflict');

        $this->dispatchApprovedWebhook('evt_cancel_p');

        $this->assertSame(BookingStatus::CancelledByProducer, $this->booking->refresh()->status);
        $this->assertSame(110000, (int) $this->producerUser->refresh()->balance);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_polling_credits_a_late_approval_on_a_cancelled_booking_once(): void
    {
        app(BookingService::class)->cancelByFace($this->booking, 'schedule_conflict');

        $transaction = \Mockery::mock(\FedaPay\Transaction::class);
        $transaction->status = 'approved';
        $transaction->reference = 'ref_poll_cancel';
        $this->mock(FedapayService::class, function ($mock) use ($transaction): void {
            $mock->shouldReceive('retrieveTransaction')->with(self::TRANSACTION_ID)->andReturn($transaction);
        });

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/bookings/{$this->booking->uuid}/payment-status")
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::CancelledByFace->value);
        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/bookings/{$this->booking->uuid}/payment-status")
            ->assertOk();

        $this->assertSame(110000, (int) $this->producerUser->refresh()->balance);
        $this->assertDatabaseCount('wallet_transactions', 1);

        // Le webhook tardif qui suit ne recrédite pas.
        $this->dispatchApprovedWebhook('evt_poll_then_webhook');
        $this->assertSame(110000, (int) $this->producerUser->refresh()->balance);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_a_paid_then_cancelled_booking_is_not_credited_a_second_time(): void
    {
        // Paiement normal (webhook) : booking payé, escrow verrouillé.
        $this->dispatchApprovedWebhook('evt_paid');
        $this->assertSame(BookingStatus::Paid, $this->booking->refresh()->status);
        $this->assertDatabaseHas('escrow_transactions', ['booking_id' => $this->booking->id]);
        $this->assertSame(0, (int) $this->producerUser->refresh()->balance);

        // Annulation Producteur du booking payé : remboursement escrow normal (une fois).
        app(BookingService::class)->cancel($this->booking, 'schedule_conflict');
        $balanceAfterRefund = (int) $this->producerUser->refresh()->balance;
        $walletRows = \App\Models\WalletTransaction::query()->count();
        $this->assertGreaterThan(0, $balanceAfterRefund);
        $this->assertSame(BookingStatus::CancelledByProducer, $this->booking->refresh()->status);

        // Webhook approuvé rejoué + polling : aucun crédit supplémentaire.
        $this->dispatchApprovedWebhook('evt_paid_replay');

        $transaction = \Mockery::mock(\FedaPay\Transaction::class);
        $transaction->status = 'approved';
        $transaction->reference = 'ref_replay';
        $this->mock(FedapayService::class, function ($mock) use ($transaction): void {
            $mock->shouldReceive('retrieveTransaction')->andReturn($transaction);
        });
        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/bookings/{$this->booking->uuid}/payment-status")
            ->assertOk();

        $this->assertSame($balanceAfterRefund, (int) $this->producerUser->refresh()->balance);
        $this->assertSame($walletRows, \App\Models\WalletTransaction::query()->count());
        $this->assertSame(1, $this->eventCount(FinancialEventType::PaymentConfirmed));
        $this->assertSame(0, FedapayWebhookEvent::query()->where('status', 'received')->count());
    }

    private function eventCount(FinancialEventType $type): int
    {
        return FinancialEvent::query()
            ->where('booking_id', $this->booking->id)
            ->where('type', $type->value)
            ->count();
    }

    private function dispatchApprovedWebhook(string $eventId): void
    {
        $payload = ['entity' => ['id' => self::TRANSACTION_ID, 'reference' => 'ref_late']];
        $webhookEvent = FedapayWebhookEvent::create([
            'fedapay_event_id' => $eventId,
            'event_name' => 'transaction.approved',
            'payload' => $payload,
            'status' => 'received',
        ]);

        (new HandleFedapayWebhook($webhookEvent->id, 'transaction.approved', $payload))->handle(
            app(BookingService::class),
            app(MissionPaymentService::class),
            app(WalletService::class),
            app(FaceSubscriptionPaymentService::class),
        );
    }
}
