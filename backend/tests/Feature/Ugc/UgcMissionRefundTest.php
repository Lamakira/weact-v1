<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Enums\UgcRefundReason;
use App\Jobs\HandleFedapayWebhook;
use App\Mail\UgcRefundRequestedMail;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\FedapayWebhookEvent;
use App\Models\FinancialEvent;
use App\Models\Mission;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use App\Services\BookingService;
use App\Services\FaceSubscriptionPaymentService;
use App\Services\MissionPaymentService;
use App\Services\Ugc\UgcCommissionPaymentService;
use App\Services\Ugc\UgcRefundService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * UGC 2.5 — remboursement de la commission de publication d'une mission UGC
 * terminée sans aucun participant (deadline passée, zéro candidature engagée).
 *
 * Une mission partiellement engagée n'est JAMAIS remboursée (la commission de
 * publication est consommée — D-2.5.d). Settlement par webhook
 * transaction.refunded, SANS FinancialEvent (asymétrie D-1.5.g).
 */
class UgcMissionRefundTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    private User $faceUser;

    private Face $face;

    private int $webhookSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['app.admin_email' => 'ops@weact.test']);

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);

        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    private function makeExpiredPaidUgcMission(int $facesVoulues = 3, int $transactionId = 910): Mission
    {
        return $this->producer->missions()->create([
            'titre' => 'Appel UGC — Unboxing',
            'description' => 'desc',
            'date_tournage' => now()->addMonth(),
            'profil_recherche' => 'Créatrices',
            'budget' => 0,
            'date_limite_candidature' => now()->subDays(2),   // deadline passée
            'nombre_faces_voulu' => $facesVoulues,
            'type_mission' => 'ugc',
            'genre_voulu' => 'femme',
            'lieu' => 'Cotonou',
            'duree' => 'Livrables vidéo',
            'status' => MissionStatus::Published,
            'type_compensation' => 'product',
            'nom_produit' => 'Tenue Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'montant_remuneration' => null,
            'commission_ugc' => 2500,
            'fedapay_transaction_id' => $transactionId,    // missions.fedapay_transaction_id est UNIQUE (1.5) : id distinct par mission
            'commission_paid_at' => now()->subDays(10),
        ]);
    }

    private function dispatchWebhook(string $eventName, int $transactionId, string $reference, ?string $transactionStatus = null): void
    {
        $this->webhookSeq++;
        $entity = ['id' => $transactionId, 'reference' => $reference];

        if ($transactionStatus !== null) {
            $entity['status'] = $transactionStatus;
        }

        $payload = ['entity' => $entity];

        $webhookEvent = FedapayWebhookEvent::create([
            'fedapay_event_id' => "evt_{$transactionId}_{$this->webhookSeq}",
            'event_name' => $eventName,
            'payload' => $payload,
            'status' => 'received',
        ]);

        (new HandleFedapayWebhook($webhookEvent->id, $eventName, $payload))->handle(
            app(BookingService::class),
            app(MissionPaymentService::class),
            app(WalletService::class),
            app(FaceSubscriptionPaymentService::class),
            app(UgcCommissionPaymentService::class),
            app(UgcRefundService::class),
        );
    }

    // ===================================================================
    // Cron — clôture + demande de remboursement (AC5)
    // ===================================================================

    public function test_cron_closes_and_requests_refund_for_paid_mission_past_deadline_without_engagement(): void
    {
        $mission = $this->makeExpiredPaidUgcMission();

        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful();

        $mission->refresh();
        $this->assertSame(MissionStatus::Closed, $mission->status);
        $this->assertNotNull($mission->commission_refund_requested_at);
        $this->assertSame(UgcRefundReason::MissionDeadlineExpired, $mission->commission_refund_reason);

        Mail::assertSent(UgcRefundRequestedMail::class, fn (UgcRefundRequestedMail $mail): bool => $mail->owner->id === $mission->id
            && $mail->reason === UgcRefundReason::MissionDeadlineExpired
            && $mail->hasTo('ops@weact.test'));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->producerUser->id,
            'type' => 'ugc_commission_refund_requested',
        ]);
    }

    public function test_cron_ignores_mission_with_confirmed_engagement(): void
    {
        // D-2.5.d : engagement partiel → commission de publication consommée,
        // ni clôture ni remboursement (lifecycle de clôture = épic 3).
        $mission = $this->makeExpiredPaidUgcMission();
        Candidature::create([
            'face_id' => $this->face->id,     // candidatures.face_id = faces.id (piège inverse du booking)
            'mission_id' => $mission->id,
            'status' => CandidatureStatus::Confirmed,
        ]);

        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful();

        $mission->refresh();
        $this->assertSame(MissionStatus::Published, $mission->status);
        $this->assertNull($mission->commission_refund_requested_at);
        Mail::assertNotSent(UgcRefundRequestedMail::class);
    }

    public function test_cron_refunds_mission_with_only_pending_candidatures(): void
    {
        // Une candidature pending n'est PAS un engagement (états 2.4 :
        // confirmed|in_progress|completed) — la mission est remboursée.
        $mission = $this->makeExpiredPaidUgcMission();
        Candidature::create([
            'face_id' => $this->face->id,
            'mission_id' => $mission->id,
            'status' => CandidatureStatus::Pending,
        ]);

        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful();

        $mission->refresh();
        $this->assertSame(MissionStatus::Closed, $mission->status);
        $this->assertNotNull($mission->commission_refund_requested_at);
    }

    public function test_cron_ignores_mission_with_deadline_today(): void
    {
        // Borne jour-inclusif : miroir exact de Mission::isAcceptingCandidatures().
        $mission = $this->makeExpiredPaidUgcMission(transactionId: 911);
        $mission->update(['date_limite_candidature' => now()->toDateString()]);

        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful();

        $mission->refresh();
        $this->assertSame(MissionStatus::Published, $mission->status);
        $this->assertNull($mission->commission_refund_requested_at);
    }

    public function test_cron_ignores_standard_mission_past_deadline(): void
    {
        $standard = $this->producer->missions()->create([
            'titre' => 'Pub standard',
            'description' => 'desc',
            'date_tournage' => now()->addMonth(),
            'profil_recherche' => 'Acteurs',
            'budget' => 50000,
            'date_limite_candidature' => now()->subDays(2),
            'nombre_faces_voulu' => 1,
            'type_mission' => 'publicite',
            'genre_voulu' => 'homme',
            'lieu' => 'Cotonou',
            'duree' => '2 jours',
            'status' => MissionStatus::Published,
        ]);

        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful();

        $standard->refresh();
        $this->assertSame(MissionStatus::Published, $standard->status);
        $this->assertNull($standard->commission_refund_requested_at);
    }

    public function test_cron_ignores_unpaid_pending_payment_ugc_mission(): void
    {
        $mission = $this->makeExpiredPaidUgcMission(transactionId: 912);
        $mission->update([
            'status' => MissionStatus::PendingPayment,
            'commission_paid_at' => null,
            'fedapay_transaction_id' => null,
        ]);

        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful();

        $mission->refresh();
        $this->assertSame(MissionStatus::PendingPayment, $mission->status);
        $this->assertNull($mission->commission_refund_requested_at);
        Mail::assertNotSent(UgcRefundRequestedMail::class);
    }

    public function test_cron_is_idempotent(): void
    {
        $mission = $this->makeExpiredPaidUgcMission();

        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful();
        $firstRequestedAt = $mission->fresh()->commission_refund_requested_at;

        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful();

        $mission->refresh();
        $this->assertSame(MissionStatus::Closed, $mission->status);
        $this->assertEquals(
            $firstRequestedAt?->toIso8601String(),
            $mission->commission_refund_requested_at?->toIso8601String(),
        );
        Mail::assertSent(UgcRefundRequestedMail::class, 1);
    }

    // ===================================================================
    // Settlement — webhook transaction.refunded (AC6, AC8c)
    // ===================================================================

    public function test_webhook_refunded_settles_mission_refund_without_financial_event(): void
    {
        $mission = $this->makeExpiredPaidUgcMission();
        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful(); // Closed + demande

        $this->dispatchWebhook('transaction.refunded', 910, 'ref_refund_m');

        $mission->refresh();
        $this->assertNotNull($mission->commission_refunded_at);
        $this->assertSame(MissionStatus::Closed, $mission->status);
        // Asymétrie D-1.5.g : audit mission = colonnes, PAS de FinancialEvent.
        $this->assertSame(0, FinancialEvent::where('type', 'refund')->count());
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->producerUser->id,
            'type' => 'ugc_commission_refunded',
        ]);
        $this->assertDatabaseMissing('fedapay_webhook_events', ['status' => 'received']);
    }

    public function test_webhook_refunded_replay_is_noop(): void
    {
        $mission = $this->makeExpiredPaidUgcMission();
        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful();

        $this->dispatchWebhook('transaction.refunded', 910, 'ref_refund_m');
        $firstRefundedAt = $mission->fresh()->commission_refunded_at;

        $this->dispatchWebhook('transaction.refunded', 910, 'ref_refund_m');

        $mission->refresh();
        $this->assertEquals(
            $firstRefundedAt?->toIso8601String(),
            $mission->commission_refunded_at?->toIso8601String(),
        );
        $this->assertSame(1, Notification::where('type', 'ugc_commission_refunded')->count());
    }

    public function test_webhook_refunded_on_still_published_mission_forces_close(): void
    {
        // AC8c : une mission dont la commission de publication est remboursée
        // ne doit plus être découvrable/acceptable — clôture forcée dans la
        // même transaction que le settlement.
        $mission = $this->makeExpiredPaidUgcMission(transactionId: 913);

        $this->dispatchWebhook('transaction.refunded', 913, 'ref_oob_m');

        $mission->refresh();
        $this->assertSame(MissionStatus::Closed, $mission->status);
        $this->assertNotNull($mission->commission_refunded_at);
        $this->assertNull($mission->commission_refund_requested_at);
    }

    public function test_webhook_partial_refund_does_not_settle_mission(): void
    {
        // Revue 2.5 : un refund PARTIEL (statut FedaPay contenant
        // partially_refunded) ne règle pas la demande mission.
        $mission = $this->makeExpiredPaidUgcMission();
        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful(); // Closed + demande

        $this->dispatchWebhook('transaction.refunded', 910, 'ref_partial_m', 'approved_partially_refunded');

        $mission->refresh();
        $this->assertNull($mission->commission_refunded_at);
        $this->assertSame(0, Notification::where('type', 'ugc_commission_refunded')->count());
        $this->assertDatabaseMissing('fedapay_webhook_events', ['status' => 'received']);
    }

    public function test_settlement_ignores_non_ugc_mission(): void
    {
        // Revue 2.5 : garde type-owner — un appel tinker (runbook §4) sur un
        // mauvais id (mission standard) ne stampe rien.
        $mission = $this->makeExpiredPaidUgcMission(transactionId: 914);
        $mission->update([
            'type_mission' => 'publicite',
            'commission_paid_at' => null,
            'commission_ugc' => null,
        ]);

        app(UgcRefundService::class)->markMissionCommissionRefunded($mission->fresh(), '914');

        $mission->refresh();
        $this->assertNull($mission->commission_refunded_at);
        $this->assertSame(MissionStatus::Published, $mission->status);
        $this->assertSame(0, Notification::where('type', 'ugc_commission_refunded')->count());
    }
}
