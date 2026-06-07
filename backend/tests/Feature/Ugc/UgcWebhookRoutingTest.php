<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc;

use App\Enums\BookingStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionStatus;
use App\Jobs\HandleFedapayWebhook;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\FedapayWebhookEvent;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use App\Models\User;
use App\Services\BookingService;
use App\Services\FaceEntitlementService;
use App\Services\FaceSubscriptionPaymentService;
use App\Services\MissionPaymentService;
use App\Services\Ugc\UgcCommissionPaymentService;
use App\Services\WalletService;
use App\ValueObjects\MissionPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Cross-domain non-regression: a single HandleFedapayWebhook must route each
 * transaction.* to exactly the right owner — cash booking, UGC booking, UGC
 * mission, and standard MissionPayment — without any leakage across domains.
 */
class UgcWebhookRoutingTest extends TestCase
{
    use RefreshDatabase;

    private Producer $producer;

    private User $producerUser;

    private User $faceUser;

    private int $webhookSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);

        $face = Face::factory()->create(['is_available' => true]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
    }

    public function test_each_transaction_webhook_routes_to_its_own_domain(): void
    {
        Mail::fake();

        // tx 1001 — cash booking (escrow path)
        $cashBooking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'type_contenu' => 'Publicité',
            'fedapay_transaction_id' => 1001,
        ]);

        // tx 1002 — UGC booking (commission_paid, no escrow)
        $ugcBooking = Booking::create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => BookingStatus::Pending,
            'date_debut' => now()->addWeek(),
            'date_fin' => now()->addWeeks(2),
            'duree_heures' => 8,
            'type_contenu' => 'UGC',
            'lieu' => 'Cotonou',
            'tarif_base' => 0,
            'montant_face_recoit' => 0,
            'montant_total_producteur' => 2500,
            'type_compensation' => 'product',
            'nom_produit' => 'Tenue Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'commission_ugc' => 2500,
            'fedapay_transaction_id' => 1002,
        ]);

        // tx 1003 — UGC mission (pending_payment → published)
        $ugcMission = $this->producer->missions()->create([
            'titre' => 'Appel UGC — Unboxing',
            'description' => 'desc',
            'date_tournage' => now()->addMonth(),
            'profil_recherche' => 'Créatrices',
            'budget' => 0,
            'date_limite_candidature' => now()->addWeeks(2),
            'nombre_faces_voulu' => 3,
            'type_mission' => 'ugc',
            'genre_voulu' => 'femme',
            'lieu' => 'Cotonou',
            'duree' => 'Livrables vidéo',
            'status' => MissionStatus::PendingPayment,
            'type_compensation' => 'product',
            'nom_produit' => 'Tenue Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'commission_ugc' => 2500,
            'fedapay_transaction_id' => 1003,
        ]);

        // tx 1004 — standard MissionPayment (escrow path)
        $missionPayment = $this->makeStandardMissionPayment('1004');

        // Dispatch all four approvals through the SAME webhook handler.
        $this->dispatchWebhook('transaction.approved', 1001, 'ref_cash');
        $this->dispatchWebhook('transaction.approved', 1002, 'ref_ugc_booking');
        $this->dispatchWebhook('transaction.approved', 1003, 'ref_ugc_mission');
        $this->dispatchWebhook('transaction.approved', 1004, 'ref_mission_payment');

        // Cash booking → paid + escrow locked.
        $this->assertSame(BookingStatus::Paid, $cashBooking->fresh()->status);
        $this->assertDatabaseHas('escrow_transactions', ['booking_id' => $cashBooking->id]);

        // UGC booking → commission_paid, NO escrow.
        $this->assertSame(BookingStatus::CommissionPaid, $ugcBooking->fresh()->status);
        $this->assertDatabaseMissing('escrow_transactions', ['booking_id' => $ugcBooking->id]);

        // UGC mission → published.
        $this->assertSame(MissionStatus::Published, $ugcMission->fresh()->status);
        $this->assertNotNull($ugcMission->fresh()->commission_paid_at);

        // Standard MissionPayment → paid.
        $this->assertSame('paid', $missionPayment->fresh()->status->value);
    }

    private function makeStandardMissionPayment(string $fedapayTransactionId): MissionPayment
    {
        $mission = Mission::factory()->published()->create([
            'producer_id' => $this->producer->id,
            'budget' => 90000,
            'date_limite_candidature' => now()->addWeek(),
        ]);

        $selected = [
            $this->createPendingCandidature($mission),
            $this->createPendingCandidature($mission),
        ];

        $pricing = new MissionPricing($mission->budget, count($selected));
        $entitlements = app(FaceEntitlementService::class);

        $commissionFacesTotal = 0;
        $montantTotalFaces = 0;
        $entries = [];

        foreach ($selected as $candidature) {
            $rate = $entitlements->capabilities($candidature->face)->commissionRate;
            $montantFaceRecoit = $pricing->budgetParFace - (int) round($pricing->budgetParFace * $rate);
            $commissionFacesTotal += $pricing->budgetParFace - $montantFaceRecoit;
            $montantTotalFaces += $montantFaceRecoit;
            $entries[] = ['candidature' => $candidature, 'montant_face_recoit' => $montantFaceRecoit];
        }

        $payment = MissionPayment::query()->create([
            'mission_id' => $mission->id,
            'producer_id' => $this->producer->id,
            'nombre_faces_retenues' => count($selected),
            'budget_par_face' => $pricing->budgetParFace,
            'montant_sous_total' => $pricing->sousTotal,
            'commission_producteur' => $pricing->commissionProducteur,
            'montant_total_producteur' => $pricing->montantTotalProducteur,
            'commission_faces_total' => $commissionFacesTotal,
            'montant_total_faces' => $montantTotalFaces,
            'fedapay_transaction_id' => $fedapayTransactionId,
            'status' => 'pending',
        ]);

        foreach ($entries as $entry) {
            MissionPaymentCandidature::query()->create([
                'mission_payment_id' => $payment->id,
                'candidature_id' => $entry['candidature']->id,
                'face_id' => $entry['candidature']->face_id,
                'montant_face_recoit' => $entry['montant_face_recoit'],
                'escrow_status' => EscrowStatus::Pending,
            ]);
        }

        $mission->update(['status' => MissionStatus::PendingPayment]);

        return $payment->fresh();
    }

    private function createPendingCandidature(Mission $mission): Candidature
    {
        $face = Face::factory()->create();

        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        return Candidature::factory()->pending()->create([
            'face_id' => $face->id,
            'mission_id' => $mission->id,
        ]);
    }

    private function dispatchWebhook(string $eventName, int $transactionId, string $reference): void
    {
        $this->webhookSeq++;
        $payload = ['entity' => ['id' => $transactionId, 'reference' => $reference]];

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
}
