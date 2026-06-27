<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc;

use App\Enums\CandidatureStatus;
use App\Enums\CompensationType;
use App\Enums\DeliverableKind;
use App\Enums\DeliverableValidationStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Enums\UgcTunnelStatus;
use App\Mail\CandidatureAcceptedMail;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use App\Models\Shipment;
use App\Models\User;
use App\Services\FedapayService;
use App\Services\MissionService;
use App\Services\Ugc\UgcSuspensionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Ugc\Concerns\BuildsUgcShipments;
use Tests\Feature\Ugc\Concerns\DispatchesFedapayWebhooks;
use Tests\TestCase;

/**
 * ugc-8-4 — règlement hybride par-Face d'une mission UGC (escrow par-Candidature,
 * mécanisme #2). Couvre le publish gate (mission hybride publiée sans paiement,
 * commission_ugc null), l'accept (entry escrow Pending parentless + FedaPay
 * cash+10 %, candidature reste pending), la capacité in-flight, le settlement
 * webhook (Locked + accepted + auto-close ; declined → entry supprimée), le
 * reconfirm gardé escrow Locked, les gardes tunnel hybrid-aware, la complétion
 * (release → wallet Face + Completed), les échecs (décline/suspension → refund
 * Producteur du net escrow), et la non-régression du flux cash (entries
 * parentless invisibles).
 *
 * Élite (5 %) : cash 15000 → Producteur paie 16500, Face reçoit (escrow) 14250,
 * WeAct garde 2250.
 */
class UgcMissionHybridSettlementTest extends TestCase
{
    use BuildsUgcShipments;
    use DispatchesFedapayWebhooks;
    use RefreshDatabase;

    // ===================================================================
    // AC1 — Publish gate (D-8.4.c)
    // ===================================================================

    public function test_hybrid_mission_is_published_without_payment(): void
    {
        [$producer] = $this->makeProducerWithUser();

        $mission = app(MissionService::class)->createMission($producer, $this->ugcMissionData([
            'type_compensation' => CompensationType::Hybrid->value,
            'valeur_produit' => 50000,
            'nombre_videos' => 3,
            'montant_remuneration' => 15000,
        ]));

        // Hybride : publiée directement, SANS commission produit (la commission porte
        // sur le cash, prélevée par-Face à l'acceptation).
        $this->assertSame(MissionStatus::Published, $mission->status);
        $this->assertNull($mission->commission_ugc);
        $this->assertSame(15000, (int) $mission->montant_remuneration);
        $this->assertSame(15000, (int) $mission->budget);
    }

    public function test_product_only_mission_stays_pending_payment_with_commission(): void
    {
        [$producer] = $this->makeProducerWithUser();

        $mission = app(MissionService::class)->createMission($producer, $this->ugcMissionData([
            'type_compensation' => CompensationType::Product->value,
            'valeur_produit' => 50000,
        ]));

        // Produit-seul : strictement inchangé — gate paiement commission (PendingPayment).
        $this->assertSame(MissionStatus::PendingPayment, $mission->status);
        $this->assertSame(5000, (int) $mission->commission_ugc); // max(2500, 10% × 50000)
        $this->assertNull($mission->montant_remuneration);
        $this->assertSame(0, (int) $mission->budget);
    }

    // ===================================================================
    // AC2 — Accept hybride : escrow Pending + FedaPay, candidature reste pending
    // ===================================================================

    public function test_accept_hybrid_creates_parentless_escrow_and_initiates_fedapay(): void
    {
        [$producer, $producerUser] = $this->makeProducerWithUser();
        [$face] = $this->makeSubscribedFace('elite');
        $mission = $this->makePublishedHybridMission($producer);
        $candidature = $this->makePendingCandidature($mission, $face);

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('initiatePaymentForUgcMissionCandidature')
                ->once()
                ->with(\Mockery::type(Candidature::class), 16500, \Mockery::type('string')) // cash 15000 + 10 %
                ->andReturn(['fedapay_transaction_id' => 950, 'checkout_url' => 'https://fedapay.test/hybride']);
        });

        $this->actingAs($producerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/accept")
            ->assertOk()
            ->assertJsonPath('checkout_url', 'https://fedapay.test/hybride')
            ->assertJsonPath('data.status', 'pending');

        // Entry escrow PARENTLESS (mission_payment_id null), Pending, net Face = faceReceives (14250).
        $this->assertDatabaseHas('mission_payment_candidatures', [
            'candidature_id' => $candidature->id,
            'face_id' => $face->id,
            'mission_payment_id' => null,
            'montant_face_recoit' => 14250,
            'escrow_status' => 'pending',
            'fedapay_transaction_id' => '950',
        ]);

        // Candidature TOUJOURS pending (l'acceptation vient du webhook), aucun escrow Locked.
        $this->assertSame(CandidatureStatus::Pending, $candidature->fresh()->status);
        $this->assertSame(0, MissionPaymentCandidature::where('escrow_status', EscrowStatus::Locked)->count());
    }

    public function test_accept_hybrid_returns_422_and_rolls_back_when_fedapay_fails(): void
    {
        [$producer, $producerUser] = $this->makeProducerWithUser();
        [$face] = $this->makeSubscribedFace('elite');
        $mission = $this->makePublishedHybridMission($producer);
        $candidature = $this->makePendingCandidature($mission, $face);

        // FedaPay indisponible : l'init throw DANS la transaction sous locks (revue 2026-06-27).
        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('initiatePaymentForUgcMissionCandidature')
                ->once()
                ->andThrow(new \RuntimeException('FedaPay indisponible'));
        });

        // La panne est convertie en 422 propre (pas un 500 brut).
        $this->actingAs($producerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/accept")
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment');

        // Rollback total (atomicité D-8.4.e) : aucune entry orpheline, candidature toujours pending.
        $this->assertDatabaseMissing('mission_payment_candidatures', ['candidature_id' => $candidature->id]);
        $this->assertSame(0, MissionPaymentCandidature::count());
        $this->assertSame(CandidatureStatus::Pending, $candidature->fresh()->status);
    }

    // ===================================================================
    // AC3 — Capacité réservée (engagés + in-flight)
    // ===================================================================

    public function test_accept_hybrid_is_full_when_inflight_reserves_capacity(): void
    {
        [$producer, $producerUser] = $this->makeProducerWithUser();
        [$faceInflight] = $this->makeSubscribedFace('elite');
        [$faceTarget] = $this->makeSubscribedFace('elite');
        $mission = $this->makePublishedHybridMission($producer, ['nombre_faces_voulu' => 1]);

        // Un slot déjà réservé in-flight : candidature pending + entry parentless Pending.
        $inflight = $this->makePendingCandidature($mission, $faceInflight);
        $this->pendingHybridEscrow($inflight, txn: '8001');

        $target = $this->makePendingCandidature($mission, $faceTarget);

        // FedaPay ne doit JAMAIS être appelé : la capacité est pleine avant l'init.
        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldNotReceive('initiatePaymentForUgcMissionCandidature');
        });

        $this->actingAs($producerUser)
            ->postJson("/api/v1/producer/candidatures/{$target->uuid}/accept")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'MISSION_FULL');

        // Aucune entry créée pour la cible.
        $this->assertDatabaseMissing('mission_payment_candidatures', ['candidature_id' => $target->id]);
        $this->assertSame(1, MissionPaymentCandidature::count());
    }

    // ===================================================================
    // AC4 — Webhook approved : Locked + accepted + auto-close + notif
    // ===================================================================

    public function test_webhook_approved_locks_escrow_accepts_candidature_and_closes_mission(): void
    {
        Mail::fake();

        [$producer] = $this->makeProducerWithUser();
        [$face, $faceUser] = $this->makeSubscribedFace('elite');
        $mission = $this->makePublishedHybridMission($producer, ['nombre_faces_voulu' => 1]);
        $candidature = $this->makePendingCandidature($mission, $face);
        $this->pendingHybridEscrow($candidature, txn: '8100');

        $this->dispatchWebhook('transaction.approved', 8100, 'ref_ok');

        // Escrow Locked, candidature accepted, mission auto-fermée (capacité atteinte).
        $this->assertDatabaseHas('mission_payment_candidatures', [
            'candidature_id' => $candidature->id,
            'escrow_status' => 'locked',
        ]);
        $this->assertNotNull($candidature->fresh()->paymentEntry?->locked_at);
        $this->assertSame(CandidatureStatus::Accepted, $candidature->fresh()->status);
        $this->assertSame(MissionStatus::Closed, $mission->fresh()->status);

        // Conversation provisionnée + notif Face + email.
        $this->assertDatabaseHas('conversations', ['candidature_id' => $candidature->id]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $faceUser->id,
            'type' => 'candidature_accepted',
        ]);
        Mail::assertQueued(CandidatureAcceptedMail::class);
    }

    public function test_webhook_approved_is_idempotent_no_double_accept(): void
    {
        Mail::fake();

        [$producer] = $this->makeProducerWithUser();
        [$face, $faceUser] = $this->makeSubscribedFace('elite');
        $mission = $this->makePublishedHybridMission($producer, ['nombre_faces_voulu' => 1]);
        $candidature = $this->makePendingCandidature($mission, $face);
        $this->pendingHybridEscrow($candidature, txn: '8101');

        $this->dispatchWebhook('transaction.approved', 8101, 'ref_ok');
        $this->dispatchWebhook('transaction.approved', 8101, 'ref_ok'); // re-jeu

        $this->assertSame(EscrowStatus::Locked, $candidature->fresh()->paymentEntry?->escrow_status);
        $this->assertSame(CandidatureStatus::Accepted, $candidature->fresh()->status);
        // Pas de double notif / double email.
        $this->assertSame(1, \App\Models\Notification::where('user_id', $faceUser->id)
            ->where('type', 'candidature_accepted')->count());
        Mail::assertQueued(CandidatureAcceptedMail::class, 1);
    }

    public function test_webhook_approved_never_calls_mark_as_paid(): void
    {
        // AC4 : markAsPaid clôturerait la mission + rejetterait les AUTRES candidatures
        // pending. Le settlement par-Face ne doit toucher QUE la candidature payée.
        [$producer] = $this->makeProducerWithUser();
        [$facePaid] = $this->makeSubscribedFace('elite');
        [$faceOther] = $this->makeSubscribedFace('elite');
        $mission = $this->makePublishedHybridMission($producer, ['nombre_faces_voulu' => 2]);

        $paid = $this->makePendingCandidature($mission, $facePaid);
        $this->pendingHybridEscrow($paid, txn: '8102');
        $other = $this->makePendingCandidature($mission, $faceOther); // pending, SANS entry

        $this->dispatchWebhook('transaction.approved', 8102, 'ref_ok');

        // La candidature payée passe accepted ; l'AUTRE pending reste pending (PAS rejetée).
        $this->assertSame(CandidatureStatus::Accepted, $paid->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $other->fresh()->status);
        // Mission NON clôturée par le paiement (capacité 1/2) + aucun MissionPayment créé.
        $this->assertSame(MissionStatus::Published, $mission->fresh()->status);
        $this->assertSame(0, MissionPayment::count());
    }

    // ===================================================================
    // AC5 — Webhook declined/canceled : entry supprimée, candidature pending
    // ===================================================================

    public function test_webhook_declined_deletes_entry_and_keeps_candidature_pending(): void
    {
        [$producer, $producerUser] = $this->makeProducerWithUser();
        [$face] = $this->makeSubscribedFace('elite');
        $mission = $this->makePublishedHybridMission($producer);
        $candidature = $this->makePendingCandidature($mission, $face);
        $this->pendingHybridEscrow($candidature, txn: '8200');

        $this->dispatchWebhook('transaction.declined', 8200, 'ref_ko');

        // Entry supprimée (slot libéré) ; candidature reste pending ; notif Producteur.
        $this->assertDatabaseMissing('mission_payment_candidatures', ['candidature_id' => $candidature->id]);
        $this->assertSame(CandidatureStatus::Pending, $candidature->fresh()->status);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $producerUser->id,
            'type' => 'mission_candidature_payment_failed',
        ]);
        // Webhook traité (queue non empoisonnée).
        $this->assertDatabaseMissing('fedapay_webhook_events', ['status' => 'received']);
    }

    public function test_webhook_declined_after_lock_never_deletes_sequestered_escrow(): void
    {
        // Garde idempotence : un declined tardif (approved a déjà locké) NE supprime PAS
        // l'escrow séquestré.
        [$producer] = $this->makeProducerWithUser();
        [$face] = $this->makeSubscribedFace('elite');
        $mission = $this->makePublishedHybridMission($producer);
        $candidature = $this->makePendingCandidature($mission, $face);
        $this->pendingHybridEscrow($candidature, txn: '8201');

        $this->dispatchWebhook('transaction.approved', 8201, 'ref_ok');   // → Locked
        $this->dispatchWebhook('transaction.declined', 8201, 'ref_late'); // tardif

        $this->assertDatabaseHas('mission_payment_candidatures', [
            'candidature_id' => $candidature->id,
            'escrow_status' => 'locked',
        ]);
        $this->assertSame(CandidatureStatus::Accepted, $candidature->fresh()->status);
    }

    // ===================================================================
    // AC6 — Reconfirm gardé escrow Locked (D-8.4.l)
    // ===================================================================

    public function test_reconfirm_hybrid_succeeds_when_escrow_locked(): void
    {
        [$producer] = $this->makeProducerWithUser();
        [$face, $faceUser] = $this->makeSubscribedFace('elite');
        $mission = $this->makePublishedHybridMission($producer);
        $candidature = $this->makePendingCandidature($mission, $face);
        $candidature->update(['status' => CandidatureStatus::Accepted]);
        $this->lockHybridEscrow($candidature);

        $this->actingAs($faceUser)
            ->postJson("/api/v1/face/candidatures/{$candidature->uuid}/reconfirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertSame(CandidatureStatus::Confirmed, $candidature->fresh()->status);
    }

    public function test_reconfirm_hybrid_rejected_without_locked_escrow(): void
    {
        [$producer] = $this->makeProducerWithUser();
        [$face, $faceUser] = $this->makeSubscribedFace('elite');
        $mission = $this->makePublishedHybridMission($producer);
        $candidature = $this->makePendingCandidature($mission, $face);
        // Accepted mais escrow non finalisé (Pending) — défense D-8.4.l.
        $candidature->update(['status' => CandidatureStatus::Accepted]);
        $this->pendingHybridEscrow($candidature, txn: '8300');

        $this->actingAs($faceUser)
            ->postJson("/api/v1/face/candidatures/{$candidature->uuid}/reconfirm")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->assertSame(CandidatureStatus::Accepted, $candidature->fresh()->status);
    }

    // ===================================================================
    // AC7 — Gardes tunnel hybrid-aware (escrow Locked, commission_paid_at null)
    // ===================================================================

    public function test_hybrid_tunnel_shipment_confirm_passes_with_null_commission_paid_at(): void
    {
        [$producer, $producerUser] = $this->makeProducerWithUser();
        [$face] = $this->makeSubscribedFace('elite');
        $mission = $this->makePublishedHybridMission($producer); // commission_paid_at null
        $candidature = $this->makePendingCandidature($mission, $face);
        $candidature->update(['status' => CandidatureStatus::Confirmed]);
        $this->lockHybridEscrow($candidature);

        $this->assertNull($mission->fresh()->commission_paid_at);

        // Le gate hybride = escrow Locked (pas commission_paid_at) → l'expédition passe.
        $this->actingAs($producerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/confirm-shipment", $this->confirmPayload())
            ->assertCreated()
            ->assertJsonPath('data.tunnel_status', UgcTunnelStatus::Shipped->value);

        $this->assertDatabaseHas('shipments', [
            'owner_type' => Candidature::class,
            'owner_id' => $candidature->id,
            'tunnel_status' => UgcTunnelStatus::Shipped->value,
        ]);
    }

    // ===================================================================
    // AC8 — Complétion : release escrow → wallet Face, candidature Completed
    // ===================================================================

    public function test_validate_avis_releases_escrow_to_face_and_completes_candidature(): void
    {
        [$producer, $producerUser] = $this->makeProducerWithUser();
        [$face, $faceUser] = $this->makeSubscribedFace('elite');
        $mission = $this->makePublishedHybridMission($producer);
        $candidature = $this->makePendingCandidature($mission, $face);
        $candidature->update(['status' => CandidatureStatus::Confirmed]);
        $this->lockHybridEscrow($candidature);
        $avis = $this->buildAvisInReview($candidature);

        $before = (int) $faceUser->fresh()->balance;

        $this->actingAs($producerUser)
            ->postJson("/api/v1/producer/deliverables/{$avis->uuid}/validate")
            ->assertOk()
            ->assertJsonPath('data.validation_status', 'validated');

        // Tunnel completed + escrow Released + candidature Completed.
        $this->assertSame(UgcTunnelStatus::Completed, $avis->fresh()->owner->shipment->tunnel_status);
        $entry = $candidature->fresh()->paymentEntry;
        $this->assertSame(EscrowStatus::Released, $entry?->escrow_status);
        $this->assertNotNull($entry?->released_at);
        $this->assertSame(CandidatureStatus::Completed, $candidature->fresh()->status);

        // Wallet Face crédité du net escrow (14250).
        $this->assertSame($before + 14250, (int) $faceUser->fresh()->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $faceUser->id,
            'type' => 'credit',
            'amount' => 14250,
        ]);
        $this->assertDatabaseHas('financial_events', [
            'type' => 'escrow_release',
            'amount' => 14250,
        ]);
    }

    // ===================================================================
    // AC9 — Échec après paiement → refund Producteur (net escrow)
    // ===================================================================

    public function test_decline_after_payment_refunds_producer_and_cancels_candidature(): void
    {
        [$producer, $producerUser] = $this->makeProducerWithUser();
        [$face, $faceUser] = $this->makeSubscribedFace('elite');
        $mission = $this->makePublishedHybridMission($producer);
        $candidature = $this->makePendingCandidature($mission, $face);
        $candidature->update(['status' => CandidatureStatus::Accepted]);
        $this->lockHybridEscrow($candidature);

        $before = (int) $producerUser->fresh()->balance;

        $this->actingAs($faceUser)
            ->postJson("/api/v1/face/candidatures/{$candidature->uuid}/cancel")
            ->assertOk();

        // Escrow Refunded + wallet Producteur crédité du net + candidature Cancelled.
        $this->assertSame(EscrowStatus::Refunded, $candidature->fresh()->paymentEntry?->escrow_status);
        $this->assertSame($before + 14250, (int) $producerUser->fresh()->balance);
        $this->assertSame(CandidatureStatus::Cancelled, $candidature->fresh()->status);
        $this->assertDatabaseHas('financial_events', [
            'type' => 'refund',
            'amount' => 14250,
        ]);
    }

    public function test_suspension_refunds_producer_from_locked_escrow(): void
    {
        [$producer, $producerUser] = $this->makeProducerWithUser();
        [$face] = $this->makeSubscribedFace('elite');
        $mission = $this->makePublishedHybridMission($producer);
        $candidature = $this->makePendingCandidature($mission, $face);
        $candidature->update(['status' => CandidatureStatus::Confirmed]);
        $this->lockHybridEscrow($candidature);

        // Shipment Received en retard (le cron passe le shipment à suspendForOverdueShipment).
        $shipment = $candidature->shipment()->create([
            'transporteur' => 'Gozem',
            'numero_suivi' => 'GZM-COT-900001',
            'tunnel_status' => UgcTunnelStatus::Received,
            'shipped_at' => now()->subDays(10),
            'recu_le' => now()->subDays(9),
            'destinataire_nom' => 'Aïcha Bello',
            'destinataire_ville' => 'Cotonou',
            'destinataire_pays' => 'Bénin',
        ]);

        $before = (int) $producerUser->fresh()->balance;

        app(UgcSuspensionService::class)->suspendForOverdueShipment($shipment);

        // Escrow Refunded + wallet Producteur crédité du net + shipment Suspended.
        $this->assertSame(EscrowStatus::Refunded, $candidature->fresh()->paymentEntry?->escrow_status);
        $this->assertSame($before + 14250, (int) $producerUser->fresh()->balance);
        $this->assertSame(UgcTunnelStatus::Suspended, $shipment->fresh()->tunnel_status);
    }

    public function test_refund_is_idempotent_no_double_credit(): void
    {
        [$producer, $producerUser] = $this->makeProducerWithUser();
        [$face, $faceUser] = $this->makeSubscribedFace('elite');
        $mission = $this->makePublishedHybridMission($producer);
        $candidature = $this->makePendingCandidature($mission, $face);
        $candidature->update(['status' => CandidatureStatus::Accepted]);
        $this->lockHybridEscrow($candidature);

        $before = (int) $producerUser->fresh()->balance;

        // 1ère décline → refund.
        $this->actingAs($faceUser)
            ->postJson("/api/v1/face/candidatures/{$candidature->uuid}/cancel")
            ->assertOk();
        // 2e décline (candidature déjà Cancelled) → garde pending-only → 400, aucun 2e crédit.
        $this->actingAs($faceUser)
            ->postJson("/api/v1/face/candidatures/{$candidature->uuid}/cancel")
            ->assertStatus(400);

        $this->assertSame($before + 14250, (int) $producerUser->fresh()->balance);
        $this->assertSame(1, \App\Models\WalletTransaction::where('user_id', $producerUser->id)->count());
    }

    // ===================================================================
    // AC10 — Non-régression : entries parentless invisibles au flux cash (D-8.4.m)
    // ===================================================================

    public function test_parentless_hybrid_entries_are_invisible_to_cash_flow(): void
    {
        [$producer] = $this->makeProducerWithUser();
        [$faceA] = $this->makeSubscribedFace('elite');
        [$faceB] = $this->makeSubscribedFace('elite');
        $mission = $this->makePublishedHybridMission($producer, ['nombre_faces_voulu' => 3]);

        $inflight = $this->makePendingCandidature($mission, $faceA);
        $this->pendingHybridEscrow($inflight, txn: '8400');

        $locked = $this->makePendingCandidature($mission, $faceB);
        $locked->update(['status' => CandidatureStatus::Confirmed]);
        $this->lockHybridEscrow($locked);

        // Aucun MissionPayment parent : les entries hybrides sont parentless.
        $this->assertSame(0, MissionPayment::count());
        $this->assertSame(2, MissionPaymentCandidature::whereNull('mission_payment_id')->count());

        // Le cron de réconciliation des sélections cash (dry-run) ne les touche pas
        // (INNER JOIN mission_payments les exclut).
        $this->artisan('candidature:reconcile-stale-selections', ['--dry-run' => true])->assertExitCode(0);

        // L'auto-validation des présences (mission jamais PendingAttendanceValidation,
        // date_tournage null) ne libère pas l'escrow locké.
        $this->artisan('missions:auto-validate-attendance')->assertExitCode(0);

        $this->assertSame(EscrowStatus::Pending, $inflight->fresh()->paymentEntry?->escrow_status);
        $this->assertSame(EscrowStatus::Locked, $locked->fresh()->paymentEntry?->escrow_status);
    }

    // ===================================================================
    // Fixtures (S11) — ni MissionFactory ni les factories User/Face/Producer
    // ne couvrent l'UGC + le User userable : tout construit à la main.
    // ===================================================================

    /** @return array{0: Producer, 1: User} */
    private function makeProducerWithUser(): array
    {
        $producer = Producer::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        return [$producer, $user];
    }

    /** @return array{0: Face, 1: User} */
    private function makeSubscribedFace(string $tier = 'elite'): array
    {
        $face = Face::factory()->create(['sexe' => 'femme']);
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        FaceSubscription::factory()->{$tier}()->active()->create(['face_id' => $face->id]);

        return [$face, $user];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makePublishedHybridMission(Producer $producer, array $overrides = []): Mission
    {
        return $producer->missions()->create(array_merge([
            'titre' => 'Appel UGC hybride',
            'description' => 'Brief',
            'date_tournage' => null, // UGC : pas de tournage (8-1)
            'lieu' => null,
            'duree' => null,
            'profil_recherche' => 'Créatrices',
            'budget' => 15000,
            'date_limite_candidature' => now()->addWeeks(2),
            'nombre_faces_voulu' => 1,
            'type_mission' => MissionType::Ugc->value,
            'genre_voulu' => 'tous',
            'status' => MissionStatus::Published,        // D-8.4.c : publié SANS paiement
            'commission_paid_at' => null,                // hybride : jamais payé au publish
            'type_compensation' => CompensationType::Hybrid->value,
            'nom_produit' => 'Sneakers',
            'valeur_produit' => 50000,
            'nombre_videos' => 3,
            'montant_remuneration' => 15000,             // le cash (uniforme par Face)
            'commission_ugc' => null,                    // hybride : pas de commission produit
        ], $overrides));
    }

    private function makePendingCandidature(Mission $mission, Face $face): Candidature
    {
        return Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $face->id, // faces.id (PAS users.id)
            'status' => CandidatureStatus::Pending,
        ]);
    }

    /** Entry hybride LOCKÉE (Élite : net 14250) — pour complétion / refund. */
    private function lockHybridEscrow(Candidature $candidature, int $net = 14250): MissionPaymentCandidature
    {
        return MissionPaymentCandidature::create([
            'mission_payment_id' => null,               // PARENTLESS (D-8.4.a)
            'candidature_id' => $candidature->id,
            'face_id' => $candidature->face_id,
            'montant_face_recoit' => $net,
            'escrow_status' => EscrowStatus::Locked,
            'locked_at' => now(),
            'fedapay_transaction_id' => '99'.$candidature->id,
        ]);
    }

    /** Entry hybride in-flight (Pending) — paiement initié, pas encore confirmé. */
    private function pendingHybridEscrow(Candidature $candidature, int $net = 14250, ?string $txn = null): MissionPaymentCandidature
    {
        return MissionPaymentCandidature::create([
            'mission_payment_id' => null,
            'candidature_id' => $candidature->id,
            'face_id' => $candidature->face_id,
            'montant_face_recoit' => $net,
            'escrow_status' => EscrowStatus::Pending,
            'fedapay_transaction_id' => $txn ?? ('70'.$candidature->id),
        ]);
    }

    /**
     * Tunnel avis_in_review : shipment + Unboxing validé + Avis in_review prêt à valider.
     */
    private function buildAvisInReview(Candidature $candidature): \App\Models\Deliverable
    {
        Storage::fake((string) config('ugc.storage_disk', 'local'));

        $shipment = $candidature->shipment()->create([
            'transporteur' => 'Gozem',
            'numero_suivi' => 'GZM-COT-882200',
            'tunnel_status' => UgcTunnelStatus::AvisInReview,
            'shipped_at' => now()->subDays(4),
            'recu_le' => now()->subDays(3),
            'destinataire_nom' => 'Aïcha Bello',
            'destinataire_ville' => 'Cotonou',
            'destinataire_pays' => 'Bénin',
        ]);

        $validatedAt = now()->subDay();
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

        /** @var \App\Models\Deliverable $avis */
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

        return $avis;
    }

    /**
     * UGC mission creation payload for MissionService::createMission (AC1).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function ugcMissionData(array $overrides = []): array
    {
        return array_merge([
            'titre' => 'Appel UGC',
            'description' => 'Brief créatrices lifestyle.',
            'date_tournage' => null,
            'lieu' => null,
            'duree' => null,
            'profil_recherche' => 'Créatrices lifestyle',
            'date_limite_candidature' => now()->addWeeks(2)->format('Y-m-d'),
            'nombre_faces_voulu' => 1,
            'type_mission' => MissionType::Ugc->value,
            'genre_voulu' => 'tous',
            'nom_produit' => 'Sneakers',
            'valeur_produit' => 50000,
            'nombre_videos' => 3,
            'montant_remuneration' => 15000,
        ], $overrides);
    }
}
