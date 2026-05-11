<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Enums\FaceSubscriptionStatus;
use App\Models\Admin;
use App\Models\Face;
use App\Models\FacePhoto;
use App\Models\FaceSubscription;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PremiumAlbumMaskingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * Build a Face with N sequential photos, attached User, and optionally a subscription state.
     *
     * @return array{face: Face, user: User}
     */
    private function makeFaceWithPhotos(int $photoCount, ?string $subscriptionState = null): array
    {
        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        FacePhoto::factory()->createSequentialForFace($face, $photoCount);

        if ($subscriptionState === 'active') {
            FaceSubscription::factory()->active()->create(['face_id' => $face->id]);
        }

        return ['face' => $face, 'user' => $user];
    }

    private function actingProducer(): User
    {
        $producer = Producer::factory()->create();

        return User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
    }

    private function adminToken(): string
    {
        return Admin::factory()->create()->createToken('admin-token')->plainTextToken;
    }

    public function test_public_response_returns_only_2_photos_for_free_face_with_4_photos(): void
    {
        ['face' => $face] = $this->makeFaceWithPhotos(4);

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");

        $response->assertOk()
            ->assertJsonCount(2, 'data.photos')
            ->assertJsonPath('data.album_photos_count', 2);

        $positions = collect($response->json('data.photos'))->pluck('position')->all();
        $this->assertSame([1, 2], $positions);
    }

    public function test_public_response_returns_4_photos_for_premium_face_with_4_photos(): void
    {
        ['face' => $face] = $this->makeFaceWithPhotos(4, 'active');

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");

        $response->assertOk()
            ->assertJsonCount(4, 'data.photos')
            ->assertJsonPath('data.album_photos_count', 4);
    }

    public function test_producer_response_returns_only_2_photos_for_free_face_with_4_photos(): void
    {
        ['face' => $face] = $this->makeFaceWithPhotos(4);
        $producerUser = $this->actingProducer();

        $response = $this->actingAs($producerUser)
            ->getJson("/api/v1/producer/candidates/{$face->uuid}");

        $response->assertOk()
            ->assertJsonCount(2, 'data.photos');

        // Producer view does not leak the per-photo lock flag
        $first = $response->json('data.photos.0');
        $this->assertArrayNotHasKey('is_publicly_visible', $first);
    }

    public function test_owner_response_returns_4_photos_with_lock_flags_when_free(): void
    {
        ['face' => $face, 'user' => $faceUser] = $this->makeFaceWithPhotos(4);

        $response = $this->actingAs($faceUser)
            ->getJson('/api/v1/face/profile');

        $response->assertOk()
            ->assertJsonCount(4, 'data.photos');

        $photos = collect($response->json('data.photos'))->keyBy('position');
        $this->assertTrue($photos[1]['is_publicly_visible']);
        $this->assertTrue($photos[2]['is_publicly_visible']);
        $this->assertFalse($photos[3]['is_publicly_visible']);
        $this->assertFalse($photos[4]['is_publicly_visible']);
    }

    public function test_owner_response_returns_4_photos_all_publicly_visible_when_premium(): void
    {
        ['face' => $face, 'user' => $faceUser] = $this->makeFaceWithPhotos(4, 'active');

        $response = $this->actingAs($faceUser)
            ->getJson('/api/v1/face/profile');

        $response->assertOk()
            ->assertJsonCount(4, 'data.photos');

        foreach ($response->json('data.photos') as $photo) {
            $this->assertTrue($photo['is_publicly_visible']);
        }
    }

    public function test_admin_response_returns_4_photos_with_lock_flags(): void
    {
        ['face' => $face] = $this->makeFaceWithPhotos(4);
        $adminToken = $this->adminToken();

        $response = $this->withToken($adminToken)
            ->getJson("/api/v1/admin/faces/{$face->uuid}");

        $response->assertOk()
            ->assertJsonCount(4, 'data.photos');

        $photos = collect($response->json('data.photos'))->keyBy('position');
        $this->assertTrue($photos[1]['is_publicly_visible']);
        $this->assertTrue($photos[2]['is_publicly_visible']);
        $this->assertFalse($photos[3]['is_publicly_visible']);
        $this->assertFalse($photos[4]['is_publicly_visible']);
    }

    public function test_downgrade_from_premium_to_expired_preserves_files_and_db_but_masks_public_response(): void
    {
        ['face' => $face, 'user' => $faceUser] = $this->makeFaceWithPhotos(4, 'active');
        $face->update([
            'acting_video' => 'old.mp4',
            'acting_video_thumbnail' => 'old-thumb.jpg',
        ]);

        // Place actual storage entries for both album files (positions 3,4) and
        // the acting video so the assertions below can confirm preservation.
        $face->load('photos');
        foreach ($face->photos as $photo) {
            Storage::disk('public')->put('avatars/faces/albums/'.$photo->filename, 'photo content');
        }
        Storage::disk('public')->put('videos/faces/acting/old.mp4', 'video content');

        // Force the active subscription into an expired state without deleting the row.
        FaceSubscription::query()
            ->where('face_id', $face->id)
            ->update([
                'status' => FaceSubscriptionStatus::Expired,
                'expires_at' => now()->subDay(),
            ]);

        // Public response: 2 photos, acting video masked
        $publicResponse = $this->getJson("/api/v1/public/faces/{$face->username}");
        $publicResponse->assertOk()
            ->assertJsonCount(2, 'data.photos')
            ->assertJsonPath('data.acting_video_url', null)
            ->assertJsonPath('data.has_acting_video', false);

        // Owner response: full 4 photos with lock flags + real acting video URL
        $ownerResponse = $this->actingAs($faceUser)
            ->getJson('/api/v1/face/profile');

        $ownerResponse->assertOk()
            ->assertJsonCount(4, 'data.photos');
        $this->assertNotNull($ownerResponse->json('data.acting_video_url'));
        $this->assertStringContainsString('old.mp4', $ownerResponse->json('data.acting_video_url'));
        $this->assertFalse($ownerResponse->json('data.is_acting_video_publicly_visible'));

        // Files preserved on disk
        $face->load('photos');
        $positionThreeFour = $face->photos->whereIn('position', [3, 4]);
        foreach ($positionThreeFour as $photo) {
            Storage::disk('public')->assertExists('avatars/faces/albums/'.$photo->filename);
        }
        Storage::disk('public')->assertExists('videos/faces/acting/old.mp4');

        // DB rows preserved
        $this->assertSame(4, FacePhoto::where('face_id', $face->id)->count());
        $this->assertSame('old.mp4', $face->fresh()->acting_video);
    }
}
