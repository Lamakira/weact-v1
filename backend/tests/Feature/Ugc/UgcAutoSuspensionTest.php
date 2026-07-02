<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc;

use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Enums\DeliverableKind;
use App\Enums\DeliverableValidationStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionStatus;
use App\Enums\UgcTunnelStatus;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\EscrowTransaction;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\FinancialEvent;
use App\Models\Mission;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\Shipment;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\EscrowService;
use App\Services\FaceEntitlementService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * UGC 5.1 — suspension automatique sur dépassement de chrono (`ugc:process-deadlines`).
 * À progress >= 1.0 sur un shipment Received/AvisPending (aucun upload validé dû par
 * l'état du tunnel), la commande SUSPEND la Face au lieu d'escalader : crée
 * `ugc_suspensions`, rembourse l'escrow au Producteur (booking hybride seulement,
 * D-5.0.a/c), gèle l'accès UGC (D-5.0.d), passe le shipment en Suspended et notifie
 * les deux parties. Couvre booking (hybride + produit-seul) et candidature (sans
 * escrow), l'idempotence et le filtre d'états. Temps figé → progress déterministe.
 */
class UgcAutoSuspensionTest extends TestCase
{
    use RefreshDatabase;

    private Producer $producer;

    private User $producerUser;

    private Face $face;

    private User $faceUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeTime();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
        $this->face = Face::factory()->create([
            'prenom' => 'Aïcha',
            'nom' => 'Bello',
        ]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    // ===================================================================
    // Fixtures
    // ===================================================================

    /**
     * Booking UGC dépassé (Unboxing). hybride → escrow Locked = montant_face_recoit
     * (exemple Élite kickoff : cash 15 000, net Face 14 250, total Producteur 16 500).
     *
     * @return array{0: Booking, 1: Shipment}
     */
    private function makeOverdueUnboxingBooking(string $compensation = 'hybrid', int $recuDaysAgo = 8): array
    {
        $booking = Booking::create([
            'face_id' => $this->faceUser->id,          // users.id
            'producer_id' => $this->producerUser->id,  // users.id
            'status' => BookingStatus::Accepted,
            'accepted_at' => now(),
            'type_contenu' => 'UGC',
            'type_compensation' => $compensation,
            'nom_produit' => 'Tenue Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'commission_ugc' => 2250,
            'commission_paid_at' => now()->subDays(10),
            'tarif_base' => 0,
            'montant_remuneration' => $compensation === 'hybrid' ? 15000 : null,
            'montant_face_recoit' => $compensation === 'hybrid' ? 14250 : 0,
            'montant_total_producteur' => $compensation === 'hybrid' ? 16500 : 2500,
        ]);

        $shipment = $booking->shipment()->create([
            'transporteur' => 'Gozem',
            'numero_suivi' => 'GZM-COT-882194',
            'tunnel_status' => UgcTunnelStatus::Received,
            'shipped_at' => now()->subDays(10),
            'recu_le' => now()->subDays($recuDaysAgo),  // 8 > 7j span → progress >= 1.0
            'destinataire_nom' => 'Aïcha Bello',
            'destinataire_ville' => 'Cotonou',
            'destinataire_pays' => 'Bénin',
        ]);

        if ($compensation === 'hybrid') {
            EscrowTransaction::create([
                'booking_id' => $booking->id,
                'amount' => $booking->montant_face_recoit,   // 14250
                'status' => EscrowStatus::Locked->value,
                'locked_at' => now()->subDays(10),
            ]);
        }

        return [$booking, $shipment];
    }

    /**
     * Variante Avis dépassé : Unboxing validé il y a 15 j (span Avis 14 j → progress >= 1.0).
     *
     * @return array{0: Booking, 1: Shipment}
     */
    private function makeOverdueAvisBooking(): array
    {
        [$booking, $shipment] = $this->makeOverdueUnboxingBooking('hybrid', 1); // recu_le récent : seul le chrono Avis compte
        $shipment->update(['tunnel_status' => UgcTunnelStatus::AvisPending]);
        $booking->deliverables()->create([
            'kind' => DeliverableKind::Unboxing,
            'validation_status' => DeliverableValidationStatus::Validated,
            'chrono_started_at' => $shipment->recu_le,
            'deadline_at' => $shipment->recu_le->copy()->addDays(7),
            'submitted_at' => $shipment->recu_le,
            'validated_at' => now()->subDays(15),  // 15 > 14j span Avis → progress >= 1.0
            'video_path' => 'ugc/deliverables/unboxing/seed.mp4',
            'duree_seconds' => 42,
        ]);

        return [$booking, $shipment];
    }

    /**
     * Candidature UGC dépassée (Unboxing) — PAS d'escrow (mission). Calque makeReceivedCandidature (4.5).
     *
     * @return array{0: Mission, 1: Candidature, 2: Shipment}
     */
    private function makeOverdueUnboxingCandidature(int $recuDaysAgo = 8): array
    {
        /** @var Mission $mission */
        $mission = $this->producer->missions()->create([
            'titre' => 'Appel UGC — Unboxing sneakers',
            'description' => 'Brief',
            'date_tournage' => now()->addMonth(),
            'profil_recherche' => 'Créatrices lifestyle',
            'budget' => 0,
            'date_limite_candidature' => now()->addWeeks(2),
            'nombre_faces_voulu' => 2,
            'type_mission' => 'ugc',
            'genre_voulu' => 'tous',
            'lieu' => 'Cotonou',
            'duree' => 'Livrables vidéo',
            'status' => MissionStatus::Published,
            'commission_paid_at' => now(),
            'type_compensation' => 'product',
            'nom_produit' => 'Sneakers Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'commission_ugc' => 2500,
        ]);

        $candidature = Candidature::create([
            'face_id' => $this->face->id,   // faces.id (PAS users.id)
            'mission_id' => $mission->id,
            'status' => CandidatureStatus::Confirmed,
        ]);

        $shipment = $candidature->shipment()->create([
            'transporteur' => 'Gozem',
            'numero_suivi' => 'GZM-COT-991337',
            'tunnel_status' => UgcTunnelStatus::Received,
            'shipped_at' => now()->subDays(10),
            'recu_le' => now()->subDays($recuDaysAgo),
            'destinataire_nom' => 'Aïcha Bello',
            'destinataire_ville' => 'Cotonou',
            'destinataire_pays' => 'Bénin',
        ]);

        return [$mission, $candidature, $shipment];
    }

    private function deadlineNotificationCount(): int
    {
        return Notification::where('type', 'ugc_deliverable_deadline_approaching')->count();
    }

    // ===================================================================
    // AC1 + AC2 — suspension + refund escrow (booking hybride)
    // ===================================================================

    public function test_suspends_face_and_refunds_escrow_on_overdue_unboxing_booking_hybrid(): void
    {
        [$booking, $shipment] = $this->makeOverdueUnboxingBooking('hybrid', 8);

        $this->artisan('ugc:process-deadlines')->assertSuccessful();

        $this->assertDatabaseHas('ugc_suspensions', [
            'face_id' => $this->face->id,
            'reason' => 'unboxing_deadline_missed',
            'appeal_status' => 'none',
            'reactivated_at' => null,
        ]);
        $this->assertDatabaseHas('escrow_transactions', [
            'booking_id' => $booking->id,
            'status' => 'refunded',
        ]);
        // refunded_at posé à la suspension (AC2).
        $this->assertNotNull(
            EscrowTransaction::where('booking_id', $booking->id)->value('refunded_at'),
        );
        // Crédit Producteur = montant_face_recoit (14 250), PAS montant_total_producteur (D-5.0.c).
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $this->producerUser->id,
            'amount' => 14250,
            'type' => 'credit',
        ]);
        $this->assertDatabaseHas('financial_events', [
            'booking_id' => $booking->id,
            'type' => 'refund',
            'amount' => 14250,
        ]);
        // metadata.kind = ugc_suspension : discriminant du canal de refund suspension (AC2),
        // distinct de RH.2 (settleLockedBooking) / cancel.
        $refundEvent = FinancialEvent::where('booking_id', $booking->id)
            ->where('type', 'refund')
            ->firstOrFail();
        $this->assertSame('ugc_suspension', $refundEvent->metadata['kind']);
        $this->assertSame(UgcTunnelStatus::Suspended, $shipment->fresh()->tunnel_status);
        // Le tick suspend AU LIEU d'escalader : aucune notif d'échéance pour ce shipment.
        $this->assertSame(0, $this->deadlineNotificationCount());
    }

    // ===================================================================
    // AC3 — booking produit-seul : suspension sans mouvement d'argent
    // ===================================================================

    public function test_product_only_booking_suspends_without_money_movement(): void
    {
        [$booking, $shipment] = $this->makeOverdueUnboxingBooking('product', 8);

        $this->artisan('ugc:process-deadlines')->assertSuccessful();

        $this->assertDatabaseHas('ugc_suspensions', ['face_id' => $this->face->id]);
        $this->assertSame(UgcTunnelStatus::Suspended, $shipment->fresh()->tunnel_status);
        $this->assertDatabaseMissing('wallet_transactions', ['booking_id' => $booking->id]);
        $this->assertDatabaseMissing('financial_events', ['booking_id' => $booking->id, 'type' => 'refund']);
        $this->assertDatabaseCount('escrow_transactions', 0);
    }

    // ===================================================================
    // AC4 — suspension sur Avis dépassé (booking)
    // ===================================================================

    public function test_suspends_on_overdue_avis_booking(): void
    {
        [, $shipment] = $this->makeOverdueAvisBooking();

        $this->artisan('ugc:process-deadlines')->assertSuccessful();

        $this->assertDatabaseHas('ugc_suspensions', [
            'face_id' => $this->face->id,
            'reason' => 'avis_deadline_missed',
            'reactivated_at' => null,
        ]);
        $this->assertSame(UgcTunnelStatus::Suspended, $shipment->fresh()->tunnel_status);
    }

    // ===================================================================
    // AC5 — suspension d'une Face sur mission (candidature), SANS refund escrow
    // ===================================================================

    public function test_suspends_candidature_face_without_escrow_refund(): void
    {
        [, , $shipment] = $this->makeOverdueUnboxingCandidature();

        $this->artisan('ugc:process-deadlines')->assertSuccessful();

        $this->assertDatabaseHas('ugc_suspensions', ['face_id' => $this->face->id]);
        $this->assertSame(UgcTunnelStatus::Suspended, $shipment->fresh()->tunnel_status);
        $this->assertDatabaseCount('escrow_transactions', 0);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->producerUser->id,
            'type' => 'ugc_face_suspended',
        ]);
    }

    // ===================================================================
    // AC6 — gel premium (D-5.0.d)
    // ===================================================================

    public function test_premium_frozen_after_suspension(): void
    {
        FaceSubscription::factory()->starter()->active()->create(['face_id' => $this->face->id]);
        $entitlement = app(FaceEntitlementService::class);
        $this->assertTrue($entitlement->canAccessUgc($this->face)); // AVANT : accès UGC ouvert

        $this->makeOverdueUnboxingBooking('hybrid', 8);
        $this->artisan('ugc:process-deadlines')->assertSuccessful();

        // Instance fraîche : isUgcSuspended n'est pas memoize, mais on évite tout
        // faux-positif de cache capabilities() lié au WeakMap par objet.
        $face = $this->face->fresh();
        $this->assertFalse($entitlement->canAccessUgc($face));
        $this->assertTrue($entitlement->isUgcSuspended($face));
        // Abonnement INCHANGÉ (statut + expires_at), login préservé (is_active).
        $this->assertDatabaseHas('face_subscriptions', [
            'face_id' => $this->face->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $this->faceUser->id,
            'is_active' => true,
        ]);
    }

    // ===================================================================
    // AC7 — déclenchement uniquement à progress >= 1.0, états actifs seulement
    // ===================================================================

    public function test_below_threshold_is_escalated_not_suspended(): void
    {
        // recu 6 j / span 7 j ≈ 0.857 < 1.0 → palier rouge escaladé, PAS de suspension.
        [$booking] = $this->makeOverdueUnboxingBooking('hybrid', 6);

        $this->artisan('ugc:process-deadlines')->assertSuccessful();

        $this->assertDatabaseCount('ugc_suspensions', 0);
        $this->assertDatabaseHas('notifications', ['type' => 'ugc_deliverable_deadline_approaching']);
        // Escrow non touché : le deal court toujours.
        $this->assertDatabaseHas('escrow_transactions', [
            'booking_id' => $booking->id,
            'status' => 'locked',
        ]);
    }

    public function test_in_review_states_not_suspended(): void
    {
        [$booking, $shipment] = $this->makeOverdueUnboxingBooking('hybrid', 8);

        // Balle côté Producteur : hors du filtre [Received, AvisPending], jamais suspendu.
        $shipment->update(['tunnel_status' => UgcTunnelStatus::UnboxingInReview]);
        $this->artisan('ugc:process-deadlines')->assertSuccessful();
        $this->assertDatabaseCount('ugc_suspensions', 0);
        $this->assertDatabaseHas('escrow_transactions', ['booking_id' => $booking->id, 'status' => 'locked']);

        $shipment->update(['tunnel_status' => UgcTunnelStatus::AvisInReview]);
        $this->artisan('ugc:process-deadlines')->assertSuccessful();
        $this->assertDatabaseCount('ugc_suspensions', 0);
        $this->assertDatabaseHas('escrow_transactions', ['booking_id' => $booking->id, 'status' => 'locked']);
    }

    // ===================================================================
    // AC8 — idempotence
    // ===================================================================

    public function test_idempotent_double_run(): void
    {
        [$booking] = $this->makeOverdueUnboxingBooking('hybrid', 8);

        $this->artisan('ugc:process-deadlines')->assertSuccessful();
        $this->artisan('ugc:process-deadlines')->assertSuccessful();

        $this->assertDatabaseCount('ugc_suspensions', 1);
        $this->assertSame(
            1,
            WalletTransaction::where('user_id', $this->producerUser->id)->where('type', 'credit')->count(),
        );
        $this->assertSame(
            1,
            FinancialEvent::where('booking_id', $booking->id)->where('type', 'refund')->count(),
        );
        $this->assertDatabaseHas('escrow_transactions', ['booking_id' => $booking->id, 'status' => 'refunded']);
    }

    /**
     * Garde d'idempotence escrow (niveau 3) en isolation : le double-run cron sort le
     * shipment du filtre [Received, AvisPending] dès le 1er tick, donc
     * `refundUgcSuspensionToProducer` n'est jamais ré-entré par ce chemin. On l'exerce
     * directement : 1er appel rembourse (true), 2e appel no-op (garde [Released, Refunded, Pending]).
     */
    public function test_escrow_refund_guard_is_idempotent_on_direct_double_call(): void
    {
        [$booking] = $this->makeOverdueUnboxingBooking('hybrid', 8);
        $escrow = app(EscrowService::class);
        $wallet = app(WalletService::class);

        // refundUgcSuspensionToProducer re-query l'escrow à chaque appel (escrowTransaction()
        // est une méthode → builder neuf), donc passer la même instance $booking est correct.
        $first = DB::transaction(fn () => $escrow->refundUgcSuspensionToProducer($booking, $wallet));
        $second = DB::transaction(fn () => $escrow->refundUgcSuspensionToProducer($booking, $wallet));

        $this->assertTrue($first);   // 1er appel : remboursé MAINTENANT
        $this->assertFalse($second); // 2e appel : no-op (escrow déjà Refunded)
        $this->assertSame(
            1,
            WalletTransaction::where('user_id', $this->producerUser->id)->where('type', 'credit')->count(),
        );
        $this->assertSame(
            1,
            FinancialEvent::where('booking_id', $booking->id)->where('type', 'refund')->count(),
        );
        $this->assertDatabaseHas('escrow_transactions', ['booking_id' => $booking->id, 'status' => 'refunded']);
    }

    public function test_two_overdue_deals_same_face_single_suspension_two_shipments_suspended(): void
    {
        [, $shipment1] = $this->makeOverdueUnboxingBooking('hybrid', 8);
        [, $shipment2] = $this->makeOverdueUnboxingBooking('hybrid', 8);

        $this->artisan('ugc:process-deadlines')->assertSuccessful();

        // Une seule suspension ACTIVE par Face, mais les deux deals dénoués.
        $this->assertDatabaseCount('ugc_suspensions', 1);
        $this->assertSame(UgcTunnelStatus::Suspended, $shipment1->fresh()->tunnel_status);
        $this->assertSame(UgcTunnelStatus::Suspended, $shipment2->fresh()->tunnel_status);
        $this->assertSame(2, EscrowTransaction::where('status', 'refunded')->count()); // refund per-booking
        // Face notifiée une seule fois (garde faceNewlySuspended), Producteur deux fois.
        $this->assertSame(
            1,
            Notification::where('user_id', $this->faceUser->id)->where('type', 'ugc_account_suspended')->count(),
        );
        $this->assertSame(
            2,
            Notification::where('user_id', $this->producerUser->id)->where('type', 'ugc_face_suspended')->count(),
        );
    }

    // ===================================================================
    // AC9 — event + notifications
    // ===================================================================

    public function test_producer_and_face_notified_on_suspension(): void
    {
        $this->makeOverdueUnboxingBooking('hybrid', 8);

        $this->artisan('ugc:process-deadlines')->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->producerUser->id,
            'type' => 'ugc_face_suspended',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->faceUser->id,
            'type' => 'ugc_account_suspended',
        ]);
    }
}
