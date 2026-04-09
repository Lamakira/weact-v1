<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReopenMissionTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    private Mission $mission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
        // Create a closed mission for reopen tests
        $this->mission = Mission::factory()->closed()->create([
            'producer_id' => $this->producer->id,
        ]);
    }

    /**
     * AC#1, AC#10: Producer can reopen a closed mission successfully.
     */
    public function test_producer_can_reopen_closed_mission(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->uuid}/reopen");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $this->mission->uuid,
                    'status' => 'published',
                ],
                'message' => 'Mission réouverte avec succès',
            ]);

        // Verify database was updated
        $this->assertDatabaseHas('missions', [
            'id' => $this->mission->id,
            'status' => 'published',
        ]);
    }

    /**
     * AC#3: Reopened mission has correct status label "Publiée".
     */
    public function test_reopened_mission_has_correct_status_label(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->uuid}/reopen");

        $response->assertOk()
            ->assertJsonPath('data.status_label', 'Publiée');
    }

    /**
     * AC#4: Cannot reopen a draft mission.
     */
    public function test_cannot_reopen_draft_mission(): void
    {
        $draftMission = Mission::factory()->draft()->create([
            'producer_id' => $this->producer->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$draftMission->uuid}/reopen");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status'])
            ->assertJsonPath('errors.status.0', 'Seules les missions clôturées peuvent être réouvertes');

        // Verify database was NOT updated
        $this->assertDatabaseHas('missions', [
            'id' => $draftMission->id,
            'status' => 'draft',
        ]);
    }

    /**
     * AC#5: Cannot reopen an already published mission.
     */
    public function test_cannot_reopen_already_published_mission(): void
    {
        $publishedMission = Mission::factory()->published()->create([
            'producer_id' => $this->producer->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$publishedMission->uuid}/reopen");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status'])
            ->assertJsonPath('errors.status.0', 'Cette mission est déjà publiée');
    }

    /**
     * AC#6: Cannot reopen a completed mission.
     */
    public function test_cannot_reopen_completed_mission(): void
    {
        $completedMission = Mission::factory()->completed()->create([
            'producer_id' => $this->producer->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$completedMission->uuid}/reopen");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status'])
            ->assertJsonPath('errors.status.0', 'Cette mission est terminée et ne peut pas être réouverte');
    }

    public function test_cannot_reopen_pending_payment_mission(): void
    {
        $pendingPaymentMission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::PendingPayment,
        ]);

        MissionPayment::create([
            'mission_id' => $pendingPaymentMission->id,
            'producer_id' => $this->producer->id,
            'nombre_faces_retenues' => 1,
            'budget_par_face' => 100000,
            'montant_sous_total' => 100000,
            'commission_producteur' => 10000,
            'montant_total_producteur' => 110000,
            'commission_faces_total' => 10000,
            'montant_total_faces' => 90000,
            'status' => MissionPaymentStatus::Pending,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$pendingPaymentMission->uuid}/reopen");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status'])
            ->assertJsonPath('errors.status.0', 'Cette mission a un paiement en cours et ne peut pas être réouverte');
    }

    /**
     * AC#7: Other producer cannot reopen another producer's mission.
     */
    public function test_other_producer_cannot_reopen_mission(): void
    {
        $otherProducer = Producer::factory()->create();
        $otherUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $otherProducer->id,
        ]);

        $response = $this->actingAs($otherUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->uuid}/reopen");

        $response->assertForbidden();

        // Verify database was NOT updated
        $this->assertDatabaseHas('missions', [
            'id' => $this->mission->id,
            'status' => 'closed',
        ]);
    }

    /**
     * AC#8: Unauthenticated user cannot reopen a mission.
     */
    public function test_unauthenticated_user_cannot_reopen_mission(): void
    {
        $response = $this->postJson("/api/v1/producer/missions/{$this->mission->uuid}/reopen");

        $response->assertUnauthorized();
    }

    /**
     * AC#9: Face user cannot reopen a mission.
     */
    public function test_face_user_cannot_reopen_mission(): void
    {
        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $response = $this->actingAs($faceUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->uuid}/reopen");

        $response->assertForbidden();
    }

    /**
     * AC#10: Response includes complete mission data.
     */
    public function test_reopen_response_includes_complete_mission_data(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->uuid}/reopen");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'titre',
                    'description',
                    'date_tournage',
                    'budget',
                    'status',
                    'status_label',
                    'lieu',
                    'nombre_faces_voulu',
                    'is_accepting_candidatures',
                    'created_at',
                    'producer',
                ],
                'message',
            ])
            ->assertJsonPath('data.is_accepting_candidatures', true);
    }

    /**
     * AC#2: Reopened mission is accepting candidatures.
     */
    public function test_reopened_mission_is_accepting_candidatures(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->uuid}/reopen");

        $response->assertOk()
            ->assertJsonPath('data.is_accepting_candidatures', true);
    }

    /**
     * Test that non-existent mission returns 404.
     */
    public function test_reopen_nonexistent_mission_returns_404(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions/00000000-0000-0000-0000-000000000000/reopen');

        $response->assertNotFound();
    }
}
