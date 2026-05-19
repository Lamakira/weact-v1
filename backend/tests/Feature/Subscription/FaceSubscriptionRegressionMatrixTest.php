<?php

declare(strict_types=1);

namespace Tests\Feature\Subscription;

use App\Models\Admin;
use App\Models\Face;
use App\Models\FacePhoto;
use App\Models\FaceSubscription;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class FaceSubscriptionRegressionMatrixTest extends TestCase
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
    private function seedFace(string $actingVideoFilename, ?string $subscriptionState = null, bool $isFeatured = false): array
    {
        $face = Face::factory()->create([
            'acting_video' => $actingVideoFilename,
            'acting_video_thumbnail' => str_replace('.mp4', '-thumb.jpg', $actingVideoFilename),
            'is_featured' => $isFeatured,
        ]);

        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        FacePhoto::factory()->createSequentialForFace($face, 4);

        if ($subscriptionState !== null) {
            FaceSubscription::factory()->{$subscriptionState}()->create(['face_id' => $face->id]);
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

    /**
     * @param  list<int>  $expected
     */
    private function assertPhotoPositions(TestResponse $response, array $expected): void
    {
        $positions = collect($response->json('data.photos'))->pluck('position')->all();

        $this->assertSame($expected, $positions);
    }

    private function assertProducerResponseShape(TestResponse $response): void
    {
        $response->assertJsonStructure([
            'data' => [
                'photos' => [
                    [
                        'id',
                        'photo_url',
                        'medium_url',
                        'thumbnail_url',
                        'position',
                    ],
                ],
            ],
        ]);

        foreach ($response->json('data.photos') as $photo) {
            $this->assertArrayNotHasKey('is_publicly_visible', $photo);
        }
    }

    // -------------------------------------------------------------------
    // Public-lens matrix — AC #1-#8
    // -------------------------------------------------------------------

    public function test_public_lens_masks_free_face_to_2_photos_and_no_acting_video(): void
    {
        ['face' => $face] = $this->seedFace('regression-free.mp4');

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");

        $response->assertOk()
            ->assertJsonCount(2, 'data.photos')
            ->assertJsonPath('data.album_photos_count', 2)
            ->assertJsonPath('data.acting_video_url', null)
            ->assertJsonPath('data.has_acting_video', false);

        $this->assertPhotoPositions($response, [1, 2]);
    }

    public function test_public_lens_masks_pending_payment_face(): void
    {
        ['face' => $face] = $this->seedFace('regression-pending.mp4', 'pendingPayment');

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");

        $response->assertOk()
            ->assertJsonCount(2, 'data.photos')
            ->assertJsonPath('data.album_photos_count', 2)
            ->assertJsonPath('data.acting_video_url', null)
            ->assertJsonPath('data.has_acting_video', false);

        $this->assertPhotoPositions($response, [1, 2]);
    }

    public function test_public_lens_exposes_4_photos_and_acting_video_for_active_face(): void
    {
        ['face' => $face] = $this->seedFace('regression-active.mp4', 'active');

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");

        $response->assertOk()
            ->assertJsonCount(4, 'data.photos')
            ->assertJsonPath('data.album_photos_count', 4)
            ->assertJsonPath('data.has_acting_video', true);

        $this->assertPhotoPositions($response, [1, 2, 3, 4]);
        $this->assertNotNull($response->json('data.acting_video_url'));
        $this->assertStringContainsString('regression-active.mp4', $response->json('data.acting_video_url'));
    }

    public function test_public_lens_masks_expired_face(): void
    {
        ['face' => $face] = $this->seedFace('regression-expired.mp4', 'expired');

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");

        $response->assertOk()
            ->assertJsonCount(2, 'data.photos')
            ->assertJsonPath('data.album_photos_count', 2)
            ->assertJsonPath('data.acting_video_url', null)
            ->assertJsonPath('data.has_acting_video', false);

        $this->assertPhotoPositions($response, [1, 2]);
    }

    public function test_public_lens_masks_cancelled_face_even_with_future_expires_at(): void
    {
        ['face' => $face] = $this->seedFace('regression-cancelled.mp4', 'cancelled');

        // Sanity-check the factory shape: cancelled() leaves the default future expires_at intact.
        $subscription = FaceSubscription::query()->where('face_id', $face->id)->firstOrFail();
        $this->assertTrue($subscription->expires_at->isFuture());

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");

        $response->assertOk()
            ->assertJsonCount(2, 'data.photos')
            ->assertJsonPath('data.album_photos_count', 2)
            ->assertJsonPath('data.acting_video_url', null)
            ->assertJsonPath('data.has_acting_video', false);

        $this->assertPhotoPositions($response, [1, 2]);
    }

    public function test_public_lens_masks_failed_face(): void
    {
        ['face' => $face] = $this->seedFace('regression-failed.mp4', 'failed');

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");

        $response->assertOk()
            ->assertJsonCount(2, 'data.photos')
            ->assertJsonPath('data.album_photos_count', 2)
            ->assertJsonPath('data.acting_video_url', null)
            ->assertJsonPath('data.has_acting_video', false);

        $this->assertPhotoPositions($response, [1, 2]);
    }

    public function test_public_lens_does_not_grant_premium_to_admin_manually_featured_free_face(): void
    {
        // Most important regression pin: is_featured is a listing flag, NOT an entitlement.
        ['face' => $face] = $this->seedFace('regression-featured-free.mp4', null, true);

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");

        $response->assertOk()
            ->assertJsonCount(2, 'data.photos')
            ->assertJsonPath('data.album_photos_count', 2)
            ->assertJsonPath('data.acting_video_url', null)
            ->assertJsonPath('data.has_acting_video', false);

        $this->assertPhotoPositions($response, [1, 2]);
    }

    public function test_public_lens_grants_premium_to_admin_featured_active_face(): void
    {
        ['face' => $face] = $this->seedFace('regression-featured-active.mp4', 'active', true);

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");

        $response->assertOk()
            ->assertJsonCount(4, 'data.photos')
            ->assertJsonPath('data.album_photos_count', 4)
            ->assertJsonPath('data.has_acting_video', true);

        $this->assertPhotoPositions($response, [1, 2, 3, 4]);
        $this->assertNotNull($response->json('data.acting_video_url'));
        $this->assertStringContainsString('regression-featured-active.mp4', $response->json('data.acting_video_url'));
    }

    // -------------------------------------------------------------------
    // Producer-lens matrix — AC #9
    // -------------------------------------------------------------------

    public function test_producer_lens_masks_free_face(): void
    {
        ['face' => $face] = $this->seedFace('producer-free.mp4');
        $producerUser = $this->actingProducer();

        $response = $this->actingAs($producerUser)
            ->getJson("/api/v1/producer/candidates/{$face->uuid}");

        $response->assertOk()
            ->assertJsonCount(2, 'data.photos')
            ->assertJsonPath('data.acting_video_url', null);

        $this->assertPhotoPositions($response, [1, 2]);
        $this->assertProducerResponseShape($response);
    }

    public function test_producer_lens_masks_pending_payment_face(): void
    {
        ['face' => $face] = $this->seedFace('producer-pending.mp4', 'pendingPayment');
        $producerUser = $this->actingProducer();

        $response = $this->actingAs($producerUser)
            ->getJson("/api/v1/producer/candidates/{$face->uuid}");

        $response->assertOk()
            ->assertJsonCount(2, 'data.photos')
            ->assertJsonPath('data.acting_video_url', null);

        $this->assertPhotoPositions($response, [1, 2]);
        $this->assertProducerResponseShape($response);
    }

    public function test_producer_lens_exposes_full_set_for_active_face(): void
    {
        ['face' => $face] = $this->seedFace('producer-active.mp4', 'active');
        $producerUser = $this->actingProducer();

        $response = $this->actingAs($producerUser)
            ->getJson("/api/v1/producer/candidates/{$face->uuid}");

        $response->assertOk()
            ->assertJsonCount(4, 'data.photos');

        $this->assertPhotoPositions($response, [1, 2, 3, 4]);
        $this->assertProducerResponseShape($response);
        $this->assertNotNull($response->json('data.acting_video_url'));
        $this->assertStringContainsString('producer-active.mp4', $response->json('data.acting_video_url'));
    }

    public function test_producer_lens_masks_expired_face(): void
    {
        ['face' => $face] = $this->seedFace('producer-expired.mp4', 'expired');
        $producerUser = $this->actingProducer();

        $response = $this->actingAs($producerUser)
            ->getJson("/api/v1/producer/candidates/{$face->uuid}");

        $response->assertOk()
            ->assertJsonCount(2, 'data.photos')
            ->assertJsonPath('data.acting_video_url', null);

        $this->assertPhotoPositions($response, [1, 2]);
        $this->assertProducerResponseShape($response);
    }

    public function test_producer_lens_masks_cancelled_face(): void
    {
        ['face' => $face] = $this->seedFace('producer-cancelled.mp4', 'cancelled');
        $producerUser = $this->actingProducer();

        $response = $this->actingAs($producerUser)
            ->getJson("/api/v1/producer/candidates/{$face->uuid}");

        $response->assertOk()
            ->assertJsonCount(2, 'data.photos')
            ->assertJsonPath('data.acting_video_url', null);

        $this->assertPhotoPositions($response, [1, 2]);
        $this->assertProducerResponseShape($response);
    }

    public function test_producer_lens_masks_failed_face(): void
    {
        ['face' => $face] = $this->seedFace('producer-failed.mp4', 'failed');
        $producerUser = $this->actingProducer();

        $response = $this->actingAs($producerUser)
            ->getJson("/api/v1/producer/candidates/{$face->uuid}");

        $response->assertOk()
            ->assertJsonCount(2, 'data.photos')
            ->assertJsonPath('data.acting_video_url', null);

        $this->assertPhotoPositions($response, [1, 2]);
        $this->assertProducerResponseShape($response);
    }

    // -------------------------------------------------------------------
    // Face-owner lens matrix — AC #10
    // -------------------------------------------------------------------

    public function test_owner_lens_returns_4_photos_with_lock_flags_for_free_face(): void
    {
        ['face' => $face, 'user' => $faceUser] = $this->seedFace('owner-free.mp4');

        $response = $this->actingAs($faceUser)
            ->getJson('/api/v1/face/profile');

        $response->assertOk()
            ->assertJsonCount(4, 'data.photos');

        $photos = collect($response->json('data.photos'))->keyBy('position');
        $this->assertTrue($photos[1]['is_publicly_visible']);
        $this->assertTrue($photos[2]['is_publicly_visible']);
        $this->assertFalse($photos[3]['is_publicly_visible']);
        $this->assertFalse($photos[4]['is_publicly_visible']);

        $this->assertNotNull($response->json('data.acting_video_url'));
        $this->assertFalse($response->json('data.is_acting_video_publicly_visible'));
    }

    public function test_owner_lens_returns_4_photos_with_lock_flags_for_pending_payment_face(): void
    {
        ['face' => $face, 'user' => $faceUser] = $this->seedFace('owner-pending.mp4', 'pendingPayment');

        $response = $this->actingAs($faceUser)
            ->getJson('/api/v1/face/profile');

        $response->assertOk()
            ->assertJsonCount(4, 'data.photos');

        $photos = collect($response->json('data.photos'))->keyBy('position');
        $this->assertTrue($photos[1]['is_publicly_visible']);
        $this->assertTrue($photos[2]['is_publicly_visible']);
        $this->assertFalse($photos[3]['is_publicly_visible']);
        $this->assertFalse($photos[4]['is_publicly_visible']);

        $this->assertNotNull($response->json('data.acting_video_url'));
        $this->assertFalse($response->json('data.is_acting_video_publicly_visible'));
    }

    public function test_owner_lens_returns_4_photos_all_unlocked_for_active_face(): void
    {
        ['face' => $face, 'user' => $faceUser] = $this->seedFace('owner-active.mp4', 'active');

        $response = $this->actingAs($faceUser)
            ->getJson('/api/v1/face/profile');

        $response->assertOk()
            ->assertJsonCount(4, 'data.photos');

        foreach ($response->json('data.photos') as $photo) {
            $this->assertTrue($photo['is_publicly_visible']);
        }

        $this->assertNotNull($response->json('data.acting_video_url'));
        $this->assertTrue($response->json('data.is_acting_video_publicly_visible'));
    }

    public function test_owner_lens_returns_4_photos_with_lock_flags_for_expired_face(): void
    {
        ['face' => $face, 'user' => $faceUser] = $this->seedFace('owner-expired.mp4', 'expired');

        $response = $this->actingAs($faceUser)
            ->getJson('/api/v1/face/profile');

        $response->assertOk()
            ->assertJsonCount(4, 'data.photos');

        $photos = collect($response->json('data.photos'))->keyBy('position');
        $this->assertTrue($photos[1]['is_publicly_visible']);
        $this->assertTrue($photos[2]['is_publicly_visible']);
        $this->assertFalse($photos[3]['is_publicly_visible']);
        $this->assertFalse($photos[4]['is_publicly_visible']);

        $this->assertNotNull($response->json('data.acting_video_url'));
        $this->assertFalse($response->json('data.is_acting_video_publicly_visible'));
    }

    public function test_owner_lens_returns_4_photos_with_lock_flags_for_cancelled_face(): void
    {
        ['face' => $face, 'user' => $faceUser] = $this->seedFace('owner-cancelled.mp4', 'cancelled');

        $response = $this->actingAs($faceUser)
            ->getJson('/api/v1/face/profile');

        $response->assertOk()
            ->assertJsonCount(4, 'data.photos');

        $photos = collect($response->json('data.photos'))->keyBy('position');
        $this->assertTrue($photos[1]['is_publicly_visible']);
        $this->assertTrue($photos[2]['is_publicly_visible']);
        $this->assertFalse($photos[3]['is_publicly_visible']);
        $this->assertFalse($photos[4]['is_publicly_visible']);

        $this->assertNotNull($response->json('data.acting_video_url'));
        $this->assertFalse($response->json('data.is_acting_video_publicly_visible'));
    }

    public function test_owner_lens_returns_4_photos_with_lock_flags_for_failed_face(): void
    {
        ['face' => $face, 'user' => $faceUser] = $this->seedFace('owner-failed.mp4', 'failed');

        $response = $this->actingAs($faceUser)
            ->getJson('/api/v1/face/profile');

        $response->assertOk()
            ->assertJsonCount(4, 'data.photos');

        $photos = collect($response->json('data.photos'))->keyBy('position');
        $this->assertTrue($photos[1]['is_publicly_visible']);
        $this->assertTrue($photos[2]['is_publicly_visible']);
        $this->assertFalse($photos[3]['is_publicly_visible']);
        $this->assertFalse($photos[4]['is_publicly_visible']);

        $this->assertNotNull($response->json('data.acting_video_url'));
        $this->assertFalse($response->json('data.is_acting_video_publicly_visible'));
    }

    // -------------------------------------------------------------------
    // Admin lens matrix — AC #11
    // -------------------------------------------------------------------

    public function test_admin_lens_returns_4_photos_with_lock_flags_for_free_face(): void
    {
        ['face' => $face] = $this->seedFace('admin-free.mp4');
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

        $this->assertNotNull($response->json('data.acting_video_url'));
        $this->assertFalse($response->json('data.is_acting_video_publicly_visible'));
    }

    public function test_admin_lens_returns_4_photos_with_lock_flags_for_pending_payment_face(): void
    {
        ['face' => $face] = $this->seedFace('admin-pending.mp4', 'pendingPayment');
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

        $this->assertNotNull($response->json('data.acting_video_url'));
        $this->assertFalse($response->json('data.is_acting_video_publicly_visible'));
    }

    public function test_admin_lens_returns_4_photos_all_unlocked_for_active_face(): void
    {
        ['face' => $face] = $this->seedFace('admin-active.mp4', 'active');
        $adminToken = $this->adminToken();

        $response = $this->withToken($adminToken)
            ->getJson("/api/v1/admin/faces/{$face->uuid}");

        $response->assertOk()
            ->assertJsonCount(4, 'data.photos');

        foreach ($response->json('data.photos') as $photo) {
            $this->assertTrue($photo['is_publicly_visible']);
        }

        $this->assertNotNull($response->json('data.acting_video_url'));
        $this->assertTrue($response->json('data.is_acting_video_publicly_visible'));
    }

    public function test_admin_lens_returns_4_photos_with_lock_flags_for_expired_face(): void
    {
        ['face' => $face] = $this->seedFace('admin-expired.mp4', 'expired');
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

        $this->assertNotNull($response->json('data.acting_video_url'));
        $this->assertFalse($response->json('data.is_acting_video_publicly_visible'));
    }

    public function test_admin_lens_returns_4_photos_with_lock_flags_for_cancelled_face(): void
    {
        ['face' => $face] = $this->seedFace('admin-cancelled.mp4', 'cancelled');
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

        $this->assertNotNull($response->json('data.acting_video_url'));
        $this->assertFalse($response->json('data.is_acting_video_publicly_visible'));
    }

    public function test_admin_lens_returns_4_photos_with_lock_flags_for_failed_face(): void
    {
        ['face' => $face] = $this->seedFace('admin-failed.mp4', 'failed');
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

        $this->assertNotNull($response->json('data.acting_video_url'));
        $this->assertFalse($response->json('data.is_acting_video_publicly_visible'));
    }

    // -------------------------------------------------------------------
    // Admin cancellation no-storage-delete pin — AC #12
    // -------------------------------------------------------------------

    public function test_admin_cancel_does_not_delete_album_or_video_media(): void
    {
        $face = Face::factory()->create([
            'acting_video' => 'cancel-test.mp4',
            'acting_video_thumbnail' => 'cancel-test-thumb.jpg',
            'presentation_video' => 'presentation.mp4',
            'presentation_video_thumbnail' => 'presentation-thumb.jpg',
        ]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        FacePhoto::factory()->createSequentialForFace($face, 4);

        foreach ($face->fresh()->photos as $photo) {
            Storage::disk('public')->put('avatars/faces/albums/'.$photo->filename, 'photo content');
        }
        Storage::disk('public')->put('videos/faces/acting/cancel-test.mp4', 'acting content');
        Storage::disk('public')->put('videos/faces/acting/thumbnails/cancel-test-thumb.jpg', 'acting thumb');
        Storage::disk('public')->put('videos/faces/presentation/presentation.mp4', 'presentation content');
        Storage::disk('public')->put('videos/faces/presentation/thumbnails/presentation-thumb.jpg', 'presentation thumb');

        $subscription = FaceSubscription::factory()->active()->create(['face_id' => $face->id]);

        $adminToken = $this->adminToken();

        $response = $this->withToken($adminToken)
            ->postJson("/api/v1/admin/face-subscriptions/{$subscription->uuid}/cancel", [
                'notes' => 'Regression coverage cancel — verify no media side effect',
            ]);

        $response->assertOk()->assertJsonPath('data.status', 'cancelled');

        foreach ($face->fresh()->photos as $photo) {
            Storage::disk('public')->assertExists('avatars/faces/albums/'.$photo->filename);
        }
        Storage::disk('public')->assertExists('videos/faces/acting/cancel-test.mp4');
        Storage::disk('public')->assertExists('videos/faces/acting/thumbnails/cancel-test-thumb.jpg');
        Storage::disk('public')->assertExists('videos/faces/presentation/presentation.mp4');
        Storage::disk('public')->assertExists('videos/faces/presentation/thumbnails/presentation-thumb.jpg');

        $this->assertSame(4, FacePhoto::where('face_id', $face->id)->count());
        $this->assertSame('cancel-test.mp4', $face->fresh()->acting_video);
        $this->assertSame('presentation.mp4', $face->fresh()->presentation_video);
    }
}
