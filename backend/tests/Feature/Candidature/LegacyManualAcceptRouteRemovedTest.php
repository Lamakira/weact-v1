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
 * Regression guard for FIX-20.3.
 *
 * The legacy manual-accept endpoint POST /api/v1/producer/candidatures/{id}/accept
 * was a pre-FedaPay vestige that transitioned a candidature to Accepted without
 * creating a MissionPayment, producing a dead-end state (the Face confirm endpoint
 * requires MissionPaymentStatus::Paid, which could never be true on that path).
 *
 * This test locks in the removal: hitting the legacy URL must return 404.
 * The paid selection flow (MissionPaymentService::prepareSelectionForPayment) is
 * now the only legitimate path to CandidatureStatus::Accepted.
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

        $response->assertNotFound();
    }
}
