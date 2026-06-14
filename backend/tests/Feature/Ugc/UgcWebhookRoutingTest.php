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
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\FaceEntitlementService;
use App\ValueObjects\MissionPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Ugc\Concerns\DispatchesFedapayWebhooks;
use Tests\TestCase;

/**
 * Cross-domain non-regression: a single HandleFedapayWebhook must route each
 * transaction.* to exactly the right owner — cash booking, UGC booking, UGC
 * mission, and standard MissionPayment — without any leakage across domains.
 */
class UgcWebhookRoutingTest extends TestCase
{
    use DispatchesFedapayWebhooks;
    use RefreshDatabase;

    private Producer $producer;

    private User $producerUser;

    private User $faceUser;

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

    public function test_transaction_refunded_ugc_booking_is_neutralized_and_does_not_leak(): void
    {
        // Story 2.6 (AC5 / D-2.6.f) : le routage transaction.refunded UGC ne
        // déclenche plus aucun settlement (refund = crédit wallet synchrone).
        // Log défensif, aucune fuite cross-domaine, balance Producteur inchangée.
        Mail::fake();
        Log::spy();

        $ugcBooking = Booking::create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => BookingStatus::CommissionPaid,
            'date_debut' => null,
            'date_fin' => null,
            'duree_heures' => null,
            'type_contenu' => 'UGC',
            'lieu' => null,
            'tarif_base' => 0,
            'montant_face_recoit' => 0,
            'montant_total_producteur' => 2500,
            'type_compensation' => 'product',
            'nom_produit' => 'Tenue Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'commission_ugc' => 2500,
            'fedapay_transaction_id' => 2002,
            'commission_paid_at' => now()->subDay(),
        ]);
        $before = (int) $this->producerUser->fresh()->balance;

        $this->dispatchWebhook('transaction.refunded', 2002, 'ref_ugc_refund');

        $ugcBooking->refresh();
        $this->assertNull($ugcBooking->commission_refunded_at);
        $this->assertSame(BookingStatus::CommissionPaid, $ugcBooking->status);
        $this->assertSame($before, (int) $this->producerUser->fresh()->balance);
        $this->assertSame(0, WalletTransaction::where('user_id', $this->producerUser->id)->count());
        $this->assertDatabaseMissing('financial_events', ['booking_id' => $ugcBooking->id, 'type' => 'refund']);
        $this->assertDatabaseMissing('fedapay_webhook_events', ['status' => 'received']);
        Log::shouldHaveReceived('critical')
            ->withArgs(fn (string $message): bool => str_contains($message, 'refund UGC inattendu'))
            ->once();
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
}
