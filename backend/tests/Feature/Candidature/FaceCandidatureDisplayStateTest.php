<?php

declare(strict_types=1);

namespace Tests\Feature\Candidature;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaceCandidatureDisplayStateTest extends TestCase
{
    use RefreshDatabase;

    private Face $face;

    private User $faceUser;

    private Producer $producer;

    private User $producerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
    }

    private function candidatureOn(Mission $mission, CandidatureStatus $status = CandidatureStatus::Pending): Candidature
    {
        return Candidature::factory()->create([
            'face_id' => $this->face->id,
            'mission_id' => $mission->id,
            'status' => $status,
        ]);
    }

    public function test_list_shows_mission_closed_for_pending_candidature_on_closed_mission(): void
    {
        $mission = Mission::factory()->closed()->create(['producer_id' => $this->producer->id]);
        $this->candidatureOn($mission);

        $this->actingAs($this->faceUser)->getJson('/api/v1/face/candidatures')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.display_status', 'mission_closed')
            ->assertJsonPath('data.0.display_status_label', 'Mission clôturée');
    }

    public function test_list_shows_selection_payment_pending_for_pending_candidature_on_pending_payment_mission(): void
    {
        $mission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::PendingPayment,
        ]);
        $this->candidatureOn($mission);

        $this->actingAs($this->faceUser)->getJson('/api/v1/face/candidatures')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.display_status', 'selection_payment_pending')
            ->assertJsonPath('data.0.display_status_label', 'Sélection en cours de paiement');
    }

    public function test_list_shows_mission_closed_for_pending_candidature_on_completed_mission(): void
    {
        $mission = Mission::factory()->completed()->create(['producer_id' => $this->producer->id]);
        $this->candidatureOn($mission);

        $this->actingAs($this->faceUser)->getJson('/api/v1/face/candidatures')
            ->assertOk()
            ->assertJsonPath('data.0.display_status', 'mission_closed');
    }

    public function test_list_keeps_pending_on_published_mission_even_past_deadline(): void
    {
        // Le Producteur peut encore accepter après date_limite_candidature :
        // « En attente » reste vrai tant que la mission est publiée.
        $mission = Mission::factory()->published()->create([
            'producer_id' => $this->producer->id,
            'date_limite_candidature' => now()->subDays(2)->toDateString(),
        ]);
        $this->candidatureOn($mission);

        $this->actingAs($this->faceUser)->getJson('/api/v1/face/candidatures')
            ->assertOk()
            ->assertJsonPath('data.0.display_status', 'pending')
            ->assertJsonPath('data.0.display_status_label', 'En attente');
    }

    public function test_list_does_not_override_non_pending_statuses_on_closed_mission(): void
    {
        $mission = Mission::factory()->closed()->create(['producer_id' => $this->producer->id]);
        $this->candidatureOn($mission, CandidatureStatus::Confirmed);

        $this->actingAs($this->faceUser)->getJson('/api/v1/face/candidatures')
            ->assertOk()
            ->assertJsonPath('data.0.display_status', 'confirmed');
    }

    public function test_mission_detail_candidature_exposes_display_status(): void
    {
        $mission = Mission::factory()->closed()->create(['producer_id' => $this->producer->id]);
        $this->candidatureOn($mission);

        $this->actingAs($this->faceUser)->getJson("/api/v1/face/missions/{$mission->uuid}")
            ->assertOk()
            ->assertJsonPath('candidature.status', 'pending')
            ->assertJsonPath('candidature.display_status', 'mission_closed')
            ->assertJsonPath('candidature.display_status_label', 'Mission clôturée');

        $pendingPayment = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::PendingPayment,
        ]);
        $this->candidatureOn($pendingPayment);

        $this->actingAs($this->faceUser)->getJson("/api/v1/face/missions/{$pendingPayment->uuid}")
            ->assertOk()
            ->assertJsonPath('candidature.display_status', 'selection_payment_pending')
            ->assertJsonPath('candidature.display_status_label', 'Sélection en cours de paiement');
    }

    public function test_manual_close_notification_does_not_claim_pending_candidates_were_rejected(): void
    {
        $mission = Mission::factory()->published()->create([
            'producer_id' => $this->producer->id,
            'titre' => 'Pub Printemps',
        ]);
        $this->candidatureOn($mission);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/close")
            ->assertOk();

        $notification = Notification::query()
            ->where('type', 'mission_closed_pending_candidature')
            ->where('user_id', $this->faceUser->id)
            ->firstOrFail();

        $this->assertSame(
            'La mission « Pub Printemps » a été clôturée par le Producteur.',
            $notification->data['message'],
        );
        $this->assertStringNotContainsString('retenue', $notification->data['message']);
    }
}
