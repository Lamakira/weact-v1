<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\FaceCategory;
use App\Enums\FaceNiche;
use App\Models\Face;
use App\Models\FacePhoto;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicFaceProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_face_profile_by_username_without_authentication(): void
    {
        $face = Face::factory()->create([
            'prenom' => 'Adjoua',
            'ville' => 'Cotonou',
            'categories' => [FaceCategory::ACTEUR->value],
            'niches' => [FaceNiche::BEAUTE->value],
            'is_available' => true,
        ]);

        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'username',
                    'prenom',
                    'ville',
                    'categories',
                    'niches',
                    'is_available',
                    'profile_photo_url',
                    'average_rating',
                    'ratings_count',
                    'has_album_photos',
                    'album_photos_count',
                    'has_presentation_video',
                    'has_acting_video',
                ],
                'message',
            ]);

        $this->assertEquals($face->uuid, $response->json('data.id'));
        $this->assertEquals('Adjoua', $response->json('data.prenom'));
        $this->assertEquals('Cotonou', $response->json('data.ville'));
    }

    public function test_returns_404_for_non_existent_face(): void
    {
        $response = $this->getJson('/api/v1/public/faces/nonexistent-user');

        $response->assertNotFound()
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                ],
            ])
            ->assertJson([
                'error' => [
                    'code' => 'FACE_NOT_FOUND',
                    'message' => 'Face non trouvée',
                ],
            ]);
    }

    public function test_only_includes_public_safe_fields_in_profile_response(): void
    {
        $face = Face::factory()->create([
            'nom' => 'Kouassi',
            'prenom' => 'Adjoua',
            'username' => 'adjouak',
            'bio' => 'This is my bio',
            'tarif_horaire' => 50000,
            'tarif_journalier' => 200000,
            'taille' => 170,
            'poids' => 65,
            'ville' => 'Cotonou',
            'pays' => 'Bénin',
            'categories' => [FaceCategory::ACTEUR->value],
            'niches' => [FaceNiche::MODE->value],
            'is_available' => true,
        ]);

        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email' => 'adjoua@example.com',
        ]);

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");

        $response->assertOk();

        $data = $response->json('data');

        // Should include public fields
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('prenom', $data);
        $this->assertArrayHasKey('ville', $data);
        $this->assertArrayHasKey('categories', $data);
        $this->assertArrayHasKey('niches', $data);
        $this->assertArrayHasKey('is_available', $data);
        $this->assertArrayHasKey('profile_photo_url', $data);
        $this->assertArrayHasKey('average_rating', $data);
        $this->assertArrayHasKey('ratings_count', $data);
        $this->assertArrayHasKey('has_album_photos', $data);
        $this->assertArrayHasKey('album_photos_count', $data);
        $this->assertArrayHasKey('has_presentation_video', $data);
        $this->assertArrayHasKey('has_acting_video', $data);

        // Should include username (public for slug-based URLs)
        $this->assertArrayHasKey('username', $data);

        // Public profile detail exposes bio, physical, location (more than list view)
        $this->assertArrayHasKey('bio', $data);
        $this->assertArrayHasKey('sexe', $data);
        $this->assertArrayHasKey('age', $data);
        $this->assertArrayHasKey('nationalite', $data);
        $this->assertArrayHasKey('taille', $data);
        $this->assertArrayHasKey('pays', $data);
        $this->assertArrayHasKey('formatted_location', $data);

        // Should NOT include sensitive/admin-only fields
        $this->assertArrayNotHasKey('nom', $data);
        $this->assertArrayNotHasKey('tarif_horaire', $data);
        $this->assertArrayNotHasKey('tarif_journalier', $data);
        $this->assertArrayNotHasKey('poids', $data);
        $this->assertArrayNotHasKey('is_featured', $data);
        $this->assertArrayNotHasKey('quartier', $data);
        $this->assertArrayNotHasKey('whatsapp_number', $data);
        $this->assertArrayNotHasKey('email', $data);
        $this->assertArrayNotHasKey('date_naissance', $data);
    }

    public function test_has_album_photos_indicator_is_correct(): void
    {
        $faceWithPhotos = Face::factory()->create(['is_available' => true]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $faceWithPhotos->id,
        ]);

        // Add album photos with sequential positions
        FacePhoto::factory()->createSequentialForFace($faceWithPhotos, 3);

        $response = $this->getJson("/api/v1/public/faces/{$faceWithPhotos->username}");

        $response->assertOk();
        $this->assertTrue($response->json('data.has_album_photos'));
        $this->assertEquals(3, $response->json('data.album_photos_count'));

        // Face without photos
        $faceWithoutPhotos = Face::factory()->create(['is_available' => true]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $faceWithoutPhotos->id,
        ]);

        $response = $this->getJson("/api/v1/public/faces/{$faceWithoutPhotos->username}");

        $response->assertOk();
        $this->assertFalse($response->json('data.has_album_photos'));
        $this->assertEquals(0, $response->json('data.album_photos_count'));
    }

    public function test_has_presentation_video_indicator_is_correct(): void
    {
        $faceWithVideo = Face::factory()->create([
            'presentation_video' => 'video.mp4',
            'is_available' => true,
        ]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $faceWithVideo->id,
        ]);

        $response = $this->getJson("/api/v1/public/faces/{$faceWithVideo->username}");

        $response->assertOk();
        $this->assertTrue($response->json('data.has_presentation_video'));

        // Face without video
        $faceWithoutVideo = Face::factory()->create([
            'presentation_video' => null,
            'is_available' => true,
        ]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $faceWithoutVideo->id,
        ]);

        $response = $this->getJson("/api/v1/public/faces/{$faceWithoutVideo->username}");

        $response->assertOk();
        $this->assertFalse($response->json('data.has_presentation_video'));
    }

    public function test_has_acting_video_indicator_is_correct(): void
    {
        $faceWithVideo = Face::factory()->create([
            'acting_video' => 'acting.mp4',
            'is_available' => true,
        ]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $faceWithVideo->id,
        ]);

        $response = $this->getJson("/api/v1/public/faces/{$faceWithVideo->username}");

        $response->assertOk();
        $this->assertTrue($response->json('data.has_acting_video'));

        // Face without video
        $faceWithoutVideo = Face::factory()->create([
            'acting_video' => null,
            'is_available' => true,
        ]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $faceWithoutVideo->id,
        ]);

        $response = $this->getJson("/api/v1/public/faces/{$faceWithoutVideo->username}");

        $response->assertOk();
        $this->assertFalse($response->json('data.has_acting_video'));
    }

    public function test_does_not_require_authentication(): void
    {
        $face = Face::factory()->create(['is_available' => true]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        // No auth token, no actingAs - should still work
        $response = $this->getJson("/api/v1/public/faces/{$face->username}");

        $response->assertOk();
    }

    public function test_returns_correct_data_types(): void
    {
        $face = Face::factory()->create([
            'prenom' => 'Adjoua',
            'ville' => 'Cotonou',
            'categories' => [FaceCategory::ACTEUR->value],
            'niches' => [FaceNiche::BEAUTE->value],
            'is_available' => true,
        ]);

        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");

        $response->assertOk();

        $data = $response->json('data');

        $this->assertIsString($data['id']);
        $this->assertIsString($data['prenom']);
        $this->assertIsString($data['ville']);
        $this->assertIsArray($data['categories']);
        $this->assertIsArray($data['niches']);
        $this->assertIsBool($data['is_available']);
        $this->assertIsInt($data['ratings_count']);
        $this->assertIsBool($data['has_album_photos']);
        $this->assertIsInt($data['album_photos_count']);
        $this->assertIsBool($data['has_presentation_video']);
        $this->assertIsBool($data['has_acting_video']);
    }

    public function test_returns_full_profile_photo_url_not_thumbnail(): void
    {
        $face = Face::factory()->create([
            'profile_photo' => 'profile.jpg',
            'profile_photo_thumbnail' => 'profile_thumb.jpg',
            'is_available' => true,
        ]);

        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");

        $response->assertOk();

        // Should have profile_photo_url (full), not thumbnail
        $this->assertArrayHasKey('profile_photo_url', $response->json('data'));
        $this->assertArrayNotHasKey('profile_photo_thumbnail_url', $response->json('data'));

        // The URL should point to the full photo, not the thumbnail
        $profilePhotoUrl = $response->json('data.profile_photo_url');
        $this->assertStringContainsString('profile.jpg', $profilePhotoUrl);
        $this->assertStringNotContainsString('thumbnails', $profilePhotoUrl);
    }

    public function test_success_message_is_returned(): void
    {
        $face = Face::factory()->create(['is_available' => true]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");

        $response->assertOk();
        $this->assertEquals('Face profile retrieved successfully', $response->json('message'));
    }

    public function test_rate_limiting_headers_are_present(): void
    {
        $face = Face::factory()->create(['is_available' => true]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");

        $response->assertOk();
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
    }

    public function test_public_profile_returns_age_when_show_age_is_true(): void
    {
        $face = Face::factory()->create([
            'date_naissance' => '1995-06-15',
            'show_age' => true,
            'is_available' => true,
        ]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");

        $response->assertOk();
        $this->assertNotNull($response->json('data.age'));
    }

    public function test_public_profile_returns_null_age_when_show_age_is_false(): void
    {
        $face = Face::factory()->create([
            'date_naissance' => '1995-06-15',
            'show_age' => false,
            'is_available' => true,
        ]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");

        $response->assertOk();
        $this->assertNull($response->json('data.age'));
    }

    public function test_producer_sees_null_age_when_show_age_is_false(): void
    {
        $face = Face::factory()->create([
            'date_naissance' => '1995-06-15',
            'show_age' => false,
            'is_available' => true,
        ]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $response = $this->actingAs($producerUser)
            ->getJson("/api/v1/producer/candidates/{$face->uuid}");

        $response->assertOk();
        $this->assertNull($response->json('data.age'));
    }
}
