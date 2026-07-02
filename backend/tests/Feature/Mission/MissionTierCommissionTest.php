<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Models\Candidature;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use App\Services\FedapayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MissionTierCommissionTest extends TestCase
{
    use RefreshDatabase;

    private Producer $producer;

    private User $producerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
    }

    /** Published mission with the given per-Face budget. */
    private function makeMission(int $budget): Mission
    {
        return Mission::factory()->published()->create([
            'producer_id' => $this->producer->id,
            'budget' => $budget,
        ]);
    }

    /**
     * Create a Face (+ its User) optionally on $planState ∈ {'starter','pro','elite'},
     * plus a pending Candidature on $mission. Returns the Candidature
     * (carries ->uuid for the payload and ->face_id for DB assertions).
     */
    private function makeSelectableFace(Mission $mission, ?string $planState = null): Candidature
    {
        $face = Face::factory()->create();
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        if ($planState !== null) {
            FaceSubscription::factory()->{$planState}()->active()->create([
                'face_id' => $face->id,
            ]);
        }

        return Candidature::factory()->pending()->create([
            'face_id' => $face->id,
            'mission_id' => $mission->id,
        ]);
    }

    private function mockFedapay(): void
    {
        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('initiatePaymentForMission')
                ->andReturn([
                    'fedapay_transaction_id' => 123456,
                    'checkout_url' => 'https://checkout.fedapay.com/mission-token',
                ]);
        });
    }

    public function test_mission_commission_is_resolved_per_face_by_tier(): void
    {
        // budget 100000 × 3 Faces = sousTotal 300000.
        // Découverte 15 % → recoit 85000 ; Pro 10 % → 90000 ; Élite 5 % → 95000.
        $this->mockFedapay();
        $mission = $this->makeMission(100000);

        $free = $this->makeSelectableFace($mission, null);   // Découverte (no subscription)
        $pro = $this->makeSelectableFace($mission, 'pro');
        $elite = $this->makeSelectableFace($mission, 'elite');

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/confirm-selection", [
                'candidature_ids' => [$free->uuid, $pro->uuid, $elite->uuid],
            ])
            ->assertOk();

        // Three DISTINCT per-Face net amounts — proves the split is per-Face, not uniform.
        $this->assertDatabaseHas('mission_payment_candidatures', [
            'face_id' => $free->face_id, 'montant_face_recoit' => 85000,
        ]);
        $this->assertDatabaseHas('mission_payment_candidatures', [
            'face_id' => $pro->face_id, 'montant_face_recoit' => 90000,
        ]);
        $this->assertDatabaseHas('mission_payment_candidatures', [
            'face_id' => $elite->face_id, 'montant_face_recoit' => 95000,
        ]);

        // commission_faces_total = 15000 + 10000 + 5000 = 30000 ; montant_total_faces = 270000.
        // Producer side unchanged: 10 % of 300000 = 30000 → total 330000.
        $this->assertDatabaseHas('mission_payments', [
            'mission_id' => $mission->id,
            'montant_sous_total' => 300000,
            'commission_producteur' => 30000,
            'montant_total_producteur' => 330000,
            'commission_faces_total' => 30000,
            'montant_total_faces' => 270000,
        ]);
        // Invariant: 270000 + 30000 = 300000 = sousTotal.
    }

    public function test_commission_faces_total_is_the_sum_not_a_flat_rate(): void
    {
        // 2 Découverte (15 %) + 1 Élite (5 %), budget 100000 × 3 = sousTotal 300000.
        // commission_faces_total = 15000 + 15000 + 5000 = 35000 ≠ producer 30000 → proves decoupling.
        $this->mockFedapay();
        $mission = $this->makeMission(100000);

        $free1 = $this->makeSelectableFace($mission, null);
        $free2 = $this->makeSelectableFace($mission, null);
        $elite = $this->makeSelectableFace($mission, 'elite');

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/confirm-selection", [
                'candidature_ids' => [$free1->uuid, $free2->uuid, $elite->uuid],
            ])
            ->assertOk();

        $this->assertDatabaseHas('mission_payment_candidatures', [
            'face_id' => $free1->face_id, 'montant_face_recoit' => 85000,
        ]);
        $this->assertDatabaseHas('mission_payment_candidatures', [
            'face_id' => $elite->face_id, 'montant_face_recoit' => 95000,
        ]);
        $this->assertDatabaseHas('mission_payments', [
            'mission_id' => $mission->id,
            'commission_producteur' => 30000,       // still flat 10 %
            'commission_faces_total' => 35000,      // sum of per-Face cuts, NOT 30000
            'montant_total_faces' => 265000,
            'montant_total_producteur' => 330000,
        ]);
        // Invariant: 265000 + 35000 = 300000 = sousTotal.
    }

    public function test_mixed_tier_commissions_round_each_face_before_summing(): void
    {
        // budget 33335 × 3 Faces = sousTotal 100005.
        // Découverte 15 %: round(5000.25)=5000 → recoit 28335.
        // Pro 10 %: round(3333.5)=3334 → recoit 30001.
        // Élite 5 %: round(1666.75)=1667 → recoit 31668.
        $this->mockFedapay();
        $mission = $this->makeMission(33335);

        $free = $this->makeSelectableFace($mission, null);
        $pro = $this->makeSelectableFace($mission, 'pro');
        $elite = $this->makeSelectableFace($mission, 'elite');

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/confirm-selection", [
                'candidature_ids' => [$free->uuid, $pro->uuid, $elite->uuid],
            ])
            ->assertOk();

        $this->assertDatabaseHas('mission_payment_candidatures', [
            'face_id' => $free->face_id, 'montant_face_recoit' => 28335,
        ]);
        $this->assertDatabaseHas('mission_payment_candidatures', [
            'face_id' => $pro->face_id, 'montant_face_recoit' => 30001,
        ]);
        $this->assertDatabaseHas('mission_payment_candidatures', [
            'face_id' => $elite->face_id, 'montant_face_recoit' => 31668,
        ]);
        $this->assertDatabaseHas('mission_payments', [
            'mission_id' => $mission->id,
            'montant_sous_total' => 100005,
            'commission_producteur' => 10001,
            'montant_total_producteur' => 110006,
            'commission_faces_total' => 10001,
            'montant_total_faces' => 90004,
        ]);
        // Invariant: 90004 + 10001 = 100005 = sousTotal.
    }

    public function test_all_decouverte_faces_each_deduct_15_percent(): void
    {
        // Regression on the common all-free selection: budget 90000 × 2 = sousTotal 180000.
        // Each Découverte 15 % → recoit 76500 ; commission_faces_total 27000 ; total faces 153000.
        $this->mockFedapay();
        $mission = $this->makeMission(90000);

        $free1 = $this->makeSelectableFace($mission, null);
        $free2 = $this->makeSelectableFace($mission, null);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/confirm-selection", [
                'candidature_ids' => [$free1->uuid, $free2->uuid],
            ])
            ->assertOk();

        $this->assertDatabaseHas('mission_payment_candidatures', [
            'face_id' => $free1->face_id, 'montant_face_recoit' => 76500,
        ]);
        $this->assertDatabaseHas('mission_payment_candidatures', [
            'face_id' => $free2->face_id, 'montant_face_recoit' => 76500,
        ]);
        $this->assertDatabaseHas('mission_payments', [
            'mission_id' => $mission->id,
            'montant_sous_total' => 180000,
            'commission_producteur' => 18000,
            'montant_total_producteur' => 198000,   // producer unchanged
            'commission_faces_total' => 27000,
            'montant_total_faces' => 153000,
        ]);
        // Invariant: 153000 + 27000 = 180000 = sousTotal.
    }
}
