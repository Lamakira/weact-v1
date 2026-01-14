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

    public function test_can_list_experiences_ordered_by_year_descending(): void
    {
        Experience::factory()->forYear(2020)->create(['face_id' => $this->face->id]);
        Experience::factory()->forYear(2024)->create(['face_id' => $this->face->id]);
        Experience::factory()->forYear(2022)->create(['face_id' => $this->face->id]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/experiences');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'titre', 'description', 'annee', 'created_at', 'updated_at'],
                ],
                'message',
            ])
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('message', 'Expériences récupérées avec succès');

        $years = collect($response->json('data'))->pluck('annee')->toArray();
        $this->assertEquals([2024, 2022, 2020], $years);
    }

    public function test_can_create_experience_with_valid_data(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/experiences', [
                'titre' => 'Publicité Coca-Cola',
                'description' => 'Rôle principal dans une publicité nationale',
                'annee' => 2024,
            ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => ['id', 'titre', 'description', 'annee', 'created_at', 'updated_at'],
                'message',
            ])
            ->assertJsonPath('data.titre', 'Publicité Coca-Cola')
            ->assertJsonPath('data.description', 'Rôle principal dans une publicité nationale')
            ->assertJsonPath('data.annee', 2024)
            ->assertJsonPath('message', 'Expérience ajoutée avec succès');

        $this->assertDatabaseHas('experiences', [
            'face_id' => $this->face->id,
            'titre' => 'Publicité Coca-Cola',
            'annee' => 2024,
        ]);
    }

    public function test_create_experience_without_title_fails(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/experiences', [
                'description' => 'Une description',
                'annee' => 2024,
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
                'annee' => 2024,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['titre'])
            ->assertJsonPath('errors.titre.0', 'Le titre ne doit pas dépasser 150 caractères');
    }

    public function test_create_experience_without_year_fails(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/experiences', [
                'titre' => 'Une expérience',
                'description' => 'Une description',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['annee'])
            ->assertJsonPath('errors.annee.0', "L'année est requise");
    }

    public function test_create_experience_with_year_before_1950_fails(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/experiences', [
                'titre' => 'Une expérience',
                'annee' => 1940,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['annee'])
            ->assertJsonPath('errors.annee.0', "L'année doit être supérieure ou égale à 1950");
    }

    public function test_create_experience_with_future_year_fails(): void
    {
        $futureYear = (int) date('Y') + 1;

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/experiences', [
                'titre' => 'Une expérience',
                'annee' => $futureYear,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['annee'])
            ->assertJsonPath('errors.annee.0', "L'année ne peut pas être dans le futur");
    }

    public function test_can_create_experience_with_optional_description(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/experiences', [
                'titre' => 'Expérience sans description',
                'annee' => 2023,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.titre', 'Expérience sans description')
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.annee', 2023);

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
                'annee' => 2024,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['description'])
            ->assertJsonPath('errors.description.0', 'La description ne doit pas dépasser 500 caractères');
    }

    public function test_can_show_single_experience(): void
    {
        $experience = Experience::factory()->create([
            'face_id' => $this->face->id,
            'titre' => 'Mon expérience',
            'annee' => 2023,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/experiences/{$experience->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'titre', 'description', 'annee', 'created_at', 'updated_at'],
            ])
            ->assertJsonPath('data.id', $experience->id)
            ->assertJsonPath('data.titre', 'Mon expérience');
    }

    public function test_show_experience_owned_by_another_face_fails(): void
    {
        $otherFace = Face::factory()->create();
        $experience = Experience::factory()->create(['face_id' => $otherFace->id]);

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/experiences/{$experience->id}");

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', "Vous n'êtes pas autorisé à modifier cette expérience");
    }

    public function test_can_update_experience_with_valid_data(): void
    {
        $experience = Experience::factory()->create([
            'face_id' => $this->face->id,
            'titre' => 'Ancien titre',
            'annee' => 2020,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->putJson("/api/v1/face/experiences/{$experience->id}", [
                'titre' => 'Nouveau titre',
                'description' => 'Nouvelle description',
                'annee' => 2024,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.titre', 'Nouveau titre')
            ->assertJsonPath('data.description', 'Nouvelle description')
            ->assertJsonPath('data.annee', 2024)
            ->assertJsonPath('message', 'Expérience mise à jour avec succès');

        $this->assertDatabaseHas('experiences', [
            'id' => $experience->id,
            'titre' => 'Nouveau titre',
            'annee' => 2024,
        ]);
    }

    public function test_update_experience_owned_by_another_face_fails(): void
    {
        $otherFace = Face::factory()->create();
        $experience = Experience::factory()->create(['face_id' => $otherFace->id]);

        $response = $this->actingAs($this->faceUser)
            ->putJson("/api/v1/face/experiences/{$experience->id}", [
                'titre' => 'Tentative de modification',
                'annee' => 2024,
            ]);

        $response->assertForbidden();
    }

    public function test_can_delete_experience(): void
    {
        $experience = Experience::factory()->create(['face_id' => $this->face->id]);

        $response = $this->actingAs($this->faceUser)
            ->deleteJson("/api/v1/face/experiences/{$experience->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Expérience supprimée avec succès');

        $this->assertDatabaseMissing('experiences', ['id' => $experience->id]);
    }

    public function test_delete_experience_owned_by_another_face_fails(): void
    {
        $otherFace = Face::factory()->create();
        $experience = Experience::factory()->create(['face_id' => $otherFace->id]);

        $response = $this->actingAs($this->faceUser)
            ->deleteJson("/api/v1/face/experiences/{$experience->id}");

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
        $this->assertNotNull($experience->annee);
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
}
