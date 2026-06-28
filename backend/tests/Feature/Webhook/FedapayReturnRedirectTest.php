<?php

declare(strict_types=1);

namespace Tests\Feature\Webhook;

use App\Enums\AttendanceStatus;
use App\Enums\CompensationType;
use App\Enums\EscrowStatus;
use App\Enums\MissionType;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
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

    public function test_ugc_product_only_commission_payment_redirects_to_its_mission_candidatures(): void
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
            $this->frontend()."/producer/missions/{$mission->id}/candidatures?payment=pending"
        );
    }

    public function test_ugc_hybrid_candidature_escrow_payment_redirects_to_the_mission_candidatures(): void
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
            $this->frontend()."/producer/missions/{$mission->id}/candidatures?payment=pending"
        );
    }

    public function test_unknown_transaction_falls_back_to_bookings(): void
    {
        $response = $this->get('/api/v1/webhooks/fedapay?id=999999999');

        $response->assertRedirect($this->frontend().'/producer/bookings?payment=pending');
    }
}
