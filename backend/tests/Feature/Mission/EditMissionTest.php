<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Enums\MissionGender;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditMissionTest extends TestCase
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

        $this->mission = Mission::factory()->published()->create([
            'producer_id' => $this->producer->id,
            'titre' => 'Original Mission Title',
            'description' => 'Original description',
            'date_tournage' => now()->addMonths(2)->format('Y-m-d'),
            'profil_recherche' => 'Original profile requirements',
            'budget' => 100000,
            'date_limite_candidature' => now()->addMonth()->format('Y-m-d'),
            'nombre_faces_voulu' => 2,
            'type_mission' => MissionType::Publicite,
            'genre_voulu' => MissionGender::Homme,
            'lieu' => 'Cotonou, Bénin',
            'duree' => '1 journée',
        ]);
    }

    private function getValidUpdateData(): array
    {
        return [
            'titre' => 'Updated Mission Title',
            'description' => 'Updated description for the mission...',
            'date_tournage' => now()->addMonths(3)->format('Y-m-d'),
            'profil_recherche' => 'Updated profile requirements',
            'budget' => 200000,
            'date_limite_candidature' => now()->addWeeks(6)->format('Y-m-d'),
            'nombre_faces_voulu' => 5,
            'type_mission' => 'film',
            'genre_voulu' => 'tous',
            'lieu' => 'Abidjan, Côte d\'Ivoire',
            'duree' => '3 jours',
        ];
    }

    public function test_producer_can_update_published_mission(): void
    {
        $data = $this->getValidUpdateData();

        $response = $this->actingAs($this->producerUser)
            ->putJson("/api/v1/producer/missions/{$this->mission->uuid}", $data);

        $response->assertOk()
            ->assertJsonPath('data.titre', 'Updated Mission Title')
            ->assertJsonPath('data.description', 'Updated description for the mission...')
            ->assertJsonPath('data.budget', 200000)
            ->assertJsonPath('data.nombre_faces_voulu', 5)
            ->assertJsonPath('data.type_mission', 'film')
            ->assertJsonPath('data.type_mission_label', 'Film')
            ->assertJsonPath('data.genre_voulu', 'tous')
            ->assertJsonPath('data.genre_voulu_label', 'Homme et Femme')
            ->assertJsonPath('data.lieu', 'Abidjan, Côte d\'Ivoire')
            ->assertJsonPath('data.duree', '3 jours')
            ->assertJsonPath('message', 'Mission modifiée avec succès');

        $this->assertDatabaseHas('missions', [
            'id' => $this->mission->id,
            'titre' => 'Updated Mission Title',
            'budget' => 200000,
        ]);
    }

    public function test_producer_can_update_draft_mission(): void
    {
        $draftMission = Mission::factory()->draft()->create([
            'producer_id' => $this->producer->id,
            'date_tournage' => now()->addMonths(2)->format('Y-m-d'),
            'date_limite_candidature' => now()->addMonth()->format('Y-m-d'),
        ]);

        $data = $this->getValidUpdateData();

        $response = $this->actingAs($this->producerUser)
            ->putJson("/api/v1/producer/missions/{$draftMission->uuid}", $data);

        $response->assertOk()
            ->assertJsonPath('data.titre', 'Updated Mission Title')
            ->assertJsonPath('message', 'Mission modifiée avec succès');
    }

    public function test_update_returns_mission_in_envelope_format(): void
    {
        $data = $this->getValidUpdateData();

        $response = $this->actingAs($this->producerUser)
            ->putJson("/api/v1/producer/missions/{$this->mission->uuid}", $data);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'titre',
                    'description',
                    'date_tournage',
                    'profil_recherche',
                    'budget',
                    'date_limite_candidature',
                    'nombre_faces_voulu',
                    'type_mission',
                    'type_mission_label',
                    'genre_voulu',
                    'genre_voulu_label',
                    'lieu',
                    'duree',
                    'status',
                    'status_label',
                    'is_accepting_candidatures',
                    'created_at',
                    'updated_at',
                ],
                'message',
            ]);
    }

    public function test_validation_error_for_missing_required_fields(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->putJson("/api/v1/producer/missions/{$this->mission->uuid}", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'titre',
                'description',
                'date_tournage',
                'profil_recherche',
                'budget',
                'date_limite_candidature',
                'type_mission',
                'genre_voulu',
                'lieu',
                'duree',
            ]);
    }

    public function test_validation_error_for_titre_exceeding_max_length(): void
    {
        $data = $this->getValidUpdateData();
        $data['titre'] = str_repeat('a', 151);

        $response = $this->actingAs($this->producerUser)
            ->putJson("/api/v1/producer/missions/{$this->mission->uuid}", $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['titre'])
            ->assertJsonPath('errors.titre.0', 'Le titre ne peut pas dépasser 150 caractères.');
    }

    public function test_validation_error_for_description_exceeding_max_length(): void
    {
        $data = $this->getValidUpdateData();
        $data['description'] = str_repeat('a', 10001);

        $response = $this->actingAs($this->producerUser)
            ->putJson("/api/v1/producer/missions/{$this->mission->uuid}", $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['description'])
            ->assertJsonPath('errors.description.0', 'La description ne peut pas dépasser 10000 caractères.');
    }

    public function test_validation_error_for_profil_recherche_exceeding_max_length(): void
    {
        $data = $this->getValidUpdateData();
        $data['profil_recherche'] = str_repeat('a', 5001);

        $response = $this->actingAs($this->producerUser)
            ->putJson("/api/v1/producer/missions/{$this->mission->uuid}", $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['profil_recherche'])
            ->assertJsonPath('errors.profil_recherche.0', 'Le profil recherché ne peut pas dépasser 5000 caractères.');
    }

    public function test_validation_error_for_past_date_limite_candidature(): void
    {
        $data = $this->getValidUpdateData();
        $data['date_limite_candidature'] = now()->subDay()->format('Y-m-d');
        $data['date_tournage'] = now()->subDay()->format('Y-m-d');

        $response = $this->actingAs($this->producerUser)
            ->putJson("/api/v1/producer/missions/{$this->mission->uuid}", $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date_limite_candidature'])
            ->assertJsonPath('errors.date_limite_candidature.0', 'La date limite doit être dans le futur.');
    }

    public function test_validation_error_for_date_tournage_before_date_limite(): void
    {
        $data = $this->getValidUpdateData();
        $data['date_limite_candidature'] = now()->addMonth()->format('Y-m-d');
        $data['date_tournage'] = now()->addWeek()->format('Y-m-d');

        $response = $this->actingAs($this->producerUser)
            ->putJson("/api/v1/producer/missions/{$this->mission->uuid}", $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date_tournage'])
            ->assertJsonPath('errors.date_tournage.0', 'La date de tournage doit être après la date limite de candidature.');
    }

    public function test_validation_error_for_budget_not_positive(): void
    {
        $data = $this->getValidUpdateData();
        $data['budget'] = 0;

        $response = $this->actingAs($this->producerUser)
            ->putJson("/api/v1/producer/missions/{$this->mission->uuid}", $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['budget'])
            ->assertJsonPath('errors.budget.0', 'Le budget doit être un nombre positif.');
    }

    public function test_validation_error_for_invalid_type_mission(): void
    {
        $data = $this->getValidUpdateData();
        $data['type_mission'] = 'invalid_type';

        $response = $this->actingAs($this->producerUser)
            ->putJson("/api/v1/producer/missions/{$this->mission->uuid}", $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type_mission'])
            ->assertJsonPath('errors.type_mission.0', 'Le type de mission sélectionné est invalide.');
    }

    public function test_validation_error_for_invalid_genre_voulu(): void
    {
        $data = $this->getValidUpdateData();
        $data['genre_voulu'] = 'invalid_gender';

        $response = $this->actingAs($this->producerUser)
            ->putJson("/api/v1/producer/missions/{$this->mission->uuid}", $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['genre_voulu'])
            ->assertJsonPath('errors.genre_voulu.0', 'Le genre sélectionné est invalide.');
    }

    public function test_cannot_edit_closed_mission(): void
    {
        $closedMission = Mission::factory()->closed()->create([
            'producer_id' => $this->producer->id,
        ]);

        $data = $this->getValidUpdateData();

        $response = $this->actingAs($this->producerUser)
            ->putJson("/api/v1/producer/missions/{$closedMission->uuid}", $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['mission'])
            ->assertJsonPath('errors.mission.0', 'Une mission clôturée ou terminée ne peut pas être modifiée');
    }

    public function test_cannot_edit_completed_mission(): void
    {
        $completedMission = Mission::factory()->completed()->create([
            'producer_id' => $this->producer->id,
        ]);

        $data = $this->getValidUpdateData();

        $response = $this->actingAs($this->producerUser)
            ->putJson("/api/v1/producer/missions/{$completedMission->uuid}", $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['mission'])
            ->assertJsonPath('errors.mission.0', 'Une mission clôturée ou terminée ne peut pas être modifiée');
    }

    public function test_cannot_edit_pending_payment_mission(): void
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
            ->putJson("/api/v1/producer/missions/{$pendingPaymentMission->uuid}", $this->getValidUpdateData());

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['mission'])
            ->assertJsonPath('errors.mission.0', 'Une mission en attente de paiement ne peut pas être modifiée');
    }

    public function test_updating_shooting_date_resets_shooting_reminder_timestamp(): void
    {
        $mission = Mission::factory()->published()->create([
            'producer_id' => $this->producer->id,
            'shooting_reminder_sent_at' => now(),
            'date_tournage' => now()->addDays(10)->format('Y-m-d'),
            'date_limite_candidature' => now()->addDays(5)->format('Y-m-d'),
        ]);

        $data = $this->getValidUpdateData();
        $data['date_limite_candidature'] = now()->addDays(8)->format('Y-m-d');
        $data['date_tournage'] = now()->addDays(15)->format('Y-m-d');

        $response = $this->actingAs($this->producerUser)
            ->putJson("/api/v1/producer/missions/{$mission->uuid}", $data);

        $response->assertOk();

        $this->assertDatabaseHas('missions', [
            'id' => $mission->id,
            'shooting_reminder_sent_at' => null,
        ]);
    }

    public function test_non_owner_cannot_edit_mission(): void
    {
        $otherProducer = Producer::factory()->create();
        $otherUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $otherProducer->id,
        ]);

        $data = $this->getValidUpdateData();

        $response = $this->actingAs($otherUser)
            ->putJson("/api/v1/producer/missions/{$this->mission->uuid}", $data);

        $response->assertForbidden();
    }

    public function test_face_cannot_edit_mission(): void
    {
        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $data = $this->getValidUpdateData();

        $response = $this->actingAs($faceUser)
            ->putJson("/api/v1/producer/missions/{$this->mission->uuid}", $data);

        $response->assertForbidden();
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $data = $this->getValidUpdateData();

        $response = $this->putJson("/api/v1/producer/missions/{$this->mission->uuid}", $data);

        $response->assertUnauthorized();
    }

    public function test_returns_404_for_nonexistent_mission(): void
    {
        $data = $this->getValidUpdateData();

        $response = $this->actingAs($this->producerUser)
            ->putJson('/api/v1/producer/missions/00000000-0000-0000-0000-000000000000', $data);

        $response->assertNotFound();
    }

    public function test_can_get_mission_details(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$this->mission->uuid}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'titre',
                    'description',
                    'date_tournage',
                    'profil_recherche',
                    'budget',
                    'date_limite_candidature',
                    'nombre_faces_voulu',
                    'type_mission',
                    'type_mission_label',
                    'genre_voulu',
                    'genre_voulu_label',
                    'lieu',
                    'duree',
                    'status',
                    'status_label',
                    'is_accepting_candidatures',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJsonPath('data.id', $this->mission->uuid)
            ->assertJsonPath('data.titre', 'Original Mission Title');
    }

    public function test_other_producer_cannot_view_mission(): void
    {
        $otherProducer = Producer::factory()->create();
        $otherUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $otherProducer->id,
        ]);

        $response = $this->actingAs($otherUser)
            ->getJson("/api/v1/producer/missions/{$this->mission->uuid}");

        // Producer routes only allow the owner to view their missions
        $response->assertForbidden();
    }

    public function test_face_cannot_view_mission_via_producer_endpoint(): void
    {
        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $response = $this->actingAs($faceUser)
            ->getJson("/api/v1/producer/missions/{$this->mission->uuid}");

        // Producer routes are restricted to producers only
        $response->assertForbidden();
    }

    public function test_unauthenticated_cannot_view_mission_via_producer_endpoint(): void
    {
        $response = $this->getJson("/api/v1/producer/missions/{$this->mission->uuid}");

        // Route is protected by auth:sanctum middleware
        $response->assertUnauthorized();
    }

    public function test_nombre_faces_voulu_defaults_to_one_when_not_provided(): void
    {
        $data = $this->getValidUpdateData();
        unset($data['nombre_faces_voulu']);

        $response = $this->actingAs($this->producerUser)
            ->putJson("/api/v1/producer/missions/{$this->mission->uuid}", $data);

        $response->assertOk()
            ->assertJsonPath('data.nombre_faces_voulu', 1);
    }

    public function test_mission_status_is_preserved_after_update(): void
    {
        $data = $this->getValidUpdateData();

        $response = $this->actingAs($this->producerUser)
            ->putJson("/api/v1/producer/missions/{$this->mission->uuid}", $data);

        $response->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.status_label', 'Publiée');
    }

    public function test_updated_at_timestamp_changes_after_update(): void
    {
        $originalUpdatedAt = $this->mission->updated_at;

        // Wait a moment to ensure timestamp difference
        sleep(1);

        $data = $this->getValidUpdateData();

        $response = $this->actingAs($this->producerUser)
            ->putJson("/api/v1/producer/missions/{$this->mission->uuid}", $data);

        $response->assertOk();

        $this->mission->refresh();
        $this->assertNotEquals($originalUpdatedAt, $this->mission->updated_at);
    }
}
