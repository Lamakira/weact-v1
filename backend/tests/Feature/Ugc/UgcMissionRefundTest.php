<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Enums\UgcRefundReason;
use App\Enums\WalletCreditMotif;
use App\Jobs\HandleFedapayWebhook;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\FedapayWebhookEvent;
use App\Models\FinancialEvent;
use App\Models\Mission;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\BookingService;
use App\Services\FaceSubscriptionPaymentService;
use App\Services\MissionPaymentService;
use App\Services\Ugc\UgcCommissionPaymentService;
use App\Services\Ugc\UgcRefundService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * UGC 2.6 — remboursement de la commission de publication d'une mission UGC
 * terminée sans aucun participant (deadline passée, zéro candidature engagée).
 *
 * Settlement = CRÉDIT WALLET DIRECT synchrone (creditDirect, booking_id null —
 * SUPERSEDE le refund FedaPay de la 2.5). Le User producteur est résolu depuis
 * missions.producer_id (= producers.id). Une mission partiellement engagée
 * n'est JAMAIS remboursée (commission consommée — D-2.5.d). SANS FinancialEvent
 * (asymétrie D-2.6.g / D-1.5.g : audit mission = colonnes). Producteur orphelin
 * → settlement différé (Log::critical, jamais de throw — AC2).
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
        return $this->makeExpiredPaidUgcMissionFor($this->producer, $facesVoulues, $transactionId);
    }

    private function makeExpiredPaidUgcMissionFor(Producer $producer, int $facesVoulues = 3, int $transactionId = 910): Mission
    {
        return $producer->missions()->create([
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
        );
    }

    // ===================================================================
    // Cron — clôture + settlement wallet (AC2, AC4)
    // ===================================================================

    public function test_cron_closes_and_settles_mission_via_wallet(): void
    {
        $mission = $this->makeExpiredPaidUgcMission();
        $before = (int) $this->producerUser->balance;

        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful();

        $mission->refresh();
        $this->assertSame(MissionStatus::Closed, $mission->status);
        $this->assertNotNull($mission->commission_refund_requested_at);
        $this->assertSame(UgcRefundReason::MissionDeadlineExpired, $mission->commission_refund_reason);
        // 2.6 : settlement synchrone — commission_refunded_at posé dans le même appel.
        $this->assertNotNull($mission->commission_refunded_at);

        // Crédit wallet direct (booking_id null) + libellé motif ; balance += commission.
        $this->assertSame($before + 2500, (int) $this->producerUser->fresh()->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $this->producerUser->id,
            'booking_id' => null,
            'type' => 'credit',
            'amount' => 2500,
            'description' => WalletCreditMotif::UgcCommissionRefund->label(),
        ]);

        // Asymétrie D-2.6.g : audit mission = colonnes, PAS de FinancialEvent.
        $this->assertSame(0, FinancialEvent::where('type', 'refund')->count());
        $this->assertSame(1, Notification::where('user_id', $this->producerUser->id)
            ->where('type', 'ugc_commission_refunded')->count());
        // Cycle « requested » supprimé (AC6).
        $this->assertSame(0, Notification::where('type', 'ugc_commission_refund_requested')->count());
    }

    public function test_cron_ignores_mission_with_confirmed_engagement(): void
    {
        // D-2.5.d : engagement partiel → commission de publication consommée,
        // ni clôture ni remboursement.
        $mission = $this->makeExpiredPaidUgcMission();
        Candidature::create([
            'face_id' => $this->face->id,     // candidatures.face_id = faces.id (piège inverse du booking)
            'mission_id' => $mission->id,
            'status' => CandidatureStatus::Confirmed,
        ]);
        $before = (int) $this->producerUser->balance;

        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful();

        $mission->refresh();
        $this->assertSame(MissionStatus::Published, $mission->status);
        $this->assertNull($mission->commission_refund_requested_at);
        $this->assertNull($mission->commission_refunded_at);
        $this->assertSame($before, (int) $this->producerUser->fresh()->balance);
    }

    public function test_cron_settles_mission_with_only_pending_candidatures(): void
    {
        // Une candidature pending n'est PAS un engagement (états 2.4 :
        // confirmed|in_progress|completed) — la mission est remboursée.
        $mission = $this->makeExpiredPaidUgcMission();
        Candidature::create([
            'face_id' => $this->face->id,
            'mission_id' => $mission->id,
            'status' => CandidatureStatus::Pending,
        ]);
        $before = (int) $this->producerUser->balance;

        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful();

        $mission->refresh();
        $this->assertSame(MissionStatus::Closed, $mission->status);
        $this->assertNotNull($mission->commission_refund_requested_at);
        $this->assertNotNull($mission->commission_refunded_at);
        $this->assertSame($before + 2500, (int) $this->producerUser->fresh()->balance);
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
        $before = (int) $this->producerUser->balance;

        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful();

        $mission->refresh();
        $this->assertSame(MissionStatus::PendingPayment, $mission->status);
        $this->assertNull($mission->commission_refund_requested_at);
        $this->assertSame($before, (int) $this->producerUser->fresh()->balance);
    }

    public function test_cron_settlement_is_idempotent(): void
    {
        $mission = $this->makeExpiredPaidUgcMission();
        $before = (int) $this->producerUser->balance;

        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful();
        $firstRefundedAt = $mission->fresh()->commission_refunded_at;

        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful();

        $mission->refresh();
        $this->assertSame(MissionStatus::Closed, $mission->status);
        $this->assertEquals(
            $firstRefundedAt?->toIso8601String(),
            $mission->commission_refunded_at?->toIso8601String(),
        );
        $this->assertSame($before + 2500, (int) $this->producerUser->fresh()->balance);
        $this->assertSame(1, WalletTransaction::where('user_id', $this->producerUser->id)
            ->where('type', 'credit')->count());
        $this->assertSame(1, Notification::where('type', 'ugc_commission_refunded')->count());
    }

    // ===================================================================
    // Gardes type-owner, orphelin & webhook neutralisé (AC2, AC5)
    // ===================================================================

    public function test_settlement_ignores_non_ugc_mission(): void
    {
        // Garde type-owner : settleRefundForMission sur une mission standard ne
        // stampe rien et ne crédite pas.
        $mission = $this->makeExpiredPaidUgcMission(transactionId: 914);
        $mission->update([
            'type_mission' => 'publicite',
            'commission_paid_at' => null,
            'commission_ugc' => null,
        ]);
        $before = (int) $this->producerUser->balance;

        app(UgcRefundService::class)->settleRefundForMission($mission->fresh(), UgcRefundReason::MissionDeadlineExpired);

        $mission->refresh();
        $this->assertNull($mission->commission_refunded_at);
        $this->assertSame(MissionStatus::Published, $mission->status);
        $this->assertSame($before, (int) $this->producerUser->fresh()->balance);
        $this->assertSame(0, Notification::where('type', 'ugc_commission_refunded')->count());
    }

    public function test_settlement_skips_orphan_producer_without_crediting(): void
    {
        // AC2 : User producteur introuvable (orphelin) → Log::critical, ne PAS
        // poser commission_refunded_at (réconciliation), aucun crédit, jamais de throw.
        $orphanProducer = Producer::factory()->create(); // aucun User userable associé
        $mission = $this->makeExpiredPaidUgcMissionFor($orphanProducer, transactionId: 920);
        Log::spy();

        app(UgcRefundService::class)->settleRefundForMission($mission, UgcRefundReason::MissionDeadlineExpired);

        $mission->refresh();
        $this->assertNull($mission->commission_refunded_at);
        $this->assertSame(0, WalletTransaction::where('type', 'credit')->count());
        $this->assertSame(0, Notification::where('type', 'ugc_commission_refunded')->count());
        Log::shouldHaveReceived('critical')
            ->withArgs(fn (string $message): bool => str_contains($message, 'producteur introuvable'))
            ->once();
    }

    public function test_webhook_refunded_ugc_mission_is_neutralized_no_settlement(): void
    {
        // AC5 / D-2.6.f : transaction.refunded sur une mission UGC ne règle plus
        // rien (le refund se fait via wallet). Log défensif, aucun crédit, pas de
        // clôture forcée, queue non empoisonnée.
        $mission = $this->makeExpiredPaidUgcMission(transactionId: 913);
        $before = (int) $this->producerUser->balance;
        Log::spy();

        $this->dispatchWebhook('transaction.refunded', 913, 'ref_oob_m');

        $mission->refresh();
        $this->assertNull($mission->commission_refunded_at);
        $this->assertSame(MissionStatus::Published, $mission->status); // pas de clôture forcée
        $this->assertSame($before, (int) $this->producerUser->fresh()->balance);
        $this->assertSame(0, WalletTransaction::where('type', 'credit')->count());
        $this->assertDatabaseMissing('fedapay_webhook_events', ['status' => 'received']);
        Log::shouldHaveReceived('critical')
            ->withArgs(fn (string $message): bool => str_contains($message, 'refund UGC inattendu'))
            ->once();
    }

    public function test_settlement_forces_close_on_still_published_mission(): void
    {
        // Défense-en-profondeur (restaure le force-close 2.5) : une réconciliation
        // ops directe sur une mission UGC encore Published doit la fermer dans la
        // même transaction que le crédit — aucune policy mission ne garde refunded_at.
        $mission = $this->makeExpiredPaidUgcMission(transactionId: 921);
        $this->assertSame(MissionStatus::Published, $mission->status);
        $before = (int) $this->producerUser->balance;

        app(UgcRefundService::class)->settleRefundForMission($mission, UgcRefundReason::MissionDeadlineExpired);

        $mission->refresh();
        $this->assertSame(MissionStatus::Closed, $mission->status); // clôture forcée
        $this->assertNotNull($mission->commission_refunded_at);
        $this->assertSame($before + 2500, (int) $this->producerUser->fresh()->balance);
    }

    public function test_cron_skips_already_settled_published_mission(): void
    {
        // Symétrie avec test_cron_skips_already_settled_booking : une mission déjà
        // réglée (refunded_at posé) ne doit ni être re-clôturée ni re-créditée, même
        // si son statut est resté Published (cas hors-cron / legacy).
        $mission = $this->makeExpiredPaidUgcMission(transactionId: 922);
        $mission->update(['commission_refunded_at' => now()]);
        $before = (int) $this->producerUser->balance;

        $this->artisan('ugc:expire-unaccepted-deals')->assertSuccessful();

        $mission->refresh();
        $this->assertSame(MissionStatus::Published, $mission->status); // non re-clôturée
        $this->assertSame($before, (int) $this->producerUser->fresh()->balance);
        $this->assertSame(0, WalletTransaction::where('user_id', $this->producerUser->id)
            ->where('type', 'credit')->count());
    }

    public function test_settlement_skips_zero_commission_without_crediting(): void
    {
        // Garde anti 0-crédit : une mission encaissée mais commission_ugc nulle/0
        // (anomalie de données) ne doit PAS être marquée remboursée ni créditer 0.
        $mission = $this->makeExpiredPaidUgcMission(transactionId: 923);
        $mission->update(['commission_ugc' => 0]);
        $before = (int) $this->producerUser->balance;
        Log::spy();

        app(UgcRefundService::class)->settleRefundForMission($mission->fresh(), UgcRefundReason::MissionDeadlineExpired);

        $mission->refresh();
        $this->assertNull($mission->commission_refunded_at);
        $this->assertSame($before, (int) $this->producerUser->fresh()->balance);
        $this->assertSame(0, WalletTransaction::where('type', 'credit')->count());
        Log::shouldHaveReceived('critical')
            ->withArgs(fn (string $message): bool => str_contains($message, 'commission_ugc mission absente/invalide'))
            ->once();
    }

    public function test_close_rolls_back_when_wallet_credit_fails_then_settles_on_retry(): void
    {
        // Revue 2.6 #1 — atomicité : clôture + crédit wallet dans la MÊME transaction.
        // Si le crédit jette, le passage à Closed est annulé (rollback) → la mission
        // reste published et le tick cron suivant règle (reprise automatique).
        $mission = $this->makeExpiredPaidUgcMission(transactionId: 924);
        $before = (int) $this->producerUser->balance;
        Log::spy();

        // 1ʳᵉ tentative : wallet en panne (creditDirect jette) → rollback complet.
        $throwingWallet = new class extends WalletService
        {
            public function creditDirect(int $userId, int $amount, string $description): WalletTransaction
            {
                throw new \RuntimeException('wallet indisponible');
            }
        };
        $this->assertFalse((new UgcRefundService($throwingWallet))->closeMissionPastDeadlineWithoutEngagement($mission));

        $mission->refresh();
        $this->assertSame(MissionStatus::Published, $mission->status); // pas de clôture
        $this->assertNull($mission->commission_refunded_at);
        $this->assertNull($mission->commission_refund_requested_at);
        $this->assertSame($before, (int) $this->producerUser->fresh()->balance);
        $this->assertSame(0, WalletTransaction::where('type', 'credit')->count());
        Log::shouldHaveReceived('critical')
            ->withArgs(fn (string $message): bool => str_contains($message, 'échec clôture/settlement mission'))
            ->once();

        // 2ᵉ tentative (wallet rétabli) : règle dans le même appel (reprise auto).
        $this->assertTrue(app(UgcRefundService::class)->closeMissionPastDeadlineWithoutEngagement($mission->fresh()));

        $mission->refresh();
        $this->assertSame(MissionStatus::Closed, $mission->status);
        $this->assertNotNull($mission->commission_refunded_at);
        $this->assertSame($before + 2500, (int) $this->producerUser->fresh()->balance);
        $this->assertSame(1, WalletTransaction::where('user_id', $this->producerUser->id)
            ->where('type', 'credit')->count());
    }
}
