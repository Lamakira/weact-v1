<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc;

use App\Enums\BookingStatus;
use App\Enums\UgcRefundReason;
use App\Enums\WalletCreditMotif;
use App\Events\BookingExpired;
use App\Models\Booking;
use App\Models\Face;
use App\Models\FinancialEvent;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\BookingService;
use App\Services\Ugc\UgcRefundService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Ugc\Concerns\DispatchesFedapayWebhooks;
use Tests\TestCase;

/**
 * UGC 2.6 — remboursement de la commission Producteur sur un booking UGC non
 * abouti (refus depuis commission_paid, fenêtre d'acceptation expirée).
 *
 * Settlement = CRÉDIT WALLET synchrone (SUPERSEDE le refund FedaPay manuel de
 * la 2.5) : la détection (refus / fenêtre expirée) ET le règlement se font dans
 * le même appel — commission_refund_requested_at + commission_refunded_at posés
 * ensemble, users.balance crédité de commission_ugc, WalletTransaction libellée,
 * FinancialEvent Refund (booking-scopé), une seule notif « créditée portefeuille ».
 * Le webhook transaction.refunded UGC est neutralisé (log défensif, D-2.6.f).
 */
class UgcBookingRefundTest extends TestCase
{
    use DispatchesFedapayWebhooks;
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    private User $faceUser;

    private Face $face;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

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

    private function makePaidUgcBooking(?\Carbon\CarbonInterface $paidAt = null, int $transactionId = 901, int $commission = 2500): Booking
    {
        return Booking::create([
            'face_id' => $this->faceUser->id,          // users.id (PAS faces.id)
            'producer_id' => $this->producerUser->id,  // users.id
            'status' => BookingStatus::CommissionPaid,
            'date_debut' => null,                       // dotation UGC : pas de tournage
            'date_fin' => null,
            'duree_heures' => null,
            'type_contenu' => 'UGC',
            'lieu' => null,
            'tarif_base' => 0,
            'montant_face_recoit' => 0,
            'montant_total_producteur' => 2500,
            'type_compensation' => 'product',
            'nom_produit' => 'Tenue Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'montant_remuneration' => null,
            'commission_ugc' => $commission,
            'fedapay_transaction_id' => $transactionId,    // colonne UNIQUE : id distinct par booking d'un même test
            'commission_paid_at' => $paidAt ?? now()->subDay(),
        ]);
    }

    // ===================================================================
    // Settlement synchrone — refus depuis commission_paid (AC1, AC7)
    // ===================================================================

    public function test_refuse_at_commission_paid_settles_refund_via_wallet(): void
    {
        $booking = $this->makePaidUgcBooking();
        $before = (int) $this->producerUser->balance;

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/refuse")
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::Refused->value);

        $booking->refresh();
        $this->assertSame(BookingStatus::Refused, $booking->status);
        $this->assertNotNull($booking->commission_refund_requested_at);
        $this->assertSame(UgcRefundReason::Refused, $booking->commission_refund_reason);
        // 2.6 : settlement synchrone — commission_refunded_at posé dans le même appel.
        $this->assertNotNull($booking->commission_refunded_at);

        // Crédit wallet Producteur (+ commission_ugc) + WalletTransaction libellée.
        $this->assertSame($before + 2500, (int) $this->producerUser->fresh()->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $this->producerUser->id,
            'type' => 'credit',
            'amount' => 2500,
            'description' => WalletCreditMotif::UgcCommissionRefund->label(),
        ]);

        // FinancialEvent Refund (booking-scopé) + UNE notif « créditée portefeuille ».
        $this->assertDatabaseHas('financial_events', [
            'booking_id' => $booking->id,
            'type' => 'refund',
            'amount' => 2500,
        ]);
        $this->assertSame(1, Notification::where('user_id', $this->producerUser->id)
            ->where('type', 'ugc_commission_refunded')->count());
        // Cycle « requested » supprimé (AC6) : aucune notif intermédiaire.
        $this->assertSame(0, Notification::where('type', 'ugc_commission_refund_requested')->count());
    }

    public function test_refuse_at_pending_does_not_settle(): void
    {
        $booking = $this->makePaidUgcBooking();
        $booking->update(['status' => BookingStatus::Pending, 'commission_paid_at' => null]);
        $before = (int) $this->producerUser->balance;

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/refuse")
            ->assertOk();

        $booking->refresh();
        $this->assertSame(BookingStatus::Refused, $booking->status);
        $this->assertNull($booking->commission_refund_requested_at);
        $this->assertNull($booking->commission_refund_reason);
        $this->assertNull($booking->commission_refunded_at);
        $this->assertSame($before, (int) $this->producerUser->fresh()->balance);
    }

    public function test_refuse_cash_booking_leaves_refund_columns_null(): void
    {
        // Témoin : le refus cash est strictement inchangé (aucun crédit wallet).
        $booking = Booking::create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => BookingStatus::Pending,
            'date_debut' => now()->addDays(5),
            'date_fin' => now()->addDays(5)->addHours(4),
            'duree_heures' => 4,
            'type_contenu' => 'video',
            'lieu' => 'Cotonou',
            'tarif_base' => 20000,
            'montant_total_producteur' => 23000,
            'montant_face_recoit' => 17000,
        ]);
        $before = (int) $this->producerUser->balance;

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/refuse")
            ->assertOk();

        $booking->refresh();
        $this->assertSame(BookingStatus::Refused, $booking->status);
        $this->assertNull($booking->commission_refund_requested_at);
        $this->assertNull($booking->commission_refund_reason);
        $this->assertSame($before, (int) $this->producerUser->fresh()->balance);
    }

    public function test_settlement_is_idempotent_no_double_credit(): void
    {
        // AC3 : un re-trigger ne produit aucun second crédit wallet.
        $booking = $this->makePaidUgcBooking();
        $before = (int) $this->producerUser->balance;
        $service = app(UgcRefundService::class);

        $service->settleRefundForBooking($booking, UgcRefundReason::Refused);
        $firstRefundedAt = $booking->fresh()->commission_refunded_at;
        $this->assertNotNull($firstRefundedAt);
        $this->assertSame($before + 2500, (int) $this->producerUser->fresh()->balance);

        // 2ᵉ appel → no-op : balance inchangée, 1 WalletTransaction, pas de 2ᵉ event/notif.
        $service->settleRefundForBooking($booking->fresh(), UgcRefundReason::Refused);

        $this->assertSame($before + 2500, (int) $this->producerUser->fresh()->balance);
        $this->assertSame(1, WalletTransaction::where('user_id', $this->producerUser->id)
            ->where('type', 'credit')->count());
        $this->assertSame(1, FinancialEvent::where('booking_id', $booking->id)
            ->where('type', 'refund')->count());
        $this->assertSame(1, Notification::where('type', 'ugc_commission_refunded')->count());
        $this->assertEquals(
            $firstRefundedAt?->toIso8601String(),
            $booking->fresh()->commission_refunded_at?->toIso8601String(),
        );
    }

    // ===================================================================
    // Cron — expiration fenêtre d'acceptation + settlement (AC1, AC4)
    // ===================================================================

    public function test_cron_expires_and_settles_booking_past_window(): void
    {
        $booking = $this->makePaidUgcBooking(now()->subDays(8)); // 8 j > 7 j config
        $before = (int) $this->producerUser->balance;

        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful();

        $booking->refresh();
        $this->assertSame(BookingStatus::Expired, $booking->status);
        $this->assertNotNull($booking->commission_refund_requested_at);
        $this->assertSame(UgcRefundReason::AcceptanceWindowExpired, $booking->commission_refund_reason);
        $this->assertNotNull($booking->commission_refunded_at);
        $this->assertSame($before + 2500, (int) $this->producerUser->fresh()->balance);
        $this->assertSame(1, Notification::where('type', 'ugc_commission_refunded')->count());
    }

    public function test_cron_ignores_commission_paid_booking_within_window(): void
    {
        $booking = $this->makePaidUgcBooking(now()->subDays(3), 902);
        $before = (int) $this->producerUser->balance;

        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful();

        $booking->refresh();
        $this->assertSame(BookingStatus::CommissionPaid, $booking->status);
        $this->assertNull($booking->commission_refund_requested_at);
        $this->assertSame($before, (int) $this->producerUser->fresh()->balance);
    }

    public function test_cron_ignores_old_accepted_cash_booking(): void
    {
        // Témoin : un cash accepté ancien ne passe jamais dans le chemin refund UGC.
        $booking = Booking::create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => BookingStatus::Accepted,
            'date_debut' => now()->subDays(10),
            'date_fin' => now()->subDays(10)->addHours(4),
            'duree_heures' => 4,
            'type_contenu' => 'video',
            'lieu' => 'Cotonou',
            'tarif_base' => 20000,
            'montant_total_producteur' => 23000,
            'montant_face_recoit' => 17000,
        ]);

        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful();

        $this->assertSame(BookingStatus::Accepted, $booking->fresh()->status);
    }

    public function test_cron_ignores_forged_lowercase_ugc_booking(): void
    {
        // Témoin BINARY (leçon 2.4) : un 'ugc' minuscule forgé (la validation
        // l'interdit mais la colonne est du texte libre) n'entre PAS dans le
        // chemin refund UGC malgré la collation _ci.
        $booking = $this->makePaidUgcBooking(now()->subDays(8), 903);
        $booking->update(['type_contenu' => 'ugc']);
        $before = (int) $this->producerUser->balance;

        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful();

        $booking->refresh();
        $this->assertSame(BookingStatus::CommissionPaid, $booking->status);
        $this->assertNull($booking->commission_refund_requested_at);
        $this->assertSame($before, (int) $this->producerUser->fresh()->balance);
    }

    public function test_cron_settlement_is_idempotent(): void
    {
        $booking = $this->makePaidUgcBooking(now()->subDays(8));
        $before = (int) $this->producerUser->balance;

        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful();
        $firstRefundedAt = $booking->fresh()->commission_refunded_at;

        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful();

        $booking->refresh();
        $this->assertSame(BookingStatus::Expired, $booking->status);
        $this->assertEquals(
            $firstRefundedAt?->toIso8601String(),
            $booking->commission_refunded_at?->toIso8601String(),
        );
        $this->assertSame($before + 2500, (int) $this->producerUser->fresh()->balance);
        $this->assertSame(1, WalletTransaction::where('user_id', $this->producerUser->id)
            ->where('type', 'credit')->count());
    }

    public function test_cron_does_not_dispatch_booking_expired(): void
    {
        // D-2.5.g : la copy de NotifyPartiesOnBookingExpired est fausse pour
        // l'UGC (date_debut null) — le Producteur est couvert par la
        // notification de crédit wallet.
        Event::fake([BookingExpired::class]);
        $this->makePaidUgcBooking(now()->subDays(8));

        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful();

        Event::assertNotDispatched(BookingExpired::class);
    }

    // ===================================================================
    // Gardes d'idempotence sur un deal déjà réglé (AC3)
    // ===================================================================

    public function test_refuse_after_settled_refund_does_not_re_credit(): void
    {
        // Un deal déjà réglé (commission_refunded_at posé) ne doit pas être
        // re-crédité si la Face refuse ensuite (garde d'idempotence du hook refuse).
        $booking = $this->makePaidUgcBooking();
        app(UgcRefundService::class)->settleRefundForBooking($booking, UgcRefundReason::AcceptanceWindowExpired);
        $this->assertNotNull($booking->fresh()->commission_refunded_at);
        $balanceAfterSettle = (int) $this->producerUser->fresh()->balance;

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/refuse")
            ->assertOk();

        $booking->refresh();
        $this->assertSame(BookingStatus::Refused, $booking->status);
        $this->assertSame($balanceAfterSettle, (int) $this->producerUser->fresh()->balance);
        $this->assertSame(1, WalletTransaction::where('user_id', $this->producerUser->id)
            ->where('type', 'credit')->count());
    }

    public function test_cron_skips_already_settled_booking(): void
    {
        // Un deal déjà réglé (refunded_at posé) ne doit ni expirer ni re-créditer.
        $booking = $this->makePaidUgcBooking(now()->subDays(8));
        $booking->update(['commission_refunded_at' => now()]);
        $before = (int) $this->producerUser->balance;

        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful();

        $booking->refresh();
        $this->assertSame(BookingStatus::CommissionPaid, $booking->status); // pas d'expiration
        $this->assertSame($before, (int) $this->producerUser->fresh()->balance);
        $this->assertSame(0, WalletTransaction::where('user_id', $this->producerUser->id)
            ->where('type', 'credit')->count());
    }

    public function test_accept_rejects_refunded_deal_under_lock(): void
    {
        // AC8a (revue 2.5, reconduit) : le re-check sous lock du service refuse
        // un deal remboursé même si la policy a été passée avant.
        $booking = $this->makePaidUgcBooking();
        $booking->update(['commission_refunded_at' => now()]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(BookingService::class)->accept($booking);
    }

    // ===================================================================
    // Gardes type-owner & webhook neutralisé (AC1, AC5)
    // ===================================================================

    public function test_settlement_ignores_non_ugc_booking(): void
    {
        // Garde type-owner : settleRefundForBooking sur un booking cash ne
        // stampe rien, ne crédite pas, n'écrit aucun FinancialEvent.
        $booking = Booking::create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => BookingStatus::Accepted,
            'date_debut' => now()->addDays(5),
            'date_fin' => now()->addDays(5)->addHours(4),
            'duree_heures' => 4,
            'type_contenu' => 'video',
            'lieu' => 'Cotonou',
            'tarif_base' => 20000,
            'montant_total_producteur' => 23000,
            'montant_face_recoit' => 17000,
            'fedapay_transaction_id' => 904,
        ]);
        $before = (int) $this->producerUser->balance;

        app(UgcRefundService::class)->settleRefundForBooking($booking, UgcRefundReason::Refused);

        $booking->refresh();
        $this->assertNull($booking->commission_refunded_at);
        $this->assertSame($before, (int) $this->producerUser->fresh()->balance);
        $this->assertDatabaseMissing('financial_events', [
            'booking_id' => $booking->id,
            'type' => 'refund',
        ]);
    }

    public function test_webhook_refunded_ugc_booking_is_neutralized_no_settlement(): void
    {
        // AC5 / D-2.6.f : un transaction.refunded UGC ne règle plus rien (le
        // refund se fait via wallet). Log défensif, aucun crédit, aucune colonne,
        // queue non empoisonnée.
        $booking = $this->makePaidUgcBooking();
        $before = (int) $this->producerUser->balance;
        Log::spy();

        $this->dispatchWebhook('transaction.refunded', 901, 'ref_oob');

        $booking->refresh();
        $this->assertNull($booking->commission_refunded_at);
        $this->assertNull($booking->commission_refund_requested_at);
        $this->assertSame(BookingStatus::CommissionPaid, $booking->status);
        $this->assertSame($before, (int) $this->producerUser->fresh()->balance);
        $this->assertSame(0, WalletTransaction::where('user_id', $this->producerUser->id)->count());
        $this->assertDatabaseMissing('financial_events', [
            'booking_id' => $booking->id,
            'type' => 'refund',
        ]);
        $this->assertDatabaseMissing('fedapay_webhook_events', ['status' => 'received']);
        Log::shouldHaveReceived('critical')
            ->withArgs(fn (string $message): bool => str_contains($message, 'refund UGC inattendu'))
            ->once();
    }

    public function test_settlement_skips_zero_commission_without_crediting(): void
    {
        // Garde anti 0-crédit : un booking UGC encaissé mais commission_ugc nulle/0
        // (anomalie de données) ne doit PAS être marqué remboursé ni créditer 0.
        $booking = $this->makePaidUgcBooking(commission: 0);
        $before = (int) $this->producerUser->balance;
        Log::spy();

        app(UgcRefundService::class)->settleRefundForBooking($booking->fresh(), UgcRefundReason::Refused);

        $booking->refresh();
        $this->assertNull($booking->commission_refunded_at);
        $this->assertSame($before, (int) $this->producerUser->fresh()->balance);
        $this->assertSame(0, WalletTransaction::where('user_id', $this->producerUser->id)
            ->where('type', 'credit')->count());
        $this->assertDatabaseMissing('financial_events', [
            'booking_id' => $booking->id,
            'type' => 'refund',
        ]);
        Log::shouldHaveReceived('critical')
            ->withArgs(fn (string $message): bool => str_contains($message, 'commission_ugc absente/invalide'))
            ->once();
    }

    public function test_expiry_rolls_back_when_wallet_credit_fails_then_settles_on_retry(): void
    {
        // Revue 2.6 #1 — atomicité : expiration + crédit wallet dans la MÊME
        // transaction. Si le crédit jette, le passage à Expired est annulé (rollback)
        // → le booking reste commission_paid et le tick cron suivant règle (reprise
        // automatique, plus de deal figé en statut terminal sans remboursement).
        $booking = $this->makePaidUgcBooking(now()->subDays(8));
        $before = (int) $this->producerUser->balance;
        Log::spy();

        // 1ʳᵉ tentative : wallet en panne (credit jette) → rollback complet.
        $throwingWallet = new class extends WalletService
        {
            public function credit(int $userId, int $amount, Booking $booking, string $description): WalletTransaction
            {
                throw new \RuntimeException('wallet indisponible');
            }
        };
        $this->assertFalse((new UgcRefundService($throwingWallet))->expireBookingPastAcceptanceWindow($booking));

        $booking->refresh();
        $this->assertSame(BookingStatus::CommissionPaid, $booking->status); // pas d'expiration
        $this->assertNull($booking->commission_refunded_at);
        $this->assertNull($booking->commission_refund_requested_at);
        $this->assertSame($before, (int) $this->producerUser->fresh()->balance);
        $this->assertSame(0, WalletTransaction::where('user_id', $this->producerUser->id)->count());
        Log::shouldHaveReceived('critical')
            ->withArgs(fn (string $message): bool => str_contains($message, 'échec expiration/settlement booking'))
            ->once();

        // 2ᵉ tentative (wallet rétabli) : règle dans le même appel (reprise auto).
        $this->assertTrue(app(UgcRefundService::class)->expireBookingPastAcceptanceWindow($booking->fresh()));

        $booking->refresh();
        $this->assertSame(BookingStatus::Expired, $booking->status);
        $this->assertNotNull($booking->commission_refunded_at);
        $this->assertSame($before + 2500, (int) $this->producerUser->fresh()->balance);
        $this->assertSame(1, WalletTransaction::where('user_id', $this->producerUser->id)
            ->where('type', 'credit')->count());
    }
}
