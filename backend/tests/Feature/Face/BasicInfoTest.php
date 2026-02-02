<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BasicInfoTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    private Face $face;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a Face user
        $this->face = Face::factory()->create([
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'username' => 'jeandupont',
        ]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    public function test_can_get_basic_info(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/basic-info');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'nom',
                    'prenom',
                    'username',
                ],
            ])
            ->assertJsonPath('data.nom', 'Dupont')
            ->assertJsonPath('data.prenom', 'Jean')
            ->assertJsonPath('data.username', 'jeandupont');
    }

    public function test_can_update_nom_successfully(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/basic-info', [
                'nom' => 'Martin',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.nom', 'Martin')
            ->assertJsonPath('message', 'Informations mises à jour avec succès');

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'nom' => 'Martin',
        ]);
    }

    public function test_can_update_prenom_successfully(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/basic-info', [
                'prenom' => 'Pierre',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.prenom', 'Pierre')
            ->assertJsonPath('message', 'Informations mises à jour avec succès');

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'prenom' => 'Pierre',
        ]);
    }

    public function test_can_update_username_successfully(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/basic-info', [
                'username' => 'jean_updated',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.username', 'jean_updated')
            ->assertJsonPath('message', 'Informations mises à jour avec succès');

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'username' => 'jean_updated',
        ]);
    }

    public function test_can_update_all_fields_together(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/basic-info', [
                'nom' => 'Nouveau',
                'prenom' => 'Prenom',
                'username' => 'nouveau_username',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.nom', 'Nouveau')
            ->assertJsonPath('data.prenom', 'Prenom')
            ->assertJsonPath('data.username', 'nouveau_username');

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'nom' => 'Nouveau',
            'prenom' => 'Prenom',
            'username' => 'nouveau_username',
        ]);
    }

    public function test_rejects_username_already_taken(): void
    {
        // Create another face with an existing username
        Face::factory()->create(['username' => 'existinguser']);

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/basic-info', [
                'username' => 'existinguser',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['username'])
            ->assertJsonPath('errors.username.0', "Ce nom d'utilisateur est déjà pris");
    }

    public function test_can_keep_same_username(): void
    {
        // User should be able to update other fields while keeping same username
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/basic-info', [
                'nom' => 'NewNom',
                'username' => 'jeandupont', // Same as current
            ]);

        $response->assertOk()
            ->assertJsonPath('data.nom', 'NewNom')
            ->assertJsonPath('data.username', 'jeandupont');
    }

    public function test_rejects_nom_exceeding_100_characters(): void
    {
        $longNom = str_repeat('a', 101);

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/basic-info', [
                'nom' => $longNom,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['nom'])
            ->assertJsonPath('errors.nom.0', 'Le nom ne peut pas dépasser 100 caractères');
    }

    public function test_rejects_prenom_exceeding_100_characters(): void
    {
        $longPrenom = str_repeat('a', 101);

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/basic-info', [
                'prenom' => $longPrenom,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['prenom'])
            ->assertJsonPath('errors.prenom.0', 'Le prénom ne peut pas dépasser 100 caractères');
    }

    public function test_rejects_username_exceeding_50_characters(): void
    {
        $longUsername = str_repeat('a', 51);

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/basic-info', [
                'username' => $longUsername,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['username'])
            ->assertJsonPath('errors.username.0', "Le nom d'utilisateur ne peut pas dépasser 50 caractères");
    }

    public function test_rejects_empty_nom_when_provided(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/basic-info', [
                'nom' => '',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['nom'])
            ->assertJsonPath('errors.nom.0', 'Le nom est obligatoire');
    }

    public function test_rejects_empty_prenom_when_provided(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/basic-info', [
                'prenom' => '',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['prenom'])
            ->assertJsonPath('errors.prenom.0', 'Le prénom est obligatoire');
    }

    public function test_rejects_empty_username_when_provided(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/basic-info', [
                'username' => '',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['username'])
            ->assertJsonPath('errors.username.0', "Le nom d'utilisateur est obligatoire");
    }

    public function test_producer_cannot_access_basic_info_endpoint(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $response = $this->actingAs($producerUser)
            ->getJson('/api/v1/face/basic-info');

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', 'Accès réservé aux Faces');
    }

    public function test_producer_cannot_update_basic_info(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $response = $this->actingAs($producerUser)
            ->putJson('/api/v1/face/basic-info', [
                'nom' => 'Test',
            ]);

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_basic_info(): void
    {
        $response = $this->getJson('/api/v1/face/basic-info');

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_update_basic_info(): void
    {
        $response = $this->putJson('/api/v1/face/basic-info', [
            'nom' => 'Test',
        ]);

        $response->assertUnauthorized();
    }

    public function test_accepts_nom_with_exactly_100_characters(): void
    {
        $exactNom = str_repeat('a', 100);

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/basic-info', [
                'nom' => $exactNom,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'nom' => $exactNom,
        ]);
    }

    public function test_accepts_username_with_exactly_50_characters(): void
    {
        $exactUsername = str_repeat('a', 50);

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/basic-info', [
                'username' => $exactUsername,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'username' => $exactUsername,
        ]);
    }
}
