<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc;

use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Enums\DeliverableKind;
use App\Enums\DeliverableValidationStatus;
use App\Enums\MissionStatus;
use App\Enums\UgcTunnelStatus;
use App\Events\DeliverableRejected;
use App\Events\DeliverableRetoucheRequested;
use App\Events\DeliverableValidated;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Deliverable;
use App\Models\Face;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Ugc\UgcDeadlineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * UGC 4.3 — décision Producteur sur un livrable (POST /api/v1/producer/
 * deliverables/{deliverable}/{validate|reject|request-retouche}). Couvre
 * l'enchaînement des chronos (valider l'Unboxing → avis_pending ; valider l'Avis
 * → completed, tunnel SEULEMENT — D-4.3.a), la branche reject/retouche (fenêtre
 * rouverte, chrono Face conservé — D-4.3.b), l'idempotence, l'autorisation
 * (DeliverablePolicy::review), les gardes refund/annulé, et l'exposition
 * review_due_at + l'ordre stable des livrables. Booking-owner ET candidature-owner.
 */
class UgcDeliverableValidationTest extends TestCase
{
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

        Storage::fake('local');
    }

    // ===================================================================
    // Fixtures
    // ===================================================================

    /**
     * @return array{0: Booking, 1: Shipment}
     */
    private function makeReceivedBooking(): array
    {
        $booking = Booking::create([
            'face_id' => $this->faceUser->id,         // users.id
            'producer_id' => $this->producerUser->id, // users.id
            'status' => BookingStatus::Accepted,
            'accepted_at' => now(),
            'type_contenu' => 'UGC',
            'type_compensation' => 'product',
            'nom_produit' => 'Tenue Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'commission_ugc' => 2500,
            'commission_paid_at' => now()->subDay(),
            'tarif_base' => 0,
            'montant_total_producteur' => 2500,
            'montant_face_recoit' => 0,
        ]);

        $shipment = $booking->shipment()->create([
            'transporteur' => 'Gozem',
            'numero_suivi' => 'GZM-COT-882194',
            'tunnel_status' => UgcTunnelStatus::Received,
            'shipped_at' => now()->subDays(2),
            'recu_le' => now()->subDay(),
            'destinataire_nom' => 'Aïcha Bello',
            'destinataire_ville' => 'Cotonou',
            'destinataire_pays' => 'Bénin',
        ]);

        return [$booking, $shipment];
    }

    /**
     * @return array{0: \App\Models\Mission, 1: Candidature, 2: Shipment}
     */
    private function makeReceivedCandidature(): array
    {
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
            'face_id' => $this->face->id,  // faces.id (PAS users.id)
            'mission_id' => $mission->id,
            'status' => CandidatureStatus::Confirmed,
        ]);

        $shipment = $candidature->shipment()->create([
            'transporteur' => 'Gozem',
            'numero_suivi' => 'GZM-COT-882194',
            'tunnel_status' => UgcTunnelStatus::Received,
            'shipped_at' => now()->subDays(2),
            'recu_le' => now()->subDay(),
            'destinataire_nom' => 'Aïcha Bello',
            'destinataire_ville' => 'Cotonou',
            'destinataire_pays' => 'Bénin',
        ]);

        return [$mission, $candidature, $shipment];
    }

    /**
     * Deal en unboxing_in_review avec un Unboxing in_review prêt à valider.
     *
     * @return array{0: Booking, 1: Shipment, 2: Deliverable}
     */
    private function makeUnboxingInReviewBooking(): array
    {
        [$booking, $shipment] = $this->makeReceivedBooking();
        $shipment->update(['tunnel_status' => UgcTunnelStatus::UnboxingInReview]);
        $unboxing = $booking->deliverables()->create([
            'kind' => DeliverableKind::Unboxing,
            'validation_status' => DeliverableValidationStatus::InReview,
            'chrono_started_at' => $shipment->recu_le,
            'deadline_at' => $shipment->recu_le->copy()->addDays(7),
            'submitted_at' => now(),
            'video_path' => 'ugc/deliverables/unboxing/seed.mp4',
            'thumbnail_path' => 'ugc/deliverables/unboxing/thumbnails/seed.jpg',
            'duree_seconds' => 42,
        ]);

        return [$booking, $shipment, $unboxing];
    }

    /**
     * @return array{0: \App\Models\Mission, 1: Candidature, 2: Shipment, 3: Deliverable}
     */
    private function makeUnboxingInReviewCandidature(): array
    {
        [$mission, $candidature, $shipment] = $this->makeReceivedCandidature();
        $shipment->update(['tunnel_status' => UgcTunnelStatus::UnboxingInReview]);
        $unboxing = $candidature->deliverables()->create([
            'kind' => DeliverableKind::Unboxing,
            'validation_status' => DeliverableValidationStatus::InReview,
            'chrono_started_at' => $shipment->recu_le,
            'deadline_at' => $shipment->recu_le->copy()->addDays(7),
            'submitted_at' => now(),
            'video_path' => 'ugc/deliverables/unboxing/seed.mp4',
            'thumbnail_path' => 'ugc/deliverables/unboxing/thumbnails/seed.jpg',
            'duree_seconds' => 42,
        ]);

        return [$mission, $candidature, $shipment, $unboxing];
    }

    /**
     * Deal en avis_in_review : Unboxing validé (chrono recu_le) + Avis in_review
     * (chrono = validated_at, POSTÉRIEUR → ordre stable testable).
     *
     * @return array{0: Booking, 1: Shipment, 2: Deliverable}
     */
    private function makeAvisInReviewBooking(): array
    {
        [$booking, $shipment] = $this->makeReceivedBooking();
        $validatedAt = now()->subHours(6); // postérieur à recu_le (now-1j)
        $shipment->update(['tunnel_status' => UgcTunnelStatus::AvisInReview]);
        $booking->deliverables()->create([
            'kind' => DeliverableKind::Unboxing,
            'validation_status' => DeliverableValidationStatus::Validated,
            'chrono_started_at' => $shipment->recu_le,
            'deadline_at' => $shipment->recu_le->copy()->addDays(7),
            'submitted_at' => $shipment->recu_le,
            'validated_at' => $validatedAt,
            'video_path' => 'ugc/deliverables/unboxing/seed.mp4',
            'thumbnail_path' => 'ugc/deliverables/unboxing/thumbnails/seed.jpg',
            'duree_seconds' => 42,
        ]);
        $avis = $booking->deliverables()->create([
            'kind' => DeliverableKind::Avis,
            'validation_status' => DeliverableValidationStatus::InReview,
            'chrono_started_at' => $validatedAt,
            'deadline_at' => $validatedAt->copy()->addDays(14),
            'submitted_at' => now(),
            'video_path' => 'ugc/deliverables/avis/seed.mp4',
            'thumbnail_path' => 'ugc/deliverables/avis/thumbnails/seed.jpg',
            'duree_seconds' => 88,
        ]);

        return [$booking, $shipment, $avis];
    }

    /**
     * @return array{0: \App\Models\Mission, 1: Candidature, 2: Shipment, 3: Deliverable}
     */
    private function makeAvisInReviewCandidature(): array
    {
        [$mission, $candidature, $shipment] = $this->makeReceivedCandidature();
        $validatedAt = now()->subHours(6);
        $shipment->update(['tunnel_status' => UgcTunnelStatus::AvisInReview]);
        $candidature->deliverables()->create([
            'kind' => DeliverableKind::Unboxing,
            'validation_status' => DeliverableValidationStatus::Validated,
            'chrono_started_at' => $shipment->recu_le,
            'deadline_at' => $shipment->recu_le->copy()->addDays(7),
            'submitted_at' => $shipment->recu_le,
            'validated_at' => $validatedAt,
            'video_path' => 'ugc/deliverables/unboxing/seed.mp4',
            'thumbnail_path' => 'ugc/deliverables/unboxing/thumbnails/seed.jpg',
            'duree_seconds' => 42,
        ]);
        $avis = $candidature->deliverables()->create([
            'kind' => DeliverableKind::Avis,
            'validation_status' => DeliverableValidationStatus::InReview,
            'chrono_started_at' => $validatedAt,
            'deadline_at' => $validatedAt->copy()->addDays(14),
            'submitted_at' => now(),
            'video_path' => 'ugc/deliverables/avis/seed.mp4',
            'thumbnail_path' => 'ugc/deliverables/avis/thumbnails/seed.jpg',
            'duree_seconds' => 88,
        ]);

        return [$mission, $candidature, $shipment, $avis];
    }

    // ===================================================================
    // AC2 — Valider l'Unboxing → démarre le chrono Avis (avis_pending)
    // ===================================================================

    public function test_producer_validates_unboxing_starts_avis_chrono_for_booking(): void
    {
        $this->freezeTime();
        [, $shipment, $unboxing] = $this->makeUnboxingInReviewBooking();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/deliverables/{$unboxing->uuid}/validate")
            ->assertOk()
            ->assertJsonPath('data.validation_status', 'validated');

        $fresh = $unboxing->fresh();
        $this->assertSame(DeliverableValidationStatus::Validated, $fresh->validation_status);
        $this->assertNotNull($fresh->validated_at);
        $this->assertNull($fresh->review_note);

        $shipmentFresh = $shipment->fresh();
        $this->assertSame(UgcTunnelStatus::AvisPending, $shipmentFresh->tunnel_status);

        // Chrono Avis démarré : deadline dérivée serveur = validated_at + 14 j.
        $avisDeadline = app(UgcDeadlineService::class)->avisDeadlineFor($shipmentFresh);
        $this->assertSame(now()->addDays(14)->toIso8601String(), $avisDeadline?->toIso8601String());

        // Notif Face « Unboxing validé — dépose ton Avis ».
        $notif = Notification::where('user_id', $this->faceUser->id)
            ->where('type', 'ugc_deliverable_validated')
            ->first();
        $this->assertNotNull($notif);
        $this->assertStringContainsString('Avis', (string) data_get($notif->data, 'message'));
    }

    public function test_producer_validates_unboxing_starts_avis_chrono_for_candidature(): void
    {
        [, , $shipment, $unboxing] = $this->makeUnboxingInReviewCandidature();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/deliverables/{$unboxing->uuid}/validate")
            ->assertOk()
            ->assertJsonPath('data.validation_status', 'validated');

        $this->assertSame(UgcTunnelStatus::AvisPending, $shipment->fresh()->tunnel_status);

        $notif = Notification::where('user_id', $this->faceUser->id)
            ->where('type', 'ugc_deliverable_validated')
            ->first();
        $this->assertNotNull($notif);
    }

    public function test_validate_dispatches_event(): void
    {
        Event::fake([DeliverableValidated::class]);
        [, , $unboxing] = $this->makeUnboxingInReviewBooking();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/deliverables/{$unboxing->uuid}/validate")
            ->assertOk();

        Event::assertDispatched(DeliverableValidated::class);
    }

    // ===================================================================
    // AC4 — Valider l'Avis → clôture (tunnel SEULEMENT, statut amont intact)
    // ===================================================================

    public function test_producer_validates_avis_closes_deal_for_booking(): void
    {
        [$booking, $shipment, $avis] = $this->makeAvisInReviewBooking();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/deliverables/{$avis->uuid}/validate")
            ->assertOk()
            ->assertJsonPath('data.validation_status', 'validated');

        $this->assertSame(UgcTunnelStatus::Completed, $shipment->fresh()->tunnel_status);
        // D-4.3.a : statut amont INCHANGÉ, aucun payout.
        $this->assertSame(BookingStatus::Accepted, $booking->fresh()->status);

        $notif = Notification::where('user_id', $this->faceUser->id)
            ->where('type', 'ugc_deliverable_validated')
            ->first();
        $this->assertNotNull($notif);
        $this->assertStringContainsString('terminé', (string) data_get($notif->data, 'message'));
    }

    public function test_producer_validates_avis_closes_deal_for_candidature(): void
    {
        [, $candidature, $shipment, $avis] = $this->makeAvisInReviewCandidature();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/deliverables/{$avis->uuid}/validate")
            ->assertOk();

        $this->assertSame(UgcTunnelStatus::Completed, $shipment->fresh()->tunnel_status);
        // D-4.3.a : statut amont INCHANGÉ.
        $this->assertSame(CandidatureStatus::Confirmed, $candidature->fresh()->status);
    }

    // ===================================================================
    // AC5 — Rejeter / demander une retouche (re-upload attendu, chrono conservé)
    // ===================================================================

    public function test_producer_rejects_unboxing_reopens_window_keeps_chrono(): void
    {
        [, $shipment, $unboxing] = $this->makeUnboxingInReviewBooking();
        $conservedDeadline = $unboxing->deadline_at->toIso8601String();
        // Baseline lue en DB (précision seconde) pour comparer submitted_at à valeur égale (D-4.3.g).
        $conservedSubmittedAt = $unboxing->fresh()->submitted_at->toIso8601String();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/deliverables/{$unboxing->uuid}/reject", [
                'review_note' => 'Le cadrage est hors sujet, recommence.',
            ])
            ->assertOk()
            ->assertJsonPath('data.validation_status', 'rejected')
            ->assertJsonPath('data.review_note', 'Le cadrage est hors sujet, recommence.');

        $fresh = $unboxing->fresh();
        $this->assertSame(DeliverableValidationStatus::Rejected, $fresh->validation_status);
        $this->assertSame('Le cadrage est hors sujet, recommence.', $fresh->review_note);
        $this->assertNull($fresh->validated_at);
        $this->assertSame($conservedDeadline, $fresh->deadline_at->toIso8601String()); // D-4.3.b
        // SLA Producteur tracé : submitted_at INCHANGÉ au reject (re-posé seulement au re-upload, D-4.3.g).
        $this->assertSame($conservedSubmittedAt, $fresh->submitted_at->toIso8601String());

        $this->assertSame(UgcTunnelStatus::Received, $shipment->fresh()->tunnel_status);

        $notif = Notification::where('user_id', $this->faceUser->id)
            ->where('type', 'ugc_deliverable_rejected')
            ->first();
        $this->assertNotNull($notif);
        $this->assertStringContainsString('hors sujet', (string) data_get($notif->data, 'message'));
    }

    public function test_producer_requests_retouche_on_unboxing(): void
    {
        [, $shipment, $unboxing] = $this->makeUnboxingInReviewBooking();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/deliverables/{$unboxing->uuid}/request-retouche", [
                'review_note' => 'Ajoute le plan packaging avant la fin.',
            ])
            ->assertOk()
            ->assertJsonPath('data.validation_status', 'retouche_requested');

        $this->assertSame(UgcTunnelStatus::Received, $shipment->fresh()->tunnel_status);

        $notif = Notification::where('user_id', $this->faceUser->id)
            ->where('type', 'ugc_deliverable_retouche_requested')
            ->first();
        $this->assertNotNull($notif);
        $this->assertStringContainsString('packaging', (string) data_get($notif->data, 'message'));
    }

    public function test_producer_rejects_avis_reopens_avis_pending(): void
    {
        [, $shipment, $avis] = $this->makeAvisInReviewBooking();
        $conservedDeadline = $avis->deadline_at->toIso8601String();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/deliverables/{$avis->uuid}/reject", [
                'review_note' => 'Audio inaudible, refais la prise.',
            ])
            ->assertOk()
            ->assertJsonPath('data.validation_status', 'rejected');

        $this->assertSame(UgcTunnelStatus::AvisPending, $shipment->fresh()->tunnel_status);
        $this->assertSame($conservedDeadline, $avis->fresh()->deadline_at->toIso8601String());
    }

    public function test_producer_requests_retouche_on_avis(): void
    {
        [, $shipment, $avis] = $this->makeAvisInReviewBooking();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/deliverables/{$avis->uuid}/request-retouche", [
                'review_note' => 'Ajoute un plan large du produit.',
            ])
            ->assertOk()
            ->assertJsonPath('data.validation_status', 'retouche_requested');

        $this->assertSame(UgcTunnelStatus::AvisPending, $shipment->fresh()->tunnel_status);
    }

    public function test_reject_requires_review_note(): void
    {
        [, , $unboxing] = $this->makeUnboxingInReviewBooking();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/deliverables/{$unboxing->uuid}/reject", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['review_note']);

        // Aucune mutation.
        $this->assertSame(DeliverableValidationStatus::InReview, $unboxing->fresh()->validation_status);
    }

    public function test_reject_dispatches_rejected_event_only(): void
    {
        Event::fake([DeliverableRejected::class, DeliverableRetoucheRequested::class]);
        [, , $unboxing] = $this->makeUnboxingInReviewBooking();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/deliverables/{$unboxing->uuid}/reject", [
                'review_note' => 'Motif de rejet suffisant.',
            ])
            ->assertOk();

        Event::assertDispatched(DeliverableRejected::class);
        Event::assertNotDispatched(DeliverableRetoucheRequested::class);
    }

    // ===================================================================
    // Idempotence (re-statuer un livrable non-in_review → 422, no-op)
    // ===================================================================

    public function test_cannot_validate_already_validated_deliverable(): void
    {
        [, $shipment, $unboxing] = $this->makeUnboxingInReviewBooking();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/deliverables/{$unboxing->uuid}/validate")
            ->assertOk();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/deliverables/{$unboxing->uuid}/validate")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->assertSame(UgcTunnelStatus::AvisPending, $shipment->fresh()->tunnel_status);
        $this->assertSame(1, Notification::where('type', 'ugc_deliverable_validated')->count());
    }

    public function test_cannot_reject_already_rejected_deliverable(): void
    {
        [, , $unboxing] = $this->makeUnboxingInReviewBooking();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/deliverables/{$unboxing->uuid}/reject", ['review_note' => 'Premier motif de rejet.'])
            ->assertOk();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/deliverables/{$unboxing->uuid}/reject", ['review_note' => 'Deuxième motif de rejet.'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    // ===================================================================
    // AC7 — Autorisation (seul le Producteur propriétaire statue)
    // ===================================================================

    public function test_other_producer_cannot_review(): void
    {
        [, , $unboxing] = $this->makeUnboxingInReviewBooking();

        $otherProducer = Producer::factory()->create();
        $otherProducerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $otherProducer->id,
        ]);

        $this->actingAs($otherProducerUser)
            ->postJson("/api/v1/producer/deliverables/{$unboxing->uuid}/validate")
            ->assertForbidden();

        $this->assertSame(DeliverableValidationStatus::InReview, $unboxing->fresh()->validation_status);
    }

    public function test_face_cannot_review(): void
    {
        [, , $unboxing] = $this->makeUnboxingInReviewBooking();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/producer/deliverables/{$unboxing->uuid}/validate")
            ->assertForbidden();

        $this->assertSame(DeliverableValidationStatus::InReview, $unboxing->fresh()->validation_status);
    }

    public function test_unauthenticated_cannot_review(): void
    {
        [, , $unboxing] = $this->makeUnboxingInReviewBooking();

        $this->postJson("/api/v1/producer/deliverables/{$unboxing->uuid}/validate")
            ->assertUnauthorized();

        $this->assertSame(DeliverableValidationStatus::InReview, $unboxing->fresh()->validation_status);
    }

    // ===================================================================
    // AC8 — Gardes refund/annulé + review_due_at + ordre des livrables
    // ===================================================================

    public function test_review_rejected_on_cancelled_booking(): void
    {
        [$booking, $shipment, $unboxing] = $this->makeUnboxingInReviewBooking();
        $booking->update(['status' => BookingStatus::CancelledByProducer]);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/deliverables/{$unboxing->uuid}/validate")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->assertSame(DeliverableValidationStatus::InReview, $unboxing->fresh()->validation_status);
        $this->assertSame(UgcTunnelStatus::UnboxingInReview, $shipment->fresh()->tunnel_status);
    }

    public function test_review_rejected_on_cancelled_candidature(): void
    {
        [, $candidature, , $unboxing] = $this->makeUnboxingInReviewCandidature();
        $candidature->update(['status' => CandidatureStatus::Cancelled]);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/deliverables/{$unboxing->uuid}/validate")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->assertSame(DeliverableValidationStatus::InReview, $unboxing->fresh()->validation_status);
    }

    public function test_review_rejected_when_refund_requested(): void
    {
        [$booking, , $unboxing] = $this->makeUnboxingInReviewBooking();
        $booking->update(['commission_refund_requested_at' => now()]);

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/deliverables/{$unboxing->uuid}/validate");

        $response->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_STATUS');
        $this->assertStringContainsString('remboursement', (string) $response->json('error.message'));
        $this->assertSame(DeliverableValidationStatus::InReview, $unboxing->fresh()->validation_status);
    }

    public function test_review_rejected_when_refunded_out_of_band(): void
    {
        // D-2.5.h : refund réglé hors-procédure, statut owner resté Accepted.
        [$booking, , $unboxing] = $this->makeUnboxingInReviewBooking();
        $booking->update(['commission_refunded_at' => now()]);

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/deliverables/{$unboxing->uuid}/reject", ['review_note' => 'Motif quelconque suffisant.']);

        $response->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_STATUS');
        $this->assertStringContainsString('remboursement', (string) $response->json('error.message'));
    }

    public function test_review_due_at_exposed_only_while_in_review(): void
    {
        $this->freezeTime();
        [$booking, , $unboxing] = $this->makeUnboxingInReviewBooking();

        // submitted_at = now() (helper) → review_due_at = now + 48 h (in_review).
        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}")
            ->assertOk()
            ->assertJsonPath('data.deliverables.0.review_due_at', now()->addHours(48)->toIso8601String());

        // Après validation → plus in_review → review_due_at null.
        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/deliverables/{$unboxing->uuid}/validate")
            ->assertOk();

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}")
            ->assertOk()
            ->assertJsonPath('data.deliverables.0.review_due_at', null);
    }

    public function test_deliverables_ordered_unboxing_before_avis(): void
    {
        [$booking] = $this->makeAvisInReviewBooking();

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}")
            ->assertOk()
            ->assertJsonPath('data.deliverables.0.kind', 'unboxing')
            ->assertJsonPath('data.deliverables.1.kind', 'avis');
    }
}
