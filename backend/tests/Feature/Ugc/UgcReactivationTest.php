<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc;

use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Enums\DeliverableKind;
use App\Enums\DeliverableValidationStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionStatus;
use App\Enums\UgcSuspensionAppealStatus;
use App\Enums\UgcSuspensionReason;
use App\Enums\UgcTunnelStatus;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Deliverable;
use App\Models\EscrowTransaction;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\Shipment;
use App\Models\UgcSuspension;
use App\Models\User;
use App\Services\FaceEntitlementService;
use App\Services\Ugc\UgcDeliverableService;
use App\Services\Ugc\UgcSuspensionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UGC 5.3 — réactivation « terminer en retard » + correctif fausse notif crédit.
 * Couvre la réouverture du tunnel (resume, sans dégeler — Q1), la garde J+30 + état,
 * l'idempotence anti-re-suspension (Option Z) du cron, le dégel à la complétion
 * (Avis validé, booking ET candidature) via le listener ReactivateFaceOnLateUgcCompletion,
 * et la suppression de la notif « X XOF crédités » quand l'escrow est déjà Refunded
 * (D-5.0.a). Temps figé → fenêtre J+30 déterministe. La Face a une souscription active
 * (Starter) → canAccessUgc reflète UNIQUEMENT l'état de suspension.
 */
class UgcReactivationTest extends TestCase
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

        // Souscription active : ugcAccess=true → canAccessUgc ne dépend QUE de la suspension.
        FaceSubscription::factory()->starter()->active()->create(['face_id' => $this->face->id]);
    }

    // ===================================================================
    // Fixtures (helpers makeOverdue* COPIÉS de 5.1/5.2 — privés, non hérités)
    // ===================================================================

    /**
     * Booking UGC dépassé (Unboxing). hybride → escrow Locked = montant_face_recoit.
     * Variante ARRAY de 5.1 (UgcAutoSuspensionTest:85).
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
     * COPIE de 5.1 (UgcAutoSuspensionTest:133).
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
     * Candidature UGC (mission) au shipment AvisPending dépassé : Unboxing validé J-15
     * (span Avis 14 j). Mission `commission_paid_at = now()` (passe la garde candidature).
     * COPIE de 5.2 (UgcSuspensionStatusTest:109) — retourne [$mission, $shipment] (2 éléments).
     *
     * @return array{0: Mission, 1: Shipment}
     */
    private function makeOverdueAvisCandidature(): array
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
            'tunnel_status' => UgcTunnelStatus::AvisPending,
            'shipped_at' => now()->subDays(20),
            'recu_le' => now()->subDays(18),
            'destinataire_nom' => 'Aïcha Bello',
            'destinataire_ville' => 'Cotonou',
            'destinataire_pays' => 'Bénin',
        ]);

        // Unboxing validé il y a 15 j → avisDeadlineFor = J-1 (dépassé).
        $candidature->deliverables()->create([
            'kind' => DeliverableKind::Unboxing,
            'validation_status' => DeliverableValidationStatus::Validated,
            'chrono_started_at' => now()->subDays(18),
            'deadline_at' => now()->subDays(11),
            'submitted_at' => now()->subDays(17),
            'validated_at' => now()->subDays(15),
            'video_path' => 'ugc/deliverables/unboxing/seed.mp4',
            'duree_seconds' => 42,
        ]);

        return [$mission, $shipment];
    }

    /**
     * État post-suspension d'un booking hybride : ligne ugc_suspensions active +
     * tunnel Suspended + escrow Refunded (D-5.0.a — pas de financial_event, simple flip).
     *
     * @return array{0: Booking, 1: Shipment, 2: UgcSuspension}
     */
    private function makeSuspendedBooking(UgcSuspensionReason $reason): array
    {
        [$booking, $shipment] = $reason === UgcSuspensionReason::UnboxingDeadlineMissed
            ? $this->makeOverdueUnboxingBooking()
            : $this->makeOverdueAvisBooking();

        EscrowTransaction::where('booking_id', $booking->id)
            ->update(['status' => EscrowStatus::Refunded->value, 'refunded_at' => now()]);
        $shipment->update(['tunnel_status' => UgcTunnelStatus::Suspended]);

        $suspension = UgcSuspension::create([
            'face_id' => $this->face->id,       // faces.id
            'shipment_id' => $shipment->id,
            'reason' => $reason,
            'appeal_status' => UgcSuspensionAppealStatus::None,
            'suspended_at' => now(),
        ]);

        return [$booking, $shipment, $suspension];
    }

    /**
     * État post-suspension d'une CANDIDATURE UGC (AC6) — pas d'escrow per-engagement.
     *
     * @return array{0: Candidature, 1: Shipment, 2: UgcSuspension}
     */
    private function makeSuspendedCandidature(): array
    {
        [, $shipment] = $this->makeOverdueAvisCandidature(); // [$mission, $shipment]
        /** @var Candidature $candidature */
        $candidature = $shipment->owner; // morphTo Candidature

        $shipment->update(['tunnel_status' => UgcTunnelStatus::Suspended]);

        $suspension = UgcSuspension::create([
            'face_id' => $this->face->id,                       // faces.id
            'shipment_id' => $shipment->id,
            'reason' => UgcSuspensionReason::AvisDeadlineMissed,
            'appeal_status' => UgcSuspensionAppealStatus::None,
            'suspended_at' => now(),
        ]);

        return [$candidature, $shipment, $suspension];
    }

    /** Pose un Avis in_review + tunnel AvisInReview (prêt pour validate()), sans ffmpeg. */
    private function uploadAvisInReview(Booking|Candidature $owner, Shipment $shipment): Deliverable
    {
        /** @var Deliverable $avis */
        $avis = $owner->deliverables()->create([
            'kind' => DeliverableKind::Avis,
            'validation_status' => DeliverableValidationStatus::InReview,
            'chrono_started_at' => now()->subDay(),
            'deadline_at' => now()->addDays(13),
            'submitted_at' => now(),
            'video_path' => 'ugc/deliverables/avis/seed.mp4',
            'thumbnail_path' => 'ugc/deliverables/avis/thumbnails/seed.jpg',
            'duree_seconds' => 88,
        ]);
        $shipment->update(['tunnel_status' => UgcTunnelStatus::AvisInReview]);

        return $avis;
    }

    private function entitlement(): FaceEntitlementService
    {
        return app(FaceEntitlementService::class);
    }

    // ===================================================================
    // AC1 — réouverture du tunnel « terminer en retard »
    // ===================================================================

    public function test_resume_reopens_tunnel_to_received_for_unboxing_reason(): void
    {
        [, $shipment, $s] = $this->makeSuspendedBooking(UgcSuspensionReason::UnboxingDeadlineMissed);

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/ugc/suspension/resume')
            ->assertOk();

        $this->assertSame(UgcTunnelStatus::Received, $shipment->fresh()->tunnel_status);
        // Pas de dégel à la réouverture (Q1) : la suspension reste active.
        $this->assertNull(UgcSuspension::find($s->id)->reactivated_at);
        $this->assertTrue($this->entitlement()->isUgcSuspended($this->face->fresh()));
        $this->assertFalse($this->entitlement()->canAccessUgc($this->face->fresh()));
    }

    public function test_resume_reopens_to_avis_pending_for_avis_reason(): void
    {
        [, $shipment] = $this->makeSuspendedBooking(UgcSuspensionReason::AvisDeadlineMissed);

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/ugc/suspension/resume')
            ->assertOk();

        $this->assertSame(UgcTunnelStatus::AvisPending, $shipment->fresh()->tunnel_status);
    }

    // ===================================================================
    // AC2 — garde fenêtre J+30 + état
    // ===================================================================

    public function test_resume_refused_past_j30_window(): void
    {
        [, $shipment, $s] = $this->makeSuspendedBooking(UgcSuspensionReason::UnboxingDeadlineMissed);
        $s->update(['suspended_at' => now()->subDays(31)]); // J+30 dépassé

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/ugc/suspension/resume')
            ->assertStatus(422);

        $this->assertSame(UgcTunnelStatus::Suspended, $shipment->fresh()->tunnel_status);
    }

    public function test_resume_refused_when_no_active_suspension(): void
    {
        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/ugc/suspension/resume')
            ->assertStatus(422);
    }

    public function test_resume_refused_when_already_resumed(): void
    {
        [, $shipment] = $this->makeSuspendedBooking(UgcSuspensionReason::UnboxingDeadlineMissed);

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/ugc/suspension/resume')
            ->assertOk(); // tunnel → Received

        // 2ᵉ resume : le deal n'est plus Suspended → 422.
        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/ugc/suspension/resume')
            ->assertStatus(422);

        $this->assertSame(UgcTunnelStatus::Received, $shipment->fresh()->tunnel_status);
    }

    // ===================================================================
    // AC3 — le cron ne re-suspend PAS un deal rouvert (Option Z)
    // ===================================================================

    public function test_cron_does_not_resuspend_resumed_deal(): void
    {
        [, $shipment] = $this->makeSuspendedBooking(UgcSuspensionReason::UnboxingDeadlineMissed);

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/ugc/suspension/resume')
            ->assertOk(); // tunnel Received, deadline passée

        $this->artisan('ugc:process-deadlines')->assertSuccessful();

        // Pas de 2ᵉ ligne, pas de re-flip Suspended, aucun (re-)refund au Producteur.
        $this->assertDatabaseCount('ugc_suspensions', 1);
        $this->assertSame(UgcTunnelStatus::Received, $shipment->fresh()->tunnel_status);
        $this->assertDatabaseMissing('financial_events', ['type' => 'refund']);
        $this->assertDatabaseMissing('wallet_transactions', [
            'user_id' => $this->producerUser->id,
            'type' => 'credit',
        ]);
    }

    public function test_cron_does_not_resuspend_after_non_completion_reactivation(): void
    {
        // [Review patch #1] Dégel hors-complétion (admin direct AC9 / appel accepté AC8)
        // d'un deal déjà repris : sans clôture du shipment, le cron le re-suspendrait au
        // tick suivant (suspension active devenue null → garde Option Z inopérante).
        [, $shipment, $s] = $this->makeSuspendedBooking(UgcSuspensionReason::AvisDeadlineMissed);

        // La Face reprend le deal (tunnel AvisPending, deadline déjà passée)…
        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/ugc/suspension/resume')
            ->assertOk();
        $this->assertSame(UgcTunnelStatus::AvisPending, $shipment->fresh()->tunnel_status);

        // …puis un admin la réactive directement, SANS complétion (pas d'Avis validé).
        app(UgcSuspensionService::class)->reactivate(UgcSuspension::find($s->id));

        // Le dégel clôture le deal rouvert non complété : shipment repassé Suspended.
        $this->assertNotNull(UgcSuspension::find($s->id)->reactivated_at);
        $this->assertSame(UgcTunnelStatus::Suspended, $shipment->fresh()->tunnel_status);
        $this->assertTrue($this->entitlement()->canAccessUgc($this->face->fresh()));

        // Tick du cron : deal Suspended (hors filtre [Received, AvisPending]) → pas de
        // re-suspension, pas de 2ᵉ ligne, accès UGC préservé.
        $this->artisan('ugc:process-deadlines')->assertSuccessful();

        $this->assertDatabaseCount('ugc_suspensions', 1);
        $this->assertTrue($this->entitlement()->canAccessUgc($this->face->fresh()));
        $this->assertSame(UgcTunnelStatus::Suspended, $shipment->fresh()->tunnel_status);
    }

    // ===================================================================
    // AC4 — dégel à la complétion (booking, Avis validé) sans double paiement
    // ===================================================================

    public function test_completion_lifts_suspension_for_booking(): void
    {
        [$booking, $shipment, $s] = $this->makeSuspendedBooking(UgcSuspensionReason::AvisDeadlineMissed);

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/ugc/suspension/resume')
            ->assertOk(); // tunnel AvisPending

        $avis = $this->uploadAvisInReview($booking, $shipment->fresh());
        app(UgcDeliverableService::class)->validate($avis);

        // Dégel via le listener sur DeliverableValidated (kind=Avis).
        $this->assertNotNull(UgcSuspension::find($s->id)->reactivated_at);
        $this->assertTrue($this->entitlement()->canAccessUgc($this->face->fresh()));
        // La complétion procède, mais SANS double paiement (escrow reste Refunded).
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'completed']);
        $this->assertDatabaseHas('escrow_transactions', [
            'booking_id' => $booking->id,
            'status' => 'refunded',
        ]);
        $this->assertDatabaseMissing('wallet_transactions', [
            'user_id' => $this->faceUser->id,
            'type' => 'credit',
        ]);
    }

    // ===================================================================
    // AC5 — suppression de la fausse notif crédit (escrow Refunded)
    // ===================================================================

    public function test_completion_suppresses_false_credit_notification(): void
    {
        [$booking, $shipment] = $this->makeSuspendedBooking(UgcSuspensionReason::AvisDeadlineMissed);

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/ugc/suspension/resume')
            ->assertOk();

        $avis = $this->uploadAvisInReview($booking, $shipment->fresh());
        app(UgcDeliverableService::class)->validate($avis);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->faceUser->id,
            'type' => 'booking_wallet_credited',
        ]);
        // La notif Producteur reste créée (inchangée).
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->producerUser->id,
            'type' => 'booking_completed',
        ]);
    }

    public function test_normal_hybrid_completion_still_notifies_credit(): void
    {
        // Booking hybride NON suspendu (escrow Locked) → complétion normale → crédit + notif.
        [$booking, $shipment] = $this->makeOverdueAvisBooking();

        $avis = $this->uploadAvisInReview($booking, $shipment->fresh());
        app(UgcDeliverableService::class)->validate($avis);

        $this->assertDatabaseHas('escrow_transactions', [
            'booking_id' => $booking->id,
            'status' => 'released',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->faceUser->id,
            'type' => 'booking_wallet_credited',
        ]);
    }

    // ===================================================================
    // AC6 — dégel à la complétion (candidature) + Unboxing ne dégèle pas
    // ===================================================================

    public function test_completion_lifts_suspension_for_candidature(): void
    {
        [$candidature, $shipment, $s] = $this->makeSuspendedCandidature();

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/ugc/suspension/resume')
            ->assertOk(); // tunnel AvisPending

        $avis = $this->uploadAvisInReview($candidature, $shipment->fresh());
        app(UgcDeliverableService::class)->validate($avis);

        $this->assertNotNull(UgcSuspension::find($s->id)->reactivated_at);
        $this->assertTrue($this->entitlement()->canAccessUgc($this->face->fresh()));
        // Pas d'escrow per-engagement sur une mission : aucun mouvement d'argent.
        $this->assertDatabaseCount('escrow_transactions', 0);
    }

    public function test_unboxing_validation_does_not_lift_suspension(): void
    {
        [$booking, $shipment, $s] = $this->makeSuspendedBooking(UgcSuspensionReason::UnboxingDeadlineMissed);

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/ugc/suspension/resume')
            ->assertOk(); // tunnel Received

        // Unboxing in_review → validate (kind=Unboxing → tunnel AvisPending, deal NON complété).
        /** @var Deliverable $unboxing */
        $unboxing = $booking->deliverables()->create([
            'kind' => DeliverableKind::Unboxing,
            'validation_status' => DeliverableValidationStatus::InReview,
            'chrono_started_at' => now()->subDay(),
            'deadline_at' => now()->addDays(6),
            'submitted_at' => now(),
            'video_path' => 'ugc/deliverables/unboxing/seed.mp4',
            'thumbnail_path' => 'ugc/deliverables/unboxing/thumbnails/seed.jpg',
            'duree_seconds' => 42,
        ]);
        $shipment->fresh()->update(['tunnel_status' => UgcTunnelStatus::UnboxingInReview]);

        app(UgcDeliverableService::class)->validate($unboxing);

        // Le listener ne réactive que sur kind=Avis → toujours suspendue.
        $this->assertNull(UgcSuspension::find($s->id)->reactivated_at);
        $this->assertSame(UgcTunnelStatus::AvisPending, $shipment->fresh()->tunnel_status);
    }

    // ===================================================================
    // AC10 — notification de réactivation
    // ===================================================================

    public function test_reactivation_dispatches_face_notification(): void
    {
        [$booking, $shipment, $s] = $this->makeSuspendedBooking(UgcSuspensionReason::AvisDeadlineMissed);

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/ugc/suspension/resume')
            ->assertOk();

        $avis = $this->uploadAvisInReview($booking, $shipment->fresh());
        app(UgcDeliverableService::class)->validate($avis);

        $this->assertNotNull(UgcSuspension::find($s->id)->reactivated_at);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->faceUser->id,
            'type' => 'ugc_account_reactivated',
        ]);
    }
}
