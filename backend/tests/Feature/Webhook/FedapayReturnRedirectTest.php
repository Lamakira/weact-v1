<?php

declare(strict_types=1);

namespace Tests\Feature\Webhook;

use App\Enums\AttendanceStatus;
use App\Enums\CompensationType;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionType;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le handler de retour navigateur FedaPay (GET /api/v1/webhooks/fedapay?id=...)
 * doit ramener le Producteur sur SA mission après un paiement UGC — pas sur
 * « Mes bookings ». Le fedapay_transaction_id d'un paiement UGC ne vit PAS dans
 * mission_payments : produit-seul → missions ; hybride par-Face → mission_payment_candidatures.
 */
class FedapayReturnRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function frontend(): string
    {
        return rtrim((string) config('app.frontend_url'), '/');
    }

    public function test_ugc_product_only_commission_payment_redirects_to_the_missions_list_with_mission_commission_return(): void
    {
        // La commission produit-seul est payée au publish ; le tx est stocké SUR la mission.
        $producer = Producer::factory()->create();
        $mission = Mission::factory()->for($producer)->published()->create([
            'type_mission' => MissionType::Ugc,
            'type_compensation' => CompensationType::Product,
            // FedaPay envoie un id numérique ; missions.fedapay_transaction_id est entier.
            'fedapay_transaction_id' => '900001',
        ]);

        $response = $this->get('/api/v1/webhooks/fedapay?id=900001');

        $response->assertRedirect(
            $this->frontend()."/producer/missions?payment_return=mission_commission&mission={$mission->uuid}"
        );
    }

    public function test_ugc_hybrid_candidature_escrow_payment_redirects_to_the_mission_candidatures_with_escrow_return(): void
    {
        // L'escrow hybride par-Face est payé à l'acceptation ; le tx est sur mission_payment_candidatures.
        $producer = Producer::factory()->create();
        $mission = Mission::factory()->for($producer)->published()->create([
            'type_mission' => MissionType::Ugc,
            'type_compensation' => CompensationType::Hybrid,
        ]);
        $face = Face::factory()->create();
        $candidature = Candidature::factory()->for($mission)->for($face)->accepted()->create();

        MissionPaymentCandidature::create([
            'mission_payment_id' => null,
            'fedapay_transaction_id' => '900002',
            'candidature_id' => $candidature->id,
            'face_id' => $face->id,
            'montant_face_recoit' => 50000,
            'escrow_status' => EscrowStatus::Locked,
            'attendance_status' => AttendanceStatus::Pending,
        ]);

        $response = $this->get('/api/v1/webhooks/fedapay?id=900002');

        $response->assertRedirect(
            $this->frontend()."/producer/missions/{$mission->uuid}/candidatures?payment_return=candidature_escrow&candidature={$candidature->uuid}"
        );
    }

    public function test_cash_mission_selection_payment_redirects_with_mission_selection_return(): void
    {
        $producer = Producer::factory()->create();
        $mission = Mission::factory()->for($producer)->published()->create();
        MissionPayment::create([
            'mission_id' => $mission->id,
            'producer_id' => $producer->id,
            'fedapay_transaction_id' => '900003',
            'nombre_faces_retenues' => 1,
            'budget_par_face' => 100000,
            'montant_sous_total' => 100000,
            'commission_producteur' => 10000,
            'montant_total_producteur' => 110000,
            'commission_faces_total' => 10000,
            'montant_total_faces' => 90000,
            'status' => MissionPaymentStatus::Pending,
        ]);

        $this->get('/api/v1/webhooks/fedapay?id=900003')->assertRedirect(
            $this->frontend()."/producer/missions/{$mission->uuid}/candidatures?payment_return=mission_selection"
        );
    }

    public function test_cash_booking_payment_redirects_to_booking_detail_with_booking_return(): void
    {
        $booking = Booking::factory()->accepted()->create([
            'type_contenu' => 'Publicité',
            'fedapay_transaction_id' => 900004,
        ]);

        $this->get('/api/v1/webhooks/fedapay?id=900004')->assertRedirect(
            $this->frontend()."/producer/bookings/{$booking->uuid}?payment_return=booking"
        );
    }

    public function test_ugc_booking_commission_payment_redirects_with_booking_commission_return(): void
    {
        $booking = Booking::factory()->accepted()->create([
            'type_contenu' => 'UGC',
            'fedapay_transaction_id' => 900005,
        ]);

        $this->get('/api/v1/webhooks/fedapay?id=900005')->assertRedirect(
            $this->frontend()."/producer/bookings/{$booking->uuid}?payment_return=booking_commission"
        );
    }

    public function test_face_subscription_payment_redirects_to_billing_with_subscription_return(): void
    {
        FaceSubscription::factory()->pendingPayment()->create([
            'provider_reference' => '900006',
        ]);

        $this->get('/api/v1/webhooks/fedapay?id=900006')->assertRedirect(
            $this->frontend().'/face/billing?payment_return=subscription'
        );
    }

    public function test_whitelisted_fedapay_status_is_forwarded_as_a_display_hint(): void
    {
        $booking = Booking::factory()->accepted()->create([
            'type_contenu' => 'Publicité',
            'fedapay_transaction_id' => 900010,
        ]);

        foreach (['approved', 'canceled', 'declined'] as $status) {
            $this->get("/api/v1/webhooks/fedapay?id=900010&status={$status}")->assertRedirect(
                $this->frontend()."/producer/bookings/{$booking->uuid}?payment_return=booking&fedapay_status={$status}"
            );
        }
    }

    public function test_unknown_fedapay_status_is_dropped(): void
    {
        $booking = Booking::factory()->accepted()->create([
            'type_contenu' => 'Publicité',
            'fedapay_transaction_id' => 900011,
        ]);

        $this->get('/api/v1/webhooks/fedapay?id=900011&status=%3Cscript%3E')->assertRedirect(
            $this->frontend()."/producer/bookings/{$booking->uuid}?payment_return=booking"
        );
        $this->get('/api/v1/webhooks/fedapay?id=900011&status[]=canceled')->assertRedirect(
            $this->frontend()."/producer/bookings/{$booking->uuid}?payment_return=booking"
        );
    }

    public function test_unknown_transaction_falls_back_to_the_neutral_payment_return_page(): void
    {
        $this->get('/api/v1/webhooks/fedapay?id=999999999')
            ->assertRedirect($this->frontend().'/paiement/retour');
    }

    public function test_missing_transaction_id_falls_back_to_the_neutral_page_keeping_the_whitelisted_hint(): void
    {
        $this->get('/api/v1/webhooks/fedapay?status=approved')
            ->assertRedirect($this->frontend().'/paiement/retour?fedapay_status=approved');
    }

    public function test_fallback_forwards_canceled_and_drops_an_unknown_status(): void
    {
        $this->get('/api/v1/webhooks/fedapay?id=999999999&status=canceled')
            ->assertRedirect($this->frontend().'/paiement/retour?fedapay_status=canceled');
        $this->get('/api/v1/webhooks/fedapay?id=999999999&status=hacked')
            ->assertRedirect($this->frontend().'/paiement/retour');
    }

    public function test_declined_hybrid_escrow_entry_already_deleted_by_the_webhook_falls_back_with_the_hint(): void
    {
        // Le webhook decline supprime l'entry (markUgcMissionCandidatureFailed) AVANT que
        // le navigateur n'arrive : plus aucune entité à retrouver par l'id de transaction.
        $producer = Producer::factory()->create();
        $mission = Mission::factory()->for($producer)->published()->create([
            'type_mission' => MissionType::Ugc,
            'type_compensation' => CompensationType::Hybrid,
        ]);
        $face = Face::factory()->create();
        $candidature = Candidature::factory()->for($mission)->for($face)->create();
        $entry = MissionPaymentCandidature::create([
            'mission_payment_id' => null,
            'fedapay_transaction_id' => '900020',
            'candidature_id' => $candidature->id,
            'face_id' => $face->id,
            'montant_face_recoit' => 50000,
            'escrow_status' => EscrowStatus::Pending,
            'attendance_status' => AttendanceStatus::Pending,
        ]);
        app(\App\Services\MissionPaymentService::class)->markUgcMissionCandidatureFailed($entry, 'webhook_declined');

        $this->get('/api/v1/webhooks/fedapay?id=900020&status=declined')
            ->assertRedirect($this->frontend().'/paiement/retour?fedapay_status=declined');
    }

    public function test_the_return_route_is_throttled(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->get('/api/v1/webhooks/fedapay?id=1')->assertRedirect();
        }

        $this->get('/api/v1/webhooks/fedapay?id=1')->assertStatus(429);
    }
}
