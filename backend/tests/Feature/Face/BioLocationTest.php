<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BioLocationTest extends TestCase
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

    public function test_can_get_bio_location_info(): void
    {
        $this->face->update([
            'bio' => 'Je suis acteur professionnel',
            'ville' => 'Cotonou',
            'quartier' => 'Akpakpa',
            'pays' => 'Bénin',
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/bio-location');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'bio',
                    'ville',
                    'quartier',
                    'pays',
                    'formatted_location',
                ],
            ])
            ->assertJsonPath('data.bio', 'Je suis acteur professionnel')
            ->assertJsonPath('data.ville', 'Cotonou')
            ->assertJsonPath('data.quartier', 'Akpakpa')
            ->assertJsonPath('data.pays', 'Bénin')
            ->assertJsonPath('data.formatted_location', 'Cotonou, Akpakpa, Bénin');
    }

    public function test_can_update_bio_successfully(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/bio-location', [
                'bio' => 'Une bio de test pour mon profil',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.bio', 'Une bio de test pour mon profil')
            ->assertJsonPath('message', 'Profil mis à jour avec succès');

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'bio' => 'Une bio de test pour mon profil',
        ]);
    }

    public function test_can_update_location_successfully(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/bio-location', [
                'ville' => 'Porto-Novo',
                'quartier' => 'Centre',
                'pays' => 'Bénin',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.ville', 'Porto-Novo')
            ->assertJsonPath('data.quartier', 'Centre')
            ->assertJsonPath('data.pays', 'Bénin')
            ->assertJsonPath('data.formatted_location', 'Porto-Novo, Centre, Bénin');
    }

    public function test_can_update_bio_and_location_together(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/bio-location', [
                'bio' => 'Acteur et mannequin professionnel',
                'ville' => 'Cotonou',
                'quartier' => 'Ganhi',
                'pays' => 'Bénin',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.bio', 'Acteur et mannequin professionnel')
            ->assertJsonPath('data.ville', 'Cotonou')
            ->assertJsonPath('data.quartier', 'Ganhi')
            ->assertJsonPath('data.formatted_location', 'Cotonou, Ganhi, Bénin');
    }

    public function test_rejects_bio_exceeding_500_characters(): void
    {
        $longBio = str_repeat('a', 501);

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/bio-location', [
                'bio' => $longBio,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['bio'])
            ->assertJsonPath('errors.bio.0', 'La bio ne peut pas dépasser 500 caractères');
    }

    public function test_accepts_bio_with_exactly_500_characters(): void
    {
        $exactBio = str_repeat('a', 500);

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/bio-location', [
                'bio' => $exactBio,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'bio' => $exactBio,
        ]);
    }

    public function test_rejects_quartier_without_ville(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/bio-location', [
                'quartier' => 'Akpakpa',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['quartier'])
            ->assertJsonPath('errors.quartier.0', 'La ville est requise pour enregistrer le quartier');
    }

    public function test_can_clear_bio(): void
    {
        $this->face->update(['bio' => 'Original bio']);

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/bio-location', [
                'bio' => null,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.bio', null);

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'bio' => null,
        ]);
    }

    public function test_can_clear_bio_with_empty_string(): void
    {
        $this->face->update(['bio' => 'Original bio']);

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/bio-location', [
                'bio' => '',
            ]);

        $response->assertOk();
    }

    public function test_can_clear_location_fields(): void
    {
        $this->face->update([
            'ville' => 'Cotonou',
            'quartier' => 'Akpakpa',
            'pays' => 'Bénin',
        ]);

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/bio-location', [
                'ville' => null,
                'quartier' => null,
                'pays' => null,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.ville', null)
            ->assertJsonPath('data.quartier', null)
            ->assertJsonPath('data.pays', null);
    }

    public function test_formatted_location_with_only_ville(): void
    {
        // First clear pays to test only ville
        $this->face->update(['pays' => null]);

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/bio-location', [
                'ville' => 'Cotonou',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.formatted_location', 'Cotonou');
    }

    public function test_formatted_location_with_ville_and_pays(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/bio-location', [
                'ville' => 'Cotonou',
                'pays' => 'Bénin',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.formatted_location', 'Cotonou, Bénin');
    }

    public function test_producer_cannot_access_bio_location_endpoint(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $response = $this->actingAs($producerUser)
            ->getJson('/api/v1/face/bio-location');

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', 'Accès réservé aux Faces');
    }

    public function test_producer_cannot_update_bio_location(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $response = $this->actingAs($producerUser)
            ->putJson('/api/v1/face/bio-location', [
                'bio' => 'Test bio',
            ]);

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_bio_location(): void
    {
        $response = $this->getJson('/api/v1/face/bio-location');

        $response->assertUnauthorized();
    }

    public function test_rejects_ville_exceeding_100_characters(): void
    {
        $longVille = str_repeat('a', 101);

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/bio-location', [
                'ville' => $longVille,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['ville'])
            ->assertJsonPath('errors.ville.0', 'La ville ne peut pas dépasser 100 caractères');
    }

    public function test_formatted_location_accessor_returns_null_when_all_empty(): void
    {
        $face = Face::factory()->create([
            'ville' => null,
            'quartier' => null,
            'pays' => null,
        ]);

        $this->assertNull($face->formatted_location);
    }

    public function test_formatted_location_accessor_filters_empty_values(): void
    {
        $face = Face::factory()->create([
            'ville' => 'Cotonou',
            'quartier' => null,
            'pays' => 'Bénin',
        ]);

        $this->assertEquals('Cotonou, Bénin', $face->formatted_location);
    }

    public function test_default_pays_is_benin(): void
    {
        // Create a new face with migration default
        $newFace = Face::create([
            'nom' => 'Test',
            'prenom' => 'User',
            'username' => 'testuser',
        ]);

        // Refresh from database to get the default value
        $newFace->refresh();

        $this->assertEquals('Bénin', $newFace->pays);
    }
}
