<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc;

use App\Enums\BookingStatus;
use App\Enums\UgcRefundReason;
use App\Events\BookingExpired;
use App\Jobs\HandleFedapayWebhook;
use App\Mail\UgcRefundRequestedMail;
use App\Models\Booking;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\FedapayWebhookEvent;
use App\Models\Producer;
use App\Models\User;
use App\Services\BookingService;
use App\Services\FaceSubscriptionPaymentService;
use App\Services\MissionPaymentService;
use App\Services\Ugc\UgcCommissionPaymentService;
use App\Services\Ugc\UgcRefundService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * UGC 2.5 — remboursement de la commission Producteur sur un booking UGC
 * non abouti (refus depuis commission_paid, fenêtre d'acceptation expirée).
 *
 * Le remboursement est un flux OPS (spike OI-2 : SDK sans refund()) : le
 * système trace la demande (commission_refund_requested_at + reason), alerte
 * (mail admin + notification Producteur), et le webhook transaction.refunded
 * règle (commission_refunded_at + FinancialEvent refund).
 */
class UgcBookingRefundTest extends TestCase
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

        Mail::fake();
        config(['app.admin_email' => 'ops@weact.test']);

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

    private function subscribeFace(): void
    {
        FaceSubscription::factory()->starter()->active()->create(['face_id' => $this->face->id]);
    }

    private function makePaidUgcBooking(?\Carbon\CarbonInterface $paidAt = null, int $transactionId = 901): Booking
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
            'commission_ugc' => 2500,
            'fedapay_transaction_id' => $transactionId,    // colonne UNIQUE : id distinct par booking d'un même test
            'commission_paid_at' => $paidAt ?? now()->subDay(),
        ]);
    }

    private function dispatchWebhook(string $eventName, int $transactionId, string $reference, ?string $transactionStatus = null): void
    {
        $this->webhookSeq++;
        $entity = ['id' => $transactionId, 'reference' => $reference];

        if ($transactionStatus !== null) {
            $entity['status'] = $transactionStatus;
        }

        $payload = ['entity' => $entity];

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
            app(UgcRefundService::class),
        );
    }

    // ===================================================================
    // Demande de remboursement — refus depuis commission_paid (AC2, AC3)
    // ===================================================================

    public function test_refuse_at_commission_paid_requests_refund(): void
    {
        $booking = $this->makePaidUgcBooking();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/refuse")
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::Refused->value);

        $booking->refresh();
        $this->assertSame(BookingStatus::Refused, $booking->status);
        $this->assertNotNull($booking->commission_refund_requested_at);
        $this->assertSame(UgcRefundReason::Refused, $booking->commission_refund_reason);
        $this->assertNull($booking->commission_refunded_at);

        Mail::assertSent(UgcRefundRequestedMail::class, fn (UgcRefundRequestedMail $mail): bool => $mail->owner->id === $booking->id
            && $mail->reason === UgcRefundReason::Refused
            && $mail->hasTo('ops@weact.test'));

        // L'event UgcCommissionRefundRequested a tourné (listener réel) :
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->producerUser->id,
            'type' => 'ugc_commission_refund_requested',
        ]);
    }

    public function test_refuse_at_pending_requests_no_refund(): void
    {
        $booking = $this->makePaidUgcBooking();
        $booking->update(['status' => BookingStatus::Pending, 'commission_paid_at' => null]);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/refuse")
            ->assertOk();

        $booking->refresh();
        $this->assertSame(BookingStatus::Refused, $booking->status);
        $this->assertNull($booking->commission_refund_requested_at);
        $this->assertNull($booking->commission_refund_reason);
        Mail::assertNotSent(UgcRefundRequestedMail::class);
    }

    public function test_refuse_cash_booking_leaves_refund_columns_null(): void
    {
        // Témoin : le refus cash est strictement inchangé par 2.5.
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

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/refuse")
            ->assertOk();

        $booking->refresh();
        $this->assertSame(BookingStatus::Refused, $booking->status);
        $this->assertNull($booking->commission_refund_requested_at);
        $this->assertNull($booking->commission_refund_reason);
        Mail::assertNotSent(UgcRefundRequestedMail::class);
    }

    public function test_refund_request_is_idempotent(): void
    {
        $booking = $this->makePaidUgcBooking();
        $service = app(UgcRefundService::class);

        $service->requestRefundForBooking($booking, UgcRefundReason::Refused);
        $firstRequestedAt = $booking->fresh()->commission_refund_requested_at;

        $service->requestRefundForBooking($booking->fresh(), UgcRefundReason::Refused);

        $this->assertEquals(
            $firstRequestedAt?->toIso8601String(),
            $booking->fresh()->commission_refund_requested_at?->toIso8601String(),
        );
        Mail::assertSent(UgcRefundRequestedMail::class, 1);
        $this->assertSame(1, \App\Models\Notification::where('type', 'ugc_commission_refund_requested')->count());
    }

    public function test_missing_admin_email_skips_mail_without_exception(): void
    {
        config(['app.admin_email' => '']);
        Log::spy();

        $booking = $this->makePaidUgcBooking();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/refuse")
            ->assertOk();

        $this->assertNotNull($booking->fresh()->commission_refund_requested_at);
        Mail::assertNotSent(UgcRefundRequestedMail::class);
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message): bool => str_contains($message, 'admin_email non configuré'))
            ->once();
    }

    // ===================================================================
    // Cron — expiration fenêtre d'acceptation (AC4)
    // ===================================================================

    public function test_cron_expires_commission_paid_booking_past_window(): void
    {
        $booking = $this->makePaidUgcBooking(now()->subDays(8)); // 8 j > 7 j config

        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful();

        $booking->refresh();
        $this->assertSame(BookingStatus::Expired, $booking->status);
        $this->assertNotNull($booking->commission_refund_requested_at);
        $this->assertSame(UgcRefundReason::AcceptanceWindowExpired, $booking->commission_refund_reason);
        Mail::assertSent(UgcRefundRequestedMail::class, 1);
    }

    public function test_cron_ignores_commission_paid_booking_within_window(): void
    {
        $booking = $this->makePaidUgcBooking(now()->subDays(3), 902);

        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful();

        $booking->refresh();
        $this->assertSame(BookingStatus::CommissionPaid, $booking->status);
        $this->assertNull($booking->commission_refund_requested_at);
        Mail::assertNotSent(UgcRefundRequestedMail::class);
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

        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful();

        $booking->refresh();
        $this->assertSame(BookingStatus::CommissionPaid, $booking->status);
        $this->assertNull($booking->commission_refund_requested_at);
    }

    public function test_cron_is_idempotent(): void
    {
        $booking = $this->makePaidUgcBooking(now()->subDays(8));

        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful();
        $firstRequestedAt = $booking->fresh()->commission_refund_requested_at;

        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful();

        $booking->refresh();
        $this->assertSame(BookingStatus::Expired, $booking->status);
        $this->assertEquals(
            $firstRequestedAt?->toIso8601String(),
            $booking->commission_refund_requested_at?->toIso8601String(),
        );
        Mail::assertSent(UgcRefundRequestedMail::class, 1);
    }

    public function test_cron_does_not_dispatch_booking_expired(): void
    {
        // D-2.5.g : la copy de NotifyPartiesOnBookingExpired est fausse pour
        // l'UGC (date_debut null) — le Producteur est couvert par la
        // notification refund.
        Event::fake([BookingExpired::class]);
        $this->makePaidUgcBooking(now()->subDays(8));

        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful();

        Event::assertNotDispatched(BookingExpired::class);
    }

    // ===================================================================
    // Settlement — webhook transaction.refunded (AC6, AC8)
    // ===================================================================

    public function test_webhook_refunded_settles_booking_refund(): void
    {
        $booking = $this->makePaidUgcBooking();
        app(UgcRefundService::class)->requestRefundForBooking($booking, UgcRefundReason::Refused);

        $this->dispatchWebhook('transaction.refunded', 901, 'ref_refund');

        $booking->refresh();
        $this->assertNotNull($booking->commission_refunded_at);
        $this->assertDatabaseHas('financial_events', [
            'booking_id' => $booking->id,
            'type' => 'refund',
            'amount' => 2500,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->producerUser->id,
            'type' => 'ugc_commission_refunded',
        ]);
        // Webhook traité (queue non empoisonnée).
        $this->assertDatabaseMissing('fedapay_webhook_events', ['status' => 'received']);
    }

    public function test_webhook_refunded_replay_records_single_financial_event(): void
    {
        $booking = $this->makePaidUgcBooking();
        app(UgcRefundService::class)->requestRefundForBooking($booking, UgcRefundReason::Refused);

        $this->dispatchWebhook('transaction.refunded', 901, 'ref_refund');
        $firstRefundedAt = $booking->fresh()->commission_refunded_at;

        $this->dispatchWebhook('transaction.refunded', 901, 'ref_refund');

        $booking->refresh();
        $this->assertEquals(
            $firstRefundedAt?->toIso8601String(),
            $booking->commission_refunded_at?->toIso8601String(),
        );
        $this->assertSame(1, \App\Models\FinancialEvent::where('booking_id', $booking->id)
            ->where('type', 'refund')->count());
    }

    public function test_webhook_refunded_without_local_request_settles_and_blocks_accept(): void
    {
        // AC8 : refund ops hors-procédure — settlement enregistré (les livres
        // suivent FedaPay), AUCUNE transition de statut, et la policy accept
        // exclut le deal remboursé (AC8a).
        $this->subscribeFace();
        $booking = $this->makePaidUgcBooking();

        $this->dispatchWebhook('transaction.refunded', 901, 'ref_oob');

        $booking->refresh();
        $this->assertNotNull($booking->commission_refunded_at);
        $this->assertNull($booking->commission_refund_requested_at);
        $this->assertSame(BookingStatus::CommissionPaid, $booking->status); // statut inchangé (D-2.5.h)

        // can_accept policy-driven : le deal remboursé n'est plus acceptable.
        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}")
            ->assertOk()
            ->assertJsonPath('data.can_accept', false);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/accept")
            ->assertForbidden();

        $this->assertSame(BookingStatus::CommissionPaid, $booking->fresh()->status);
    }

    public function test_refuse_after_out_of_band_refund_requests_no_refund(): void
    {
        // Revue 2.5 : un deal réglé hors-procédure (refunded_at posé, statut
        // resté commission_paid — D-2.5.h) ne doit JAMAIS re-déclencher une
        // demande de remboursement au refus de la Face.
        $booking = $this->makePaidUgcBooking();
        $this->dispatchWebhook('transaction.refunded', 901, 'ref_oob');
        $this->assertNotNull($booking->fresh()->commission_refunded_at);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/refuse")
            ->assertOk();

        $booking->refresh();
        $this->assertSame(BookingStatus::Refused, $booking->status);
        $this->assertNull($booking->commission_refund_requested_at);
        Mail::assertNotSent(UgcRefundRequestedMail::class);
        $this->assertSame(0, \App\Models\Notification::where('type', 'ugc_commission_refund_requested')->count());
    }

    public function test_cron_skips_out_of_band_refunded_booking(): void
    {
        // Revue 2.5 : même garde côté cron — le deal remboursé reste
        // commission_paid (D-2.5.h) mais ne doit ni expirer ni redemander.
        $booking = $this->makePaidUgcBooking(now()->subDays(8));
        $this->dispatchWebhook('transaction.refunded', 901, 'ref_oob');

        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful();

        $booking->refresh();
        $this->assertSame(BookingStatus::CommissionPaid, $booking->status);
        $this->assertNull($booking->commission_refund_requested_at);
        Mail::assertNotSent(UgcRefundRequestedMail::class);
    }

    public function test_accept_rejects_refunded_deal_under_lock(): void
    {
        // Revue 2.5 (AC8a, race policy → lock) : le re-check sous lock du
        // service refuse un deal remboursé même si la policy a été passée
        // avant le settlement du webhook.
        $booking = $this->makePaidUgcBooking();
        $booking->update(['commission_refunded_at' => now()]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(BookingService::class)->accept($booking);
    }

    public function test_webhook_partial_refund_does_not_settle(): void
    {
        // Revue 2.5 : un refund PARTIEL (statut FedaPay contenant
        // partially_refunded) ne règle pas la demande — elle reste ouverte
        // jusqu'au remboursement complet.
        $booking = $this->makePaidUgcBooking();
        app(UgcRefundService::class)->requestRefundForBooking($booking, UgcRefundReason::Refused);

        $this->dispatchWebhook('transaction.refunded', 901, 'ref_partial', 'approved_partially_refunded');

        $booking->refresh();
        $this->assertNull($booking->commission_refunded_at);
        $this->assertDatabaseMissing('financial_events', [
            'booking_id' => $booking->id,
            'type' => 'refund',
        ]);
        // Webhook quand même marqué traité (queue non empoisonnée) ; le refund
        // complet arrivera dans un événement ultérieur distinct.
        $this->assertDatabaseMissing('fedapay_webhook_events', ['status' => 'received']);
    }

    public function test_settlement_ignores_non_ugc_booking(): void
    {
        // Revue 2.5 : garde type-owner — un appel tinker (runbook §4) sur un
        // mauvais id (booking cash) ne stampe rien et n'écrit aucun event.
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

        app(UgcRefundService::class)->markBookingCommissionRefunded($booking, '904');

        $booking->refresh();
        $this->assertNull($booking->commission_refunded_at);
        $this->assertDatabaseMissing('financial_events', [
            'booking_id' => $booking->id,
            'type' => 'refund',
        ]);
    }
}
