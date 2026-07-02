<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Enums\FaceGender;
use App\Models\Face;
use App\Models\FaceVideo;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalInfoTest extends TestCase
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

    public function test_can_get_personal_info(): void
    {
        $this->face->update([
            'sexe' => FaceGender::HOMME->value,
            'date_naissance' => '1995-06-15',
            'nationalite' => 'Béninoise',
            'pays' => 'Bénin',
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/personal-info');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'sexe',
                    'date_naissance',
                    'nationalite',
                    'pays',
                ],
            ])
            ->assertJsonPath('data.sexe', 'homme')
            ->assertJsonPath('data.date_naissance', '1995-06-15')
            ->assertJsonPath('data.nationalite', 'Béninoise')
            ->assertJsonPath('data.pays', 'Bénin');
    }

    public function test_can_update_personal_info(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/personal-info', [
                'sexe' => 'femme',
                'date_naissance' => '1990-03-20',
                'nationalite' => 'Togolaise',
                'pays' => 'Togo',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.sexe', 'femme')
            ->assertJsonPath('data.date_naissance', '1990-03-20')
            ->assertJsonPath('data.nationalite', 'Togolaise')
            ->assertJsonPath('data.pays', 'Togo')
            ->assertJsonPath('message', 'Informations personnelles mises à jour avec succès');

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'sexe' => 'femme',
            'nationalite' => 'Togolaise',
            'pays' => 'Togo',
        ]);
    }

    public function test_can_update_single_field(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/personal-info', [
                'sexe' => 'homme',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.sexe', 'homme')
            ->assertJsonPath('message', 'Informations personnelles mises à jour avec succès');

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'sexe' => 'homme',
        ]);
    }

    public function test_rejects_invalid_sexe(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/personal-info', [
                'sexe' => 'invalide',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['sexe'])
            ->assertJsonPath('errors.sexe.0', 'Le sexe sélectionné est invalide.');
    }

    public function test_rejects_too_young_date_naissance(): void
    {
        $tooYoung = now()->subYears(10)->format('Y-m-d');

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/personal-info', [
                'date_naissance' => $tooYoung,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date_naissance'])
            ->assertJsonPath('errors.date_naissance.0', 'Vous devez avoir au moins 16 ans.');
    }

    public function test_rejects_non_face_user(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $response = $this->actingAs($producerUser)
            ->getJson('/api/v1/face/personal-info');

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', 'Accès réservé aux Faces');
    }

    public function test_rejects_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/face/personal-info');

        $response->assertUnauthorized();
    }

    public function test_show_age_defaults_to_true(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/personal-info');

        $response->assertOk()
            ->assertJsonPath('data.show_age', true);

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'show_age' => true,
        ]);
    }

    public function test_can_update_show_age_to_false(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/personal-info', [
                'show_age' => false,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.show_age', false);

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'show_age' => false,
        ]);
    }

    public function test_can_update_show_age_to_true(): void
    {
        $this->face->update(['show_age' => false]);

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/personal-info', [
                'show_age' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.show_age', true);

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'show_age' => true,
        ]);
    }

    public function test_rejects_non_boolean_show_age(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/personal-info', [
                'show_age' => 'yes',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['show_age'])
            ->assertJsonPath('errors.show_age.0', 'La valeur doit être vrai ou faux.');
    }

    public function test_get_personal_info_includes_whatsapp_number(): void
    {
        $this->face->update(['whatsapp_number' => '+22990001122']);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/personal-info');

        $response->assertOk()
            ->assertJsonPath('data.whatsapp_number', '+22990001122');
    }

    public function test_can_update_whatsapp_number(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/personal-info', [
                'whatsapp_number' => '+22997001234',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.whatsapp_number', '+22997001234');

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'whatsapp_number' => '+22997001234',
        ]);
    }

    public function test_can_clear_whatsapp_number(): void
    {
        $this->face->update(['whatsapp_number' => '+22990001122']);

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/personal-info', [
                'whatsapp_number' => null,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.whatsapp_number', null);

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'whatsapp_number' => null,
        ]);
    }

    public function test_rejects_whatsapp_number_longer_than_30_chars(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/personal-info', [
                'whatsapp_number' => str_repeat('1', 31),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['whatsapp_number'])
            ->assertJsonPath('errors.whatsapp_number.0', 'Le numéro WhatsApp ne peut pas dépasser 30 caractères.');
    }

    public function test_profile_completion_counts_whatsapp_number(): void
    {
        // Face with all 9 fields filled
        $this->face->update([
            'profile_photo' => 'photo.jpg',
            'presentation_video' => 'video.mp4',
            'bio' => 'My bio',
            'ville' => 'Cotonou',
            'categories' => ['mannequin'],
            'tarif_horaire' => 5000,
            'langues' => ['francais'],
            'whatsapp_number' => '+22990001122',
        ]);
        FaceVideo::factory()->acting()->create(['face_id' => $this->face->id]);

        $this->assertEquals(100, $this->face->fresh()->profile_completion_percentage);
    }

    public function test_profile_completion_missing_includes_whatsapp_when_empty(): void
    {
        $face = $this->face->fresh();
        $missing = $face->profile_completion_missing;
        $missingKeys = array_column($missing, 'key');

        $this->assertContains('whatsapp_number', $missingKeys);

        $whatsappItem = collect($missing)->firstWhere('key', 'whatsapp_number');
        $this->assertEquals('Ajoutez votre numéro WhatsApp', $whatsappItem['label']);
    }

    public function test_face_owner_always_sees_own_age_regardless_of_show_age(): void
    {
        $this->face->update([
            'date_naissance' => '1995-06-15',
            'show_age' => false,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/profile');

        $response->assertOk();
        $this->assertNotNull($response->json('data.age'));
    }
}
