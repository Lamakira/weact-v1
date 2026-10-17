<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Enums\FinancialEventType;
use App\Enums\WalletCreditMotif;
use App\Jobs\HandleFedapayWebhook;
use App\Mail\BookingPaymentReminderMail;
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
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Lot B11 — le paiement d'un booking accepté est possible jusqu'à exactement
 * accepted_at + 24 h, pas au-delà (policy et initiation). Un paiement FedaPay approuvé
 * après l'expiration est crédité au wallet du Producteur. Relance de paiement aussi par e-mail.
 */
class BookingPaymentDeadlineTest extends TestCase
{
    use RefreshDatabase;

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
            'tarif_base' => 100000,
            'montant_total_producteur' => 110000,
            'montant_face_recoit' => 90000,
        ]);
    }

    public function test_payment_deadline_is_24_hours_after_acceptance(): void
    {
        $this->assertTrue($this->booking->paymentDeadline()->equalTo(Carbon::parse('2026-10-21 12:00:00', 'UTC')));

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/bookings/{$this->booking->uuid}")
            ->assertOk()
            ->assertJsonPath('data.payment_deadline', '2026-10-21T12:00:00+00:00')
            ->assertJsonPath('data.can_pay', true);

        // La Face voit aussi l'échéance (bannière), sans pouvoir payer.
        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$this->booking->uuid}")
            ->assertOk()
            ->assertJsonPath('data.payment_deadline', '2026-10-21T12:00:00+00:00')
            ->assertJsonPath('data.can_pay', false);
    }

    public function test_the_producer_can_pay_until_exactly_the_deadline(): void
    {
        $this->travelTo(Carbon::parse('2026-10-21 11:59:59', 'UTC'));
        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/bookings/{$this->booking->uuid}")
            ->assertJsonPath('data.can_pay', true);

        $this->travelTo(Carbon::parse('2026-10-21 12:00:00', 'UTC'));
        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/bookings/{$this->booking->uuid}")
            ->assertJsonPath('data.can_pay', false);
    }

    public function test_payment_initiation_is_refused_after_the_deadline_even_before_the_expiry_cron(): void
    {
        $this->mock(FedapayService::class, fn ($mock) => $mock->shouldNotReceive('initiatePayment'));
        $this->travelTo(Carbon::parse('2026-10-21 12:30:00', 'UTC'));

        // Le cron horaire n'est pas encore passé : statut toujours accepted.
        $this->assertSame(BookingStatus::Accepted, $this->booking->refresh()->status);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$this->booking->uuid}/pay")
            ->assertForbidden();

        try {
            app(BookingService::class)->initiatePayment($this->booking);
            $this->fail('initiatePayment aurait dû refuser un paiement hors délai.');
        } catch (ValidationException $e) {
            $this->assertSame(['Le délai de paiement de 24 h est dépassé.'], $e->errors()['status']);
        }
    }

    public function test_an_existing_checkout_is_not_reused_after_the_deadline(): void
    {
        $this->booking->update(['fedapay_transaction_id' => 5151]);
        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldNotReceive('retrieveTransaction');
            $mock->shouldNotReceive('initiatePayment');
        });
        $this->travelTo(Carbon::parse('2026-10-21 12:00:00', 'UTC'));

        $this->expectException(ValidationException::class);
        app(BookingService::class)->initiatePayment($this->booking);
    }

    public function test_a_payment_approved_after_expiry_is_credited_to_the_producer_wallet_once(): void
    {
        $this->booking->update(['fedapay_transaction_id' => 6262]);
        $this->travelTo(Carbon::parse('2026-10-21 13:00:00', 'UTC'));
        $this->artisan('bookings:expire-unpaid');
        $this->assertSame(BookingStatus::Expired, $this->booking->refresh()->status);

        $this->dispatchApprovedWebhook(6262, 'evt_late_1');
        $this->dispatchApprovedWebhook(6262, 'evt_late_2');

        $this->booking->refresh();
        $this->assertSame(BookingStatus::Expired, $this->booking->status);
        $this->assertSame(110000, (int) $this->producerUser->refresh()->balance);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $this->producerUser->id,
            'booking_id' => $this->booking->id,
            'amount' => 110000,
            'description' => WalletCreditMotif::BookingLatePaymentRefund->label(),
        ]);
        $this->assertDatabaseMissing('escrow_transactions', ['booking_id' => $this->booking->id]);

        $this->assertSame(1, FinancialEvent::query()->where('booking_id', $this->booking->id)->where('type', FinancialEventType::PaymentConfirmed->value)->count());
        $this->assertSame(1, FinancialEvent::query()->where('booking_id', $this->booking->id)->where('type', FinancialEventType::Refund->value)->count());

        $this->assertSame(1, Notification::query()
            ->where('user_id', $this->producerUser->id)
            ->where('type', 'booking_wallet_credited')
            ->count());
        Mail::assertQueued(WalletCreditedMail::class, 1);
        Mail::assertQueued(WalletCreditedMail::class, fn (WalletCreditedMail $mail): bool => $mail->hasTo('producer@test.bj')
            && $mail->amount === 110000
            && $mail->motif === WalletCreditMotif::BookingLatePaymentRefund);

        $this->assertSame(0, FedapayWebhookEvent::query()->where('status', 'received')->count());
    }

    public function test_the_payment_reminder_is_also_sent_by_email(): void
    {
        $this->travelTo(Carbon::parse('2026-10-21 03:00:00', 'UTC'));

        $this->artisan('bookings:remind-payment')
            ->expectsOutput('Found 1 booking(s) requiring a payment reminder.');

        Mail::assertQueued(BookingPaymentReminderMail::class, fn (BookingPaymentReminderMail $mail): bool => $mail->hasTo('producer@test.bj'));
        $this->assertNotNull($this->booking->refresh()->payment_reminder_sent_at);

        $html = (new BookingPaymentReminderMail($this->booking->fresh(['face.userable'])))->render();
        // 21/10 12:00 UTC = 13:00 à Porto-Novo.
        $this->assertStringContainsString('Payez avant le 21/10 à 13:00', $html);
    }

    private function dispatchApprovedWebhook(int $transactionId, string $eventId): void
    {
        $payload = ['entity' => ['id' => $transactionId, 'reference' => 'ref_late']];
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
