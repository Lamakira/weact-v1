<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Models\Experience;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExperienceTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    private Face $face;

    protected function setUp(): void
    {
        parent::setUp();

        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    public function test_can_list_experiences_ordered_by_date_descending(): void
    {
        Experience::factory()->withDateRange('2020-03-15', '2020-12-31')->create(['face_id' => $this->face->id]);
        Experience::factory()->withDateRange('2024-01-10', '2024-06-30')->create(['face_id' => $this->face->id]);
        Experience::factory()->withDateRange('2022-06-01', '2023-02-28')->create(['face_id' => $this->face->id]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/experiences');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'titre', 'description', 'date_debut', 'date_fin', 'is_ongoing', 'formatted_period', 'created_at', 'updated_at'],
                ],
                'message',
            ])
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('message', 'Expériences récupérées avec succès');

        $dates = collect($response->json('data'))->pluck('date_debut')->toArray();
        $this->assertEquals(['2024-01-10', '2022-06-01', '2020-03-15'], $dates);
    }

    public function test_can_create_experience_with_valid_dates(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/experiences', [
                'titre' => 'Publicité Coca-Cola',
                'description' => 'Rôle principal dans une publicité nationale',
                'date_debut' => '2023-01-15',
                'date_fin' => '2023-03-20',
            ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => ['id', 'titre', 'description', 'date_debut', 'date_fin', 'is_ongoing', 'formatted_period', 'created_at', 'updated_at'],
                'message',
            ])
            ->assertJsonPath('data.titre', 'Publicité Coca-Cola')
            ->assertJsonPath('data.description', 'Rôle principal dans une publicité nationale')
            ->assertJsonPath('data.date_debut', '2023-01-15')
            ->assertJsonPath('data.date_fin', '2023-03-20')
            ->assertJsonPath('data.is_ongoing', false)
            ->assertJsonPath('data.formatted_period', '15/01/2023 - 20/03/2023')
            ->assertJsonPath('message', 'Expérience ajoutée avec succès');

        $this->assertDatabaseHas('experiences', [
            'face_id' => $this->face->id,
            'titre' => 'Publicité Coca-Cola',
            'date_debut' => '2023-01-15',
            'date_fin' => '2023-03-20',
        ]);
    }

    public function test_can_create_ongoing_experience(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/experiences', [
                'titre' => 'Série TV en cours',
                'description' => 'Rôle récurrent',
                'date_debut' => '2024-06-01',
                'date_fin' => null,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.titre', 'Série TV en cours')
            ->assertJsonPath('data.date_debut', '2024-06-01')
            ->assertJsonPath('data.date_fin', null)
            ->assertJsonPath('data.is_ongoing', true)
            ->assertJsonPath('data.formatted_period', '01/06/2024 - Présent');

        $this->assertDatabaseHas('experiences', [
            'face_id' => $this->face->id,
            'titre' => 'Série TV en cours',
            'date_fin' => null,
        ]);
    }

    public function test_create_experience_without_title_fails(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/experiences', [
                'description' => 'Une description',
                'date_debut' => '2024-01-01',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['titre'])
            ->assertJsonPath('errors.titre.0', 'Le titre est requis');
    }

    public function test_create_experience_with_title_exceeding_max_length_fails(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/experiences', [
                'titre' => str_repeat('a', 151),
                'date_debut' => '2024-01-01',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['titre'])
            ->assertJsonPath('errors.titre.0', 'Le titre ne doit pas dépasser 150 caractères');
    }

    public function test_create_experience_without_start_date_fails(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/experiences', [
                'titre' => 'Une expérience',
                'description' => 'Une description',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date_debut'])
            ->assertJsonPath('errors.date_debut.0', 'La date de début est requise');
    }

    public function test_create_experience_with_invalid_start_date_fails(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/experiences', [
                'titre' => 'Une expérience',
                'date_debut' => 'invalid-date',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date_debut'])
            ->assertJsonPath('errors.date_debut.0', "La date de début n'est pas valide");
    }

    public function test_create_experience_with_future_start_date_fails(): void
    {
        $futureDate = now()->addMonth()->format('Y-m-d');

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/experiences', [
                'titre' => 'Une expérience',
                'date_debut' => $futureDate,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date_debut'])
            ->assertJsonPath('errors.date_debut.0', 'La date de début ne peut pas être dans le futur');
    }

    public function test_create_experience_with_end_date_before_start_date_fails(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/experiences', [
                'titre' => 'Une expérience',
                'date_debut' => '2024-06-01',
                'date_fin' => '2024-01-01',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date_fin'])
            ->assertJsonPath('errors.date_fin.0', 'La date de fin doit être après la date de début');
    }

    public function test_can_create_experience_with_same_day_start_and_end(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/experiences', [
                'titre' => 'One day shooting',
                'description' => 'Tournage d\'une journée',
                'date_debut' => '2024-01-15',
                'date_fin' => '2024-01-15',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.titre', 'One day shooting')
            ->assertJsonPath('data.date_debut', '2024-01-15')
            ->assertJsonPath('data.date_fin', '2024-01-15')
            ->assertJsonPath('data.is_ongoing', false)
            ->assertJsonPath('data.formatted_period', '15/01/2024 - 15/01/2024');

        $this->assertDatabaseHas('experiences', [
            'face_id' => $this->face->id,
            'titre' => 'One day shooting',
            'date_debut' => '2024-01-15',
            'date_fin' => '2024-01-15',
        ]);
    }

    public function test_create_experience_with_future_end_date_fails(): void
    {
        $futureDate = now()->addMonth()->format('Y-m-d');

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/experiences', [
                'titre' => 'Une expérience',
                'date_debut' => '2024-01-01',
                'date_fin' => $futureDate,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date_fin'])
            ->assertJsonPath('errors.date_fin.0', 'La date de fin ne peut pas être dans le futur');
    }

    public function test_can_create_experience_with_optional_description(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/experiences', [
                'titre' => 'Expérience sans description',
                'date_debut' => '2023-01-01',
                'date_fin' => '2023-12-31',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.titre', 'Expérience sans description')
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.date_debut', '2023-01-01');

        $this->assertDatabaseHas('experiences', [
            'face_id' => $this->face->id,
            'titre' => 'Expérience sans description',
            'description' => null,
        ]);
    }

    public function test_create_experience_with_description_exceeding_max_length_fails(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/experiences', [
                'titre' => 'Une expérience',
                'description' => str_repeat('a', 501),
                'date_debut' => '2024-01-01',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['description'])
            ->assertJsonPath('errors.description.0', 'La description ne doit pas dépasser 500 caractères');
    }

    public function test_can_show_single_experience(): void
    {
        $experience = Experience::factory()->withDateRange('2023-01-15', '2023-06-30')->create([
            'face_id' => $this->face->id,
            'titre' => 'Mon expérience',
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/experiences/{$experience->uuid}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'titre', 'description', 'date_debut', 'date_fin', 'is_ongoing', 'formatted_period', 'created_at', 'updated_at'],
            ])
            ->assertJsonPath('data.id', $experience->uuid)
            ->assertJsonPath('data.titre', 'Mon expérience')
            ->assertJsonPath('data.date_debut', '2023-01-15')
            ->assertJsonPath('data.date_fin', '2023-06-30')
            ->assertJsonPath('data.is_ongoing', false)
            ->assertJsonPath('data.formatted_period', '15/01/2023 - 30/06/2023');
    }

    public function test_show_ongoing_experience_has_correct_format(): void
    {
        $experience = Experience::factory()->ongoing()->withDateRange('2024-01-01', null)->create([
            'face_id' => $this->face->id,
            'titre' => 'Expérience en cours',
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/experiences/{$experience->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.date_fin', null)
            ->assertJsonPath('data.is_ongoing', true)
            ->assertJsonPath('data.formatted_period', '01/01/2024 - Présent');
    }

    public function test_show_experience_owned_by_another_face_fails(): void
    {
        $otherFace = Face::factory()->create();
        $experience = Experience::factory()->create(['face_id' => $otherFace->id]);

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/experiences/{$experience->uuid}");

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', "Vous n'êtes pas autorisé à modifier cette expérience");
    }

    public function test_can_update_experience_with_valid_dates(): void
    {
        $experience = Experience::factory()->withDateRange('2020-01-01', '2020-12-31')->create([
            'face_id' => $this->face->id,
            'titre' => 'Ancien titre',
        ]);

        $response = $this->actingAs($this->faceUser)
            ->putJson("/api/v1/face/experiences/{$experience->uuid}", [
                'titre' => 'Nouveau titre',
                'description' => 'Nouvelle description',
                'date_debut' => '2024-01-15',
                'date_fin' => '2024-06-30',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.titre', 'Nouveau titre')
            ->assertJsonPath('data.description', 'Nouvelle description')
            ->assertJsonPath('data.date_debut', '2024-01-15')
            ->assertJsonPath('data.date_fin', '2024-06-30')
            ->assertJsonPath('data.is_ongoing', false)
            ->assertJsonPath('data.formatted_period', '15/01/2024 - 30/06/2024')
            ->assertJsonPath('message', 'Expérience mise à jour avec succès');

        $this->assertDatabaseHas('experiences', [
            'id' => $experience->id,
            'titre' => 'Nouveau titre',
            'date_debut' => '2024-01-15',
            'date_fin' => '2024-06-30',
        ]);
    }

    public function test_can_update_experience_to_ongoing(): void
    {
        $experience = Experience::factory()->withDateRange('2023-01-01', '2023-12-31')->create([
            'face_id' => $this->face->id,
            'titre' => 'Expérience terminée',
        ]);

        $response = $this->actingAs($this->faceUser)
            ->putJson("/api/v1/face/experiences/{$experience->uuid}", [
                'titre' => 'Expérience maintenant en cours',
                'date_debut' => '2024-01-01',
                'date_fin' => null,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.date_fin', null)
            ->assertJsonPath('data.is_ongoing', true)
            ->assertJsonPath('data.formatted_period', '01/01/2024 - Présent');
    }

    public function test_update_experience_owned_by_another_face_fails(): void
    {
        $otherFace = Face::factory()->create();
        $experience = Experience::factory()->create(['face_id' => $otherFace->id]);

        $response = $this->actingAs($this->faceUser)
            ->putJson("/api/v1/face/experiences/{$experience->uuid}", [
                'titre' => 'Tentative de modification',
                'date_debut' => '2024-01-01',
            ]);

        $response->assertForbidden();
    }

    public function test_can_delete_experience(): void
    {
        $experience = Experience::factory()->create(['face_id' => $this->face->id]);

        $response = $this->actingAs($this->faceUser)
            ->deleteJson("/api/v1/face/experiences/{$experience->uuid}");

        $response->assertOk()
            ->assertJsonPath('message', 'Expérience supprimée avec succès');

        $this->assertDatabaseMissing('experiences', ['id' => $experience->id]);
    }

    public function test_delete_experience_owned_by_another_face_fails(): void
    {
        $otherFace = Face::factory()->create();
        $experience = Experience::factory()->create(['face_id' => $otherFace->id]);

        $response = $this->actingAs($this->faceUser)
            ->deleteJson("/api/v1/face/experiences/{$experience->uuid}");

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->assertDatabaseHas('experiences', ['id' => $experience->id]);
    }

    public function test_unauthenticated_access_fails(): void
    {
        $response = $this->getJson('/api/v1/face/experiences');

        $response->assertUnauthorized();
    }

    public function test_producer_cannot_access_experiences_endpoint(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $response = $this->actingAs($producerUser)
            ->getJson('/api/v1/face/experiences');

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', 'Accès réservé aux Faces');
    }

    public function test_experience_factory_works_correctly(): void
    {
        $experience = Experience::factory()->create(['face_id' => $this->face->id]);

        $this->assertNotNull($experience->id);
        $this->assertNotNull($experience->titre);
        $this->assertNotNull($experience->date_debut);
        $this->assertEquals($this->face->id, $experience->face_id);
    }

    public function test_experience_belongs_to_face(): void
    {
        $experience = Experience::factory()->create(['face_id' => $this->face->id]);

        $this->assertInstanceOf(Face::class, $experience->face);
        $this->assertEquals($this->face->id, $experience->face->id);
    }

    public function test_face_has_many_experiences(): void
    {
        Experience::factory()->count(3)->create(['face_id' => $this->face->id]);

        $this->face->refresh();

        $this->assertCount(3, $this->face->experiences);
    }

    public function test_experiences_are_cascade_deleted_when_face_is_deleted(): void
    {
        $experience = Experience::factory()->create(['face_id' => $this->face->id]);
        $experienceId = $experience->id;

        $this->face->delete();

        $this->assertDatabaseMissing('experiences', ['id' => $experienceId]);
    }

    public function test_is_ongoing_accessor_returns_true_for_null_end_date(): void
    {
        $experience = Experience::factory()->ongoing()->create(['face_id' => $this->face->id]);

        $this->assertTrue($experience->is_ongoing);
    }

    public function test_is_ongoing_accessor_returns_false_for_non_null_end_date(): void
    {
        $experience = Experience::factory()->withDateRange('2023-01-01', '2023-12-31')->create(['face_id' => $this->face->id]);

        $this->assertFalse($experience->is_ongoing);
    }

    public function test_formatted_period_accessor_with_end_date(): void
    {
        $experience = Experience::factory()->withDateRange('2023-01-15', '2023-03-20')->create(['face_id' => $this->face->id]);

        $this->assertEquals('15/01/2023 - 20/03/2023', $experience->formatted_period);
    }

    public function test_formatted_period_accessor_without_end_date(): void
    {
        $experience = Experience::factory()->withDateRange('2024-06-01', null)->create(['face_id' => $this->face->id]);

        $this->assertEquals('01/06/2024 - Présent', $experience->formatted_period);
    }
}
