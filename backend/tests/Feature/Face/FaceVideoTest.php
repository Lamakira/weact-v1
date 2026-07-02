<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Enums\FaceVideoType;
use App\Exceptions\VideoQuotaReachedException;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\FaceVideo;
use App\Models\Producer;
use App\Models\User;
use App\Services\FaceVideoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class FaceVideoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * @return array{face: Face, user: User}
     */
    private function makeFace(?string $tier = null): array
    {
        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        if ($tier !== null) {
            FaceSubscription::factory()->{$tier}()->active()->create(['face_id' => $face->id]);
        }

        return ['face' => $face, 'user' => $user];
    }

    /**
     * Partial mock: stub the FFMpeg-backed methods (uploadVideo, getVideoDuration)
     * with a fake-file implementation; leave deleteVideo real so its reorder
     * logic is exercised end-to-end.
     */
    private function mockVideoService(float $duration = 60.0): MockInterface
    {
        return $this->partialMock(FaceVideoService::class, function (MockInterface $mock) use ($duration): void {
            $mock->shouldReceive('getVideoDuration')->andReturn($duration);

            $mock->shouldReceive('uploadVideo')
                ->andReturnUsing(function (Face $face, FaceVideoType $type, UploadedFile $video): FaceVideo {
                    $position = $face->videos()->where('type', $type)->count() + 1;
                    $filename = 'test-'.$type->value.'-'.$position.'.mp4';
                    $thumbnail = 'test-'.$type->value.'-'.$position.'.jpg';

                    Storage::disk('public')->put('videos/faces/'.$type->value.'/'.$filename, 'video content');
                    Storage::disk('public')->put('videos/faces/'.$type->value.'/thumbnails/'.$thumbnail, 'thumb content');

                    return FaceVideo::create([
                        'face_id' => $face->id,
                        'type' => $type,
                        'filename' => $filename,
                        'thumbnail' => $thumbnail,
                        'position' => $position,
                    ]);
                });
        });
    }

    private function fakeVideo(string $name = 'clip.mp4'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 5 * 1024, 'video/mp4');
    }

    // === index ===

    public function test_index_returns_authenticated_face_videos(): void
    {
        ['face' => $face, 'user' => $user] = $this->makeFace('elite');
        FaceVideo::factory()->createSequentialForFace($face, FaceVideoType::Acting, 2);
        FaceVideo::factory()->ugc()->create(['face_id' => $face->id, 'position' => 1]);

        $response = $this->actingAs($user)->getJson('/api/v1/face/videos');

        $response->assertOk()->assertJsonCount(3, 'data');
    }

    // === upload — per-tier per-type quota ===

    public function test_pro_face_can_upload_an_acting_video(): void
    {
        ['user' => $user] = $this->makeFace('pro');
        $this->mockVideoService();

        $response = $this->actingAs($user)->postJson('/api/v1/face/videos', [
            'type' => 'acting',
            'video' => $this->fakeVideo(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'acting')
            ->assertJsonPath('data.position', 1);
    }

    public function test_elite_face_can_upload_two_acting_videos(): void
    {
        ['face' => $face, 'user' => $user] = $this->makeFace('elite');
        $this->mockVideoService();

        $this->actingAs($user)->postJson('/api/v1/face/videos', [
            'type' => 'acting',
            'video' => $this->fakeVideo('a1.mp4'),
        ])->assertCreated();

        $this->actingAs($user)->postJson('/api/v1/face/videos', [
            'type' => 'acting',
            'video' => $this->fakeVideo('a2.mp4'),
        ])->assertCreated();

        $this->assertSame(2, $face->videos()->where('type', FaceVideoType::Acting)->count());
    }

    public function test_elite_face_can_upload_a_ugc_video(): void
    {
        ['user' => $user] = $this->makeFace('elite');
        $this->mockVideoService();

        $response = $this->actingAs($user)->postJson('/api/v1/face/videos', [
            'type' => 'ugc',
            'video' => $this->fakeVideo(),
        ]);

        $response->assertCreated()->assertJsonPath('data.type', 'ugc');
    }

    public function test_free_face_cannot_upload_an_acting_video(): void
    {
        ['user' => $user] = $this->makeFace();

        $response = $this->actingAs($user)->postJson('/api/v1/face/videos', [
            'type' => 'acting',
            'video' => $this->fakeVideo(),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['video']);
        $this->assertStringContainsString('formule', $response->json('errors.video.0'));
    }

    public function test_starter_face_cannot_upload_an_acting_video(): void
    {
        ['user' => $user] = $this->makeFace('starter');

        $response = $this->actingAs($user)->postJson('/api/v1/face/videos', [
            'type' => 'acting',
            'video' => $this->fakeVideo(),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['video']);
    }

    public function test_pro_face_cannot_upload_a_second_acting_video(): void
    {
        ['face' => $face, 'user' => $user] = $this->makeFace('pro');
        FaceVideo::factory()->acting()->create(['face_id' => $face->id, 'position' => 1]);

        $response = $this->actingAs($user)->postJson('/api/v1/face/videos', [
            'type' => 'acting',
            'video' => $this->fakeVideo(),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['video']);
        $this->assertStringContainsString('Quota', $response->json('errors.video.0'));
    }

    public function test_pro_face_cannot_upload_a_ugc_video(): void
    {
        ['user' => $user] = $this->makeFace('pro');

        $response = $this->actingAs($user)->postJson('/api/v1/face/videos', [
            'type' => 'ugc',
            'video' => $this->fakeVideo(),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['video']);
    }

    public function test_free_face_cannot_upload_a_ugc_video(): void
    {
        ['user' => $user] = $this->makeFace();

        $response = $this->actingAs($user)->postJson('/api/v1/face/videos', [
            'type' => 'ugc',
            'video' => $this->fakeVideo(),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['video']);
    }

    public function test_starter_face_cannot_upload_a_ugc_video(): void
    {
        ['user' => $user] = $this->makeFace('starter');

        $response = $this->actingAs($user)->postJson('/api/v1/face/videos', [
            'type' => 'ugc',
            'video' => $this->fakeVideo(),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['video']);
    }

    public function test_store_maps_service_layer_quota_exception_to_error_envelope(): void
    {
        // The FormRequest guard rejects over-quota uploads first; the controller's
        // VideoQuotaReachedException catch only fires on a service-layer race.
        ['user' => $user] = $this->makeFace('pro');

        $this->partialMock(FaceVideoService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getVideoDuration')->andReturn(60.0);
            $mock->shouldReceive('uploadVideo')
                ->andThrow(new VideoQuotaReachedException(1, FaceVideoType::Acting));
        });

        $response = $this->actingAs($user)->postJson('/api/v1/face/videos', [
            'type' => 'acting',
            'video' => $this->fakeVideo(),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VIDEO_QUOTA_REACHED')
            ->assertJsonPath('error.message', 'Quota de 1 vidéo Acting atteint.');
    }

    // === upload — validation ===

    public function test_upload_rejects_missing_type(): void
    {
        ['user' => $user] = $this->makeFace('pro');

        $response = $this->actingAs($user)->postJson('/api/v1/face/videos', [
            'video' => $this->fakeVideo(),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['type']);
    }

    public function test_upload_rejects_invalid_type(): void
    {
        ['user' => $user] = $this->makeFace('pro');

        $response = $this->actingAs($user)->postJson('/api/v1/face/videos', [
            'type' => 'presentation',
            'video' => $this->fakeVideo(),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['type']);
    }

    public function test_upload_rejects_oversized_video(): void
    {
        ['user' => $user] = $this->makeFace('pro');

        $response = $this->actingAs($user)->postJson('/api/v1/face/videos', [
            'type' => 'acting',
            'video' => UploadedFile::fake()->create('big.mp4', 51 * 1024, 'video/mp4'),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['video']);
    }

    public function test_upload_rejects_video_longer_than_2_minutes(): void
    {
        ['user' => $user] = $this->makeFace('pro');
        $this->mockVideoService(150.0);

        $response = $this->actingAs($user)->postJson('/api/v1/face/videos', [
            'type' => 'acting',
            'video' => $this->fakeVideo(),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['video']);
        $this->assertStringContainsString('2 minutes', $response->json('errors.video.0'));
    }

    public function test_upload_rejects_non_video_file(): void
    {
        ['user' => $user] = $this->makeFace('pro');

        $response = $this->actingAs($user)->postJson('/api/v1/face/videos', [
            'type' => 'acting',
            'video' => UploadedFile::fake()->create('doc.pdf', 1024, 'application/pdf'),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['video']);
    }

    public function test_upload_rejects_image_file(): void
    {
        ['user' => $user] = $this->makeFace('pro');

        $response = $this->actingAs($user)->postJson('/api/v1/face/videos', [
            'type' => 'acting',
            'video' => UploadedFile::fake()->image('photo.jpg'),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['video']);
    }

    public function test_upload_rejects_disallowed_formats(): void
    {
        ['user' => $user] = $this->makeFace('pro');

        $disallowed = [
            UploadedFile::fake()->create('notes.txt', 1024, 'text/plain'),
            UploadedFile::fake()->create('clip.wmv', 1024, 'video/x-ms-wmv'),
            UploadedFile::fake()->create('clip.webm', 1024, 'video/webm'),
        ];

        foreach ($disallowed as $file) {
            $this->actingAs($user)->postJson('/api/v1/face/videos', [
                'type' => 'acting',
                'video' => $file,
            ])->assertUnprocessable()->assertJsonValidationErrors(['video']);
        }
    }

    public function test_upload_rejects_unreadable_video(): void
    {
        ['user' => $user] = $this->makeFace('pro');

        $this->partialMock(FaceVideoService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getVideoDuration')
                ->andThrow(new \RuntimeException('ffprobe could not read the file'));
        });

        $response = $this->actingAs($user)->postJson('/api/v1/face/videos', [
            'type' => 'acting',
            'video' => $this->fakeVideo(),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['video']);
    }

    // === delete ===

    public function test_face_can_delete_own_video(): void
    {
        ['face' => $face, 'user' => $user] = $this->makeFace('pro');
        $video = FaceVideo::factory()->acting()->create([
            'face_id' => $face->id,
            'position' => 1,
            'filename' => 'a.mp4',
        ]);
        Storage::disk('public')->put('videos/faces/acting/a.mp4', 'content');

        $response = $this->actingAs($user)->deleteJson("/api/v1/face/videos/{$video->uuid}");

        $response->assertOk();
        $this->assertSame(0, FaceVideo::where('id', $video->id)->count());
        Storage::disk('public')->assertMissing('videos/faces/acting/a.mp4');
    }

    public function test_delete_reorders_remaining_same_type_videos(): void
    {
        ['face' => $face, 'user' => $user] = $this->makeFace('elite');
        $videos = FaceVideo::factory()->createSequentialForFace($face, FaceVideoType::Acting, 2);

        $this->actingAs($user)->deleteJson("/api/v1/face/videos/{$videos[0]->uuid}")->assertOk();

        $remaining = $face->videos()->where('type', FaceVideoType::Acting)->get();
        $this->assertCount(1, $remaining);
        $this->assertSame(1, $remaining->first()->position);
        $this->assertSame($videos[1]->id, $remaining->first()->id);
    }

    public function test_face_cannot_delete_another_faces_video(): void
    {
        ['user' => $user] = $this->makeFace('pro');
        $otherFace = Face::factory()->create();
        $otherVideo = FaceVideo::factory()->acting()->create(['face_id' => $otherFace->id, 'position' => 1]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/face/videos/{$otherVideo->uuid}");

        $response->assertForbidden();
        $this->assertSame(1, FaceVideo::where('id', $otherVideo->id)->count());
    }

    // === authorization ===

    public function test_producer_cannot_access_video_endpoints(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $faceVideo = FaceVideo::factory()->acting()->create([
            'face_id' => Face::factory()->create()->id,
            'position' => 1,
        ]);

        $this->actingAs($producerUser)->getJson('/api/v1/face/videos')->assertForbidden();
        $this->actingAs($producerUser)->postJson('/api/v1/face/videos', [
            'type' => 'acting',
            'video' => $this->fakeVideo(),
        ])->assertForbidden();
        $this->actingAs($producerUser)
            ->deleteJson("/api/v1/face/videos/{$faceVideo->uuid}")
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_video_endpoints(): void
    {
        $this->getJson('/api/v1/face/videos')->assertUnauthorized();
        $this->postJson('/api/v1/face/videos', [
            'type' => 'acting',
            'video' => $this->fakeVideo(),
        ])->assertUnauthorized();
    }

    // === rate limiting ===

    public function test_upload_is_rate_limited(): void
    {
        ['user' => $user] = $this->makeFace('elite');
        $this->mockVideoService();

        for ($i = 1; $i <= 21; $i++) {
            $response = $this->actingAs($user)->postJson('/api/v1/face/videos', [
                'type' => 'acting',
                'video' => $this->fakeVideo("v{$i}.mp4"),
            ]);

            if ($i <= 20) {
                $this->assertNotSame(429, $response->status(), "Request {$i} should not be rate-limited");
            } else {
                $response->assertTooManyRequests();
            }
        }
    }
}
