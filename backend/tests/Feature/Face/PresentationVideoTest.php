<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use App\Services\PresentationVideoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class PresentationVideoTest extends TestCase
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

    /**
     * Mock the PresentationVideoService for tests that need FFmpeg.
     */
    private function mockVideoService(?float $duration = 60.0): MockInterface
    {
        return $this->mock(PresentationVideoService::class, function (MockInterface $mock) use ($duration) {
            $mock->shouldReceive('getVideoDuration')
                ->andReturn($duration);

            $mock->shouldReceive('uploadPresentationVideo')
                ->andReturnUsing(function (Face $face, UploadedFile $video) {
                    // Delete old files first (simulating real service behavior)
                    if ($face->presentation_video) {
                        Storage::disk('public')->delete('videos/faces/presentation/' . $face->presentation_video);
                    }
                    if ($face->presentation_video_thumbnail) {
                        Storage::disk('public')->delete('videos/faces/presentation/thumbnails/' . $face->presentation_video_thumbnail);
                    }

                    $filename = 'test-video.mp4';
                    $thumbnail = 'test-thumbnail.jpg';

                    // Create fake files
                    Storage::disk('public')->put('videos/faces/presentation/' . $filename, 'video content');
                    Storage::disk('public')->put('videos/faces/presentation/thumbnails/' . $thumbnail, 'thumbnail content');

                    $face->update([
                        'presentation_video' => $filename,
                        'presentation_video_thumbnail' => $thumbnail,
                    ]);

                    return ['video' => $filename, 'thumbnail' => $thumbnail];
                });

            $mock->shouldReceive('deletePresentationVideo')
                ->andReturnUsing(function (Face $face) {
                    if ($face->presentation_video) {
                        Storage::disk('public')->delete('videos/faces/presentation/' . $face->presentation_video);
                    }
                    if ($face->presentation_video_thumbnail) {
                        Storage::disk('public')->delete('videos/faces/presentation/thumbnails/' . $face->presentation_video_thumbnail);
                    }
                    $face->update([
                        'presentation_video' => null,
                        'presentation_video_thumbnail' => null,
                    ]);

                    return true;
                });

            $mock->shouldReceive('getMaxDurationSeconds')
                ->andReturn(120);
        });
    }

    // =========================================================================
    // Get Video Info Tests
    // =========================================================================

    public function test_can_get_video_info_when_no_video(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/presentation-video');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'presentation_video_url' => null,
                    'presentation_video_thumbnail_url' => null,
                ],
            ]);
    }

    public function test_can_get_video_info_when_video_exists(): void
    {
        $this->face->update([
            'presentation_video' => 'test-video.mp4',
            'presentation_video_thumbnail' => 'test-thumbnail.jpg',
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/presentation-video');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'presentation_video_url',
                    'presentation_video_thumbnail_url',
                ],
            ]);

        $data = $response->json('data');
        $this->assertStringContainsString('test-video.mp4', $data['presentation_video_url']);
        $this->assertStringContainsString('test-thumbnail.jpg', $data['presentation_video_thumbnail_url']);
    }

    // =========================================================================
    // Upload Video Tests
    // =========================================================================

    public function test_can_upload_mp4_video(): void
    {
        $this->mockVideoService(60.0);

        $file = UploadedFile::fake()->create('video.mp4', 10 * 1024, 'video/mp4');

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/presentation-video', [
                'video' => $file,
            ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'presentation_video_url',
                    'presentation_video_thumbnail_url',
                ],
                'message',
            ])
            ->assertJson([
                'message' => 'Vidéo de présentation uploadée avec succès',
            ]);

        // Verify database updated
        $this->face->refresh();
        $this->assertNotNull($this->face->presentation_video);
        $this->assertNotNull($this->face->presentation_video_thumbnail);
    }

    public function test_can_upload_mov_video(): void
    {
        $this->mockVideoService(60.0);

        $file = UploadedFile::fake()->create('video.mov', 10 * 1024, 'video/quicktime');

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/presentation-video', [
                'video' => $file,
            ]);

        $response->assertCreated();
    }

    public function test_can_upload_avi_video(): void
    {
        $this->mockVideoService(60.0);

        $file = UploadedFile::fake()->create('video.avi', 10 * 1024, 'video/x-msvideo');

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/presentation-video', [
                'video' => $file,
            ]);

        $response->assertCreated();
    }

    public function test_thumbnail_is_generated_on_upload(): void
    {
        $this->mockVideoService(60.0);

        $file = UploadedFile::fake()->create('video.mp4', 10 * 1024, 'video/mp4');

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/presentation-video', [
                'video' => $file,
            ]);

        $response->assertCreated();

        $this->face->refresh();
        $this->assertNotNull($this->face->presentation_video_thumbnail);

        // Verify thumbnail exists in storage
        Storage::disk('public')->assertExists('videos/faces/presentation/thumbnails/' . $this->face->presentation_video_thumbnail);
    }

    public function test_uploading_new_video_replaces_old_video(): void
    {
        // Set up existing video
        $oldVideo = 'old-video.mp4';
        $oldThumbnail = 'old-thumbnail.jpg';
        $this->face->update([
            'presentation_video' => $oldVideo,
            'presentation_video_thumbnail' => $oldThumbnail,
        ]);
        Storage::disk('public')->put('videos/faces/presentation/' . $oldVideo, 'old video content');
        Storage::disk('public')->put('videos/faces/presentation/thumbnails/' . $oldThumbnail, 'old thumbnail content');

        $this->mockVideoService(60.0);

        $file = UploadedFile::fake()->create('new-video.mp4', 10 * 1024, 'video/mp4');

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/presentation-video', [
                'video' => $file,
            ]);

        $response->assertCreated();

        // Verify old files are deleted
        Storage::disk('public')->assertMissing('videos/faces/presentation/' . $oldVideo);
        Storage::disk('public')->assertMissing('videos/faces/presentation/thumbnails/' . $oldThumbnail);
    }

    // =========================================================================
    // Validation Tests
    // =========================================================================

    public function test_rejects_oversized_video(): void
    {
        // Create a file larger than 50MB (50 * 1024 KB)
        $file = UploadedFile::fake()->create('video.mp4', 51 * 1024, 'video/mp4');

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/presentation-video', [
                'video' => $file,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['video']);

        $errors = $response->json('errors.video');
        $this->assertStringContainsString('50MB', $errors[0]);
    }

    public function test_rejects_video_longer_than_2_minutes(): void
    {
        // Mock service to return a duration > 120 seconds
        $this->mockVideoService(150.0);

        $file = UploadedFile::fake()->create('video.mp4', 10 * 1024, 'video/mp4');

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/presentation-video', [
                'video' => $file,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['video']);

        $errors = $response->json('errors.video');
        $this->assertStringContainsString('2 minutes', $errors[0]);
    }

    public function test_rejects_invalid_file_type_pdf(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/presentation-video', [
                'video' => $file,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['video']);
    }

    public function test_rejects_invalid_file_type_txt(): void
    {
        $file = UploadedFile::fake()->create('text.txt', 1024, 'text/plain');

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/presentation-video', [
                'video' => $file,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['video']);
    }

    public function test_rejects_invalid_file_type_image(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 500, 500);

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/presentation-video', [
                'video' => $file,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['video']);
    }

    // =========================================================================
    // Delete Video Tests
    // =========================================================================

    public function test_can_delete_video(): void
    {
        // Set up existing video
        $this->face->update([
            'presentation_video' => 'test-video.mp4',
            'presentation_video_thumbnail' => 'test-thumbnail.jpg',
        ]);
        Storage::disk('public')->put('videos/faces/presentation/test-video.mp4', 'video content');
        Storage::disk('public')->put('videos/faces/presentation/thumbnails/test-thumbnail.jpg', 'thumbnail content');

        $this->mockVideoService();

        $response = $this->actingAs($this->faceUser)
            ->deleteJson('/api/v1/face/presentation-video');

        $response->assertOk()
            ->assertJson([
                'message' => 'Vidéo supprimée avec succès',
            ]);

        // Verify database cleared
        $this->face->refresh();
        $this->assertNull($this->face->presentation_video);
        $this->assertNull($this->face->presentation_video_thumbnail);

        // Verify files deleted
        Storage::disk('public')->assertMissing('videos/faces/presentation/test-video.mp4');
        Storage::disk('public')->assertMissing('videos/faces/presentation/thumbnails/test-thumbnail.jpg');
    }

    public function test_delete_returns_404_when_no_video(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->deleteJson('/api/v1/face/presentation-video');

        $response->assertNotFound()
            ->assertJson([
                'error' => [
                    'code' => 'NO_VIDEO',
                    'message' => 'Aucune vidéo de présentation à supprimer',
                ],
            ]);
    }

    // =========================================================================
    // Authorization Tests
    // =========================================================================

    public function test_producer_cannot_access_video_endpoints(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        // Get
        $response = $this->actingAs($producerUser)->getJson('/api/v1/face/presentation-video');
        $response->assertForbidden();

        // Upload
        $file = UploadedFile::fake()->create('video.mp4', 10 * 1024, 'video/mp4');
        $response = $this->actingAs($producerUser)->postJson('/api/v1/face/presentation-video', ['video' => $file]);
        $response->assertForbidden();

        // Delete
        $response = $this->actingAs($producerUser)->deleteJson('/api/v1/face/presentation-video');
        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_video_endpoints(): void
    {
        $response = $this->getJson('/api/v1/face/presentation-video');
        $response->assertUnauthorized();

        $file = UploadedFile::fake()->create('video.mp4', 10 * 1024, 'video/mp4');
        $response = $this->postJson('/api/v1/face/presentation-video', ['video' => $file]);
        $response->assertUnauthorized();

        $response = $this->deleteJson('/api/v1/face/presentation-video');
        $response->assertUnauthorized();
    }

    // =========================================================================
    // Rate Limiting Test
    // =========================================================================

    public function test_upload_is_rate_limited(): void
    {
        $this->mockVideoService(60.0);

        // Make 11 requests (limit is 10/min)
        for ($i = 0; $i < 11; $i++) {
            $file = UploadedFile::fake()->create("video{$i}.mp4", 10 * 1024, 'video/mp4');
            $response = $this->actingAs($this->faceUser)
                ->postJson('/api/v1/face/presentation-video', ['video' => $file]);

            if ($i < 10) {
                // First 10 should succeed (video gets replaced each time)
                $response->assertCreated();
            } else {
                // 11th request should hit rate limit
                $response->assertTooManyRequests();
            }
        }
    }
}
