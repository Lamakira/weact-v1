<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc;

use App\Enums\BookingStatus;
use App\Enums\DeliverableKind;
use App\Enums\DeliverableValidationStatus;
use App\Enums\UgcTunnelStatus;
use App\Events\ShipmentConfirmed;
use App\Models\Booking;
use App\Models\Face;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Ugc\Concerns\BuildsUgcShipments;
use Tests\TestCase;

/**
 * UGC 3.1 — POST /api/v1/producer/bookings/{booking}/confirm-shipment :
 * confirmation d'expédition d'un booking UGC accepté (tunnel étape 3).
 * Crée le Shipment polymorphe (snapshot destinataire figé), ouvre le
 * micro-tunnel à `shipped`, idempotent (ALREADY_SHIPPED), gardes refund
 * propagées (D-2.5.h / action #5 rétro épic 2).
 */
class UgcBookingShipmentTest extends TestCase
{
    use BuildsUgcShipments;
    use RefreshDatabase;

    private Producer $producer;

    private User $producerUser;

    private Face $face;

    private User $faceUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
        $this->face = Face::factory()->create([
            'prenom' => 'Aïcha',
            'nom' => 'Bello',
            'ville' => 'Cotonou',
            'pays' => 'Bénin',
        ]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    private function makeUgcBooking(BookingStatus $status = BookingStatus::Accepted): Booking
    {
        // PAS de fedapay_transaction_id (colonne unique — piège n°3) ; la garde
        // shipment ne lit que le statut + les colonnes refund.
        return Booking::create([
            'face_id' => $this->faceUser->id,        // users.id
            'producer_id' => $this->producerUser->id, // users.id
            'status' => $status,
            'accepted_at' => $status === BookingStatus::Accepted ? now() : null,
            'date_debut' => null,
            'type_contenu' => 'UGC',
            'type_compensation' => 'product',
            'nom_produit' => 'Tenue Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'montant_remuneration' => null,
            'commission_ugc' => 2500,
            'commission_paid_at' => now()->subDay(),
            // Colonnes NOT NULL sans default (create_bookings_table:23-25) —
            // obligatoires sinon SQLSTATE « Field 'tarif_base' doesn't have a
            // default value » (calque UgcBookingAcceptanceTest:77-79).
            'tarif_base' => 0,
            'montant_total_producteur' => 2500,
            'montant_face_recoit' => 0,
        ]);
    }

    // ===================================================================
    // Happy path (AC3, AC5)
    // ===================================================================

    public function test_producer_confirms_shipment_on_accepted_ugc_booking(): void
    {
        $booking = $this->makeUgcBooking();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/bookings/{$booking->uuid}/confirm-shipment", $this->confirmPayload())
            ->assertCreated()
            ->assertJsonPath('message', 'Expédition confirmée')
            ->assertJsonPath('data.transporteur', 'Gozem')
            ->assertJsonPath('data.numero_suivi', 'GZM-COT-882194')
            ->assertJsonPath('data.note_envoi', 'Le colis arrive demain entre 14h et 16h.')
            ->assertJsonPath('data.tunnel_status', UgcTunnelStatus::Shipped->value)
            ->assertJsonPath('data.tunnel_status_label', 'Produit expédié')
            ->assertJsonPath('data.destinataire.nom', 'Aïcha Bello')
            ->assertJsonPath('data.destinataire.ville', 'Cotonou')
            ->assertJsonPath('data.destinataire.pays', 'Bénin');

        $this->assertDatabaseHas('shipments', [
            'owner_type' => Booking::class,
            'owner_id' => $booking->id,
            'tunnel_status' => UgcTunnelStatus::Shipped->value,
        ]);

        $shipment = Shipment::firstOrFail();
        $this->assertNotNull($shipment->shipped_at);
        $this->assertNull($shipment->recu_le);
        $this->assertSame(BookingStatus::Accepted, $booking->fresh()->status); // statut owner inchangé (D-3.1.c)
    }

    public function test_shipment_snapshot_is_frozen_at_confirmation(): void
    {
        $booking = $this->makeUgcBooking();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/bookings/{$booking->uuid}/confirm-shipment", $this->confirmPayload())
            ->assertCreated();

        $this->face->update(['ville' => 'Porto-Novo']);

        $this->assertDatabaseHas('shipments', [
            'owner_id' => $booking->id,
            'destinataire_ville' => 'Cotonou', // snapshot figé (D-3.1.f)
        ]);
    }

    public function test_confirm_dispatches_event_and_notifies_face(): void
    {
        $booking = $this->makeUgcBooking();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/bookings/{$booking->uuid}/confirm-shipment", $this->confirmPayload())
            ->assertCreated();

        $notification = Notification::where('user_id', $this->faceUser->id)
            ->where('type', 'ugc_shipment_confirmed')
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame("/face/bookings/{$booking->uuid}", data_get($notification->data, 'url'));
        $this->assertStringContainsString('Gozem', (string) data_get($notification->data, 'message'));
        $this->assertStringContainsString('GZM-COT-882194', (string) data_get($notification->data, 'message'));
    }

    // ===================================================================
    // Idempotence (AC5)
    // ===================================================================

    public function test_reconfirm_returns_already_shipped_and_keeps_single_row(): void
    {
        $booking = $this->makeUgcBooking();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/bookings/{$booking->uuid}/confirm-shipment", $this->confirmPayload())
            ->assertCreated();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/bookings/{$booking->uuid}/confirm-shipment", $this->confirmPayload())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'ALREADY_SHIPPED');

        $this->assertSame(1, Shipment::count());
        $this->assertSame(1, Notification::where('type', 'ugc_shipment_confirmed')->count());
    }

    // ===================================================================
    // Gardes de statut (AC5)
    // ===================================================================

    public function test_confirm_rejected_at_commission_paid(): void
    {
        $booking = $this->makeUgcBooking(BookingStatus::CommissionPaid);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/bookings/{$booking->uuid}/confirm-shipment", $this->confirmPayload())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->assertSame(0, Shipment::count());
    }

    public function test_confirm_rejected_at_pending(): void
    {
        $booking = $this->makeUgcBooking(BookingStatus::Pending);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/bookings/{$booking->uuid}/confirm-shipment", $this->confirmPayload())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->assertSame(0, Shipment::count());
    }

    public function test_confirm_rejected_for_cash_accepted_booking(): void
    {
        // Témoin : un booking cash Accepted n'est PAS expédiable.
        $booking = Booking::factory()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => BookingStatus::Accepted,
        ]);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/bookings/{$booking->uuid}/confirm-shipment", $this->confirmPayload())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->assertSame(0, Shipment::count());
    }

    // ===================================================================
    // Gardes refund (AC5 — propagation D-2.5.h, action #5 rétro)
    // ===================================================================

    public function test_confirm_rejected_when_refund_requested(): void
    {
        $booking = $this->makeUgcBooking();
        $booking->update(['commission_refund_requested_at' => now()]);

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/bookings/{$booking->uuid}/confirm-shipment", $this->confirmPayload());

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->assertStringContainsString('en cours de remboursement', (string) $response->json('error.message'));
        $this->assertSame(0, Shipment::count());
    }

    public function test_confirm_rejected_when_refunded_out_of_band(): void
    {
        // D-2.5.h : refund réglé hors-procédure, statut resté Accepted.
        $booking = $this->makeUgcBooking();
        $booking->update(['commission_refunded_at' => now()]);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/bookings/{$booking->uuid}/confirm-shipment", $this->confirmPayload())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->assertSame(0, Shipment::count());
    }

    // ===================================================================
    // Autorisation (AC7)
    // ===================================================================

    public function test_other_producer_gets_403(): void
    {
        $booking = $this->makeUgcBooking();

        $otherProducer = Producer::factory()->create();
        $otherProducerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $otherProducer->id,
        ]);

        $this->actingAs($otherProducerUser)
            ->postJson("/api/v1/producer/bookings/{$booking->uuid}/confirm-shipment", $this->confirmPayload())
            ->assertForbidden();

        $this->assertSame(0, Shipment::count());
    }

    public function test_face_gets_403(): void
    {
        $booking = $this->makeUgcBooking();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/producer/bookings/{$booking->uuid}/confirm-shipment", $this->confirmPayload())
            ->assertForbidden();

        $this->assertSame(0, Shipment::count());
    }

    // ===================================================================
    // Validation (AC4)
    // ===================================================================

    public function test_transporteur_is_required(): void
    {
        $booking = $this->makeUgcBooking();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/bookings/{$booking->uuid}/confirm-shipment", $this->confirmPayload(['transporteur' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['transporteur' => 'Le transporteur est obligatoire.']);
    }

    public function test_numero_suivi_is_required(): void
    {
        $booking = $this->makeUgcBooking();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/bookings/{$booking->uuid}/confirm-shipment", $this->confirmPayload(['numero_suivi' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['numero_suivi' => 'Le numéro de suivi est obligatoire.']);
    }

    public function test_note_envoi_is_optional(): void
    {
        $booking = $this->makeUgcBooking();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/bookings/{$booking->uuid}/confirm-shipment", [
                'transporteur' => 'DHL',
                'numero_suivi' => 'DHL-123456',
            ])
            ->assertCreated()
            ->assertJsonPath('data.note_envoi', null);

        $this->assertDatabaseHas('shipments', [
            'owner_id' => $booking->id,
            'note_envoi' => null,
        ]);
    }

    // ===================================================================
    // Exposition resource (AC9) + auth (AC3)
    // ===================================================================

    public function test_booking_show_exposes_shipment(): void
    {
        $booking = $this->makeUgcBooking();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/bookings/{$booking->uuid}/confirm-shipment", $this->confirmPayload())
            ->assertCreated();

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}")
            ->assertOk()
            ->assertJsonPath('data.shipment.tunnel_status', UgcTunnelStatus::Shipped->value);
    }

    public function test_unauthenticated_gets_401(): void
    {
        Event::fake([ShipmentConfirmed::class]);
        $booking = $this->makeUgcBooking();

        $this->postJson("/api/v1/producer/bookings/{$booking->uuid}/confirm-shipment", $this->confirmPayload())
            ->assertUnauthorized();

        Event::assertNotDispatched(ShipmentConfirmed::class);
    }

    // ===================================================================
    // UGC 3.3 — POST /api/v1/face/shipments/{shipment}/confirm-receipt :
    // « Produit reçu » — recu_le figé, tunnel `received`, chrono Unboxing.
    // ===================================================================

    public function test_face_confirms_product_receipt(): void
    {
        $booking = $this->makeUgcBooking();
        $shipment = $this->makeShippedShipment($booking);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/confirm-receipt")
            ->assertOk()
            ->assertJsonPath('message', 'Réception confirmée — le chrono Unboxing démarre')
            ->assertJsonPath('data.tunnel_status', UgcTunnelStatus::Received->value)
            ->assertJsonPath('data.unboxing_deadline_at', fn ($value) => $value !== null);

        $shipment->refresh();
        $this->assertSame(UgcTunnelStatus::Received, $shipment->tunnel_status);
        $this->assertNotNull($shipment->recu_le);
        $this->assertSame(BookingStatus::Accepted, $booking->fresh()->status); // statut owner inchangé (D-3.1.c reconduite)
    }

    public function test_confirm_receipt_sets_unboxing_deadline_from_config(): void
    {
        $this->freezeTime();
        config(['ugc.deliverable_days.unboxing' => 3]);

        $booking = $this->makeUgcBooking();
        $shipment = $this->makeShippedShipment($booking);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/confirm-receipt")
            ->assertOk()
            ->assertJsonPath('data.unboxing_deadline_at', now()->addDays(3)->toIso8601String());
    }

    public function test_confirm_receipt_is_idempotent_and_does_not_reset_chrono(): void
    {
        $booking = $this->makeUgcBooking();
        $shipment = $this->makeShippedShipment($booking);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/confirm-receipt")
            ->assertOk();

        $recuLe = $shipment->fresh()->recu_le;

        $this->travel(1)->hours();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/confirm-receipt")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'ALREADY_RECEIVED');

        // Le 2ᵉ clic ne réinitialise JAMAIS le chrono (D-3.3.d).
        $this->assertTrue($shipment->fresh()->recu_le->equalTo($recuLe));
        $this->assertSame(1, Notification::where('type', 'ugc_product_received')->count());
    }

    public function test_confirm_receipt_notifies_producer(): void
    {
        $booking = $this->makeUgcBooking();
        $shipment = $this->makeShippedShipment($booking);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/confirm-receipt")
            ->assertOk();

        $notification = Notification::where('user_id', $this->producerUser->id)
            ->where('type', 'ugc_product_received')
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame("/producer/bookings/{$booking->uuid}", data_get($notification->data, 'url'));
        $this->assertSame($shipment->uuid, data_get($notification->data, 'shipment_id'));
        $this->assertStringContainsString('Tenue Shade Fit', (string) data_get($notification->data, 'message'));
        $this->assertStringContainsString('Aïcha Bello', (string) data_get($notification->data, 'message'));
    }

    public function test_producer_cannot_confirm_receipt(): void
    {
        $booking = $this->makeUgcBooking();
        $shipment = $this->makeShippedShipment($booking);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/confirm-receipt")
            ->assertForbidden();

        $this->assertNull($shipment->fresh()->recu_le);
    }

    public function test_other_face_cannot_confirm_receipt(): void
    {
        $booking = $this->makeUgcBooking();
        $shipment = $this->makeShippedShipment($booking);

        $otherFace = Face::factory()->create();
        $otherFaceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $otherFace->id,
        ]);

        $this->actingAs($otherFaceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/confirm-receipt")
            ->assertForbidden();

        $this->assertNull($shipment->fresh()->recu_le);
    }

    public function test_confirm_receipt_rejected_when_refund_requested(): void
    {
        $booking = $this->makeUgcBooking();
        $shipment = $this->makeShippedShipment($booking);
        // L'expédition a eu lieu AVANT la demande de refund (état hors-procédure D-2.5.h).
        $booking->update(['commission_refund_requested_at' => now()]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/confirm-receipt");

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->assertStringContainsString('en cours de remboursement', (string) $response->json('error.message'));
        $this->assertNull($shipment->fresh()->recu_le);
    }

    public function test_confirm_receipt_rejected_on_cancelled_booking(): void
    {
        // Garde owner ré-exécutée (D-3.3.f) : un booking annulé après le ship
        // (defer « cancel UGC-blind ») ne démarre pas de chrono.
        $booking = $this->makeUgcBooking();
        $shipment = $this->makeShippedShipment($booking);
        $booking->update(['status' => BookingStatus::CancelledByProducer]);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/confirm-receipt")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->assertSame(UgcTunnelStatus::Shipped, $shipment->fresh()->tunnel_status);
    }

    public function test_confirm_receipt_rejected_when_shipment_not_shipped(): void
    {
        // État défensif fabriqué (review 3.3) : tunnel hors `shipped` avec
        // recu_le null — la garde tunnel_status rejette AVANT les gardes owner.
        $booking = $this->makeUgcBooking();
        $shipment = $this->makeShippedShipment($booking);
        $shipment->update(['tunnel_status' => UgcTunnelStatus::Suspended]);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/confirm-receipt")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->assertNull($shipment->fresh()->recu_le);
    }

    public function test_booking_show_exposes_receipt_and_unboxing_deadline(): void
    {
        $booking = $this->makeUgcBooking();
        $shipment = $this->makeShippedShipment($booking);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/confirm-receipt")
            ->assertOk();

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}")
            ->assertOk()
            ->assertJsonPath('data.shipment.recu_le', fn ($value) => $value !== null)
            ->assertJsonPath('data.shipment.unboxing_deadline_at', fn ($value) => $value !== null);
    }

    public function test_unauthenticated_confirm_receipt_gets_401(): void
    {
        $booking = $this->makeUgcBooking();
        $shipment = $this->makeShippedShipment($booking);

        $this->postJson("/api/v1/face/shipments/{$shipment->uuid}/confirm-receipt")
            ->assertUnauthorized();

        $this->assertNull($shipment->fresh()->recu_le);
    }

    // ===================================================================
    // UGC 4.6 — avis_deadline_at exposé par ShipmentResource (AC2).
    // Échéance Avis dérivée serveur (validated_at Unboxing + ugc.deliverable_days.avis,
    // NFR3), null tant qu'aucun Unboxing n'est validé — calque unboxing_deadline_at.
    // ===================================================================

    public function test_booking_show_exposes_avis_deadline_once_unboxing_validated(): void
    {
        $this->freezeTime();

        $booking = $this->makeUgcBooking();
        $shipment = $this->makeShippedShipment($booking);
        // Unboxing validé → le deal passe avis_pending (chrono Avis ouvert, D-4.3.b).
        $shipment->update([
            'tunnel_status' => UgcTunnelStatus::AvisPending,
            'recu_le' => now()->subDays(5),
        ]);
        $validatedAt = now()->subDays(2);
        $booking->deliverables()->create([
            'kind' => DeliverableKind::Unboxing,
            'validation_status' => DeliverableValidationStatus::Validated,
            'chrono_started_at' => now()->subDays(5),
            'deadline_at' => now()->subDays(5)->copy()->addDays(7),
            'submitted_at' => now()->subDays(3),
            'validated_at' => $validatedAt,
            'video_path' => 'ugc/deliverables/unboxing/seed.mp4',
            'duree_seconds' => 42,
        ]);

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}")
            ->assertOk()
            ->assertJsonPath('data.shipment.tunnel_status', UgcTunnelStatus::AvisPending->value)
            // validated_at + ugc.deliverable_days.avis (14 j) — dérivée serveur (NFR3).
            ->assertJsonPath(
                'data.shipment.avis_deadline_at',
                $validatedAt->copy()->addDays((int) config('ugc.deliverable_days.avis', 14))->toIso8601String(),
            );
    }

    public function test_booking_show_returns_null_avis_deadline_before_unboxing_validated(): void
    {
        // Shipment received (chrono Unboxing en cours) — aucun Unboxing validé
        // ⇒ avis_deadline_at null (calque unboxing_deadline_at null avant réception).
        $booking = $this->makeUgcBooking();
        $shipment = $this->makeShippedShipment($booking);
        $shipment->update([
            'tunnel_status' => UgcTunnelStatus::Received,
            'recu_le' => now(),
        ]);

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}")
            ->assertOk()
            ->assertJsonPath('data.shipment.recu_le', fn ($value) => $value !== null)
            ->assertJsonPath('data.shipment.avis_deadline_at', null);
    }
}
