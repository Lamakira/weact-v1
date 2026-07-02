<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc;

use App\Enums\BookingStatus;
use App\Enums\EscrowStatus;
use App\Enums\FinancialEventType;
use App\Models\Booking;
use App\Models\EscrowTransaction;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * UGC 5.0 — garde transverse : pas d'annulation volontaire d'un booking UGC
 * post-paiement. RH.2 séquestre le « net Face » dans une EscrowTransaction dès
 * CommissionPaid ; cancel/cancelByFace étaient UGC-blind (Accepted autorisé,
 * refund escrow seulement si Paid) → escrow stranded on-platform. D-5.0.b
 * (kickoff épic 5) : seule sortie post-acceptation = clôture (RH.3) ou
 * suspension (5.1). Garde primaire policy (403) + mirror défensif service.
 */
class UgcBookingCancelGuardTest extends TestCase
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

    private function makeUgcBooking(BookingStatus $status, string $compensation = 'hybrid'): Booking
    {
        return Booking::create([
            'face_id' => $this->faceUser->id,          // users.id (PAS faces.id)
            'producer_id' => $this->producerUser->id,
            'status' => $status,
            'date_debut' => null,
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

    // Escrow Locked pour AC1/AC4 (calque EscrowService::lock — montant = montant_face_recoit).
    private function lockEscrow(Booking $booking): void
    {
        EscrowTransaction::create([
            'booking_id' => $booking->id,
            'amount' => $booking->montant_face_recoit,
            'status' => EscrowStatus::Locked->value,
            'locked_at' => now(),
        ]);
    }

    // ===================================================================
    // AC1 — Producteur ne peut pas annuler un UGC Accepted
    // ===================================================================

    public function test_producer_cannot_cancel_ugc_accepted_booking(): void
    {
        $booking = $this->makeUgcBooking(BookingStatus::Accepted);
        $this->lockEscrow($booking);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/cancel", [
                'cancellation_reason' => 'schedule_conflict',
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->assertSame(BookingStatus::Accepted, $booking->fresh()->status);

        $this->assertDatabaseMissing('financial_events', [
            'booking_id' => $booking->id,
            'type' => FinancialEventType::Refund->value,
        ]);

        $this->assertDatabaseHas('escrow_transactions', [
            'booking_id' => $booking->id,
            'status' => EscrowStatus::Locked->value,
        ]);
    }

    // ===================================================================
    // AC2 — Face ne peut pas annuler un UGC Accepted (pas de pénalité)
    // ===================================================================

    public function test_face_cannot_cancel_ugc_accepted_booking(): void
    {
        $booking = $this->makeUgcBooking(BookingStatus::Accepted);
        $this->lockEscrow($booking);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/cancel", [
                'cancellation_reason' => 'schedule_conflict',
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->assertSame(BookingStatus::Accepted, $booking->fresh()->status);

        // Aucune pénalité de notation : le code de pénalité de cancelByFace ne s'exécute pas.
        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'rating_penalty' => 0.0,
        ]);
    }

    // ===================================================================
    // AC3 — UGC CommissionPaid non annulable (verrouillage défensif)
    // ===================================================================

    public function test_ugc_commission_paid_is_not_cancellable(): void
    {
        $booking = $this->makeUgcBooking(BookingStatus::CommissionPaid);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/cancel", [
                'cancellation_reason' => 'schedule_conflict',
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->assertSame(BookingStatus::CommissionPaid, $booking->fresh()->status);
    }

    public function test_face_cannot_cancel_ugc_commission_paid(): void
    {
        $booking = $this->makeUgcBooking(BookingStatus::CommissionPaid);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/cancel", [
                'cancellation_reason' => 'schedule_conflict',
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->assertSame(BookingStatus::CommissionPaid, $booking->fresh()->status);

        // Aucune pénalité de notation : la garde policy court-circuite avant le service.
        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'rating_penalty' => 0.0,
        ]);
    }

    // ===================================================================
    // AC6 — Cancel d'un UGC Pending (pré-paiement) préservé
    // ===================================================================

    public function test_producer_can_still_cancel_ugc_pending_booking(): void
    {
        $booking = $this->makeUgcBooking(BookingStatus::Pending);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/cancel", [
                'cancellation_reason' => 'schedule_conflict',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::CancelledByProducer->value);

        $this->assertDatabaseMissing('financial_events', [
            'booking_id' => $booking->id,
            'type' => FinancialEventType::Refund->value,
        ]);
    }

    // ===================================================================
    // AC4 — Defense-in-depth service (appel direct)
    // ===================================================================

    public function test_booking_service_cancel_throws_on_ugc_accepted(): void
    {
        $booking = $this->makeUgcBooking(BookingStatus::Accepted);
        $this->lockEscrow($booking);

        try {
            app(BookingService::class)->cancel($booking, 'schedule_conflict');
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(BookingStatus::Accepted, $booking->fresh()->status);
        $this->assertDatabaseHas('escrow_transactions', [
            'booking_id' => $booking->id,
            'status' => EscrowStatus::Locked->value,
        ]);
    }

    public function test_booking_service_cancel_by_face_throws_on_ugc_accepted(): void
    {
        $booking = $this->makeUgcBooking(BookingStatus::Accepted);
        $this->lockEscrow($booking);

        try {
            app(BookingService::class)->cancelByFace($booking, 'schedule_conflict');
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(BookingStatus::Accepted, $booking->fresh()->status);
        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'rating_penalty' => 0.0,
        ]);
    }
}
