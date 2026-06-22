<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\MissionType;
use App\Enums\UgcSuspensionReason;
use App\Enums\UgcTunnelStatus;
use App\Enums\WalletCreditMotif;
use App\Events\BookingRefused;
use App\Events\FaceUgcSuspended;
use App\Events\UgcCommissionRefunded;
use App\Listeners\Booking\SendBookingRefusedEmail;
use App\Listeners\Ugc\SendWalletCreditedEmailOnFaceUgcSuspended;
use App\Listeners\Ugc\SendWalletCreditedEmailOnUgcRefunded;
use App\Mail\BookingRefusedMail;
use App\Mail\WalletCreditedMail;
use App\Models\Booking;
use App\Models\EscrowTransaction;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * UGC 7.5 — emails Producteur ADDITIFS sur refus + remboursements UGC.
 * Trou A : BookingRefused → BookingRefusedMail (tous bookings, non-fatal in-transaction).
 * Trou B1 : UgcCommissionRefunded → WalletCreditedMail réutilisé (booking/mission).
 * Trou B2 : FaceUgcSuspended → WalletCreditedMail réutilisé (booking-only, garde escrow Refunded).
 * Mail::fake() OBLIGATOIRE (D-7.1.f : MAIL_MAILER=smtp pointe sur Gmail réel en local).
 */
class ProducerRefusalRefundEmailsTest extends TestCase
{
    use RefreshDatabase;

    private const PRODUCER_EMAIL = 'prod.refusal@test.bj';

    // ===================================================================
    // Fixtures
    // ===================================================================

    /**
     * Booking avec Face + Producteur résolvables (users.id), montants explicites.
     * ProducerFactory/FaceFactory ne créent PAS de User → on attache les Users (piège ugc-3-0).
     */
    private function makeBookingWithProducer(string $typeContenu = 'Publicité', int $montantTotalProducteur = 100000, int $montantFaceRecoit = 0): Booking
    {
        $face = Face::factory()->create(['prenom' => 'Awa', 'nom' => 'Diallo']);
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email' => 'face.refusal@test.bj',
        ]);

        $producer = Producer::factory()->create(['first_name' => 'Jean', 'last_name' => 'Dupont']);
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
            'email' => self::PRODUCER_EMAIL,
        ]);

        return Booking::factory()->create([
            'face_id' => $faceUser->id,         // users.id
            'producer_id' => $producerUser->id, // users.id
            'type_contenu' => $typeContenu,
            'cancellation_reason' => 'Indisponible cette semaine',
            'montant_total_producteur' => $montantTotalProducteur,
            'montant_face_recoit' => $montantFaceRecoit,
        ]);
    }

    /**
     * Mission UGC avec Producteur résolvable (producers.id) + son User attaché
     * (ProducerFactory ne crée PAS de User → sinon $mission->producer->user null, piège ugc-3-0).
     */
    private function makeUgcMissionWithProducer(int $commissionUgc = 5000): Mission
    {
        $producer = Producer::factory()->create(['first_name' => 'Sara', 'last_name' => 'Koné']);
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
            'email' => self::PRODUCER_EMAIL,
        ]);

        return Mission::factory()->create([
            'producer_id' => $producer->id,        // producers.id (PAS users.id)
            'type_mission' => MissionType::Ugc,
            'commission_ugc' => $commissionUgc,
        ]);
    }

    /**
     * Shipment owner=Booking (pas de ShipmentFactory ; uuid auto via HasRouteUuid).
     * owner_type/owner_id ne sont PAS fillable → on les pose via la relation morph owner()->associate().
     */
    private function makeShipmentForBooking(Booking $booking): Shipment
    {
        $shipment = new Shipment([
            'transporteur' => 'Gozem',
            'numero_suivi' => 'GZM-TEST-001',
            'tunnel_status' => UgcTunnelStatus::Received->value,
            'shipped_at' => now(),
            'destinataire_nom' => 'Awa Diallo',
        ]);
        $shipment->owner()->associate($booking);
        $shipment->save();

        return $shipment;
    }

    // ===================================================================
    // Trou A — BookingRefused → BookingRefusedMail (AC1/AC2)
    // ===================================================================

    public function test_refused_cash_booking_queues_producer_email(): void
    {
        Mail::fake();
        $booking = $this->makeBookingWithProducer('Publicité');
        (new SendBookingRefusedEmail)->handle(new BookingRefused($booking));
        Mail::assertQueued(BookingRefusedMail::class, fn (BookingRefusedMail $m): bool => $m->hasTo(self::PRODUCER_EMAIL));
    }

    public function test_refused_ugc_booking_queues_producer_email(): void
    {
        Mail::fake();
        $booking = $this->makeBookingWithProducer('UGC');
        (new SendBookingRefusedEmail)->handle(new BookingRefused($booking));
        Mail::assertQueued(BookingRefusedMail::class, fn (BookingRefusedMail $m): bool => $m->hasTo(self::PRODUCER_EMAIL));
    }

    public function test_refused_booking_empty_producer_email_queues_nothing(): void
    {
        Mail::fake();
        $booking = $this->makeBookingWithProducer('Publicité');
        $booking->producer->update(['email' => '']); // Producteur User sans email résolvable
        (new SendBookingRefusedEmail)->handle(new BookingRefused($booking));
        Mail::assertNothingQueued();
    }

    /**
     * Garantie NON-FATALE CRITIQUE (AC2/D-7.5.a) : BookingRefused est dispatché DANS la
     * transaction de BookingService::refuse → un throw rollbackerait le refus. On force
     * Mail::to() à throw et on prouve que le listener AVALE l'exception (try/catch englobant
     * SANS re-throw) : aucune exception ne se propage + le warning de fallback est loggé.
     */
    public function test_refused_booking_listener_swallows_failure_without_rethrow(): void
    {
        Log::spy();
        $booking = $this->makeBookingWithProducer('UGC');
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('mail down'));

        // Atteindre la ligne d'assertion prouve qu'aucune exception ne s'est propagée
        // (un re-throw ferait remonter l'exception et échouer/rollback le test).
        (new SendBookingRefusedEmail)->handle(new BookingRefused($booking));

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message): bool => $message === 'BookingRefusedMail queue failed')
            ->once();
    }

    // ===================================================================
    // Trou B1 — UgcCommissionRefunded → WalletCreditedMail (AC3/AC4)
    // ===================================================================

    public function test_ugc_refunded_booking_queues_wallet_email(): void
    {
        Mail::fake();
        $booking = $this->makeBookingWithProducer('UGC', 100000);
        (new SendWalletCreditedEmailOnUgcRefunded)->handle(new UgcCommissionRefunded($booking));
        Mail::assertQueued(
            WalletCreditedMail::class,
            fn (WalletCreditedMail $m): bool => $m->hasTo(self::PRODUCER_EMAIL)
                && $m->amount === 100000
                && $m->motif === WalletCreditMotif::UgcSettlementRefund,
        );
    }

    public function test_ugc_refunded_mission_queues_wallet_email(): void
    {
        Mail::fake();
        $mission = $this->makeUgcMissionWithProducer(5000);
        (new SendWalletCreditedEmailOnUgcRefunded)->handle(new UgcCommissionRefunded($mission));
        Mail::assertQueued(
            WalletCreditedMail::class,
            fn (WalletCreditedMail $m): bool => $m->hasTo(self::PRODUCER_EMAIL)
                && $m->amount === 5000
                && $m->motif === WalletCreditMotif::UgcCommissionRefund,
        );
    }

    // ===================================================================
    // Trou B2 — FaceUgcSuspended → WalletCreditedMail (AC5/AC6)
    // ===================================================================

    public function test_face_suspension_on_hybrid_booking_queues_wallet_email(): void
    {
        Mail::fake();
        $booking = $this->makeBookingWithProducer('UGC', 100000, 85000); // montant_face_recoit = net Face
        EscrowTransaction::factory()->refunded()->create(['booking_id' => $booking->id]);
        $shipment = $this->makeShipmentForBooking($booking->fresh());
        (new SendWalletCreditedEmailOnFaceUgcSuspended)->handle(
            new FaceUgcSuspended($shipment, UgcSuspensionReason::UnboxingDeadlineMissed, true)
        );
        Mail::assertQueued(
            WalletCreditedMail::class,
            fn (WalletCreditedMail $m): bool => $m->hasTo(self::PRODUCER_EMAIL)
                && $m->amount === 85000
                && $m->motif === WalletCreditMotif::UgcSuspensionRefund,
        );
    }

    public function test_face_suspension_product_only_queues_nothing(): void
    {
        Mail::fake();
        $booking = $this->makeBookingWithProducer('UGC', 100000, 0); // produit-seul : aucun escrow
        $shipment = $this->makeShipmentForBooking($booking);
        (new SendWalletCreditedEmailOnFaceUgcSuspended)->handle(
            new FaceUgcSuspended($shipment, UgcSuspensionReason::UnboxingDeadlineMissed, true)
        );
        Mail::assertNothingQueued();
    }

    public function test_face_suspension_escrow_not_refunded_queues_nothing(): void
    {
        Mail::fake();
        $booking = $this->makeBookingWithProducer('UGC', 100000, 85000);
        EscrowTransaction::factory()->create(['booking_id' => $booking->id]); // status 'locked' par défaut, pas 'refunded'
        $shipment = $this->makeShipmentForBooking($booking->fresh());
        (new SendWalletCreditedEmailOnFaceUgcSuspended)->handle(
            new FaceUgcSuspended($shipment, UgcSuspensionReason::UnboxingDeadlineMissed, true)
        );
        Mail::assertNothingQueued();
    }

    // ===================================================================
    // AC9 — smoke render BookingRefusedMail
    // ===================================================================

    public function test_booking_refused_mailable_renders_its_blade_view(): void
    {
        $mailable = new BookingRefusedMail($this->makeBookingWithProducer('UGC'));

        $html = $mailable->render();

        $this->assertStringContainsString('WEACT', $html); // layout de base rendu
        $this->assertStringContainsString('refusé', $html);
        $this->assertStringContainsString('/producer/bookings/', $html);
    }
}
