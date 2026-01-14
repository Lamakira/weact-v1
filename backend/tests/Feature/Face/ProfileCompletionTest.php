<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Enums\FaceCategory;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileCompletionTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    private Face $face;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a Face user with empty profile
        $this->face = Face::factory()->create([
            'profile_photo' => null,
            'presentation_video' => null,
            'acting_video' => null,
            'bio' => null,
            'ville' => null,
            'categorie' => null,
            'tarif_horaire' => null,
            'tarif_journalier' => null,
        ]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    public function test_can_get_profile_completion_status(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/profile-completion');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'profile_completion_percentage',
                    'profile_completion_missing',
                    'profile_completion_is_complete',
                ],
            ]);
    }

    public function test_empty_profile_shows_zero_percent(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/profile-completion');

        $response->assertOk()
            ->assertJsonPath('data.profile_completion_percentage', 0)
            ->assertJsonPath('data.profile_completion_is_complete', false);

        // Should have all 7 missing items
        $missing = $response->json('data.profile_completion_missing');
        $this->assertCount(7, $missing);
    }

    public function test_partial_profile_shows_correct_percentage(): void
    {
        // Fill 3 out of 7 fields (profile_photo, bio, ville) = ~43%
        $this->face->update([
            'profile_photo' => 'photo.jpg',
            'bio' => 'Test bio',
            'ville' => 'Paris',
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/profile-completion');

        $response->assertOk()
            ->assertJsonPath('data.profile_completion_percentage', 43) // 3/7 = 42.86% rounds to 43%
            ->assertJsonPath('data.profile_completion_is_complete', false);

        // Should have 4 missing items
        $missing = $response->json('data.profile_completion_missing');
        $this->assertCount(4, $missing);
    }

    public function test_complete_profile_shows_hundred_percent(): void
    {
        $this->face->update([
            'profile_photo' => 'photo.jpg',
            'presentation_video' => 'presentation.mp4',
            'acting_video' => 'acting.mp4',
            'bio' => 'Test bio',
            'ville' => 'Paris',
            'categorie' => FaceCategory::MANNEQUIN,
            'tarif_horaire' => 50000,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/profile-completion');

        $response->assertOk()
            ->assertJsonPath('data.profile_completion_percentage', 100)
            ->assertJsonPath('data.profile_completion_is_complete', true);

        // Should have 0 missing items
        $missing = $response->json('data.profile_completion_missing');
        $this->assertCount(0, $missing);
    }

    public function test_missing_items_list_is_correct(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/profile-completion');

        $response->assertOk();

        $missing = $response->json('data.profile_completion_missing');

        // Check expected keys
        $keys = array_column($missing, 'key');
        $this->assertContains('profile_photo', $keys);
        $this->assertContains('presentation_video', $keys);
        $this->assertContains('acting_video', $keys);
        $this->assertContains('bio', $keys);
        $this->assertContains('ville', $keys);
        $this->assertContains('categorie', $keys);
        $this->assertContains('tarifs', $keys);

        // Check French labels exist
        $labels = array_column($missing, 'label');
        $this->assertContains('Ajoutez une photo de profil', $labels);
        $this->assertContains('Ajoutez une vidéo de présentation', $labels);
        $this->assertContains("Ajoutez une vidéo d'acting", $labels);
        $this->assertContains('Ajoutez une bio', $labels);
        $this->assertContains('Ajoutez votre ville', $labels);
        $this->assertContains('Sélectionnez votre catégorie', $labels);
        $this->assertContains('Ajoutez vos tarifs', $labels);
    }

    public function test_tarif_validation_with_hourly_only(): void
    {
        // Only hourly rate should satisfy tarif requirement
        $this->face->update([
            'tarif_horaire' => 50000,
            'tarif_journalier' => null,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/profile-completion');

        $response->assertOk();

        $missing = $response->json('data.profile_completion_missing');
        $keys = array_column($missing, 'key');

        $this->assertNotContains('tarifs', $keys);
    }

    public function test_tarif_validation_with_daily_only(): void
    {
        // Only daily rate should satisfy tarif requirement
        $this->face->update([
            'tarif_horaire' => null,
            'tarif_journalier' => 200000,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/profile-completion');

        $response->assertOk();

        $missing = $response->json('data.profile_completion_missing');
        $keys = array_column($missing, 'key');

        $this->assertNotContains('tarifs', $keys);
    }

    public function test_tarif_validation_with_both_rates(): void
    {
        // Both rates should also satisfy tarif requirement
        $this->face->update([
            'tarif_horaire' => 50000,
            'tarif_journalier' => 200000,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/profile-completion');

        $response->assertOk();

        $missing = $response->json('data.profile_completion_missing');
        $keys = array_column($missing, 'key');

        $this->assertNotContains('tarifs', $keys);
    }

    public function test_unauthenticated_user_cannot_access_profile_completion(): void
    {
        $response = $this->getJson('/api/v1/face/profile-completion');

        $response->assertUnauthorized();
    }

    public function test_producer_cannot_access_profile_completion(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $response = $this->actingAs($producerUser)
            ->getJson('/api/v1/face/profile-completion');

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', 'Accès réservé aux Faces');
    }

    public function test_completion_percentage_updates_when_field_is_filled(): void
    {
        // Start with empty
        $response1 = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/profile-completion');

        $initialPercentage = $response1->json('data.profile_completion_percentage');
        $this->assertEquals(0, $initialPercentage);

        // Fill a field
        $this->face->update(['bio' => 'My bio']);

        $response2 = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/profile-completion');

        $newPercentage = $response2->json('data.profile_completion_percentage');
        $this->assertEquals(14, $newPercentage); // 1/7 = 14.28% rounds to 14%
    }

    public function test_empty_string_is_treated_as_missing(): void
    {
        $this->face->update([
            'bio' => '',
            'ville' => '',
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/profile-completion');

        $response->assertOk()
            ->assertJsonPath('data.profile_completion_percentage', 0);

        $missing = $response->json('data.profile_completion_missing');
        $keys = array_column($missing, 'key');

        $this->assertContains('bio', $keys);
        $this->assertContains('ville', $keys);
    }
}
