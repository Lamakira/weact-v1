<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc;

use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Enums\DeliverableKind;
use App\Enums\DeliverableValidationStatus;
use App\Enums\MissionStatus;
use App\Enums\UgcSuspensionAppealStatus;
use App\Enums\UgcSuspensionReason;
use App\Enums\UgcTunnelStatus;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\Shipment;
use App\Models\UgcSuspension;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UGC 5.2 — endpoint read `GET /api/v1/face/ugc/suspension` (écran 10A). Source serveur
 * autoritative + suspension-aware : la même garde que `isUgcSuspended`
 * (whereNull('reactivated_at')). Crée DIRECTEMENT la ligne `ugc_suspensions` (l'invariant
 * 10A) sans lancer le cron 5.1 (le moteur de suspension n'est pas le sujet du test). Temps
 * figé → reactivation_deadline déterministe.
 */
class UgcSuspensionStatusTest extends TestCase
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
    // Fixtures (calque UgcAutoSuspensionTest 5.1 — création DIRECTE de la suspension)
    // ===================================================================

    /** Booking UGC hybride au shipment Unboxing dépassé (recu_le J-8, span 7 j). */
    private function makeOverdueUnboxingBooking(): Shipment
    {
        $booking = Booking::create([
            'face_id' => $this->faceUser->id,          // users.id
            'producer_id' => $this->producerUser->id,  // users.id
            'status' => BookingStatus::Accepted,
            'accepted_at' => now(),
            'type_contenu' => 'UGC',
            'type_compensation' => 'hybrid',
            'nom_produit' => 'Tenue Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'commission_ugc' => 2250,
            'commission_paid_at' => now()->subDays(10),
            'tarif_base' => 0,
            'montant_remuneration' => 15000,
            'montant_face_recoit' => 14250,
            'montant_total_producteur' => 16500,
        ]);

        return $booking->shipment()->create([
            'transporteur' => 'Gozem',
            'numero_suivi' => 'GZM-COT-882194',
            'tunnel_status' => UgcTunnelStatus::Suspended,   // post-suspension (5.1)
            'shipped_at' => now()->subDays(10),
            'recu_le' => now()->subDays(8),
            'destinataire_nom' => 'Aïcha Bello',
            'destinataire_ville' => 'Cotonou',
            'destinataire_pays' => 'Bénin',
        ]);
    }

    /**
     * Candidature UGC (mission) au shipment AvisPending dépassé : Unboxing validé J-15
     * (span Avis 14 j) → avisDeadlineFor non-null. Calque makeOverdueAvisBooking 5.1.
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

    /** Crée la ligne de suspension active pour un shipment donné (l'invariant 10A). */
    private function suspend(?int $shipmentId, UgcSuspensionReason $reason): UgcSuspension
    {
        return UgcSuspension::create([
            'face_id' => $this->face->id,
            'shipment_id' => $shipmentId,
            'reason' => $reason,
            'appeal_status' => UgcSuspensionAppealStatus::None,
            'suspended_at' => now(),
        ]);
    }

    // ===================================================================
    // AC1 — pas de suspension active
    // ===================================================================

    public function test_returns_not_suspended_for_clean_face(): void
    {
        $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/ugc/suspension')
            ->assertOk()
            ->assertJsonPath('data.is_suspended', false)
            ->assertJsonPath('data.suspension', null);
    }

    // ===================================================================
    // AC2 — contrat data.suspension (booking, Unboxing)
    // ===================================================================

    public function test_returns_suspension_with_booking_deal(): void
    {
        $shipment = $this->makeOverdueUnboxingBooking();
        $this->suspend($shipment->id, UgcSuspensionReason::UnboxingDeadlineMissed);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/ugc/suspension')
            ->assertOk()
            ->assertJsonPath('data.is_suspended', true)
            ->assertJsonPath('data.suspension.reason', 'unboxing_deadline_missed')
            ->assertJsonPath('data.suspension.reason_label', 'Unboxing non livré dans les délais')
            ->assertJsonPath('data.suspension.appeal_status', 'none')
            ->assertJsonPath('data.suspension.deal.owner_kind', 'booking')
            ->assertJsonPath('data.suspension.deal.owner_uuid', $shipment->owner->uuid)
            ->assertJsonPath('data.suspension.deal.product_name', 'Tenue Shade Fit');

        $this->assertNotNull($response->json('data.suspension.deal.missed_deadline_at'));
        $this->assertNotNull($response->json('data.suspension.reactivation_deadline'));

        $response->assertJsonStructure([
            'data' => [
                'is_suspended',
                'suspension' => [
                    'reason',
                    'reason_label',
                    'suspended_at',
                    'reactivation_deadline',
                    'appeal_status',
                    'deal' => [
                        'owner_kind',
                        'owner_uuid',
                        'product_name',
                        'missed_deadline_at',
                    ],
                ],
            ],
        ]);
    }

    // ===================================================================
    // AC2 — reason Avis + deal mission (candidature)
    // ===================================================================

    public function test_returns_avis_reason_and_mission_deal_for_candidature(): void
    {
        [$mission, $shipment] = $this->makeOverdueAvisCandidature();
        $this->suspend($shipment->id, UgcSuspensionReason::AvisDeadlineMissed);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/ugc/suspension')
            ->assertOk()
            ->assertJsonPath('data.is_suspended', true)
            ->assertJsonPath('data.suspension.reason', 'avis_deadline_missed')
            ->assertJsonPath('data.suspension.reason_label', 'Avis non livré dans les délais')
            ->assertJsonPath('data.suspension.deal.owner_kind', 'candidature')
            ->assertJsonPath('data.suspension.deal.owner_uuid', $mission->uuid)
            ->assertJsonPath('data.suspension.deal.product_name', 'Sneakers Shade Fit');

        $this->assertNotNull($response->json('data.suspension.deal.missed_deadline_at'));
    }

    // ===================================================================
    // AC2 — deal null quand le shipment est absent
    // ===================================================================

    public function test_returns_null_deal_when_shipment_missing(): void
    {
        $this->suspend(null, UgcSuspensionReason::UnboxingDeadlineMissed);

        $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/ugc/suspension')
            ->assertOk()
            ->assertJsonPath('data.is_suspended', true)
            ->assertJsonPath('data.suspension.deal', null);
    }

    // ===================================================================
    // AC2 — reactivation_deadline = suspended_at + config('ugc.late_completion_days')
    // ===================================================================

    public function test_reactivation_deadline_is_suspended_at_plus_config(): void
    {
        $this->suspend(null, UgcSuspensionReason::UnboxingDeadlineMissed);

        $expected = now()->addDays((int) config('ugc.late_completion_days'))->toIso8601String();

        $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/ugc/suspension')
            ->assertOk()
            ->assertJsonPath('data.suspension.reactivation_deadline', $expected);
    }

    // ===================================================================
    // AC3 — login préservé (Face suspendue = 200) + accès non-Face refusé
    // ===================================================================

    public function test_producer_gets_403(): void
    {
        $this->actingAs($this->producerUser)
            ->getJson('/api/v1/face/ugc/suspension')
            ->assertForbidden();
    }

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/v1/face/ugc/suspension')
            ->assertUnauthorized();
    }
}
