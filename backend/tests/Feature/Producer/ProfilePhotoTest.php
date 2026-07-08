<?php

declare(strict_types=1);

namespace Tests\Feature\Producer;

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

    private User $producerUser;

    private Producer $producer;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // Create Producer user
        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
    }

    public function test_producer_can_upload_jpg_profile_photo(): void
    {
        $file = UploadedFile::fake()->image('profile.jpg', 500, 500);

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/profile/photo', [
                'photo' => $file,
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'type',
                    'display_name',
                    'profile_photo_url',
                    'thumbnail_url',
                ],
                'message',
            ])
            ->assertJson([
                'message' => 'Photo de profil mise à jour',
            ]);

        $this->producer->refresh();
        $this->assertNotNull($this->producer->profile_photo);
        $this->assertNotNull($this->producer->profile_photo_thumbnail);

        // Verify files exist in storage. The sync test queue runs the
        // GenerateImageVariants job inline, so every variant asserted here is
        // produced through the queued path, not in the HTTP request.
        Storage::disk('public')->assertExists('avatars/producers/'.$this->producer->profile_photo);
        Storage::disk('public')->assertExists('avatars/producers/thumbnails/'.$this->producer->profile_photo_thumbnail);

        $this->assertNotNull($this->producer->profile_photo_medium);
        $this->assertNotNull($this->producer->profile_photo_grid);
        $this->assertNotNull($this->producer->profile_photo_large);
        Storage::disk('public')->assertExists('avatars/producers/medium/'.$this->producer->profile_photo_medium);
        Storage::disk('public')->assertExists('avatars/producers/grid/'.$this->producer->profile_photo_grid);
        Storage::disk('public')->assertExists('avatars/producers/large/'.$this->producer->profile_photo_large);
    }

    public function test_producer_can_upload_png_profile_photo(): void
    {
        $file = UploadedFile::fake()->image('profile.png', 500, 500);

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/profile/photo', [
                'photo' => $file,
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Photo de profil mise à jour');

        $this->producer->refresh();
        $this->assertNotNull($this->producer->profile_photo);
    }

    public function test_thumbnail_is_generated_on_upload(): void
    {
        $file = UploadedFile::fake()->image('profile.jpg', 1000, 1000);

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/profile/photo', [
                'photo' => $file,
            ]);

        $response->assertOk();

        $this->producer->refresh();
        $this->assertNotNull($this->producer->profile_photo_thumbnail);

        // Verify thumbnail exists
        Storage::disk('public')->assertExists('avatars/producers/thumbnails/'.$this->producer->profile_photo_thumbnail);
    }

    public function test_rejects_invalid_file_type_gif(): void
    {
        $file = UploadedFile::fake()->image('profile.gif', 500, 500);

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/profile/photo', [
                'photo' => $file,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_rejects_invalid_file_type_pdf(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/profile/photo', [
                'photo' => $file,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_rejects_oversized_file(): void
    {
        // Create a file larger than 8MB (8192KB)
        $file = UploadedFile::fake()->image('profile.jpg')->size(9 * 1024);

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/profile/photo', [
                'photo' => $file,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_old_photo_is_deleted_when_uploading_new_one(): void
    {
        // Upload first photo
        $firstFile = UploadedFile::fake()->image('first.jpg', 500, 500);
        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/profile/photo', ['photo' => $firstFile]);

        $this->producer->refresh();
        $oldPhotoPath = 'avatars/producers/'.$this->producer->profile_photo;
        $oldThumbnailPath = 'avatars/producers/thumbnails/'.$this->producer->profile_photo_thumbnail;

        // Verify first photo exists
        Storage::disk('public')->assertExists($oldPhotoPath);
        Storage::disk('public')->assertExists($oldThumbnailPath);

        // Upload second photo
        $secondFile = UploadedFile::fake()->image('second.jpg', 500, 500);
        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/profile/photo', ['photo' => $secondFile]);

        $response->assertOk();

        // Old photo should be deleted
        Storage::disk('public')->assertMissing($oldPhotoPath);
        Storage::disk('public')->assertMissing($oldThumbnailPath);

        // New photo should exist
        $this->producer->refresh();
        Storage::disk('public')->assertExists('avatars/producers/'.$this->producer->profile_photo);
    }

    public function test_face_cannot_upload_producer_profile_photo(): void
    {
        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $file = UploadedFile::fake()->image('profile.jpg', 500, 500);

        $response = $this->actingAs($faceUser)
            ->postJson('/api/v1/producer/profile/photo', [
                'photo' => $file,
            ]);

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_upload_photo(): void
    {
        $file = UploadedFile::fake()->image('profile.jpg', 500, 500);

        $response = $this->postJson('/api/v1/producer/profile/photo', [
            'photo' => $file,
        ]);

        $response->assertUnauthorized();
    }

    public function test_producer_can_delete_profile_photo(): void
    {
        // First upload a photo
        $file = UploadedFile::fake()->image('profile.jpg', 500, 500);
        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/profile/photo', ['photo' => $file]);

        $this->producer->refresh();
        $photoPath = 'avatars/producers/'.$this->producer->profile_photo;
        $thumbnailPath = 'avatars/producers/thumbnails/'.$this->producer->profile_photo_thumbnail;
        $gridPath = 'avatars/producers/grid/'.$this->producer->profile_photo_grid;
        $largePath = 'avatars/producers/large/'.$this->producer->profile_photo_large;

        // Verify photo exists
        Storage::disk('public')->assertExists($photoPath);
        Storage::disk('public')->assertExists($thumbnailPath);
        Storage::disk('public')->assertExists($gridPath);
        Storage::disk('public')->assertExists($largePath);

        // Delete the photo
        $response = $this->actingAs($this->producerUser)
            ->deleteJson('/api/v1/producer/profile/photo');

        $response->assertOk()
            ->assertJson([
                'message' => 'Photo de profil supprimée',
            ]);

        // Verify files are deleted
        Storage::disk('public')->assertMissing($photoPath);
        Storage::disk('public')->assertMissing($thumbnailPath);
        Storage::disk('public')->assertMissing($gridPath);
        Storage::disk('public')->assertMissing($largePath);

        // Verify database is updated
        $this->producer->refresh();
        $this->assertNull($this->producer->profile_photo);
        $this->assertNull($this->producer->profile_photo_thumbnail);
        $this->assertNull($this->producer->profile_photo_grid);
        $this->assertNull($this->producer->profile_photo_large);
    }

    public function test_producer_can_get_profile(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/profile');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'type',
                    'display_name',
                    'profile_photo_url',
                    'thumbnail_url',
                ],
            ]);
    }
}
