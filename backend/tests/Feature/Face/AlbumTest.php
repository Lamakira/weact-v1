<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Models\Face;
use App\Models\FacePhoto;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AlbumTest extends TestCase
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

    // =========================================================================
    // List Album Photos Tests
    // =========================================================================

    public function test_can_list_empty_album(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/album');

        $response->assertOk()
            ->assertJson([
                'data' => [],
            ]);
    }

    public function test_can_list_album_with_photos(): void
    {
        // Create some photos for this face with explicit positions
        for ($i = 1; $i <= 3; $i++) {
            FacePhoto::factory()->create([
                'face_id' => $this->face->id,
                'position' => $i,
            ]);
        }

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/album');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'photo_url',
                        'thumbnail_url',
                        'position',
                    ],
                ],
            ]);
    }

    // =========================================================================
    // Upload Album Photo Tests
    // =========================================================================

    public function test_can_upload_jpg_album_photo(): void
    {
        $file = UploadedFile::fake()->image('album.jpg', 500, 500);

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/album', [
                'photo' => $file,
            ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'photo_url',
                    'thumbnail_url',
                    'position',
                ],
                'message',
            ])
            ->assertJson([
                'message' => 'Photo ajoutée à l\'album',
            ]);

        // Verify database record created
        $this->assertDatabaseCount('face_photos', 1);
        $photo = FacePhoto::first();
        $this->assertEquals($this->face->id, $photo->face_id);
        $this->assertEquals(1, $photo->position);

        // Verify files exist in storage
        Storage::disk('public')->assertExists('avatars/faces/albums/' . $photo->filename);
        Storage::disk('public')->assertExists('avatars/faces/albums/thumbnails/' . $photo->thumbnail);
    }

    public function test_can_upload_png_album_photo(): void
    {
        $file = UploadedFile::fake()->image('album.png', 500, 500);

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/album', [
                'photo' => $file,
            ]);

        $response->assertCreated()
            ->assertJson([
                'message' => 'Photo ajoutée à l\'album',
            ]);
    }

    public function test_thumbnail_is_generated_on_upload(): void
    {
        $file = UploadedFile::fake()->image('album.jpg', 1000, 1000);

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/album', [
                'photo' => $file,
            ]);

        $response->assertCreated();

        $photo = FacePhoto::first();
        $this->assertNotNull($photo->thumbnail);

        // Verify thumbnail exists
        Storage::disk('public')->assertExists('avatars/faces/albums/thumbnails/' . $photo->thumbnail);
    }

    public function test_rejects_upload_when_album_is_full(): void
    {
        // Create 4 photos to fill the album with explicit positions
        for ($i = 1; $i <= 4; $i++) {
            FacePhoto::factory()->create([
                'face_id' => $this->face->id,
                'position' => $i,
            ]);
        }

        $file = UploadedFile::fake()->image('album.jpg', 500, 500);

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/album', [
                'photo' => $file,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_rejects_invalid_file_type_gif(): void
    {
        $file = UploadedFile::fake()->image('album.gif', 500, 500);

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/album', [
                'photo' => $file,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_rejects_invalid_file_type_pdf(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/album', [
                'photo' => $file,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_rejects_oversized_file(): void
    {
        // Create a file larger than 8MB (8192KB)
        $file = UploadedFile::fake()->image('album.jpg')->size(9 * 1024);

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/album', [
                'photo' => $file,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_position_increments_correctly(): void
    {
        // Upload 3 photos
        for ($i = 1; $i <= 3; $i++) {
            $file = UploadedFile::fake()->image("album{$i}.jpg", 500, 500);
            $this->actingAs($this->faceUser)
                ->postJson('/api/v1/face/album', ['photo' => $file]);
        }

        $photos = FacePhoto::where('face_id', $this->face->id)->orderBy('position')->get();
        $this->assertCount(3, $photos);
        $this->assertEquals(1, $photos[0]->position);
        $this->assertEquals(2, $photos[1]->position);
        $this->assertEquals(3, $photos[2]->position);
    }

    // =========================================================================
    // Delete Album Photo Tests
    // =========================================================================

    public function test_can_delete_album_photo(): void
    {
        $photo = FacePhoto::factory()->create([
            'face_id' => $this->face->id,
        ]);

        // Create actual files in storage
        Storage::disk('public')->put('avatars/faces/albums/' . $photo->filename, 'photo content');
        Storage::disk('public')->put('avatars/faces/albums/thumbnails/' . $photo->thumbnail, 'thumbnail content');

        $response = $this->actingAs($this->faceUser)
            ->deleteJson("/api/v1/face/album/{$photo->id}");

        $response->assertOk()
            ->assertJson([
                'message' => 'Photo supprimée de l\'album',
            ]);

        // Verify database record deleted
        $this->assertDatabaseMissing('face_photos', ['id' => $photo->id]);

        // Verify files deleted
        Storage::disk('public')->assertMissing('avatars/faces/albums/' . $photo->filename);
        Storage::disk('public')->assertMissing('avatars/faces/albums/thumbnails/' . $photo->thumbnail);
    }

    public function test_positions_reorder_after_delete(): void
    {
        // Create 4 photos
        $photos = [];
        for ($i = 1; $i <= 4; $i++) {
            $photos[] = FacePhoto::factory()->create([
                'face_id' => $this->face->id,
                'position' => $i,
            ]);
        }

        // Delete the photo at position 2
        $this->actingAs($this->faceUser)
            ->deleteJson("/api/v1/face/album/{$photos[1]->id}");

        // Verify remaining photos reordered
        $remaining = FacePhoto::where('face_id', $this->face->id)->orderBy('position')->get();
        $this->assertCount(3, $remaining);
        $this->assertEquals(1, $remaining[0]->position);
        $this->assertEquals(2, $remaining[1]->position);
        $this->assertEquals(3, $remaining[2]->position);
    }

    public function test_cannot_delete_other_face_photo(): void
    {
        // Create another face with a photo
        $otherFace = Face::factory()->create();
        $otherPhoto = FacePhoto::factory()->create([
            'face_id' => $otherFace->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->deleteJson("/api/v1/face/album/{$otherPhoto->id}");

        $response->assertForbidden();
    }

    // =========================================================================
    // Reorder Album Photos Tests
    // =========================================================================

    public function test_can_reorder_album_photos(): void
    {
        // Create 3 photos
        $photo1 = FacePhoto::factory()->create(['face_id' => $this->face->id, 'position' => 1]);
        $photo2 = FacePhoto::factory()->create(['face_id' => $this->face->id, 'position' => 2]);
        $photo3 = FacePhoto::factory()->create(['face_id' => $this->face->id, 'position' => 3]);

        // Reorder: [3, 1, 2]
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/album/reorder', [
                'order' => [$photo3->id, $photo1->id, $photo2->id],
            ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Ordre des photos mis à jour',
            ]);

        // Verify new order
        $photo1->refresh();
        $photo2->refresh();
        $photo3->refresh();

        $this->assertEquals(2, $photo1->position);
        $this->assertEquals(3, $photo2->position);
        $this->assertEquals(1, $photo3->position);
    }

    public function test_reorder_fails_with_invalid_photo_id(): void
    {
        FacePhoto::factory()->create(['face_id' => $this->face->id, 'position' => 1]);

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/album/reorder', [
                'order' => [999, 1000],
            ]);

        $response->assertUnprocessable();
    }

    public function test_reorder_fails_with_other_face_photo_id(): void
    {
        $photo = FacePhoto::factory()->create(['face_id' => $this->face->id, 'position' => 1]);

        $otherFace = Face::factory()->create();
        $otherPhoto = FacePhoto::factory()->create(['face_id' => $otherFace->id]);

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/album/reorder', [
                'order' => [$photo->id, $otherPhoto->id],
            ]);

        $response->assertUnprocessable();
    }

    // =========================================================================
    // Authorization Tests
    // =========================================================================

    public function test_producer_cannot_access_album_endpoints(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        // List
        $response = $this->actingAs($producerUser)->getJson('/api/v1/face/album');
        $response->assertForbidden();

        // Upload
        $file = UploadedFile::fake()->image('album.jpg', 500, 500);
        $response = $this->actingAs($producerUser)->postJson('/api/v1/face/album', ['photo' => $file]);
        $response->assertForbidden();

        // Reorder
        $response = $this->actingAs($producerUser)->putJson('/api/v1/face/album/reorder', ['order' => []]);
        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_album_endpoints(): void
    {
        $response = $this->getJson('/api/v1/face/album');
        $response->assertUnauthorized();

        $file = UploadedFile::fake()->image('album.jpg', 500, 500);
        $response = $this->postJson('/api/v1/face/album', ['photo' => $file]);
        $response->assertUnauthorized();
    }

    // =========================================================================
    // Rate Limiting Test
    // =========================================================================

    public function test_upload_is_rate_limited(): void
    {
        // Make 11 requests (limit is 10/min)
        for ($i = 0; $i < 11; $i++) {
            $file = UploadedFile::fake()->image("album{$i}.jpg", 500, 500);
            $response = $this->actingAs($this->faceUser)
                ->postJson('/api/v1/face/album', ['photo' => $file]);

            if ($i < 4) {
                // First 4 should succeed (we have 4 photo limit)
                $response->assertCreated();
            } elseif ($i < 10) {
                // Next ones will hit album full validation before rate limit
                $response->assertUnprocessable();
            } else {
                // 11th request should hit rate limit
                $response->assertTooManyRequests();
            }
        }
    }
}
