<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `can_complete` (liste Producteur) doit refléter exactement ce que
 * POST /producer/missions/{mission}/complete accepte.
 */
class MissionCanCompleteFlagTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
    }

    private function paidSelection(Mission $mission, CandidatureStatus $status): void
    {
        $face = Face::factory()->create();
        $candidature = Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $face->id,
            'status' => $status,
        ]);

        $payment = MissionPayment::create([
            'mission_id' => $mission->id,
            'producer_id' => $this->producer->id,
            'nombre_faces_retenues' => 1,
            'budget_par_face' => 100000,
            'montant_sous_total' => 100000,
            'commission_producteur' => 10000,
            'montant_total_producteur' => 110000,
            'commission_faces_total' => 10000,
            'montant_total_faces' => 90000,
            'status' => MissionPaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        MissionPaymentCandidature::create([
            'mission_payment_id' => $payment->id,
            'candidature_id' => $candidature->id,
            'face_id' => $face->id,
            'montant_face_recoit' => 90000,
            'escrow_status' => EscrowStatus::Locked,
            'locked_at' => now(),
        ]);
    }

    private function flagFor(Mission $mission): bool
    {
        $row = collect(
            $this->actingAs($this->producerUser)->getJson('/api/v1/producer/missions')->assertOk()->json('data')
        )->firstWhere('id', $mission->uuid);

        return $row['can_complete'];
    }

    public function test_closed_mission_without_payment_cannot_be_completed(): void
    {
        $mission = Mission::factory()->closed()->create(['producer_id' => $this->producer->id]);

        $this->assertFalse($this->flagFor($mission));
        $this->actingAs($this->producerUser)->postJson("/api/v1/producer/missions/{$mission->uuid}/complete")
            ->assertStatus(422);
    }

    public function test_closed_ugc_mission_cannot_be_completed(): void
    {
        $mission = Mission::factory()->closed()->create([
            'producer_id' => $this->producer->id,
            'type_mission' => 'ugc',
            'commission_ugc' => 5000,
        ]);

        $this->assertFalse($this->flagFor($mission));
        $this->actingAs($this->producerUser)->postJson("/api/v1/producer/missions/{$mission->uuid}/complete")
            ->assertStatus(422);
    }

    public function test_closed_mission_with_unconfirmed_face_cannot_be_completed(): void
    {
        $mission = Mission::factory()->closed()->create(['producer_id' => $this->producer->id]);
        $this->paidSelection($mission, CandidatureStatus::Accepted);

        $this->assertFalse($this->flagFor($mission));
        $this->actingAs($this->producerUser)->postJson("/api/v1/producer/missions/{$mission->uuid}/complete")
            ->assertStatus(422);
    }

    public function test_closed_paid_confirmed_mission_can_be_completed(): void
    {
        $mission = Mission::factory()->closed()->create(['producer_id' => $this->producer->id]);
        $this->paidSelection($mission, CandidatureStatus::Confirmed);

        $this->assertTrue($this->flagFor($mission));
        $this->actingAs($this->producerUser)->postJson("/api/v1/producer/missions/{$mission->uuid}/complete")
            ->assertOk();
    }

    public function test_published_mission_cannot_be_completed(): void
    {
        $mission = Mission::factory()->published()->create(['producer_id' => $this->producer->id]);

        $this->assertFalse($this->flagFor($mission));
    }
}
