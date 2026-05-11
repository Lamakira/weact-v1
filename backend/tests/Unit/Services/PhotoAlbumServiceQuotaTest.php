<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\AlbumQuotaReachedException;
use App\Models\Face;
use App\Models\FacePhoto;
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
        for ($i = 1; $i <= 2; $i++) {
            FacePhoto::factory()->create([
                'face_id' => $face->id,
                'position' => $i,
            ]);
        }

        $service = app(PhotoAlbumService::class);
        $photo = UploadedFile::fake()->image('p.jpg', 500, 500);

        try {
            $service->addPhoto($face, $photo);
            $this->fail('Expected AlbumQuotaReachedException to be thrown.');
        } catch (AlbumQuotaReachedException $e) {
            $this->assertSame(2, $e->limit);
        }

        // No new face_photos row created (count stays at 2)
        $this->assertSame(2, FacePhoto::where('face_id', $face->id)->count());

        // No file written for the rejected upload attempt under the album path
        $files = Storage::disk('public')->files('avatars/faces/albums');
        $this->assertSame([], $files, 'No file should be written when the quota is reached.');
    }
}
