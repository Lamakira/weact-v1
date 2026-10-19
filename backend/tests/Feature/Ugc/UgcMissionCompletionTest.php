<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc;

use App\Enums\CandidatureStatus;
use App\Enums\DeliverableKind;
use App\Enums\DeliverableValidationStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionStatus;
use App\Enums\UgcTunnelStatus;
use App\Models\Candidature;
use App\Models\Deliverable;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPaymentCandidature;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Lot B8 — fin des missions UGC : une candidature produit seul passe « terminée » quand
 * l'Avis est validé, et la mission UGC passe « terminée » quand le recrutement est fini et
 * que toutes ses candidatures engagées sont terminales (au moins une livraison terminée).
 * Sweep idempotent (missions:complete-finished-ugc) qui sert aussi de rattrapage.
 */
class UgcMissionCompletionTest extends TestCase
{
    use RefreshDatabase;

    private Producer $producer;

    private User $producerUser;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('local');

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function ugcMission(array $overrides = []): Mission
    {
        return $this->producer->missions()->create(array_merge([
            'titre' => 'Appel UGC — Unboxing sneakers',
            'description' => 'Brief',
            'date_tournage' => now()->addMonth(),
            'profil_recherche' => 'Créatrices lifestyle',
            'budget' => 0,
            'date_limite_candidature' => now()->addWeeks(2),
            'nombre_faces_voulu' => 1,
            'type_mission' => 'ugc',
            'genre_voulu' => 'tous',
            'lieu' => 'Cotonou',
            'duree' => 'Livrables vidéo',
            'status' => MissionStatus::Closed,
            'commission_paid_at' => now(),
            'type_compensation' => 'product',
            'nom_produit' => 'Sneakers Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'commission_ugc' => 2500,
        ], $overrides));
    }

    private function candidature(Mission $mission, CandidatureStatus $status = CandidatureStatus::Confirmed): Candidature
    {
        $face = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        return Candidature::create([
            'face_id' => $face->id,
            'mission_id' => $mission->id,
            'status' => $status,
        ]);
    }

    /**
     * Candidature au stade « Avis en revue », prête à être validée par le Producer.
     */
    private function avisInReview(Candidature $candidature): Deliverable
    {
        $shipment = $candidature->shipment()->create([
            'transporteur' => 'Gozem',
            'numero_suivi' => 'GZM-COT-'.$candidature->id,
            'tunnel_status' => UgcTunnelStatus::AvisInReview,
            'shipped_at' => now()->subDays(5),
            'recu_le' => now()->subDays(4),
            'destinataire_nom' => 'Aïcha Bello',
            'destinataire_ville' => 'Cotonou',
            'destinataire_pays' => 'Bénin',
        ]);
        $candidature->deliverables()->create([
            'kind' => DeliverableKind::Unboxing,
            'validation_status' => DeliverableValidationStatus::Validated,
            'chrono_started_at' => $shipment->recu_le,
            'deadline_at' => $shipment->recu_le->copy()->addDays(7),
            'submitted_at' => $shipment->recu_le,
            'validated_at' => now()->subDays(2),
            'video_path' => 'ugc/deliverables/unboxing/seed.mp4',
            'thumbnail_path' => 'ugc/deliverables/unboxing/thumbnails/seed.jpg',
            'duree_seconds' => 42,
        ]);

        return $candidature->deliverables()->create([
            'kind' => DeliverableKind::Avis,
            'validation_status' => DeliverableValidationStatus::InReview,
            'chrono_started_at' => now()->subDays(2),
            'deadline_at' => now()->addDays(12),
            'submitted_at' => now(),
            'video_path' => 'ugc/deliverables/avis/seed.mp4',
            'thumbnail_path' => 'ugc/deliverables/avis/thumbnails/seed.jpg',
            'duree_seconds' => 88,
        ]);
    }

    private function validateAvis(Deliverable $avis): void
    {
        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/deliverables/{$avis->uuid}/validate")
            ->assertOk();
    }

    // --- Candidature produit seul ---

    public function test_a_product_only_candidature_is_completed_when_the_avis_is_validated(): void
    {
        $mission = $this->ugcMission(['nombre_faces_voulu' => 2]);
        $candidature = $this->candidature($mission);

        $this->validateAvis($this->avisInReview($candidature));

        $this->assertSame(CandidatureStatus::Completed, $candidature->fresh()->status);
    }

    // --- Mission ---

    public function test_the_mission_completes_when_its_last_engaged_face_delivers(): void
    {
        $mission = $this->ugcMission();
        $candidature = $this->candidature($mission);

        $this->validateAvis($this->avisInReview($candidature));

        $this->assertSame(MissionStatus::Completed, $mission->fresh()->status);
        $this->assertTrue(Notification::query()
            ->where('user_id', $this->producerUser->id)
            ->where('type', 'mission_completed_producer')
            ->exists());
    }

    public function test_the_mission_waits_while_another_engaged_face_has_not_delivered(): void
    {
        $mission = $this->ugcMission(['nombre_faces_voulu' => 2]);
        $first = $this->candidature($mission);
        $this->candidature($mission, CandidatureStatus::Confirmed);

        $this->validateAvis($this->avisInReview($first));

        $this->assertSame(MissionStatus::Closed, $mission->fresh()->status);
        $this->assertSame(
            ['candidatures', 'Toutes les Faces engagées doivent avoir terminé leur livraison (ou quitté la mission) avant de terminer la mission.'],
            $mission->fresh()->completionBlocker(),
        );
    }

    public function test_a_mission_still_recruiting_is_not_completed(): void
    {
        $mission = $this->ugcMission(['status' => MissionStatus::Published, 'nombre_faces_voulu' => 3]);
        $candidature = $this->candidature($mission);

        $this->validateAvis($this->avisInReview($candidature));

        $this->assertSame(MissionStatus::Published, $mission->fresh()->status);
        $this->assertFalse($mission->fresh()->canBeCompleted());
    }

    public function test_a_published_mission_past_its_deadline_completes_once_engaged_faces_are_terminal(): void
    {
        $mission = $this->ugcMission([
            'status' => MissionStatus::Published,
            'nombre_faces_voulu' => 3,
            'date_limite_candidature' => now()->subDays(2),
        ]);
        $this->candidature($mission, CandidatureStatus::Completed);
        $this->candidature($mission, CandidatureStatus::Rejected);
        $this->candidature($mission, CandidatureStatus::Cancelled);

        $this->artisan('missions:complete-finished-ugc')
            ->expectsOutputToContain('UGC mission(s) completed: 1')
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame(MissionStatus::Completed, $mission->fresh()->status);
    }

    public function test_a_mission_without_any_completed_delivery_is_never_completed(): void
    {
        $mission = $this->ugcMission(['date_limite_candidature' => now()->subDays(2)]);
        $this->candidature($mission, CandidatureStatus::Rejected);

        $this->artisan('missions:complete-finished-ugc')->assertExitCode(Command::SUCCESS);

        $this->assertSame(MissionStatus::Closed, $mission->fresh()->status);
        $this->assertSame(['candidatures', "Cette mission n'a aucune livraison terminée."], $mission->fresh()->completionBlocker());
    }

    public function test_a_hybrid_mission_releases_the_escrow_then_completes(): void
    {
        $mission = $this->ugcMission(['type_compensation' => 'hybrid']);
        $candidature = $this->candidature($mission);
        $entry = MissionPaymentCandidature::create([
            'mission_payment_id' => null,
            'candidature_id' => $candidature->id,
            'face_id' => $candidature->face_id,
            'montant_face_recoit' => 14250,
            'escrow_status' => EscrowStatus::Locked,
            'locked_at' => now()->subDays(5),
        ]);

        $this->validateAvis($this->avisInReview($candidature));

        $this->assertSame(EscrowStatus::Released, $entry->fresh()->escrow_status);
        $this->assertSame(CandidatureStatus::Completed, $candidature->fresh()->status);
        $this->assertSame(MissionStatus::Completed, $mission->fresh()->status);
    }

    // --- Terminer manuellement ---

    public function test_the_producer_can_complete_an_eligible_ugc_mission(): void
    {
        $mission = $this->ugcMission();
        $this->candidature($mission, CandidatureStatus::Completed);

        $this->assertTrue($mission->fresh()->canBeCompleted());

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/complete")
            ->assertOk();

        $this->assertSame(MissionStatus::Completed, $mission->fresh()->status);
    }

    public function test_the_producer_cannot_complete_a_ugc_mission_with_a_face_still_delivering(): void
    {
        $mission = $this->ugcMission();
        $this->candidature($mission, CandidatureStatus::InProgress);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/complete")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['candidatures']);

        $this->assertSame(MissionStatus::Closed, $mission->fresh()->status);
    }

    // --- Rattrapage ---

    public function test_the_sweep_backfills_product_only_candidatures_whose_avis_was_already_validated(): void
    {
        $mission = $this->ugcMission();
        $candidature = $this->candidature($mission);
        $avis = $this->avisInReview($candidature);
        // État laissé par l'ancien code : Avis validé, tunnel clôturé, candidature restée confirmée.
        $avis->update(['validation_status' => DeliverableValidationStatus::Validated, 'validated_at' => now()]);
        $candidature->shipment()->update(['tunnel_status' => UgcTunnelStatus::Completed]);

        $this->artisan('missions:complete-finished-ugc')
            ->expectsOutputToContain('Candidatures completed: 1')
            ->expectsOutputToContain('UGC mission(s) completed: 1')
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame(CandidatureStatus::Completed, $candidature->fresh()->status);
        $this->assertSame(MissionStatus::Completed, $mission->fresh()->status);

        // Idempotent.
        $this->artisan('missions:complete-finished-ugc')
            ->expectsOutputToContain('Candidatures completed: 0')
            ->expectsOutputToContain('UGC mission(s) completed: 0');
    }

    public function test_the_sweep_never_touches_standard_missions(): void
    {
        $mission = Mission::factory()->closed()->create([
            'producer_id' => $this->producer->id,
            'type_mission' => 'publicite',
            'date_limite_candidature' => now()->subDays(2),
        ]);
        $this->candidature($mission, CandidatureStatus::Completed);

        $this->artisan('missions:complete-finished-ugc')->assertExitCode(Command::SUCCESS);

        $this->assertSame(MissionStatus::Closed, $mission->fresh()->status);
    }
}
