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

/**
 * Tier-aware album-photo masking (FEATURE-FP-2.2).
 *
 * Quota per tier (config/face_subscription_tiers.php): Free 1 / Starter 2 / Pro 4 / Élite 6.
 * Public + producer lenses receive a filtered photo list (no lock fields).
 * Owner + admin lenses receive the full list with per-item is_locked + lock_reason.
 */
class PremiumAlbumMaskingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * Build a Face with N sequential album photos, a linked User, and an
     * optional active subscription at the given paid tier.
     *
     * @param  null|'starter'|'pro'|'elite'  $tier  null → Free
     * @return array{face: Face, user: User}
     */
    private function makeFace(int $photoCount, ?string $tier = null): array
    {
        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        FacePhoto::factory()->createSequentialForFace($face, $photoCount);

        if ($tier !== null) {
            FaceSubscription::factory()->{$tier}()->active()->create(['face_id' => $face->id]);
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

    // ===================================================================
    // Public lens — list filtered to the tier's max_album_photos
    // ===================================================================

    public function test_public_lens_masks_free_face_to_1_photo(): void
    {
        ['face' => $face] = $this->makeFace(6);

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");

        $response->assertOk()
            ->assertJsonCount(1, 'data.photos')
            ->assertJsonPath('data.album_photos_count', 1);

        $this->assertSame([1], collect($response->json('data.photos'))->pluck('position')->all());
    }

    public function test_public_lens_masks_starter_face_to_2_photos(): void
    {
        ['face' => $face] = $this->makeFace(6, 'starter');

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");

        $response->assertOk()
            ->assertJsonCount(2, 'data.photos')
            ->assertJsonPath('data.album_photos_count', 2);

        $this->assertSame([1, 2], collect($response->json('data.photos'))->pluck('position')->all());
    }

    public function test_public_lens_masks_pro_face_to_4_photos(): void
    {
        ['face' => $face] = $this->makeFace(6, 'pro');

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");

        $response->assertOk()
            ->assertJsonCount(4, 'data.photos')
            ->assertJsonPath('data.album_photos_count', 4);
    }

    public function test_public_lens_exposes_all_6_photos_for_elite_face(): void
    {
        ['face' => $face] = $this->makeFace(6, 'elite');

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");

        $response->assertOk()
            ->assertJsonCount(6, 'data.photos')
            ->assertJsonPath('data.album_photos_count', 6);
    }

    // ===================================================================
    // Producer lens — list filtered, no lock fields leaked
    // ===================================================================

    public function test_producer_lens_masks_free_face_to_1_photo(): void
    {
        ['face' => $face] = $this->makeFace(6);
        $producerUser = $this->actingProducer();

        $response = $this->actingAs($producerUser)
            ->getJson("/api/v1/producer/candidates/{$face->uuid}");

        $response->assertOk()->assertJsonCount(1, 'data.photos');
    }

    public function test_producer_lens_masks_starter_face_to_2_photos(): void
    {
        ['face' => $face] = $this->makeFace(6, 'starter');
        $producerUser = $this->actingProducer();

        $response = $this->actingAs($producerUser)
            ->getJson("/api/v1/producer/candidates/{$face->uuid}");

        $response->assertOk()->assertJsonCount(2, 'data.photos');
    }

    public function test_producer_lens_masks_pro_face_to_4_photos(): void
    {
        ['face' => $face] = $this->makeFace(6, 'pro');
        $producerUser = $this->actingProducer();

        $response = $this->actingAs($producerUser)
            ->getJson("/api/v1/producer/candidates/{$face->uuid}");

        $response->assertOk()->assertJsonCount(4, 'data.photos');
    }

    public function test_producer_lens_exposes_all_6_photos_for_elite_face(): void
    {
        ['face' => $face] = $this->makeFace(6, 'elite');
        $producerUser = $this->actingProducer();

        $response = $this->actingAs($producerUser)
            ->getJson("/api/v1/producer/candidates/{$face->uuid}");

        $response->assertOk()->assertJsonCount(6, 'data.photos');
    }

    public function test_producer_lens_omits_lock_fields(): void
    {
        ['face' => $face] = $this->makeFace(6);
        $producerUser = $this->actingProducer();

        $response = $this->actingAs($producerUser)
            ->getJson("/api/v1/producer/candidates/{$face->uuid}");

        $response->assertOk();
        $first = $response->json('data.photos.0');
        $this->assertArrayNotHasKey('is_locked', $first);
        $this->assertArrayNotHasKey('lock_reason', $first);
    }

    // ===================================================================
    // Owner lens — full list with is_locked + lock_reason
    // ===================================================================

    public function test_owner_lens_returns_all_6_photos_with_lock_flags_for_free_face(): void
    {
        ['face' => $face, 'user' => $faceUser] = $this->makeFace(6);

        $response = $this->actingAs($faceUser)->getJson('/api/v1/face/profile');

        $response->assertOk()->assertJsonCount(6, 'data.photos');

        $photos = collect($response->json('data.photos'))->keyBy('position');
        $this->assertFalse($photos[1]['is_locked']);
        $this->assertNull($photos[1]['lock_reason']);
        foreach ([2, 3, 4, 5, 6] as $pos) {
            $this->assertTrue($photos[$pos]['is_locked']);
            $this->assertSame('quota_exceeded', $photos[$pos]['lock_reason']);
        }
    }

    public function test_owner_lens_lock_flags_for_pro_face(): void
    {
        ['face' => $face, 'user' => $faceUser] = $this->makeFace(6, 'pro');

        $response = $this->actingAs($faceUser)->getJson('/api/v1/face/profile');

        $response->assertOk()->assertJsonCount(6, 'data.photos');

        $photos = collect($response->json('data.photos'))->keyBy('position');
        foreach ([1, 2, 3, 4] as $pos) {
            $this->assertFalse($photos[$pos]['is_locked']);
            $this->assertNull($photos[$pos]['lock_reason']);
        }
        foreach ([5, 6] as $pos) {
            $this->assertTrue($photos[$pos]['is_locked']);
            $this->assertSame('quota_exceeded', $photos[$pos]['lock_reason']);
        }
    }

    public function test_owner_lens_returns_all_6_photos_unlocked_for_elite_face(): void
    {
        ['face' => $face, 'user' => $faceUser] = $this->makeFace(6, 'elite');

        $response = $this->actingAs($faceUser)->getJson('/api/v1/face/profile');

        $response->assertOk()->assertJsonCount(6, 'data.photos');

        foreach ($response->json('data.photos') as $photo) {
            $this->assertFalse($photo['is_locked']);
            $this->assertNull($photo['lock_reason']);
        }
    }

    // ===================================================================
    // Admin lens — full list with is_locked + lock_reason (same as owner)
    // ===================================================================

    public function test_admin_lens_returns_all_6_photos_with_lock_flags_for_free_face(): void
    {
        ['face' => $face] = $this->makeFace(6);
        $token = $this->adminToken();

        $response = $this->withToken($token)->getJson("/api/v1/admin/faces/{$face->uuid}");

        $response->assertOk()->assertJsonCount(6, 'data.photos');

        $photos = collect($response->json('data.photos'))->keyBy('position');
        $this->assertFalse($photos[1]['is_locked']);
        foreach ([2, 3, 4, 5, 6] as $pos) {
            $this->assertTrue($photos[$pos]['is_locked']);
            $this->assertSame('quota_exceeded', $photos[$pos]['lock_reason']);
        }
    }

    public function test_admin_lens_returns_all_6_photos_unlocked_for_elite_face(): void
    {
        ['face' => $face] = $this->makeFace(6, 'elite');
        $token = $this->adminToken();

        $response = $this->withToken($token)->getJson("/api/v1/admin/faces/{$face->uuid}");

        $response->assertOk()->assertJsonCount(6, 'data.photos');

        foreach ($response->json('data.photos') as $photo) {
            $this->assertFalse($photo['is_locked']);
        }
    }

    // ===================================================================
    // lock_reason semantics + downgrade preservation
    // ===================================================================

    public function test_lock_reason_is_quota_exceeded_for_every_locked_photo(): void
    {
        ['face' => $face, 'user' => $faceUser] = $this->makeFace(6);

        $response = $this->actingAs($faceUser)->getJson('/api/v1/face/profile');

        $response->assertOk();
        foreach ($response->json('data.photos') as $photo) {
            if ($photo['is_locked']) {
                $this->assertSame('quota_exceeded', $photo['lock_reason']);
            } else {
                $this->assertNull($photo['lock_reason']);
            }
            $this->assertNotSame('tier_below_required', $photo['lock_reason']);
        }
    }

    public function test_downgrade_from_elite_to_free_preserves_photos_but_masks_public(): void
    {
        ['face' => $face, 'user' => $faceUser] = $this->makeFace(6, 'elite');

        // Place real files on disk so preservation can be asserted.
        $face->load('photos');
        foreach ($face->photos as $photo) {
            Storage::disk('public')->put('avatars/faces/albums/'.$photo->filename, 'photo content');
        }

        // Downgrade: flip the active subscription to expired without deleting the row.
        FaceSubscription::query()
            ->where('face_id', $face->id)
            ->update([
                'status' => FaceSubscriptionStatus::Expired,
                'expires_at' => now()->subDay(),
            ]);

        // Public lens now masks to the Free quota of 1.
        $this->getJson("/api/v1/public/faces/{$face->username}")
            ->assertOk()
            ->assertJsonCount(1, 'data.photos');

        // Owner still sees all 6; positions 2-6 are locked.
        $ownerResponse = $this->actingAs($faceUser)->getJson('/api/v1/face/profile');
        $ownerResponse->assertOk()->assertJsonCount(6, 'data.photos');
        $photos = collect($ownerResponse->json('data.photos'))->keyBy('position');
        $this->assertFalse($photos[1]['is_locked']);
        $this->assertTrue($photos[6]['is_locked']);

        // DB rows + files preserved — nothing deleted on downgrade.
        $this->assertSame(6, FacePhoto::where('face_id', $face->id)->count());
        foreach ($face->fresh()->photos as $photo) {
            Storage::disk('public')->assertExists('avatars/faces/albums/'.$photo->filename);
        }
    }

    // ===================================================================
    // FP-2.12 has_elite_badge per lens
    // ===================================================================

    public function test_public_lens_has_elite_badge_is_true_for_elite_face(): void
    {
        ['face' => $face] = $this->makeFace(6, 'elite');

        $this->getJson("/api/v1/public/faces/{$face->username}")
            ->assertOk()
            ->assertJsonPath('data.has_elite_badge', true);
    }

    public function test_public_lens_has_elite_badge_is_false_for_non_elite_tiers(): void
    {
        foreach ([null, 'starter', 'pro'] as $tier) {
            ['face' => $face] = $this->makeFace(6, $tier);

            $this->getJson("/api/v1/public/faces/{$face->username}")
                ->assertOk()
                ->assertJsonPath('data.has_elite_badge', false);
        }
    }

    public function test_producer_lens_has_elite_badge_is_true_for_elite_face(): void
    {
        ['face' => $face] = $this->makeFace(6, 'elite');
        $producer = $this->actingProducer();

        $this->actingAs($producer)
            ->getJson("/api/v1/producer/candidates/{$face->uuid}")
            ->assertOk()
            ->assertJsonPath('data.has_elite_badge', true);
    }

    public function test_owner_lens_has_elite_badge_is_true_for_elite_face(): void
    {
        ['face' => $face, 'user' => $user] = $this->makeFace(6, 'elite');

        $this->actingAs($user)
            ->getJson('/api/v1/face/profile')
            ->assertOk()
            ->assertJsonPath('data.has_elite_badge', true);
    }

    public function test_admin_lens_emits_has_elite_badge_alongside_subscription_tier_for_elite_face(): void
    {
        ['face' => $face] = $this->makeFace(6, 'elite');
        $token = $this->adminToken();

        $this->withToken($token)
            ->getJson("/api/v1/admin/faces/{$face->uuid}")
            ->assertOk()
            ->assertJsonPath('data.has_elite_badge', true)
            ->assertJsonPath('data.subscription_tier', 'elite');
    }

    public function test_chained_renewal_expired_elite_plus_active_pro_resolves_has_elite_badge_to_false(): void
    {
        ['face' => $face] = $this->makeFace(6); // Free baseline (no subscription created by makeFace)
        FaceSubscription::factory()->elite()->expired()->create(['face_id' => $face->id]);
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $face->id]);

        $this->getJson("/api/v1/public/faces/{$face->username}")
            ->assertOk()
            ->assertJsonPath('data.has_elite_badge', false);
    }

    // AC #11 — Cancelled Élite avec `expires_at` futur : la souscription reste
    // visible (grace period) mais n'est plus Active, donc le badge doit tomber.
    public function test_cancelled_elite_with_future_expiry_resolves_has_elite_badge_to_false(): void
    {
        ['face' => $face] = $this->makeFace(6); // Free baseline
        FaceSubscription::factory()->elite()->cancelled()->create(['face_id' => $face->id]);

        $this->getJson("/api/v1/public/faces/{$face->username}")
            ->assertOk()
            ->assertJsonPath('data.has_elite_badge', false);
    }
}
