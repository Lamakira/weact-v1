<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\AlbumQuotaReachedException;
use App\Models\Face;
use App\Models\FacePhoto;
use App\Models\FaceSubscription;
use App\Services\PhotoAlbumService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoAlbumServiceQuotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_throws_album_quota_reached_for_free_face_at_limit(): void
    {
        Storage::fake('public');

        $face = Face::factory()->create();
        FacePhoto::factory()->create([
            'face_id' => $face->id,
            'position' => 1,
        ]);

        $service = app(PhotoAlbumService::class);
        $photo = UploadedFile::fake()->image('p.jpg', 500, 500);

        try {
            $service->addPhoto($face, $photo);
            $this->fail('Expected AlbumQuotaReachedException to be thrown.');
        } catch (AlbumQuotaReachedException $e) {
            $this->assertSame(1, $e->limit);
        }

        // No new face_photos row created (count stays at the Free limit of 1)
        $this->assertSame(1, FacePhoto::where('face_id', $face->id)->count());

        // No file written for the rejected upload attempt under the album path
        $files = Storage::disk('public')->files('avatars/faces/albums');
        $this->assertSame([], $files, 'No file should be written when the quota is reached.');
    }

    public function test_throws_album_quota_reached_for_elite_face_at_limit(): void
    {
        Storage::fake('public');

        $face = Face::factory()->create();
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $face->id]);
        FacePhoto::factory()->createSequentialForFace($face, 6);

        $service = app(PhotoAlbumService::class);
        $photo = UploadedFile::fake()->image('p.jpg', 500, 500);

        try {
            $service->addPhoto($face, $photo);
            $this->fail('Expected AlbumQuotaReachedException to be thrown.');
        } catch (AlbumQuotaReachedException $e) {
            $this->assertSame(6, $e->limit);
        }

        // No new face_photos row created (count stays at the Élite limit of 6)
        $this->assertSame(6, FacePhoto::where('face_id', $face->id)->count());

        $files = Storage::disk('public')->files('avatars/faces/albums');
        $this->assertSame([], $files, 'No file should be written when the quota is reached.');
    }
}
