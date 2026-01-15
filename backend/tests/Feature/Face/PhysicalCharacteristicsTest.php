<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhysicalCharacteristicsTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    private Face $face;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a Face user
        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    public function test_can_get_physical_characteristics(): void
    {
        $this->face->update([
            'taille' => 175,
            'poids' => 70,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/physical-characteristics');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'taille',
                    'poids',
                ],
            ])
            ->assertJsonPath('data.taille', 175)
            ->assertJsonPath('data.poids', 70);
    }

    public function test_can_update_taille_successfully(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/physical-characteristics', [
                'taille' => 180,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.taille', 180)
            ->assertJsonPath('message', 'Profil mis à jour avec succès');

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'taille' => 180,
        ]);
    }

    public function test_can_update_poids_successfully(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/physical-characteristics', [
                'poids' => 75,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.poids', 75)
            ->assertJsonPath('message', 'Profil mis à jour avec succès');

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'poids' => 75,
        ]);
    }

    public function test_can_update_taille_and_poids_together(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/physical-characteristics', [
                'taille' => 172,
                'poids' => 68,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.taille', 172)
            ->assertJsonPath('data.poids', 68);

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'taille' => 172,
            'poids' => 68,
        ]);
    }

    public function test_rejects_taille_below_minimum(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/physical-characteristics', [
                'taille' => 49,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['taille'])
            ->assertJsonPath('errors.taille.0', 'La taille doit être entre 50 et 300 cm');
    }

    public function test_rejects_taille_above_maximum(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/physical-characteristics', [
                'taille' => 301,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['taille'])
            ->assertJsonPath('errors.taille.0', 'La taille doit être entre 50 et 300 cm');
    }

    public function test_rejects_poids_below_minimum(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/physical-characteristics', [
                'poids' => 19,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['poids'])
            ->assertJsonPath('errors.poids.0', 'Le poids doit être entre 20 et 500 kg');
    }

    public function test_rejects_poids_above_maximum(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/physical-characteristics', [
                'poids' => 501,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['poids'])
            ->assertJsonPath('errors.poids.0', 'Le poids doit être entre 20 et 500 kg');
    }

    public function test_accepts_taille_at_minimum_boundary(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/physical-characteristics', [
                'taille' => 50,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.taille', 50);
    }

    public function test_accepts_taille_at_maximum_boundary(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/physical-characteristics', [
                'taille' => 300,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.taille', 300);
    }

    public function test_accepts_poids_at_minimum_boundary(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/physical-characteristics', [
                'poids' => 20,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.poids', 20);
    }

    public function test_accepts_poids_at_maximum_boundary(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/physical-characteristics', [
                'poids' => 500,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.poids', 500);
    }

    public function test_can_clear_taille(): void
    {
        $this->face->update(['taille' => 175]);

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/physical-characteristics', [
                'taille' => null,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.taille', null);

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'taille' => null,
        ]);
    }

    public function test_can_clear_poids(): void
    {
        $this->face->update(['poids' => 70]);

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/physical-characteristics', [
                'poids' => null,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.poids', null);

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'poids' => null,
        ]);
    }

    public function test_producer_cannot_access_physical_characteristics_endpoint(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $response = $this->actingAs($producerUser)
            ->getJson('/api/v1/face/physical-characteristics');

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', 'Accès réservé aux Faces');
    }

    public function test_producer_cannot_update_physical_characteristics(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $response = $this->actingAs($producerUser)
            ->putJson('/api/v1/face/physical-characteristics', [
                'taille' => 175,
            ]);

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_physical_characteristics(): void
    {
        $response = $this->getJson('/api/v1/face/physical-characteristics');

        $response->assertUnauthorized();
    }

    public function test_rejects_negative_taille(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/physical-characteristics', [
                'taille' => -10,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['taille'])
            ->assertJsonPath('errors.taille.0', 'La valeur doit être positive');
    }

    public function test_rejects_negative_poids(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/physical-characteristics', [
                'poids' => -5,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['poids'])
            ->assertJsonPath('errors.poids.0', 'La valeur doit être positive');
    }

    public function test_rejects_non_integer_taille(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/physical-characteristics', [
                'taille' => 175.5,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['taille'])
            ->assertJsonPath('errors.taille.0', 'La taille doit être un nombre entier');
    }

    public function test_rejects_non_integer_poids(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/physical-characteristics', [
                'poids' => 70.5,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['poids'])
            ->assertJsonPath('errors.poids.0', 'Le poids doit être un nombre entier');
    }

    public function test_rejects_string_taille(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/physical-characteristics', [
                'taille' => 'grand',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['taille']);
    }

    public function test_rejects_string_poids(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/physical-characteristics', [
                'poids' => 'lourd',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['poids']);
    }
}
