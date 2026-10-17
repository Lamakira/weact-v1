<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\BookingStatus;
use App\Enums\FinancialEventType;
use App\Enums\UgcRefundReason;
use App\Models\Booking;
use App\Models\Face;
use App\Models\FinancialEvent;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Ugc\Concerns\DispatchesFedapayWebhooks;
use Tests\TestCase;

/**
 * Lot B10 — une demande UGC dont la commission n'est pas réglée expire 48 h après sa
 * création, les deux parties sont prévenues. Un paiement FedaPay approuvé après cette
 * expiration crédite le wallet du Producteur du montant encaissé (jamais perdu).
 */
class ExpireUnpaidUgcBookingsCommandTest extends TestCase
{
    use DispatchesFedapayWebhooks;
    use RefreshDatabase;

    private User $producerUser;

    private User $faceUser;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->travelTo(Carbon::parse('2026-10-20 12:00:00', 'UTC'));

        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => Producer::factory()->create()->id,
            'balance' => 0,
        ]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => Face::factory()->create()->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function ugcBooking(array $overrides = []): Booking
    {
        return Booking::factory()->create(array_merge([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => BookingStatus::Pending,
            'type_contenu' => 'UGC',
            'date_debut' => null,
            'date_fin' => null,
            'duree_heures' => null,
            'lieu' => null,
            'tarif_base' => 0,
            'montant_face_recoit' => 0,
            'montant_total_producteur' => 2500,
            'type_compensation' => 'product',
            'nom_produit' => 'Tenue Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'commission_ugc' => 2500,
            'created_at' => now(),
        ], $overrides));
    }

    public function test_deadline_is_48_hours_after_creation(): void
    {
        $booking = $this->ugcBooking();

        $this->assertTrue($booking->ugcCommissionPaymentDeadline()->equalTo(Carbon::parse('2026-10-22 12:00:00', 'UTC')));

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}")
            ->assertOk()
            ->assertJsonPath('data.ugc_commission_payment_deadline', '2026-10-22T12:00:00+00:00');
    }

    public function test_an_unpaid_ugc_request_expires_after_48_hours(): void
    {
        $booking = $this->ugcBooking();

        $this->travelTo(Carbon::parse('2026-10-22 11:59:00', 'UTC'));
        $this->artisan('bookings:expire-unpaid-ugc')->expectsOutput('Found 0 unpaid UGC booking(s) to expire.');
        $this->assertSame(BookingStatus::Pending, $booking->refresh()->status);

        $this->travelTo(Carbon::parse('2026-10-22 12:00:00', 'UTC'));
        $this->artisan('bookings:expire-unpaid-ugc')
            ->expectsOutput('Found 1 unpaid UGC booking(s) to expire.')
            ->expectsOutput("Expired UGC booking #{$booking->id}")
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame(BookingStatus::Expired, $booking->refresh()->status);

        // Idempotent.
        $this->artisan('bookings:expire-unpaid-ugc')->expectsOutput('Found 0 unpaid UGC booking(s) to expire.');
    }

    public function test_both_parties_are_notified_that_the_commission_was_not_paid(): void
    {
        $this->ugcBooking(['created_at' => now()->subHours(49)]);

        $this->artisan('bookings:expire-unpaid-ugc')->assertExitCode(Command::SUCCESS);

        foreach ([$this->producerUser, $this->faceUser] as $user) {
            $message = Notification::query()
                ->where('user_id', $user->id)
                ->where('type', 'booking_expired')
                ->sole()
                ->data['message'];
            $this->assertStringContainsString("la commission n'a pas été réglée dans les 48 h", $message);
        }
    }

    public function test_paid_cash_or_answered_requests_are_left_alone(): void
    {
        $paid = $this->ugcBooking([
            'status' => BookingStatus::CommissionPaid,
            'commission_paid_at' => now()->subHours(50),
            'created_at' => now()->subHours(60),
        ]);
        $cash = Booking::factory()->pending()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'type_contenu' => 'Publicité',
            'date_debut' => '2026-11-10 00:00:00',
            'date_fin' => '2026-11-10 00:00:00',
            'created_at' => now()->subHours(60),
        ]);
        $refused = $this->ugcBooking(['status' => BookingStatus::Refused, 'created_at' => now()->subHours(60)]);

        $this->artisan('bookings:expire-unpaid-ugc')->expectsOutput('Found 0 unpaid UGC booking(s) to expire.');

        $this->assertSame(BookingStatus::CommissionPaid, $paid->refresh()->status);
        $this->assertSame(BookingStatus::Pending, $cash->refresh()->status);
        $this->assertSame(BookingStatus::Refused, $refused->refresh()->status);
    }

    public function test_a_payment_approved_after_expiry_is_credited_to_the_producer_wallet_once(): void
    {
        $booking = $this->ugcBooking([
            'created_at' => now()->subHours(49),
            'fedapay_transaction_id' => 4242,
        ]);
        $this->artisan('bookings:expire-unpaid-ugc')->assertExitCode(Command::SUCCESS);
        $this->assertSame(BookingStatus::Expired, $booking->refresh()->status);

        $this->dispatchWebhook('transaction.approved', 4242, 'ref_late');
        $this->dispatchWebhook('transaction.approved', 4242, 'ref_late');

        $booking->refresh();
        $this->assertSame(BookingStatus::Expired, $booking->status);
        $this->assertNotNull($booking->commission_paid_at);
        $this->assertNotNull($booking->commission_refunded_at);
        $this->assertSame(UgcRefundReason::CommissionPaidAfterExpiry, $booking->commission_refund_reason);
        $this->assertSame(2500, (int) $this->producerUser->refresh()->balance);

        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $this->producerUser->id,
            'booking_id' => $booking->id,
            'type' => 'credit',
            'amount' => 2500,
        ]);
        $this->assertSame(1, FinancialEvent::query()->where('booking_id', $booking->id)->where('type', FinancialEventType::PaymentConfirmed->value)->count());
        $this->assertSame(1, FinancialEvent::query()->where('booking_id', $booking->id)->where('type', FinancialEventType::Refund->value)->count());
        $this->assertDatabaseMissing('escrow_transactions', ['booking_id' => $booking->id]);
    }

    public function test_a_late_payment_on_a_hybrid_request_credits_the_full_amount_and_locks_no_escrow(): void
    {
        $booking = $this->ugcBooking([
            'created_at' => now()->subHours(49),
            'fedapay_transaction_id' => 4343,
            'type_compensation' => 'hybrid',
            'montant_face_recoit' => 14250,
            'montant_total_producteur' => 16500,
            'montant_remuneration' => 15000,
            'commission_ugc' => 2250,
            'nombre_videos' => 3,
        ]);
        $this->artisan('bookings:expire-unpaid-ugc')->assertExitCode(Command::SUCCESS);

        $this->dispatchWebhook('transaction.approved', 4343, 'ref_late');

        $this->assertSame(16500, (int) $this->producerUser->refresh()->balance);
        $this->assertDatabaseMissing('escrow_transactions', ['booking_id' => $booking->id, 'status' => 'locked']);
    }
}
