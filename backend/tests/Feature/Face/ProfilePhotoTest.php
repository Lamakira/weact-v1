<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;
    private Face $face;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // Create Face user
        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    public function test_face_can_upload_jpg_profile_photo(): void
    {
        $file = UploadedFile::fake()->image('profile.jpg', 500, 500);

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/profile/photo', [
                'photo' => $file,
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'nom',
                    'prenom',
                    'username',
                    'profile_photo_url',
                    'thumbnail_url',
                ],
                'message',
            ])
            ->assertJson([
                'message' => 'Photo de profil mise à jour',
            ]);

        $this->face->refresh();
        $this->assertNotNull($this->face->profile_photo);
        $this->assertNotNull($this->face->profile_photo_thumbnail);

        // Verify files exist in storage
        Storage::disk('public')->assertExists('avatars/faces/' . $this->face->profile_photo);
        Storage::disk('public')->assertExists('avatars/faces/thumbnails/' . $this->face->profile_photo_thumbnail);
    }

    public function test_face_can_upload_png_profile_photo(): void
    {
        $file = UploadedFile::fake()->image('profile.png', 500, 500);

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/profile/photo', [
                'photo' => $file,
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Photo de profil mise à jour');

        $this->face->refresh();
        $this->assertNotNull($this->face->profile_photo);
    }

    public function test_thumbnail_is_generated_on_upload(): void
    {
        $file = UploadedFile::fake()->image('profile.jpg', 1000, 1000);

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/profile/photo', [
                'photo' => $file,
            ]);

        $response->assertOk();

        $this->face->refresh();
        $this->assertNotNull($this->face->profile_photo_thumbnail);

        // Verify thumbnail exists
        Storage::disk('public')->assertExists('avatars/faces/thumbnails/' . $this->face->profile_photo_thumbnail);
    }

    public function test_rejects_invalid_file_type_gif(): void
    {
        $file = UploadedFile::fake()->image('profile.gif', 500, 500);

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/profile/photo', [
                'photo' => $file,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_rejects_invalid_file_type_pdf(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/profile/photo', [
                'photo' => $file,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_rejects_oversized_file(): void
    {
        // Create a file larger than 8MB (8192KB)
        $file = UploadedFile::fake()->image('profile.jpg')->size(9 * 1024);

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/profile/photo', [
                'photo' => $file,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_old_photo_is_deleted_when_uploading_new_one(): void
    {
        // Upload first photo
        $firstFile = UploadedFile::fake()->image('first.jpg', 500, 500);
        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/profile/photo', ['photo' => $firstFile]);

        $this->face->refresh();
        $oldPhotoPath = 'avatars/faces/' . $this->face->profile_photo;
        $oldThumbnailPath = 'avatars/faces/thumbnails/' . $this->face->profile_photo_thumbnail;

        // Verify first photo exists
        Storage::disk('public')->assertExists($oldPhotoPath);
        Storage::disk('public')->assertExists($oldThumbnailPath);

        // Upload second photo
        $secondFile = UploadedFile::fake()->image('second.jpg', 500, 500);
        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/profile/photo', ['photo' => $secondFile]);

        $response->assertOk();

        // Old photo should be deleted
        Storage::disk('public')->assertMissing($oldPhotoPath);
        Storage::disk('public')->assertMissing($oldThumbnailPath);

        // New photo should exist
        $this->face->refresh();
        Storage::disk('public')->assertExists('avatars/faces/' . $this->face->profile_photo);
    }

    public function test_producer_cannot_upload_face_profile_photo(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $file = UploadedFile::fake()->image('profile.jpg', 500, 500);

        $response = $this->actingAs($producerUser)
            ->postJson('/api/v1/face/profile/photo', [
                'photo' => $file,
            ]);

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_upload_photo(): void
    {
        $file = UploadedFile::fake()->image('profile.jpg', 500, 500);

        $response = $this->postJson('/api/v1/face/profile/photo', [
            'photo' => $file,
        ]);

        $response->assertUnauthorized();
    }

    public function test_face_can_delete_profile_photo(): void
    {
        // First upload a photo
        $file = UploadedFile::fake()->image('profile.jpg', 500, 500);
        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/profile/photo', ['photo' => $file]);

        $this->face->refresh();
        $photoPath = 'avatars/faces/' . $this->face->profile_photo;
        $thumbnailPath = 'avatars/faces/thumbnails/' . $this->face->profile_photo_thumbnail;

        // Verify photo exists
        Storage::disk('public')->assertExists($photoPath);
        Storage::disk('public')->assertExists($thumbnailPath);

        // Delete the photo
        $response = $this->actingAs($this->faceUser)
            ->deleteJson('/api/v1/face/profile/photo');

        $response->assertOk()
            ->assertJson([
                'message' => 'Photo de profil supprimée',
            ]);

        // Verify files are deleted
        Storage::disk('public')->assertMissing($photoPath);
        Storage::disk('public')->assertMissing($thumbnailPath);

        // Verify database is updated
        $this->face->refresh();
        $this->assertNull($this->face->profile_photo);
        $this->assertNull($this->face->profile_photo_thumbnail);
    }

    public function test_face_can_get_profile(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/profile');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'nom',
                    'prenom',
                    'username',
                    'profile_photo_url',
                    'thumbnail_url',
                ],
            ]);
    }
}
