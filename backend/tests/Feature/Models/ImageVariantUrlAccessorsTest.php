<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Http\Resources\FacePhotoResource;
use App\Models\Face;
use App\Models\FacePhoto;
use App\Models\Producer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * grid_url / large_url accessor fallback chains for legacy rows and rows
 * whose GenerateImageVariants job has not run yet:
 * grid_url → grid → medium → original ; large_url → large → original.
 */
class ImageVariantUrlAccessorsTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Face
    // =========================================================================

    public function test_face_grid_url_uses_the_grid_variant_when_present(): void
    {
        $face = Face::factory()->create([
            'profile_photo' => 'photo.jpg',
            'profile_photo_medium' => 'photo.webp',
            'profile_photo_grid' => 'photo.webp',
        ]);

        $this->assertSame(asset('storage/avatars/faces/grid/photo.webp'), $face->grid_url);
    }

    public function test_face_grid_url_falls_back_to_medium_then_original(): void
    {
        $withMedium = Face::factory()->create([
            'profile_photo' => 'photo.jpg',
            'profile_photo_medium' => 'photo.webp',
            'profile_photo_grid' => null,
        ]);
        $this->assertSame(asset('storage/avatars/faces/medium/photo.webp'), $withMedium->grid_url);

        $originalOnly = Face::factory()->create([
            'profile_photo' => 'photo.jpg',
            'profile_photo_medium' => null,
            'profile_photo_grid' => null,
        ]);
        $this->assertSame(asset('storage/avatars/faces/photo.jpg'), $originalOnly->grid_url);
    }

    public function test_face_large_url_uses_the_large_variant_or_falls_back_to_medium_then_original(): void
    {
        $withLarge = Face::factory()->create([
            'profile_photo' => 'photo.jpg',
            'profile_photo_large' => 'photo.webp',
        ]);
        $this->assertSame(asset('storage/avatars/faces/large/photo.webp'), $withLarge->large_url);

        // Large pending but medium present: serve the 800px medium, never the
        // multi-MB original (the profile hero binds large_url first).
        $withMedium = Face::factory()->create([
            'profile_photo' => 'photo.jpg',
            'profile_photo_medium' => 'photo.webp',
            'profile_photo_large' => null,
        ]);
        $this->assertSame(asset('storage/avatars/faces/medium/photo.webp'), $withMedium->large_url);

        // Neither large nor medium: fall through to the original.
        $originalOnly = Face::factory()->create([
            'profile_photo' => 'photo.jpg',
            'profile_photo_medium' => null,
            'profile_photo_large' => null,
        ]);
        $this->assertSame(asset('storage/avatars/faces/photo.jpg'), $originalOnly->large_url);
    }

    public function test_face_grid_and_large_urls_are_null_without_any_photo(): void
    {
        $face = Face::factory()->create([
            'profile_photo' => null,
            'profile_photo_thumbnail' => null,
        ]);

        $this->assertNull($face->grid_url);
        $this->assertNull($face->large_url);
    }

    public function test_face_thumbnail_url_falls_back_to_original_while_variant_is_pending(): void
    {
        // Upload just happened, variant job pending: avatars must not go blank
        $pending = Face::factory()->create([
            'profile_photo' => 'photo.jpg',
            'profile_photo_thumbnail' => null,
        ]);
        $this->assertSame(asset('storage/avatars/faces/photo.jpg'), $pending->thumbnail_url);

        // Variant present: served directly
        $ready = Face::factory()->create([
            'profile_photo' => 'photo.jpg',
            'profile_photo_thumbnail' => 'photo.jpg',
        ]);
        $this->assertSame(asset('storage/avatars/faces/thumbnails/photo.jpg'), $ready->thumbnail_url);

        // No photo at all: stays null
        $bare = Face::factory()->create([
            'profile_photo' => null,
            'profile_photo_thumbnail' => null,
        ]);
        $this->assertNull($bare->thumbnail_url);
    }

    // =========================================================================
    // FacePhoto
    // =========================================================================

    public function test_face_photo_grid_url_uses_the_grid_variant_when_present(): void
    {
        $photo = FacePhoto::factory()->create([
            'filename' => 'photo.jpg',
            'medium' => 'photo.webp',
            'grid' => 'photo.webp',
        ]);

        $this->assertSame(asset('storage/avatars/faces/albums/grid/photo.webp'), $photo->grid_url);
    }

    public function test_face_photo_grid_url_falls_back_to_medium_then_original(): void
    {
        $withMedium = FacePhoto::factory()->create([
            'filename' => 'photo.jpg',
            'medium' => 'photo.webp',
            'grid' => null,
        ]);
        $this->assertSame(asset('storage/avatars/faces/albums/medium/photo.webp'), $withMedium->grid_url);

        $originalOnly = FacePhoto::factory()->create([
            'filename' => 'photo.jpg',
            'medium' => null,
            'grid' => null,
        ]);
        $this->assertSame(asset('storage/avatars/faces/albums/photo.jpg'), $originalOnly->grid_url);
    }

    public function test_face_photo_large_url_uses_the_large_variant_or_falls_back_to_medium_then_original(): void
    {
        $withLarge = FacePhoto::factory()->create([
            'filename' => 'photo.jpg',
            'large' => 'photo.webp',
        ]);
        $this->assertSame(asset('storage/avatars/faces/albums/large/photo.webp'), $withLarge->large_url);

        // Large pending but medium present: serve the 800px medium, not the original.
        $withMedium = FacePhoto::factory()->create([
            'filename' => 'photo.jpg',
            'medium' => 'photo.webp',
            'large' => null,
        ]);
        $this->assertSame(asset('storage/avatars/faces/albums/medium/photo.webp'), $withMedium->large_url);

        // Neither large nor medium: fall through to the original.
        $originalOnly = FacePhoto::factory()->create([
            'filename' => 'photo.jpg',
            'medium' => null,
            'large' => null,
        ]);
        $this->assertSame(asset('storage/avatars/faces/albums/photo.jpg'), $originalOnly->large_url);
    }

    public function test_face_photo_thumbnail_url_falls_back_to_original_while_variant_is_pending(): void
    {
        // face_photos.filename is NOT NULL — the only reachable fallback case
        // is « thumbnail pending, original present ».
        $pending = FacePhoto::factory()->create([
            'filename' => 'photo.jpg',
            'thumbnail' => null,
        ]);
        $this->assertSame(asset('storage/avatars/faces/albums/photo.jpg'), $pending->thumbnail_url);
    }

    public function test_face_photo_resource_exposes_grid_and_large_urls(): void
    {
        $photo = FacePhoto::factory()->create([
            'filename' => 'photo.jpg',
            'medium' => 'photo.webp',
            'grid' => 'photo.webp',
            'large' => 'photo.webp',
        ]);

        $payload = FacePhotoResource::make($photo)->resolve();

        $this->assertSame(asset('storage/avatars/faces/albums/grid/photo.webp'), $payload['grid_url']);
        $this->assertSame(asset('storage/avatars/faces/albums/large/photo.webp'), $payload['large_url']);
    }

    // =========================================================================
    // Producer
    // =========================================================================

    public function test_producer_grid_url_uses_the_grid_variant_when_present(): void
    {
        $producer = Producer::factory()->create([
            'profile_photo' => 'photo.jpg',
            'profile_photo_grid' => 'photo.webp',
        ]);

        $this->assertSame(asset('storage/avatars/producers/grid/photo.webp'), $producer->grid_url);
    }

    public function test_producer_grid_url_falls_back_to_medium_then_original(): void
    {
        $withMedium = Producer::factory()->create([
            'profile_photo' => 'photo.jpg',
            'profile_photo_medium' => 'photo.webp',
            'profile_photo_grid' => null,
        ]);
        $this->assertSame(asset('storage/avatars/producers/medium/photo.webp'), $withMedium->grid_url);

        $originalOnly = Producer::factory()->create([
            'profile_photo' => 'photo.jpg',
            'profile_photo_medium' => null,
            'profile_photo_grid' => null,
        ]);
        $this->assertSame(asset('storage/avatars/producers/photo.jpg'), $originalOnly->grid_url);
    }

    public function test_producer_large_url_uses_the_large_variant_or_falls_back_to_medium_then_original(): void
    {
        $withLarge = Producer::factory()->create([
            'profile_photo' => 'photo.jpg',
            'profile_photo_large' => 'photo.webp',
        ]);
        $this->assertSame(asset('storage/avatars/producers/large/photo.webp'), $withLarge->large_url);

        // Large pending but medium present: serve the 800px medium, not the original.
        $withMedium = Producer::factory()->create([
            'profile_photo' => 'photo.jpg',
            'profile_photo_medium' => 'photo.webp',
            'profile_photo_large' => null,
        ]);
        $this->assertSame(asset('storage/avatars/producers/medium/photo.webp'), $withMedium->large_url);

        // Neither large nor medium: fall through to the original.
        $originalOnly = Producer::factory()->create([
            'profile_photo' => 'photo.jpg',
            'profile_photo_medium' => null,
            'profile_photo_large' => null,
        ]);
        $this->assertSame(asset('storage/avatars/producers/photo.jpg'), $originalOnly->large_url);
    }

    public function test_producer_grid_and_large_urls_are_null_without_any_photo(): void
    {
        $producer = Producer::factory()->create(['profile_photo' => null]);

        $this->assertNull($producer->grid_url);
        $this->assertNull($producer->large_url);
    }

    public function test_producer_thumbnail_url_falls_back_to_original_while_variant_is_pending(): void
    {
        $pending = Producer::factory()->create([
            'profile_photo' => 'photo.jpg',
            'profile_photo_thumbnail' => null,
        ]);
        $this->assertSame(asset('storage/avatars/producers/photo.jpg'), $pending->thumbnail_url);

        $bare = Producer::factory()->create([
            'profile_photo' => null,
            'profile_photo_thumbnail' => null,
        ]);
        $this->assertNull($bare->thumbnail_url);
    }
}
