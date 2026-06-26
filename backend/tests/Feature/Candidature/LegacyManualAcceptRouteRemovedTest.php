<?php

declare(strict_types=1);

namespace Tests\Feature\Candidature;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard for FIX-20.3, updated for ugc-8-2.
 *
 * The producer accept endpoint POST /api/v1/producer/candidatures/{id}/accept was
 * reintroduced in ugc-8-2 — but ONLY for UGC product-only candidatures (the
 * explicit candidature cycle replacing the former Face auto-acceptance). The
 * FIX-20.3 invariant still holds for cash/standard missions: a STANDARD mission
 * candidature can NOT be manually accepted (it would be a pre-FedaPay dead-end —
 * the Face confirm endpoint requires MissionPaymentStatus::Paid). So hitting the
 * route with a standard candidature is rejected 422 INVALID_STATUS (the UGC-only
 * guard), not 404. The only path to Accepted on a cash mission remains
 * MissionPaymentService::applySelectionOutcomesOnPaid (FedaPay webhook).
 */
class LegacyManualAcceptRouteRemovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_manual_accept_endpoint_is_removed(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $mission = Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Published,
        ]);

        $face = Face::factory()->create();
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $candidature = Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $face->id,
            'status' => CandidatureStatus::Pending,
        ]);

        $response = $this->actingAs($producerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/accept");

        // The route exists for UGC product-only candidatures, but a STANDARD
        // mission candidature is rejected by the UGC-only guard (ugc-8-2).
        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'INVALID_STATUS');
    }
}
