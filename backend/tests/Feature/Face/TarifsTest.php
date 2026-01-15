<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TarifsTest extends TestCase
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

    public function test_can_get_tarifs_empty_initially(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/tarifs');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'tarif_horaire',
                    'tarif_journalier',
                    'formatted_tarif_horaire',
                    'formatted_tarif_journalier',
                ],
            ])
            ->assertJsonPath('data.tarif_horaire', null)
            ->assertJsonPath('data.tarif_journalier', null)
            ->assertJsonPath('data.formatted_tarif_horaire', null)
            ->assertJsonPath('data.formatted_tarif_journalier', null);
    }

    public function test_can_get_tarifs_with_existing_values(): void
    {
        $this->face->update([
            'tarif_horaire' => 75000,
            'tarif_journalier' => 250000,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/tarifs');

        $response->assertOk()
            ->assertJsonPath('data.tarif_horaire', 75000)
            ->assertJsonPath('data.tarif_journalier', 250000)
            ->assertJsonPath('data.formatted_tarif_horaire', '75 000 XOF/heure')
            ->assertJsonPath('data.formatted_tarif_journalier', '250 000 XOF/jour');
    }

    public function test_can_update_tarif_horaire_successfully(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/tarifs', [
                'tarif_horaire' => 75000,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.tarif_horaire', 75000)
            ->assertJsonPath('data.formatted_tarif_horaire', '75 000 XOF/heure')
            ->assertJsonPath('message', 'Tarifs mis à jour avec succès');

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'tarif_horaire' => 75000,
        ]);
    }

    public function test_can_update_tarif_journalier_successfully(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/tarifs', [
                'tarif_journalier' => 250000,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.tarif_journalier', 250000)
            ->assertJsonPath('data.formatted_tarif_journalier', '250 000 XOF/jour')
            ->assertJsonPath('message', 'Tarifs mis à jour avec succès');

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'tarif_journalier' => 250000,
        ]);
    }

    public function test_can_update_both_tarifs_together(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/tarifs', [
                'tarif_horaire' => 75000,
                'tarif_journalier' => 250000,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.tarif_horaire', 75000)
            ->assertJsonPath('data.tarif_journalier', 250000);

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'tarif_horaire' => 75000,
            'tarif_journalier' => 250000,
        ]);
    }

    public function test_can_clear_tarif_horaire(): void
    {
        $this->face->update(['tarif_horaire' => 75000]);

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/tarifs', [
                'tarif_horaire' => null,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.tarif_horaire', null)
            ->assertJsonPath('data.formatted_tarif_horaire', null);

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'tarif_horaire' => null,
        ]);
    }

    public function test_can_clear_tarif_journalier(): void
    {
        $this->face->update(['tarif_journalier' => 250000]);

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/tarifs', [
                'tarif_journalier' => null,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.tarif_journalier', null)
            ->assertJsonPath('data.formatted_tarif_journalier', null);

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'tarif_journalier' => null,
        ]);
    }

    public function test_rejects_negative_tarif_horaire(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/tarifs', [
                'tarif_horaire' => -1,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['tarif_horaire'])
            ->assertJsonPath('errors.tarif_horaire.0', 'Le tarif horaire doit être positif');
    }

    public function test_rejects_negative_tarif_journalier(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/tarifs', [
                'tarif_journalier' => -1,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['tarif_journalier'])
            ->assertJsonPath('errors.tarif_journalier.0', 'Le tarif journalier doit être positif');
    }

    public function test_rejects_non_integer_tarif_horaire(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/tarifs', [
                'tarif_horaire' => 75000.5,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['tarif_horaire'])
            ->assertJsonPath('errors.tarif_horaire.0', 'Le tarif horaire doit être un nombre entier');
    }

    public function test_rejects_non_integer_tarif_journalier(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/tarifs', [
                'tarif_journalier' => 250000.5,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['tarif_journalier'])
            ->assertJsonPath('errors.tarif_journalier.0', 'Le tarif journalier doit être un nombre entier');
    }

    public function test_rejects_string_tarif_horaire(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/tarifs', [
                'tarif_horaire' => 'cher',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['tarif_horaire']);
    }

    public function test_rejects_string_tarif_journalier(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/tarifs', [
                'tarif_journalier' => 'cher',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['tarif_journalier']);
    }

    public function test_rejects_tarif_horaire_above_maximum(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/tarifs', [
                'tarif_horaire' => 10000001,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['tarif_horaire'])
            ->assertJsonPath('errors.tarif_horaire.0', 'Le tarif horaire ne peut pas dépasser 10 000 000 XOF');
    }

    public function test_rejects_tarif_journalier_above_maximum(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/tarifs', [
                'tarif_journalier' => 100000001,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['tarif_journalier'])
            ->assertJsonPath('errors.tarif_journalier.0', 'Le tarif journalier ne peut pas dépasser 100 000 000 XOF');
    }

    public function test_accepts_tarif_horaire_at_maximum_boundary(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/tarifs', [
                'tarif_horaire' => 10000000,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.tarif_horaire', 10000000);
    }

    public function test_accepts_tarif_journalier_at_maximum_boundary(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/tarifs', [
                'tarif_journalier' => 100000000,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.tarif_journalier', 100000000);
    }

    public function test_accepts_zero_tarif_horaire(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/tarifs', [
                'tarif_horaire' => 0,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.tarif_horaire', 0);
    }

    public function test_accepts_zero_tarif_journalier(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/tarifs', [
                'tarif_journalier' => 0,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.tarif_journalier', 0);
    }

    public function test_producer_cannot_access_tarifs_endpoint(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $response = $this->actingAs($producerUser)
            ->getJson('/api/v1/face/tarifs');

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', 'Accès réservé aux Faces');
    }

    public function test_producer_cannot_update_tarifs(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $response = $this->actingAs($producerUser)
            ->putJson('/api/v1/face/tarifs', [
                'tarif_horaire' => 75000,
            ]);

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_tarifs(): void
    {
        $response = $this->getJson('/api/v1/face/tarifs');

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_update_tarifs(): void
    {
        $response = $this->putJson('/api/v1/face/tarifs', [
            'tarif_horaire' => 75000,
        ]);

        $response->assertUnauthorized();
    }

    public function test_rejects_tarif_journalier_lower_than_tarif_horaire(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/tarifs', [
                'tarif_horaire' => 100000,
                'tarif_journalier' => 50000,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['tarif_journalier'])
            ->assertJsonPath('errors.tarif_journalier.0', 'Le tarif journalier doit être supérieur ou égal au tarif horaire');
    }

    public function test_accepts_tarif_journalier_equal_to_tarif_horaire(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/tarifs', [
                'tarif_horaire' => 100000,
                'tarif_journalier' => 100000,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.tarif_horaire', 100000)
            ->assertJsonPath('data.tarif_journalier', 100000);
    }

    public function test_accepts_tarif_journalier_higher_than_tarif_horaire(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/tarifs', [
                'tarif_horaire' => 75000,
                'tarif_journalier' => 250000,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.tarif_horaire', 75000)
            ->assertJsonPath('data.tarif_journalier', 250000);
    }
}
