<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishMissionTest extends TestCase
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

    private function getValidMissionData(): array
    {
        return [
            'titre' => 'Recherche acteurs pour publicité Coca',
            'description' => 'Nous recherchons 3 acteurs pour une publicité nationale...',
            'date_tournage' => now()->addMonth()->format('Y-m-d'),
            'profil_recherche' => 'Hommes 25-35 ans, bonne élocution, présentation soignée',
            'budget' => 150000,
            'date_limite_candidature' => now()->addWeeks(2)->format('Y-m-d'),
            'nombre_faces_voulu' => 3,
            'type_mission' => 'publicite',
            'genre_voulu' => 'homme',
            'lieu' => 'Cotonou, Bénin',
            'duree' => '1 journée',
        ];
    }

    public function test_producer_can_publish_mission_with_valid_data(): void
    {
        $data = $this->getValidMissionData();

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $data);

        $response->assertCreated()
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
            ])
            ->assertJsonPath('data.titre', 'Recherche acteurs pour publicité Coca')
            ->assertJsonPath('data.budget', 150000)
            ->assertJsonPath('data.nombre_faces_voulu', 3)
            ->assertJsonPath('data.type_mission', 'publicite')
            ->assertJsonPath('data.type_mission_label', 'Publicité')
            ->assertJsonPath('data.genre_voulu', 'homme')
            ->assertJsonPath('data.genre_voulu_label', 'Homme')
            ->assertJsonPath('data.lieu', 'Cotonou, Bénin')
            ->assertJsonPath('message', 'Mission publiée avec succès');

        $this->assertDatabaseHas('missions', [
            'producer_id' => $this->producer->id,
            'titre' => 'Recherche acteurs pour publicité Coca',
            'budget' => 150000,
        ]);
    }

    public function test_mission_status_is_published_after_creation(): void
    {
        $data = $this->getValidMissionData();

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $data);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.status_label', 'Publiée');

        $this->assertDatabaseHas('missions', [
            'producer_id' => $this->producer->id,
            'titre' => 'Recherche acteurs pour publicité Coca',
            'status' => 'published',
        ]);
    }

    public function test_mission_is_associated_with_authenticated_producer(): void
    {
        $data = $this->getValidMissionData();

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $data);

        $response->assertCreated();

        $mission = Mission::where('titre', $data['titre'])->first();

        $this->assertNotNull($mission);
        $this->assertEquals($this->producer->id, $mission->producer_id);
        $this->assertInstanceOf(Producer::class, $mission->producer);
    }

    public function test_mission_is_accepting_candidatures_after_creation(): void
    {
        $data = $this->getValidMissionData();

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $data);

        $response->assertCreated()
            ->assertJsonPath('data.is_accepting_candidatures', true);
    }

    public function test_validation_errors_for_missing_required_fields(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', []);

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

    public function test_validation_error_for_missing_titre(): void
    {
        $data = $this->getValidMissionData();
        unset($data['titre']);

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['titre'])
            ->assertJsonPath('errors.titre.0', 'Le titre est obligatoire.');
    }

    public function test_validation_error_for_titre_exceeding_max_length(): void
    {
        $data = $this->getValidMissionData();
        $data['titre'] = str_repeat('a', 151);

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['titre'])
            ->assertJsonPath('errors.titre.0', 'Le titre ne peut pas dépasser 150 caractères.');
    }

    public function test_validation_error_for_past_date_limite_candidature(): void
    {
        $data = $this->getValidMissionData();
        $data['date_limite_candidature'] = now()->subDay()->format('Y-m-d');
        $data['date_tournage'] = now()->subDay()->format('Y-m-d');

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date_limite_candidature'])
            ->assertJsonPath('errors.date_limite_candidature.0', 'La date limite doit être dans le futur.');
    }

    public function test_validation_error_for_date_tournage_before_date_limite_candidature(): void
    {
        $data = $this->getValidMissionData();
        $data['date_limite_candidature'] = now()->addMonth()->format('Y-m-d');
        $data['date_tournage'] = now()->addWeek()->format('Y-m-d');

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date_tournage'])
            ->assertJsonPath('errors.date_tournage.0', 'La date de tournage doit être après la date limite de candidature.');
    }

    public function test_validation_error_for_invalid_type_mission(): void
    {
        $data = $this->getValidMissionData();
        $data['type_mission'] = 'invalid_type';

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type_mission'])
            ->assertJsonPath('errors.type_mission.0', 'Le type de mission sélectionné est invalide.');
    }

    public function test_validation_error_for_invalid_genre_voulu(): void
    {
        $data = $this->getValidMissionData();
        $data['genre_voulu'] = 'invalid_gender';

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['genre_voulu'])
            ->assertJsonPath('errors.genre_voulu.0', 'Le genre sélectionné est invalide.');
    }

    public function test_validation_error_for_budget_not_positive(): void
    {
        $data = $this->getValidMissionData();
        $data['budget'] = 0;

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['budget'])
            ->assertJsonPath('errors.budget.0', 'Le budget doit être un nombre positif.');
    }

    public function test_validation_error_for_budget_not_integer(): void
    {
        $data = $this->getValidMissionData();
        $data['budget'] = 'not_a_number';

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['budget']);
    }

    public function test_validation_error_for_lieu_exceeding_max_length(): void
    {
        $data = $this->getValidMissionData();
        $data['lieu'] = str_repeat('a', 151);

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['lieu'])
            ->assertJsonPath('errors.lieu.0', 'Le lieu ne peut pas dépasser 150 caractères.');
    }

    public function test_validation_error_for_duree_exceeding_max_length(): void
    {
        $data = $this->getValidMissionData();
        $data['duree'] = str_repeat('a', 101);

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['duree'])
            ->assertJsonPath('errors.duree.0', 'La durée ne peut pas dépasser 100 caractères.');
    }

    public function test_validation_error_for_description_exceeding_max_length(): void
    {
        $data = $this->getValidMissionData();
        $data['description'] = str_repeat('a', 10001);

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['description'])
            ->assertJsonPath('errors.description.0', 'La description ne peut pas dépasser 10000 caractères.');
    }

    public function test_validation_error_for_profil_recherche_exceeding_max_length(): void
    {
        $data = $this->getValidMissionData();
        $data['profil_recherche'] = str_repeat('a', 5001);

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['profil_recherche'])
            ->assertJsonPath('errors.profil_recherche.0', 'Le profil recherché ne peut pas dépasser 5000 caractères.');
    }

    public function test_nombre_faces_voulu_defaults_to_one_when_not_provided(): void
    {
        $data = $this->getValidMissionData();
        unset($data['nombre_faces_voulu']);

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $data);

        $response->assertCreated()
            ->assertJsonPath('data.nombre_faces_voulu', 1);
    }

    public function test_nombre_faces_voulu_defaults_to_one_when_null(): void
    {
        $data = $this->getValidMissionData();
        $data['nombre_faces_voulu'] = null;

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $data);

        $response->assertCreated()
            ->assertJsonPath('data.nombre_faces_voulu', 1);
    }

    public function test_validation_error_for_nombre_faces_voulu_less_than_one(): void
    {
        $data = $this->getValidMissionData();
        $data['nombre_faces_voulu'] = 0;

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre_faces_voulu'])
            ->assertJsonPath('errors.nombre_faces_voulu.0', 'Le nombre de Faces doit être au moins 1.');
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $data = $this->getValidMissionData();

        $response = $this->postJson('/api/v1/producer/missions', $data);

        $response->assertUnauthorized();
    }

    public function test_face_user_cannot_create_mission_returns_403(): void
    {
        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $data = $this->getValidMissionData();

        $response = $this->actingAs($faceUser)
            ->postJson('/api/v1/producer/missions', $data);

        $response->assertForbidden();
    }

    public function test_response_format_matches_api_envelope(): void
    {
        $data = $this->getValidMissionData();

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $data);

        $response->assertCreated()
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

    public function test_dates_are_returned_in_iso8601_format(): void
    {
        $data = $this->getValidMissionData();

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $data);

        $response->assertCreated();

        $responseData = $response->json('data');

        // Check date format is ISO 8601
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $responseData['date_tournage']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $responseData['date_limite_candidature']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $responseData['created_at']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $responseData['updated_at']);
    }

    public function test_all_valid_type_mission_values_accepted(): void
    {
        $types = ['publicite', 'film', 'court_metrage', 'clip_musical', 'autre'];

        foreach ($types as $type) {
            $data = $this->getValidMissionData();
            $data['type_mission'] = $type;
            $data['titre'] = "Mission type: {$type}";

            if ($type === 'autre') {
                $data['type_mission_autre'] = 'Type personnalisé';
            }

            $response = $this->actingAs($this->producerUser)
                ->postJson('/api/v1/producer/missions', $data);

            $response->assertCreated()
                ->assertJsonPath('data.type_mission', $type);
        }
    }

    public function test_all_valid_genre_voulu_values_accepted(): void
    {
        $genders = ['homme', 'femme', 'tous'];

        foreach ($genders as $gender) {
            $data = $this->getValidMissionData();
            $data['genre_voulu'] = $gender;
            $data['titre'] = "Mission genre: {$gender}";

            $response = $this->actingAs($this->producerUser)
                ->postJson('/api/v1/producer/missions', $data);

            $response->assertCreated()
                ->assertJsonPath('data.genre_voulu', $gender);
        }
    }

    public function test_enum_labels_are_included_in_response(): void
    {
        $data = $this->getValidMissionData();
        $data['type_mission'] = 'court_metrage';
        $data['genre_voulu'] = 'femme';

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $data);

        $response->assertCreated()
            ->assertJsonPath('data.type_mission', 'court_metrage')
            ->assertJsonPath('data.type_mission_label', 'Court-métrage')
            ->assertJsonPath('data.genre_voulu', 'femme')
            ->assertJsonPath('data.genre_voulu_label', 'Femme')
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.status_label', 'Publiée');
    }

    public function test_mission_model_is_accepting_candidatures_method(): void
    {
        $mission = Mission::factory()->published()->create([
            'producer_id' => $this->producer->id,
            'date_limite_candidature' => now()->addWeek(),
        ]);

        $this->assertTrue($mission->isAcceptingCandidatures());

        $pastMission = Mission::factory()->published()->create([
            'producer_id' => $this->producer->id,
            'date_limite_candidature' => now()->subDay(),
        ]);

        $this->assertFalse($pastMission->isAcceptingCandidatures());

        $draftMission = Mission::factory()->draft()->create([
            'producer_id' => $this->producer->id,
            'date_limite_candidature' => now()->addWeek(),
        ]);

        $this->assertFalse($draftMission->isAcceptingCandidatures());
    }
}
