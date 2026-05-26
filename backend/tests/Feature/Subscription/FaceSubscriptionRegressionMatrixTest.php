<?php

declare(strict_types=1);

namespace Tests\Feature\Subscription;

use App\Enums\FaceSubscriptionPlan;
use App\Enums\FaceSubscriptionTier;
use App\Models\Admin;
use App\Models\Face;
use App\Models\FacePhoto;
use App\Models\FaceSubscription;
use App\Models\FaceVideo;
use App\Models\Producer;
use App\Models\User;
use App\Services\FaceEntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * FP-2.11 regression matrix.
 *
 * Pins the 4-tier × 6-state × 4-lens entitlement contract for the cells NOT
 * already covered by `PremiumAlbumMaskingTest` + `PremiumVideoMaskingTest`
 * (which exhaustively cover the active-tier × every lens × per-quota assertions).
 *
 * FP-2.11 owns:
 *   - 1 Free no-row cell (full lens sweep)
 *   - 12 paid × non-active cells (3 plans × 4 non-active states; every cell
 *     falls back to Free quota on every lens — that's the heart of the pin)
 *   - 1 chained-renewal cell (Expired + Active row → resolves to the Active tier)
 *   - 1 admin-featured-without-tier cell (`is_featured` is a listing flag, not an entitlement)
 *   - 1 admin-featured-with-active-Elite cell (manual + paid compose without subtraction)
 *   - 1 admin-cancel-no-storage-delete cell (extended with UGC video seed)
 *   - 1 UGC quota Pro → Elite flip cell
 *
 * Total: 18 test methods. Expected counts on every assertion are derived from
 * `FaceEntitlementService::capabilitiesForTier()` so a config bump in
 * `config/face_subscription_tiers.php` auto-propagates without test rewrites.
 */
class FaceSubscriptionRegressionMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Inventory seeded by {@see seedFace()} — Elite-max so every cell crosses every tier's quota boundary.
     * Lens helpers assert against these constants instead of literal 6/3 sprinkled across the file (Round 2 C3).
     */
    private const SEEDED_ALBUM_PHOTO_COUNT = 6;

    private const SEEDED_ACTING_VIDEO_COUNT = 2;

    private const SEEDED_UGC_VIDEO_COUNT = 1;

    private const SEEDED_VIDEO_COUNT = self::SEEDED_ACTING_VIDEO_COUNT + self::SEEDED_UGC_VIDEO_COUNT;

    private const SEEDED_PRESENTATION_FILENAME = 'matrix-presentation.mp4';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * Seed a Face with the Elite max media inventory (6 album photos + 1
     * presentation video + 2 acting videos + 1 UGC video) so every test
     * exercises media that crosses every tier's quota boundary.
     *
     * Subscription row created depending on $plan + $stateFactory:
     *   - $plan = null               → no subscription row (Free state)
     *   - $plan + null state         → active subscription with $plan
     *   - $plan + 'pendingPayment'   → pending-payment row with $plan
     *   - $plan + 'expired'          → expired row with $plan
     *   - $plan + 'cancelled'        → cancelled row with $plan (future expires_at preserved)
     *   - $plan + 'failed'           → failed row with $plan
     *
     * @return array{face: Face, user: User, sub: ?FaceSubscription}
     */
    private function seedFace(
        ?FaceSubscriptionPlan $plan = null,
        ?string $stateFactory = null,
        bool $isFeatured = false,
    ): array {
        $face = Face::factory()->create([
            'is_featured' => $isFeatured,
            'presentation_video' => self::SEEDED_PRESENTATION_FILENAME,
        ]);

        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        FacePhoto::factory()->createSequentialForFace($face, self::SEEDED_ALBUM_PHOTO_COUNT);

        FaceVideo::factory()->acting()->position(1)->create([
            'face_id' => $face->id,
            'filename' => 'matrix-acting-1.mp4',
        ]);
        FaceVideo::factory()->acting()->position(2)->create([
            'face_id' => $face->id,
            'filename' => 'matrix-acting-2.mp4',
        ]);
        FaceVideo::factory()->ugc()->position(1)->create([
            'face_id' => $face->id,
            'filename' => 'matrix-ugc.mp4',
        ]);

        $sub = null;

        if ($plan !== null) {
            // Round 2 C2: explicit match instead of strtolower($plan->name) — refactor-tool friendly
            // and crash-loud on a new plan added to the enum without updating this map.
            $planFactoryMethod = match ($plan) {
                FaceSubscriptionPlan::Starter => 'starter',
                FaceSubscriptionPlan::Pro => 'pro',
                FaceSubscriptionPlan::Elite => 'elite',
            };
            $stateMethod = $stateFactory ?? 'active';
            $expectedStatus = $stateFactory ?? 'active';
            $sub = FaceSubscription::factory()
                ->{$planFactoryMethod}()
                ->{$stateMethod}()
                ->create(['face_id' => $face->id]);

            // Sanity assert: the factory chain must produce the expected plan + status,
            // otherwise the 12 paid×non-active cells silently pass green on a wrong fixture.
            // Round 3 M5: use Str::snake() instead of a hardcoded match so any future
            // camelCase state factory (e.g. 'inGracePeriod') auto-derives the expected
            // snake_case DB value ('in_grace_period') without updating this map manually.
            $this->assertSame($plan->value, $sub->getRawOriginal('plan'), "seedFace factory drift: expected plan {$plan->value}, got {$sub->getRawOriginal('plan')}");
            $expectedDbStatus = \Illuminate\Support\Str::snake($expectedStatus);
            $this->assertSame(
                $expectedDbStatus,
                $sub->getRawOriginal('status'),
                "seedFace factory drift: expected status {$expectedStatus} (DB: {$expectedDbStatus}), got {$sub->getRawOriginal('status')}"
            );
        }

        return ['face' => $face, 'user' => $user, 'sub' => $sub];
    }

    /**
     * @return array{face: Face, user: User, sub: ?FaceSubscription}
     */
    private function seedFeaturedFace(
        ?FaceSubscriptionPlan $plan = null,
        ?string $stateFactory = null,
    ): array {
        return $this->seedFace($plan, $stateFactory, isFeatured: true);
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
            $this->assertArrayNotHasKey('is_locked', $photo, 'Producer lens must not expose is_locked on photos');
        }
    }

    // -------------------------------------------------------------------
    // Lens helpers — every helper derives expected counts via
    // FaceEntitlementService::capabilitiesForTier($expectedTier) so a
    // capabilities config bump auto-propagates without test rewrites.
    // -------------------------------------------------------------------

    private function assertPublicLens(Face $face, FaceSubscriptionTier $expectedTier): void
    {
        $caps = app(FaceEntitlementService::class)->capabilitiesForTier($expectedTier);

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");

        $response->assertOk()
            ->assertJsonCount($caps->maxAlbumPhotos, 'data.photos')
            ->assertJsonPath('data.album_photos_count', $caps->maxAlbumPhotos)
            ->assertJsonCount($caps->maxActingVideos + $caps->maxUgcVideos, 'data.videos');

        // Round 2 C9: per-type counts so a resource returning wrong types (e.g. 2 acting on a
        // cell expecting 1 acting + 1 UGC) cannot mask the bug behind a summed-count equality.
        $videos = collect($response->json('data.videos'));
        $actingCount = $videos->where('type', 'acting')->count();
        $ugcCount = $videos->where('type', 'ugc')->count();
        $this->assertSame($caps->maxActingVideos, $actingCount, "Public lens acting video count mismatch for tier {$expectedTier->value}");
        $this->assertSame($caps->maxUgcVideos, $ugcCount, "Public lens UGC video count mismatch for tier {$expectedTier->value}");

        if ($caps->maxPresentationVideos >= 1) {
            $this->assertNotNull($response->json('data.presentation_video_url'), 'Public presentation video should be visible at tier '.$expectedTier->value);
            $this->assertTrue($response->json('data.has_presentation_video'));
            // Round 2 C8: pin filename so a cross-tenant URL leak (wrong filename) is detected.
            $this->assertStringContainsString(self::SEEDED_PRESENTATION_FILENAME, $response->json('data.presentation_video_url'));
        } else {
            $this->assertNull($response->json('data.presentation_video_url'), 'Public presentation video should be masked at tier '.$expectedTier->value);
            $this->assertFalse($response->json('data.has_presentation_video'));
        }
    }

    private function assertProducerLens(Face $face, FaceSubscriptionTier $expectedTier): void
    {
        $caps = app(FaceEntitlementService::class)->capabilitiesForTier($expectedTier);
        $producerUser = $this->actingProducer();

        $response = $this->actingAs($producerUser)
            ->getJson("/api/v1/producer/candidates/{$face->uuid}");

        $response->assertOk()
            ->assertJsonCount($caps->maxAlbumPhotos, 'data.photos')
            ->assertJsonCount($caps->maxActingVideos + $caps->maxUgcVideos, 'data.videos');

        // Round 2 C9: per-type counts also on the producer lens.
        $videos = collect($response->json('data.videos'));
        $this->assertSame($caps->maxActingVideos, $videos->where('type', 'acting')->count(), "Producer lens acting video count mismatch for tier {$expectedTier->value}");
        $this->assertSame($caps->maxUgcVideos, $videos->where('type', 'ugc')->count(), "Producer lens UGC video count mismatch for tier {$expectedTier->value}");

        if ($caps->maxPresentationVideos >= 1) {
            $this->assertNotNull($response->json('data.presentation_video_url'), 'Producer presentation video should be visible at tier '.$expectedTier->value);
        } else {
            $this->assertNull($response->json('data.presentation_video_url'), 'Producer presentation video should be masked at tier '.$expectedTier->value);
        }

        // Round 2 F1: drop dead guard (Free quota is 1, branch never goes false).
        $this->assertProducerResponseShape($response);
    }

    private function assertOwnerLens(User $faceUser, FaceSubscriptionTier $expectedTier): void
    {
        $caps = app(FaceEntitlementService::class)->capabilitiesForTier($expectedTier);

        $response = $this->actingAs($faceUser)->getJson('/api/v1/face/profile');

        $response->assertOk()
            ->assertJsonCount(self::SEEDED_ALBUM_PHOTO_COUNT, 'data.photos');

        $photos = collect($response->json('data.photos'))->keyBy('position');

        // Round 2 C4: assert photo positions are exactly 1..N — defends against an off-by-one
        // if the resource ever emits 0-indexed positions ($pos > $quota would silently mis-fire).
        $this->assertSame(
            range(1, self::SEEDED_ALBUM_PHOTO_COUNT),
            $photos->keys()->sort()->values()->all(),
            'Photo positions must be 1..'.self::SEEDED_ALBUM_PHOTO_COUNT.' (1-indexed contiguous).'
        );

        foreach ($photos as $position => $photo) {
            $expectedLocked = $position > $caps->maxAlbumPhotos;
            $this->assertSame($expectedLocked, $photo['is_locked'], "Photo position {$position} lock mismatch for tier {$expectedTier->value}");
            if ($expectedLocked) {
                $this->assertSame('quota_exceeded', $photo['lock_reason']);
            } else {
                $this->assertNull($photo['lock_reason']);
            }
        }

        // Videos: SEEDED_VIDEO_COUNT stored (acting + UGC); each is locked iff position > tier quota.
        $videos = collect($response->json('data.videos'));
        $this->assertCount(self::SEEDED_VIDEO_COUNT, $videos);

        // Round 2 C4: assert acting positions are exactly [1, 2] and ugc positions [1] before
        // the position>quota check fires — protects against 0-indexed regressions.
        $actingPositions = $videos->where('type', 'acting')->pluck('position')->sort()->values()->all();
        $ugcPositions = $videos->where('type', 'ugc')->pluck('position')->sort()->values()->all();
        $this->assertSame(range(1, self::SEEDED_ACTING_VIDEO_COUNT), $actingPositions, 'Acting video positions must be 1..SEEDED_ACTING_VIDEO_COUNT (1-indexed contiguous).');
        $this->assertSame(range(1, self::SEEDED_UGC_VIDEO_COUNT), $ugcPositions, 'UGC video positions must be 1..SEEDED_UGC_VIDEO_COUNT (1-indexed contiguous).');

        foreach ($videos as $video) {
            $quota = $video['type'] === 'acting'
                ? $caps->maxActingVideos
                : $caps->maxUgcVideos;
            $expectedLocked = $video['position'] > $quota;
            $this->assertSame(
                $expectedLocked,
                $video['is_locked'],
                "Video type={$video['type']} position={$video['position']} lock mismatch for tier {$expectedTier->value}"
            );
            // Round 2 C5: assert lock_reason on videos with the same discipline as photos —
            // catches a regression flipping 'tier_below_required' ↔ 'quota_exceeded' silently.
            if ($expectedLocked) {
                $expectedReason = $quota < 1 ? 'tier_below_required' : 'quota_exceeded';
                $this->assertSame(
                    $expectedReason,
                    $video['lock_reason'],
                    "Video type={$video['type']} position={$video['position']} lock_reason mismatch for tier {$expectedTier->value} (quota={$quota})"
                );
            } else {
                $this->assertNull($video['lock_reason'], "Video type={$video['type']} position={$video['position']} must have null lock_reason when visible.");
            }
        }

        // Presentation video: privileged viewers always see the URL, with explicit lock metadata.
        $this->assertNotNull($response->json('data.presentation_video_url'));
        // Round 2 C8: pin filename to detect cross-tenant URL leaks.
        $this->assertStringContainsString(self::SEEDED_PRESENTATION_FILENAME, $response->json('data.presentation_video_url'));
        $expectedPresentationLocked = $caps->maxPresentationVideos < 1;
        $this->assertSame($expectedPresentationLocked, $response->json('data.is_presentation_video_locked'));
        if ($expectedPresentationLocked) {
            $this->assertSame('tier_below_required', $response->json('data.presentation_video_lock_reason'));
        } else {
            $this->assertNull($response->json('data.presentation_video_lock_reason'));
        }
    }

    private function assertAdminLens(Face $face, FaceSubscriptionTier $expectedTier): void
    {
        $caps = app(FaceEntitlementService::class)->capabilitiesForTier($expectedTier);
        $token = $this->adminToken();

        $response = $this->withToken($token)->getJson("/api/v1/admin/faces/{$face->uuid}");

        $response->assertOk()
            ->assertJsonCount(self::SEEDED_ALBUM_PHOTO_COUNT, 'data.photos')
            ->assertJsonPath('data.subscription_tier', $expectedTier->value);

        $photos = collect($response->json('data.photos'))->keyBy('position');
        foreach ($photos as $position => $photo) {
            $expectedLocked = $position > $caps->maxAlbumPhotos;
            $this->assertSame($expectedLocked, $photo['is_locked'], "Admin photo position {$position} lock mismatch for tier {$expectedTier->value}");
        }

        $videos = collect($response->json('data.videos'));
        $this->assertCount(self::SEEDED_VIDEO_COUNT, $videos);
        foreach ($videos as $video) {
            $quota = $video['type'] === 'acting'
                ? $caps->maxActingVideos
                : $caps->maxUgcVideos;
            $expectedLocked = $video['position'] > $quota;
            $this->assertSame(
                $expectedLocked,
                $video['is_locked'],
                "Admin video type={$video['type']} position={$video['position']} lock mismatch for tier {$expectedTier->value}"
            );
            // Round 2 C5: assert lock_reason parity (mirrors assertOwnerLens).
            if ($expectedLocked) {
                $expectedReason = $quota < 1 ? 'tier_below_required' : 'quota_exceeded';
                $this->assertSame(
                    $expectedReason,
                    $video['lock_reason'],
                    "Admin video type={$video['type']} position={$video['position']} lock_reason mismatch for tier {$expectedTier->value} (quota={$quota})"
                );
            }
        }

        // Presentation video: admin sees URL + lock metadata mirroring owner lens
        $this->assertNotNull($response->json('data.presentation_video_url'));
        // Round 2 C8: pin filename to detect cross-tenant URL leaks.
        $this->assertStringContainsString(self::SEEDED_PRESENTATION_FILENAME, $response->json('data.presentation_video_url'));
        $expectedPresentationLocked = $caps->maxPresentationVideos < 1;
        $this->assertSame($expectedPresentationLocked, $response->json('data.is_presentation_video_locked'));
    }

    // -------------------------------------------------------------------
    // Free no-row baseline — 1 cell
    // -------------------------------------------------------------------

    public function test_free_no_row_face_falls_back_to_free_quota_on_every_lens(): void
    {
        ['face' => $face, 'user' => $faceUser] = $this->seedFace(null, null);

        $this->assertPublicLens($face, FaceSubscriptionTier::Free);
        $this->assertProducerLens($face, FaceSubscriptionTier::Free);
        $this->assertOwnerLens($faceUser, FaceSubscriptionTier::Free);
        $this->assertAdminLens($face, FaceSubscriptionTier::Free);
    }

    // -------------------------------------------------------------------
    // Paid × non-active matrix — 12 cells (3 plans × 4 non-active states).
    // Every cell asserts fall-back to Free quota on every lens. The
    // `cancelled` cells additionally pin that a future `expires_at` does
    // NOT bypass the entitlement service.
    // -------------------------------------------------------------------

    public function test_starter_pending_payment_falls_back_to_free_quota_on_every_lens(): void
    {
        ['face' => $face, 'user' => $faceUser] = $this->seedFace(FaceSubscriptionPlan::Starter, 'pendingPayment');

        $this->assertPublicLens($face, FaceSubscriptionTier::Free);
        $this->assertProducerLens($face, FaceSubscriptionTier::Free);
        $this->assertOwnerLens($faceUser, FaceSubscriptionTier::Free);
        $this->assertAdminLens($face, FaceSubscriptionTier::Free);
    }

    public function test_starter_expired_falls_back_to_free_quota_on_every_lens(): void
    {
        ['face' => $face, 'user' => $faceUser] = $this->seedFace(FaceSubscriptionPlan::Starter, 'expired');

        $this->assertPublicLens($face, FaceSubscriptionTier::Free);
        $this->assertProducerLens($face, FaceSubscriptionTier::Free);
        $this->assertOwnerLens($faceUser, FaceSubscriptionTier::Free);
        $this->assertAdminLens($face, FaceSubscriptionTier::Free);
    }

    public function test_starter_cancelled_falls_back_to_free_quota_on_every_lens(): void
    {
        ['face' => $face, 'user' => $faceUser, 'sub' => $sub] = $this->seedFace(FaceSubscriptionPlan::Starter, 'cancelled');

        // Sanity-check: cancelled() factory state inherits the default future expires_at.
        $this->assertTrue($sub->expires_at->isFuture(), 'cancelled() factory expected to inherit future expires_at from definition()');

        $this->assertPublicLens($face, FaceSubscriptionTier::Free);
        $this->assertProducerLens($face, FaceSubscriptionTier::Free);
        $this->assertOwnerLens($faceUser, FaceSubscriptionTier::Free);
        $this->assertAdminLens($face, FaceSubscriptionTier::Free);
    }

    public function test_starter_failed_falls_back_to_free_quota_on_every_lens(): void
    {
        ['face' => $face, 'user' => $faceUser] = $this->seedFace(FaceSubscriptionPlan::Starter, 'failed');

        $this->assertPublicLens($face, FaceSubscriptionTier::Free);
        $this->assertProducerLens($face, FaceSubscriptionTier::Free);
        $this->assertOwnerLens($faceUser, FaceSubscriptionTier::Free);
        $this->assertAdminLens($face, FaceSubscriptionTier::Free);
    }

    public function test_pro_pending_payment_falls_back_to_free_quota_on_every_lens(): void
    {
        ['face' => $face, 'user' => $faceUser] = $this->seedFace(FaceSubscriptionPlan::Pro, 'pendingPayment');

        $this->assertPublicLens($face, FaceSubscriptionTier::Free);
        $this->assertProducerLens($face, FaceSubscriptionTier::Free);
        $this->assertOwnerLens($faceUser, FaceSubscriptionTier::Free);
        $this->assertAdminLens($face, FaceSubscriptionTier::Free);
    }

    public function test_pro_expired_falls_back_to_free_quota_on_every_lens(): void
    {
        ['face' => $face, 'user' => $faceUser] = $this->seedFace(FaceSubscriptionPlan::Pro, 'expired');

        $this->assertPublicLens($face, FaceSubscriptionTier::Free);
        $this->assertProducerLens($face, FaceSubscriptionTier::Free);
        $this->assertOwnerLens($faceUser, FaceSubscriptionTier::Free);
        $this->assertAdminLens($face, FaceSubscriptionTier::Free);
    }

    public function test_pro_cancelled_falls_back_to_free_quota_on_every_lens(): void
    {
        ['face' => $face, 'user' => $faceUser, 'sub' => $sub] = $this->seedFace(FaceSubscriptionPlan::Pro, 'cancelled');

        $this->assertTrue($sub->expires_at->isFuture(), 'cancelled() factory expected to inherit future expires_at from definition()');

        $this->assertPublicLens($face, FaceSubscriptionTier::Free);
        $this->assertProducerLens($face, FaceSubscriptionTier::Free);
        $this->assertOwnerLens($faceUser, FaceSubscriptionTier::Free);
        $this->assertAdminLens($face, FaceSubscriptionTier::Free);
    }

    public function test_pro_failed_falls_back_to_free_quota_on_every_lens(): void
    {
        ['face' => $face, 'user' => $faceUser] = $this->seedFace(FaceSubscriptionPlan::Pro, 'failed');

        $this->assertPublicLens($face, FaceSubscriptionTier::Free);
        $this->assertProducerLens($face, FaceSubscriptionTier::Free);
        $this->assertOwnerLens($faceUser, FaceSubscriptionTier::Free);
        $this->assertAdminLens($face, FaceSubscriptionTier::Free);
    }

    public function test_elite_pending_payment_falls_back_to_free_quota_on_every_lens(): void
    {
        ['face' => $face, 'user' => $faceUser] = $this->seedFace(FaceSubscriptionPlan::Elite, 'pendingPayment');

        $this->assertPublicLens($face, FaceSubscriptionTier::Free);
        $this->assertProducerLens($face, FaceSubscriptionTier::Free);
        $this->assertOwnerLens($faceUser, FaceSubscriptionTier::Free);
        $this->assertAdminLens($face, FaceSubscriptionTier::Free);
    }

    public function test_elite_expired_falls_back_to_free_quota_on_every_lens(): void
    {
        ['face' => $face, 'user' => $faceUser] = $this->seedFace(FaceSubscriptionPlan::Elite, 'expired');

        $this->assertPublicLens($face, FaceSubscriptionTier::Free);
        $this->assertProducerLens($face, FaceSubscriptionTier::Free);
        $this->assertOwnerLens($faceUser, FaceSubscriptionTier::Free);
        $this->assertAdminLens($face, FaceSubscriptionTier::Free);
    }

    public function test_elite_cancelled_falls_back_to_free_quota_on_every_lens(): void
    {
        ['face' => $face, 'user' => $faceUser, 'sub' => $sub] = $this->seedFace(FaceSubscriptionPlan::Elite, 'cancelled');

        $this->assertTrue($sub->expires_at->isFuture(), 'cancelled() factory expected to inherit future expires_at from definition()');

        $this->assertPublicLens($face, FaceSubscriptionTier::Free);
        $this->assertProducerLens($face, FaceSubscriptionTier::Free);
        $this->assertOwnerLens($faceUser, FaceSubscriptionTier::Free);
        $this->assertAdminLens($face, FaceSubscriptionTier::Free);
    }

    public function test_elite_failed_falls_back_to_free_quota_on_every_lens(): void
    {
        ['face' => $face, 'user' => $faceUser] = $this->seedFace(FaceSubscriptionPlan::Elite, 'failed');

        $this->assertPublicLens($face, FaceSubscriptionTier::Free);
        $this->assertProducerLens($face, FaceSubscriptionTier::Free);
        $this->assertOwnerLens($faceUser, FaceSubscriptionTier::Free);
        $this->assertAdminLens($face, FaceSubscriptionTier::Free);
    }

    // -------------------------------------------------------------------
    // Chained renewal — Expired + new Active row resolves to the Active row's tier.
    // -------------------------------------------------------------------

    public function test_face_with_expired_then_active_renewal_resolves_to_active_tier(): void
    {
        ['face' => $face, 'user' => $faceUser] = $this->seedFace(null, null);

        FaceSubscription::factory()->elite()->expired()->create(['face_id' => $face->id]);
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $face->id]);

        // Refresh the in-memory model so any later in-test mutation guards see persisted state.
        // (Round 2 C7: the previous comment claimed this invalidates the entitlement service
        // relation cache — that was misleading. The service queries server-side on each lens
        // request, so fresh() is a no-op for behaviour. Kept as defensive bookkeeping only.)
        $face = $face->fresh();

        $this->assertPublicLens($face, FaceSubscriptionTier::Pro);
        $this->assertProducerLens($face, FaceSubscriptionTier::Pro);
        $this->assertOwnerLens($faceUser, FaceSubscriptionTier::Pro);
        $this->assertAdminLens($face, FaceSubscriptionTier::Pro);
    }

    // -------------------------------------------------------------------
    // Admin-featured boundary — `is_featured` is a listing flag, NOT an entitlement.
    // -------------------------------------------------------------------

    public function test_public_lens_does_not_grant_tier_to_admin_manually_featured_free_face(): void
    {
        ['face' => $face] = $this->seedFeaturedFace(null, null);

        $this->assertPublicLens($face, FaceSubscriptionTier::Free);
    }

    public function test_public_lens_admin_featured_with_active_elite_subscription_sees_elite_quota(): void
    {
        ['face' => $face] = $this->seedFeaturedFace(FaceSubscriptionPlan::Elite);

        $this->assertPublicLens($face, FaceSubscriptionTier::Elite);
    }

    // -------------------------------------------------------------------
    // Admin cancellation no-storage-delete pin — extended with UGC video seed.
    // -------------------------------------------------------------------

    public function test_admin_cancel_does_not_delete_album_photo_acting_video_presentation_video_or_ugc_video_files(): void
    {
        $face = Face::factory()->create([
            'presentation_video' => 'presentation.mp4',
            'presentation_video_thumbnail' => 'presentation-thumb.jpg',
        ]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        FacePhoto::factory()->createSequentialForFace($face, 4);
        FaceVideo::factory()->acting()->create([
            'face_id' => $face->id,
            'filename' => 'cancel-test.mp4',
            'thumbnail' => 'cancel-test-thumb.jpg',
        ]);
        FaceVideo::factory()->ugc()->create([
            'face_id' => $face->id,
            'filename' => 'cancel-ugc.mp4',
            'thumbnail' => 'cancel-ugc-thumb.jpg',
        ]);

        foreach ($face->fresh()->photos as $photo) {
            Storage::disk('public')->put('avatars/faces/albums/'.$photo->filename, 'photo content');
        }
        Storage::disk('public')->put('videos/faces/acting/cancel-test.mp4', 'acting content');
        Storage::disk('public')->put('videos/faces/acting/thumbnails/cancel-test-thumb.jpg', 'acting thumb');
        Storage::disk('public')->put('videos/faces/ugc/cancel-ugc.mp4', 'ugc content');
        Storage::disk('public')->put('videos/faces/ugc/thumbnails/cancel-ugc-thumb.jpg', 'ugc thumb');
        Storage::disk('public')->put('videos/faces/presentation/presentation.mp4', 'presentation content');
        Storage::disk('public')->put('videos/faces/presentation/thumbnails/presentation-thumb.jpg', 'presentation thumb');

        $subscription = FaceSubscription::factory()->elite()->active()->create(['face_id' => $face->id]);

        $adminToken = $this->adminToken();

        $response = $this->withToken($adminToken)
            ->postJson("/api/v1/admin/face-subscriptions/{$subscription->uuid}/cancel", [
                'notes' => 'Regression coverage cancel — verify no media side effect on Elite UGC + acting + presentation',
            ]);

        $response->assertOk()->assertJsonPath('data.status', 'cancelled');

        foreach ($face->fresh()->photos as $photo) {
            Storage::disk('public')->assertExists('avatars/faces/albums/'.$photo->filename);
        }
        Storage::disk('public')->assertExists('videos/faces/acting/cancel-test.mp4');
        Storage::disk('public')->assertExists('videos/faces/acting/thumbnails/cancel-test-thumb.jpg');
        Storage::disk('public')->assertExists('videos/faces/ugc/cancel-ugc.mp4');
        Storage::disk('public')->assertExists('videos/faces/ugc/thumbnails/cancel-ugc-thumb.jpg');
        Storage::disk('public')->assertExists('videos/faces/presentation/presentation.mp4');
        Storage::disk('public')->assertExists('videos/faces/presentation/thumbnails/presentation-thumb.jpg');

        $this->assertSame(4, FacePhoto::where('face_id', $face->id)->count());
        // Round 2 C1: per-type granularity + filename pinning so a regression that flips
        // acting↔ugc rows or replaces them with phantoms cannot pass green on count alone.
        $this->assertSame(1, FaceVideo::where('face_id', $face->id)->where('type', 'acting')->count(), 'Admin cancel must not delete or replace the acting video row.');
        $this->assertSame(1, FaceVideo::where('face_id', $face->id)->where('type', 'ugc')->count(), 'Admin cancel must not delete or replace the UGC video row.');
        $this->assertSame(
            ['cancel-test.mp4', 'cancel-ugc.mp4'],
            FaceVideo::where('face_id', $face->id)->orderBy('id')->pluck('filename')->all(),
            'Admin cancel must preserve the exact stored video filenames.'
        );
        $this->assertSame('presentation.mp4', $face->fresh()->presentation_video);
    }

    // -------------------------------------------------------------------
    // UGC quota crossing — Pro (0 UGC) → Elite (1 UGC) flip surfaces the
    // most product-meaningful FP-2 capability transition.
    // -------------------------------------------------------------------

    public function test_ugc_video_quota_flips_from_zero_to_one_only_at_elite_tier(): void
    {
        ['face' => $face] = $this->seedFace(FaceSubscriptionPlan::Pro);

        // Pro: 4 photos + 1 presentation + 1 acting + 0 UGC = 1 visible video
        $this->assertPublicLens($face, FaceSubscriptionTier::Pro);

        // Upgrade in-place: same Face, plan flipped to Elite
        FaceSubscription::query()
            ->where('face_id', $face->id)
            ->update(['plan' => FaceSubscriptionPlan::Elite->value]);

        $face = $face->fresh();

        // Elite: 6 photos + 1 presentation + 2 acting + 1 UGC = 3 visible videos
        $this->assertPublicLens($face, FaceSubscriptionTier::Elite);
    }
}
