<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Constants\BeninCities;
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

    /**
     * @var array<string, string>
     */
    private array $faceHeaders;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a Face user
        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
        $this->faceHeaders = $this->authHeadersFor($this->faceUser);
    }

    public function test_can_get_bio_location_info(): void
    {
        $this->face->update([
            'bio' => 'Je suis acteur professionnel',
            'ville' => 'Cotonou',
            'pays' => 'Bénin',
        ]);

        $response = $this->getJson('/api/v1/face/bio-location', $this->faceHeaders);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'bio',
                    'ville',
                    'pays',
                    'formatted_location',
                ],
            ])
            ->assertJsonPath('data.bio', 'Je suis acteur professionnel')
            ->assertJsonPath('data.ville', 'Cotonou')
            ->assertJsonPath('data.pays', 'Bénin')
            ->assertJsonPath('data.formatted_location', 'Cotonou, Bénin')
            ->assertJsonMissingPath('data.quartier');
    }

    public function test_can_update_bio_successfully(): void
    {
        $response = $this->putJson('/api/v1/face/bio-location', [
            'bio' => 'Une bio de test pour mon profil',
        ], $this->faceHeaders);

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
        $response = $this->putJson('/api/v1/face/bio-location', [
            'ville' => 'Porto-Novo',
            'pays' => 'Bénin',
        ], $this->faceHeaders);

        $response->assertOk()
            ->assertJsonPath('data.ville', 'Porto-Novo')
            ->assertJsonPath('data.pays', 'Bénin')
            ->assertJsonPath('data.formatted_location', 'Porto-Novo, Bénin')
            ->assertJsonMissingPath('data.quartier');
    }

    public function test_can_update_bio_and_location_together(): void
    {
        $response = $this->putJson('/api/v1/face/bio-location', [
            'bio' => 'Acteur et mannequin professionnel',
            'ville' => 'Cotonou',
            'pays' => 'Bénin',
        ], $this->faceHeaders);

        $response->assertOk()
            ->assertJsonPath('data.bio', 'Acteur et mannequin professionnel')
            ->assertJsonPath('data.ville', 'Cotonou')
            ->assertJsonPath('data.formatted_location', 'Cotonou, Bénin')
            ->assertJsonMissingPath('data.quartier');
    }

    public function test_rejects_bio_exceeding_500_characters(): void
    {
        $longBio = str_repeat('a', 501);

        $response = $this->putJson('/api/v1/face/bio-location', [
            'bio' => $longBio,
        ], $this->faceHeaders);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['bio'])
            ->assertJsonPath('errors.bio.0', 'La bio ne peut pas dépasser 500 caractères');
    }

    public function test_accepts_bio_with_exactly_500_characters(): void
    {
        $exactBio = str_repeat('a', 500);

        $response = $this->putJson('/api/v1/face/bio-location', [
            'bio' => $exactBio,
        ], $this->faceHeaders);

        $response->assertOk();

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'bio' => $exactBio,
        ]);
    }

    public function test_rejects_city_outside_official_benin_list(): void
    {
        $response = $this->putJson('/api/v1/face/bio-location', [
            'ville' => 'Ab-Calavi',
            'pays' => 'Bénin',
        ], $this->faceHeaders);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['ville'])
            ->assertJsonPath('errors.ville.0', 'La ville sélectionnée doit faire partie de la liste officielle des communes du Bénin.');
    }

    public function test_rejects_city_when_country_is_not_benin(): void
    {
        $response = $this->putJson('/api/v1/face/bio-location', [
            'ville' => 'Cotonou',
            'pays' => 'Togo',
        ], $this->faceHeaders);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['ville'])
            ->assertJsonPath('errors.ville.0', 'La ville doit être vide lorsque le pays n\'est pas "Bénin".');
    }

    public function test_clears_ville_when_country_changes_away_from_benin_without_ville_payload(): void
    {
        $this->face->update([
            'ville' => 'Cotonou',
            'pays' => 'Bénin',
        ]);

        $response = $this->putJson('/api/v1/face/bio-location', [
            'pays' => 'Togo',
        ], $this->faceHeaders);

        $response->assertOk()
            ->assertJsonPath('data.pays', 'Togo')
            ->assertJsonPath('data.ville', null);

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'pays' => 'Togo',
            'ville' => null,
        ]);
    }

    public function test_can_clear_bio(): void
    {
        $this->face->update(['bio' => 'Original bio']);

        $response = $this->putJson('/api/v1/face/bio-location', [
            'bio' => null,
        ], $this->faceHeaders);

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

        $response = $this->putJson('/api/v1/face/bio-location', [
            'bio' => '',
        ], $this->faceHeaders);

        $response->assertOk();
    }

    public function test_can_clear_location_fields(): void
    {
        $this->face->update([
            'ville' => 'Cotonou',
            'pays' => 'Bénin',
        ]);

        $response = $this->putJson('/api/v1/face/bio-location', [
            'ville' => null,
            'pays' => null,
        ], $this->faceHeaders);

        $response->assertOk()
            ->assertJsonPath('data.ville', null)
            ->assertJsonPath('data.pays', null)
            ->assertJsonMissingPath('data.quartier');
    }

    public function test_formatted_location_with_only_ville(): void
    {
        $this->face->update(['pays' => 'Bénin']);

        $response = $this->putJson('/api/v1/face/bio-location', [
            'ville' => 'Cotonou',
        ], $this->faceHeaders);

        $response->assertOk()
            ->assertJsonPath('data.formatted_location', 'Cotonou, Bénin');
    }

    public function test_formatted_location_with_ville_and_pays(): void
    {
        $response = $this->putJson('/api/v1/face/bio-location', [
            'ville' => 'Cotonou',
            'pays' => 'Bénin',
        ], $this->faceHeaders);

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
        $producerHeaders = $this->authHeadersFor($producerUser);

        $response = $this->getJson('/api/v1/face/bio-location', $producerHeaders);

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
        $producerHeaders = $this->authHeadersFor($producerUser);

        $response = $this->putJson('/api/v1/face/bio-location', [
            'bio' => 'Test bio',
        ], $producerHeaders);

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

        $response = $this->putJson('/api/v1/face/bio-location', [
            'ville' => $longVille,
        ], $this->faceHeaders);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['ville'])
            ->assertJsonPath('errors.ville.0', 'La ville ne peut pas dépasser 100 caractères');
    }

    public function test_formatted_location_accessor_returns_null_when_all_empty(): void
    {
        $face = Face::factory()->create([
            'ville' => null,
            'pays' => null,
        ]);

        $this->assertNull($face->formatted_location);
    }

    public function test_formatted_location_accessor_filters_empty_values(): void
    {
        $face = Face::factory()->create([
            'ville' => 'Cotonou',
            'pays' => 'Bénin',
        ]);

        $this->assertEquals('Cotonou, Bénin', $face->formatted_location);
    }

    public function test_benin_cities_constant_contains_77_communes(): void
    {
        $this->assertCount(77, BeninCities::values());
        $this->assertContains('Abomey-Calavi', BeninCities::values());
        $this->assertContains('Porto-Novo', BeninCities::values());
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

    /**
     * @return array<string, string>
     */
    private function authHeadersFor(User $user): array
    {
        $token = $user->createToken('test-token')->plainTextToken;

        return [
            'Authorization' => "Bearer {$token}",
        ];
    }
}
