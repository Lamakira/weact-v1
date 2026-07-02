<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc;

use App\Enums\BookingStatus;
use App\Enums\UgcRefundReason;
use App\Events\BookingAccepted;
use App\Events\BookingCommissionPaid;
use App\Events\BookingRefused;
use App\Models\Booking;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * UGC 2.4 — state machine accept/refuse d'un booking UGC (bloqueur #12).
 *
 * Accept exclusivement depuis commission_paid (gate éligibilité FR5 au
 * contrôleur) ; refuse depuis pending OU commission_paid, jamais gated.
 * Le flux cash reste strictement inchangé (témoins).
 */
class UgcBookingAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    private User $faceUser;

    private Face $face;

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

    private function subscribeFace(): void
    {
        FaceSubscription::factory()->starter()->active()->create(['face_id' => $this->face->id]);
    }

    private function makeUgcBooking(BookingStatus $status, string $compensation = 'product'): Booking
    {
        return Booking::create([
            'face_id' => $this->faceUser->id,          // users.id (PAS faces.id)
            'producer_id' => $this->producerUser->id,
            'status' => $status,
            'date_debut' => null,                       // dotation UGC : pas de tournage (D-1.1)
            'date_fin' => null,
            'duree_heures' => null,
            'type_contenu' => 'UGC',
            'lieu' => null,
            'tarif_base' => 0,
            'montant_face_recoit' => $compensation === 'hybrid' ? 15000 : 0,
            'montant_total_producteur' => $compensation === 'hybrid' ? 17500 : 2500,
            'type_compensation' => $compensation,
            'nom_produit' => 'Tenue Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'montant_remuneration' => $compensation === 'hybrid' ? 15000 : null,
            'commission_ugc' => 2500,
        ]);
    }

    // ===================================================================
    // Accept — state machine UGC (AC1)
    // ===================================================================

    public function test_face_can_accept_ugc_booking_at_commission_paid(): void
    {
        Event::fake([BookingAccepted::class]);
        $this->subscribeFace();
        $booking = $this->makeUgcBooking(BookingStatus::CommissionPaid);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::Accepted->value);

        $booking->refresh();
        $this->assertSame(BookingStatus::Accepted, $booking->status);
        $this->assertNotNull($booking->accepted_at);
        $this->assertNull($booking->payment_reminder_sent_at);

        Event::assertDispatched(BookingAccepted::class, fn (BookingAccepted $event): bool => $event->booking->id === $booking->id);
    }

    public function test_face_cannot_accept_ugc_booking_at_pending(): void
    {
        $this->subscribeFace();
        $booking = $this->makeUgcBooking(BookingStatus::Pending);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/accept")
            ->assertForbidden()
            // 403 policy (statut, envelope FORBIDDEN du handler), PAS le 403 paywall
            // UGC_SUBSCRIPTION_REQUIRED : la Face est abonnée ici.
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
    }

    public function test_ugc_booking_cannot_be_accepted_from_accepted(): void
    {
        $this->subscribeFace();
        $booking = $this->makeUgcBooking(BookingStatus::Accepted);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/accept")
            ->assertForbidden();

        $this->assertSame(BookingStatus::Accepted, $booking->fresh()->status);
    }

    public function test_ugc_booking_cannot_be_refused_from_accepted(): void
    {
        // Lock-in délibéré : après acceptation, pas de sortie refuse — le refund arrive en 2.5.
        $this->subscribeFace();
        $booking = $this->makeUgcBooking(BookingStatus::Accepted);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/refuse")
            ->assertForbidden();

        $this->assertSame(BookingStatus::Accepted, $booking->fresh()->status);
    }

    // ===================================================================
    // Refuse — depuis pending OU commission_paid (AC1)
    // ===================================================================

    public function test_face_can_refuse_ugc_booking_at_pending(): void
    {
        $booking = $this->makeUgcBooking(BookingStatus::Pending);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/refuse", [
                'cancellation_reason' => 'Pas disponible',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::Refused->value);

        $booking->refresh();
        $this->assertSame(BookingStatus::Refused, $booking->status);
        $this->assertSame('Pas disponible', $booking->cancellation_reason);
        // Refus à pending : commission jamais encaissée, aucune demande de remboursement (2.5).
        $this->assertNull($booking->commission_refund_requested_at);
    }

    public function test_face_can_refuse_ugc_booking_at_commission_paid(): void
    {
        Event::fake([BookingRefused::class]);
        $booking = $this->makeUgcBooking(BookingStatus::CommissionPaid);
        // commission_paid_at : requis par la garde de settleRefundForBooking (2.6) ;
        // fedapay_transaction_id : sert de référence au FinancialEvent Refund (booking-scopé).
        // PAS dans le helper partagé : fedapay_transaction_id est UNIQUE et le helper
        // sert des tests multi-bookings.
        $booking->update(['fedapay_transaction_id' => 905, 'commission_paid_at' => now()->subHour()]);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/refuse")
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::Refused->value);

        $booking->refresh();
        $this->assertSame(BookingStatus::Refused, $booking->status);
        // Trace commission conservée + demande de remboursement posée (2.5).
        $this->assertNotNull($booking->commission_ugc);
        $this->assertNotNull($booking->commission_refund_requested_at);
        $this->assertSame(UgcRefundReason::Refused, $booking->commission_refund_reason);

        Event::assertDispatched(BookingRefused::class, fn (BookingRefused $event): bool => $event->booking->id === $booking->id);
    }

    // ===================================================================
    // Gate éligibilité FR5 — accept gated, refuse non gated (AC2)
    // ===================================================================

    public function test_free_face_cannot_accept_ugc_booking_at_commission_paid(): void
    {
        $booking = $this->makeUgcBooking(BookingStatus::CommissionPaid);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/accept")
            ->assertForbidden()
            ->assertJsonPath('error.code', 'UGC_SUBSCRIPTION_REQUIRED')
            ->assertJsonPath('error.message', "L'accès aux missions UGC est réservé aux Faces abonnées (Starter et plus).");

        $this->assertSame(BookingStatus::CommissionPaid, $booking->fresh()->status);
    }

    public function test_free_face_can_refuse_ugc_booking_at_commission_paid(): void
    {
        $booking = $this->makeUgcBooking(BookingStatus::CommissionPaid);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/refuse")
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::Refused->value);
    }

    // ===================================================================
    // Flags policy-driven du show (AC1)
    // ===================================================================

    public function test_show_flags_for_ugc_booking_at_pending(): void
    {
        $this->subscribeFace();
        $booking = $this->makeUgcBooking(BookingStatus::Pending);

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}")
            ->assertOk()
            ->assertJsonPath('data.can_accept', false)
            ->assertJsonPath('data.can_refuse', true);
    }

    public function test_show_flags_for_ugc_booking_at_commission_paid(): void
    {
        $this->subscribeFace();
        $booking = $this->makeUgcBooking(BookingStatus::CommissionPaid);

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}")
            ->assertOk()
            ->assertJsonPath('data.can_accept', true)
            ->assertJsonPath('data.can_refuse', true);
    }

    // ===================================================================
    // Témoins cash — flux standard inchangé (AC1)
    // ===================================================================

    public function test_cash_booking_accept_from_pending_still_works(): void
    {
        $booking = Booking::factory()->pending()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::Accepted->value);
    }

    public function test_cash_booking_cannot_be_accepted_from_accepted(): void
    {
        $booking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/accept")
            ->assertForbidden();
    }

    // ===================================================================
    // Cron remind-payment — exclusion UGC (AC3)
    // ===================================================================

    public function test_remind_payment_command_ignores_accepted_ugc_booking(): void
    {
        // Fenêtre de relance du cron : accepted_at entre 12h et 20h — 15h est dedans.
        $booking = $this->makeUgcBooking(BookingStatus::CommissionPaid);
        $booking->update([
            'status' => BookingStatus::Accepted,
            'accepted_at' => now()->subHours(15),
        ]);

        $this->artisan('bookings:remind-payment')
            ->expectsOutput('Found 0 booking(s) requiring a payment reminder.');

        $this->assertNull($booking->fresh()->payment_reminder_sent_at);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->producerUser->id,
            'type' => 'booking_payment_reminder',
        ]);
    }

    public function test_remind_payment_command_still_reminds_accepted_cash_booking(): void
    {
        $booking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'accepted_at' => now()->subHours(15),
            'payment_reminder_sent_at' => null,
        ]);

        $this->artisan('bookings:remind-payment')
            ->expectsOutput('Found 1 booking(s) requiring a payment reminder.');

        $this->assertNotNull($booking->fresh()->payment_reminder_sent_at);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->producerUser->id,
            'type' => 'booking_payment_reminder',
        ]);
    }

    // ===================================================================
    // Listener enablement Face (AC6)
    // ===================================================================

    public function test_booking_commission_paid_event_notifies_face(): void
    {
        $booking = $this->makeUgcBooking(BookingStatus::CommissionPaid);

        BookingCommissionPaid::dispatch($booking);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->faceUser->id,
            'type' => 'ugc_commission_paid',
        ]);
    }
}
