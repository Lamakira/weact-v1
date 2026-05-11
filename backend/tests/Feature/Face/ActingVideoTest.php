<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Producer;
use App\Models\User;
use App\Services\ActingVideoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class ActingVideoTest extends TestCase
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
     * Mock the ActingVideoService for tests that need FFmpeg.
     */
    private function mockVideoService(?float $duration = 60.0): MockInterface
    {
        return $this->mock(ActingVideoService::class, function (MockInterface $mock) use ($duration) {
            $mock->shouldReceive('getVideoDuration')
                ->andReturn($duration);

            $mock->shouldReceive('uploadActingVideo')
                ->andReturnUsing(function (Face $face, UploadedFile $video) {
                    // Delete old files first (simulating real service behavior)
                    if ($face->acting_video) {
                        Storage::disk('public')->delete('videos/faces/acting/'.$face->acting_video);
                    }
                    if ($face->acting_video_thumbnail) {
                        Storage::disk('public')->delete('videos/faces/acting/thumbnails/'.$face->acting_video_thumbnail);
                    }

                    $filename = 'test-video.mp4';
                    $thumbnail = 'test-thumbnail.jpg';

                    // Create fake files
                    Storage::disk('public')->put('videos/faces/acting/'.$filename, 'video content');
                    Storage::disk('public')->put('videos/faces/acting/thumbnails/'.$thumbnail, 'thumbnail content');

                    $face->update([
                        'acting_video' => $filename,
                        'acting_video_thumbnail' => $thumbnail,
                    ]);

                    return ['video' => $filename, 'thumbnail' => $thumbnail];
                });

            $mock->shouldReceive('deleteActingVideo')
                ->andReturnUsing(function (Face $face) {
                    if ($face->acting_video) {
                        Storage::disk('public')->delete('videos/faces/acting/'.$face->acting_video);
                    }
                    if ($face->acting_video_thumbnail) {
                        Storage::disk('public')->delete('videos/faces/acting/thumbnails/'.$face->acting_video_thumbnail);
                    }
                    $face->update([
                        'acting_video' => null,
                        'acting_video_thumbnail' => null,
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
            ->getJson('/api/v1/face/acting-video');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'acting_video_url' => null,
                    'acting_video_thumbnail_url' => null,
                ],
            ]);
    }

    public function test_can_get_video_info_when_video_exists(): void
    {
        $this->face->update([
            'acting_video' => 'test-video.mp4',
            'acting_video_thumbnail' => 'test-thumbnail.jpg',
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/acting-video');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'acting_video_url',
                    'acting_video_thumbnail_url',
                ],
            ]);

        $data = $response->json('data');
        $this->assertStringContainsString('test-video.mp4', $data['acting_video_url']);
        $this->assertStringContainsString('test-thumbnail.jpg', $data['acting_video_thumbnail_url']);
    }

    // =========================================================================
    // Upload Video Tests
    // =========================================================================

    public function test_can_upload_mp4_video(): void
    {
        FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);
        $this->mockVideoService(60.0);

        $file = UploadedFile::fake()->create('video.mp4', 10 * 1024, 'video/mp4');

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/acting-video', [
                'video' => $file,
            ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'acting_video_url',
                    'acting_video_thumbnail_url',
                ],
                'message',
            ])
            ->assertJson([
                'message' => 'Vidéo d\'acting uploadée avec succès',
            ]);

        // Verify database updated
        $this->face->refresh();
        $this->assertNotNull($this->face->acting_video);
        $this->assertNotNull($this->face->acting_video_thumbnail);
    }

    public function test_can_upload_mov_video(): void
    {
        FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);
        $this->mockVideoService(60.0);

        $file = UploadedFile::fake()->create('video.mov', 10 * 1024, 'video/quicktime');

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/acting-video', [
                'video' => $file,
            ]);

        $response->assertCreated();
    }

    public function test_can_upload_avi_video(): void
    {
        FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);
        $this->mockVideoService(60.0);

        $file = UploadedFile::fake()->create('video.avi', 10 * 1024, 'video/x-msvideo');

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/acting-video', [
                'video' => $file,
            ]);

        $response->assertCreated();
    }

    public function test_thumbnail_is_generated_on_upload(): void
    {
        FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);
        $this->mockVideoService(60.0);

        $file = UploadedFile::fake()->create('video.mp4', 10 * 1024, 'video/mp4');

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/acting-video', [
                'video' => $file,
            ]);

        $response->assertCreated();

        $this->face->refresh();
        $this->assertNotNull($this->face->acting_video_thumbnail);

        // Verify thumbnail exists in storage
        Storage::disk('public')->assertExists('videos/faces/acting/thumbnails/'.$this->face->acting_video_thumbnail);
    }

    public function test_uploading_new_video_replaces_old_video(): void
    {
        FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);

        // Set up existing video
        $oldVideo = 'old-video.mp4';
        $oldThumbnail = 'old-thumbnail.jpg';
        $this->face->update([
            'acting_video' => $oldVideo,
            'acting_video_thumbnail' => $oldThumbnail,
        ]);
        Storage::disk('public')->put('videos/faces/acting/'.$oldVideo, 'old video content');
        Storage::disk('public')->put('videos/faces/acting/thumbnails/'.$oldThumbnail, 'old thumbnail content');

        $this->mockVideoService(60.0);

        $file = UploadedFile::fake()->create('new-video.mp4', 10 * 1024, 'video/mp4');

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/acting-video', [
                'video' => $file,
            ]);

        $response->assertCreated();

        // Verify old files are deleted
        Storage::disk('public')->assertMissing('videos/faces/acting/'.$oldVideo);
        Storage::disk('public')->assertMissing('videos/faces/acting/thumbnails/'.$oldThumbnail);
    }

    // =========================================================================
    // Premium Gating Tests
    // =========================================================================

    public function test_free_face_cannot_upload_acting_video(): void
    {
        // No subscription on $this->face
        $this->mockVideoService(60.0);

        $file = UploadedFile::fake()->create('video.mp4', 10 * 1024, 'video/mp4');

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/acting-video', [
                'video' => $file,
            ]);

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'PREMIUM_REQUIRED');

        $this->assertStringContainsString(
            'abonnement premium actif',
            $response->json('error.message')
        );

        // No file written to storage under acting video path
        $this->assertSame(
            [],
            Storage::disk('public')->files('videos/faces/acting'),
            'No acting video file should be written for a free Face.'
        );

        $this->face->refresh();
        $this->assertNull($this->face->acting_video);
    }

    public function test_pending_payment_subscription_cannot_upload_acting_video(): void
    {
        FaceSubscription::factory()->pendingPayment()->create(['face_id' => $this->face->id]);
        $this->mockVideoService(60.0);

        $file = UploadedFile::fake()->create('video.mp4', 10 * 1024, 'video/mp4');

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/acting-video', [
                'video' => $file,
            ]);

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'PREMIUM_REQUIRED');
    }

    public function test_expired_subscription_cannot_upload_acting_video(): void
    {
        FaceSubscription::factory()->expired()->create(['face_id' => $this->face->id]);
        $this->mockVideoService(60.0);

        $file = UploadedFile::fake()->create('video.mp4', 10 * 1024, 'video/mp4');

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/acting-video', [
                'video' => $file,
            ]);

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'PREMIUM_REQUIRED');
    }

    // =========================================================================
    // Validation Tests
    // =========================================================================

    public function test_rejects_oversized_video(): void
    {
        FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);

        // Create a file larger than 50MB (50 * 1024 KB)
        $file = UploadedFile::fake()->create('video.mp4', 51 * 1024, 'video/mp4');

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/acting-video', [
                'video' => $file,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['video']);

        $errors = $response->json('errors.video');
        $this->assertStringContainsString('50MB', $errors[0]);
    }

    public function test_rejects_video_longer_than_2_minutes(): void
    {
        FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);

        // Mock service to return a duration > 120 seconds
        $this->mockVideoService(150.0);

        $file = UploadedFile::fake()->create('video.mp4', 10 * 1024, 'video/mp4');

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/acting-video', [
                'video' => $file,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['video']);

        $errors = $response->json('errors.video');
        $this->assertStringContainsString('2 minutes', $errors[0]);
    }

    public function test_rejects_invalid_file_type_pdf(): void
    {
        FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);

        $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/acting-video', [
                'video' => $file,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['video']);
    }

    public function test_rejects_invalid_file_type_txt(): void
    {
        FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);

        $file = UploadedFile::fake()->create('text.txt', 1024, 'text/plain');

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/acting-video', [
                'video' => $file,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['video']);
    }

    public function test_rejects_invalid_file_type_image(): void
    {
        FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);

        $file = UploadedFile::fake()->image('photo.jpg', 500, 500);

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/acting-video', [
                'video' => $file,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['video']);
    }

    public function test_rejects_wmv_video_format(): void
    {
        FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);

        $file = UploadedFile::fake()->create('video.wmv', 10 * 1024, 'video/x-ms-wmv');

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/acting-video', [
                'video' => $file,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['video']);
    }

    public function test_rejects_webm_video_format(): void
    {
        FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);

        $file = UploadedFile::fake()->create('video.webm', 10 * 1024, 'video/webm');

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/acting-video', [
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
            'acting_video' => 'test-video.mp4',
            'acting_video_thumbnail' => 'test-thumbnail.jpg',
        ]);
        Storage::disk('public')->put('videos/faces/acting/test-video.mp4', 'video content');
        Storage::disk('public')->put('videos/faces/acting/thumbnails/test-thumbnail.jpg', 'thumbnail content');

        $this->mockVideoService();

        $response = $this->actingAs($this->faceUser)
            ->deleteJson('/api/v1/face/acting-video');

        $response->assertOk()
            ->assertJson([
                'message' => 'Vidéo supprimée avec succès',
            ]);

        // Verify database cleared
        $this->face->refresh();
        $this->assertNull($this->face->acting_video);
        $this->assertNull($this->face->acting_video_thumbnail);

        // Verify files deleted
        Storage::disk('public')->assertMissing('videos/faces/acting/test-video.mp4');
        Storage::disk('public')->assertMissing('videos/faces/acting/thumbnails/test-thumbnail.jpg');
    }

    public function test_delete_returns_404_when_no_video(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->deleteJson('/api/v1/face/acting-video');

        $response->assertNotFound()
            ->assertJson([
                'error' => [
                    'code' => 'NO_VIDEO',
                    'message' => 'Aucune vidéo d\'acting à supprimer',
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
        $response = $this->actingAs($producerUser)->getJson('/api/v1/face/acting-video');
        $response->assertForbidden();

        // Upload
        $file = UploadedFile::fake()->create('video.mp4', 10 * 1024, 'video/mp4');
        $response = $this->actingAs($producerUser)->postJson('/api/v1/face/acting-video', ['video' => $file]);
        $response->assertForbidden();

        // Delete
        $response = $this->actingAs($producerUser)->deleteJson('/api/v1/face/acting-video');
        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_video_endpoints(): void
    {
        $response = $this->getJson('/api/v1/face/acting-video');
        $response->assertUnauthorized();

        $file = UploadedFile::fake()->create('video.mp4', 10 * 1024, 'video/mp4');
        $response = $this->postJson('/api/v1/face/acting-video', ['video' => $file]);
        $response->assertUnauthorized();

        $response = $this->deleteJson('/api/v1/face/acting-video');
        $response->assertUnauthorized();
    }

    // =========================================================================
    // Rate Limiting Test
    // =========================================================================

    public function test_upload_is_rate_limited(): void
    {
        FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);
        $this->mockVideoService(60.0);

        // Make 21 requests (limit is 20/min)
        for ($i = 0; $i < 21; $i++) {
            $file = UploadedFile::fake()->create("video{$i}.mp4", 10 * 1024, 'video/mp4');
            $response = $this->actingAs($this->faceUser)
                ->postJson('/api/v1/face/acting-video', ['video' => $file]);

            if ($i < 20) {
                // First 20 should succeed (video gets replaced each time)
                $response->assertCreated();
            } else {
                // 21st request should hit rate limit
                $response->assertTooManyRequests();
            }
        }
    }
}
