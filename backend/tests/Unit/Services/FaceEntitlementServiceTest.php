<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\FaceSubscriptionTier;
use App\Enums\UgcSuspensionAppealStatus;
use App\Enums\UgcSuspensionReason;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\UgcSuspension;
use App\Services\FaceEntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FaceEntitlementServiceTest extends TestCase
{
    use RefreshDatabase;

    private FaceEntitlementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FaceEntitlementService;
    }

    // ===================================================================
    // Capabilities matrix — one test per tier, all 9 fields asserted
    // ===================================================================

    public function test_capabilities_for_free_tier_returns_free_matrix(): void
    {
        $face = Face::factory()->create();

        $caps = $this->service->capabilities($face);

        $this->assertSame(FaceSubscriptionTier::Free, $caps->tier);
        $this->assertSame(1, $caps->maxAlbumPhotos);
        $this->assertSame(0, $caps->maxPresentationVideos);
        $this->assertSame(0, $caps->maxActingVideos);
        $this->assertSame(0, $caps->maxUgcVideos);
        $this->assertFalse($caps->ugcAccess);
        $this->assertSame(0.15, $caps->commissionRate); // FP-3.1a: Découverte commission raised 0.10 → 0.15
        $this->assertSame(4, $caps->sortPriority);
        $this->assertFalse($caps->hasEliteBadge);
    }

    public function test_capabilities_for_starter_tier_returns_starter_matrix(): void
    {
        $face = Face::factory()->create();
        FaceSubscription::factory()->starter()->active()->create(['face_id' => $face->id]);

        $caps = $this->service->capabilities($face);

        $this->assertSame(FaceSubscriptionTier::Starter, $caps->tier);
        $this->assertSame(2, $caps->maxAlbumPhotos);
        $this->assertSame(1, $caps->maxPresentationVideos);
        $this->assertSame(0, $caps->maxActingVideos);
        $this->assertSame(0, $caps->maxUgcVideos);
        $this->assertTrue($caps->ugcAccess);
        $this->assertSame(0.10, $caps->commissionRate);
        $this->assertSame(3, $caps->sortPriority);
        $this->assertFalse($caps->hasEliteBadge);
    }

    public function test_capabilities_for_pro_tier_returns_pro_matrix(): void
    {
        $face = Face::factory()->create();
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $face->id]);

        $caps = $this->service->capabilities($face);

        $this->assertSame(FaceSubscriptionTier::Pro, $caps->tier);
        $this->assertSame(4, $caps->maxAlbumPhotos);
        $this->assertSame(1, $caps->maxPresentationVideos);
        $this->assertSame(1, $caps->maxActingVideos);
        $this->assertSame(0, $caps->maxUgcVideos);
        $this->assertTrue($caps->ugcAccess);
        $this->assertSame(0.10, $caps->commissionRate);
        $this->assertSame(2, $caps->sortPriority);
        $this->assertFalse($caps->hasEliteBadge);
    }

    public function test_capabilities_for_elite_tier_returns_elite_matrix(): void
    {
        $face = Face::factory()->create();
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $face->id]);

        $caps = $this->service->capabilities($face);

        $this->assertSame(FaceSubscriptionTier::Elite, $caps->tier);
        $this->assertSame(6, $caps->maxAlbumPhotos);
        $this->assertSame(1, $caps->maxPresentationVideos);
        $this->assertSame(2, $caps->maxActingVideos);
        $this->assertSame(1, $caps->maxUgcVideos);
        $this->assertTrue($caps->ugcAccess);
        $this->assertSame(0.05, $caps->commissionRate);
        $this->assertSame(1, $caps->sortPriority);
        $this->assertTrue($caps->hasEliteBadge);
    }

    public function test_capabilities_rejects_invalid_commission_rate_config(): void
    {
        $configKey = 'face_subscription_tiers.tiers.free.capabilities.commission_rate';
        $originalRate = config($configKey);

        try {
            // 1.0 (100 %) is rejected: it would zero out the Face's earnings.
            foreach ([15, 1.0, -0.05, NAN, INF, -INF, 'not-a-rate'] as $invalidRate) {
                config()->set($configKey, $invalidRate);

                try {
                    (new FaceEntitlementService)->capabilitiesForTier(FaceSubscriptionTier::Free);
                    $this->fail('Invalid commission_rate ['.var_export($invalidRate, true).'] should fail loudly.');
                } catch (\RuntimeException $exception) {
                    $this->assertStringContainsString('Invalid commission_rate', $exception->getMessage());
                }
            }

            // 0.0 (no commission) stays valid — the lower bound is inclusive.
            config()->set($configKey, 0.0);
            $caps = (new FaceEntitlementService)->capabilitiesForTier(FaceSubscriptionTier::Free);
            $this->assertSame(0.0, $caps->commissionRate);
        } finally {
            config()->set($configKey, $originalRate);
        }
    }

    // ===================================================================
    // Free fallback — no row and every non-granting subscription state
    // ===================================================================

    public function test_face_with_no_subscription_row_resolves_to_free(): void
    {
        $face = Face::factory()->create();

        $this->assertSame(FaceSubscriptionTier::Free, $this->service->capabilities($face)->tier);
    }

    public function test_pending_payment_subscription_resolves_to_free(): void
    {
        $face = Face::factory()->create();
        FaceSubscription::factory()->pendingPayment()->create(['face_id' => $face->id]);

        $this->assertSame(FaceSubscriptionTier::Free, $this->service->capabilities($face)->tier);
    }

    public function test_expired_subscription_resolves_to_free(): void
    {
        $face = Face::factory()->create();
        FaceSubscription::factory()->expired()->create(['face_id' => $face->id]);

        $this->assertSame(FaceSubscriptionTier::Free, $this->service->capabilities($face)->tier);
    }

    public function test_cancelled_subscription_resolves_to_free(): void
    {
        $face = Face::factory()->create();
        FaceSubscription::factory()->cancelled()->create(['face_id' => $face->id]);

        $this->assertSame(FaceSubscriptionTier::Free, $this->service->capabilities($face)->tier);
    }

    public function test_failed_subscription_resolves_to_free(): void
    {
        $face = Face::factory()->create();
        FaceSubscription::factory()->failed()->create(['face_id' => $face->id]);

        $this->assertSame(FaceSubscriptionTier::Free, $this->service->capabilities($face)->tier);
    }

    public function test_active_status_with_past_expiry_resolves_to_free(): void
    {
        $face = Face::factory()->create();
        FaceSubscription::factory()->elite()->active()->create([
            'face_id' => $face->id,
            'expires_at' => now()->subDay(),
        ]);

        $this->assertSame(FaceSubscriptionTier::Free, $this->service->capabilities($face)->tier);
    }

    // ===================================================================
    // Multiple-subscription resolution
    // ===================================================================

    public function test_active_paid_subscription_wins_over_stale_historical_rows(): void
    {
        $face = Face::factory()->create();
        FaceSubscription::factory()->expired()->create(['face_id' => $face->id]);
        FaceSubscription::factory()->cancelled()->create(['face_id' => $face->id]);
        FaceSubscription::factory()->failed()->create(['face_id' => $face->id]);
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $face->id]);

        $this->assertSame(FaceSubscriptionTier::Elite, $this->service->capabilities($face)->tier);
    }

    public function test_stale_subscriptions_alone_resolve_to_free(): void
    {
        $face = Face::factory()->create();
        FaceSubscription::factory()->expired()->create(['face_id' => $face->id]);
        FaceSubscription::factory()->cancelled()->create(['face_id' => $face->id]);
        FaceSubscription::factory()->failed()->create(['face_id' => $face->id]);
        FaceSubscription::factory()->pendingPayment()->create(['face_id' => $face->id]);

        $this->assertSame(FaceSubscriptionTier::Free, $this->service->capabilities($face)->tier);
    }

    // ===================================================================
    // Eager-load awareness + memoization (NFR5)
    // ===================================================================

    public function test_capabilities_reads_preloaded_active_subscription_relation(): void
    {
        $face = Face::factory()->create();
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $face->id]);

        $faceWithRelation = Face::query()->with('activeSubscription')->findOrFail($face->id);

        $this->assertTrue($faceWithRelation->relationLoaded('activeSubscription'));
        $this->assertSame(
            FaceSubscriptionTier::Pro,
            $this->service->capabilities($faceWithRelation)->tier
        );
    }

    public function test_preloaded_path_and_query_path_agree(): void
    {
        $face = Face::factory()->create();
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $face->id]);

        $queryPathFace = Face::query()->findOrFail($face->id);
        $this->assertFalse($queryPathFace->relationLoaded('activeSubscription'));
        $queryCaps = $this->service->capabilities($queryPathFace);

        $preloadedFace = Face::query()->with('activeSubscription')->findOrFail($face->id);
        $this->assertTrue($preloadedFace->relationLoaded('activeSubscription'));
        $preloadedCaps = $this->service->capabilities($preloadedFace);

        $this->assertEquals($queryCaps, $preloadedCaps);
        $this->assertSame(FaceSubscriptionTier::Elite, $queryCaps->tier);
    }

    public function test_capabilities_is_memoized_on_the_same_face_object(): void
    {
        $face = Face::factory()->create();
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $face->id]);

        // Warm the memo — this first call hits the database.
        $this->service->capabilities($face);

        DB::enableQueryLog();
        $this->service->capabilities($face);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(0, $queries);
    }

    public function test_capabilities_memo_does_not_leak_between_destroyed_face_objects(): void
    {
        $eliteFaces = Face::factory()->count(120)->create();
        foreach ($eliteFaces as $face) {
            FaceSubscription::factory()->elite()->active()->create(['face_id' => $face->id]);
            $this->assertSame(FaceSubscriptionTier::Elite, $this->service->capabilities($face)->tier);
        }

        unset($face, $eliteFaces);
        gc_collect_cycles();

        $freeFace = Face::factory()->create();

        $this->assertSame(
            FaceSubscriptionTier::Free,
            $this->service->capabilities($freeFace)->tier,
            'A long-lived service must not reuse a stale memo entry when PHP reuses an object id.',
        );
    }

    public function test_capabilities_is_eager_load_aware_with_no_n_plus_one(): void
    {
        $faces = Face::factory()->count(5)->create();
        foreach ($faces as $face) {
            FaceSubscription::factory()->pro()->active()->create(['face_id' => $face->id]);
        }

        $loaded = Face::query()->with('activeSubscription')->get();

        DB::enableQueryLog();
        foreach ($loaded as $face) {
            $this->service->capabilities($face);
        }
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Every capabilities() call read the eager-loaded relation — zero queries.
        $this->assertCount(0, $queries);
    }

    // ===================================================================
    // canAccessUgc() — UGC gating (FR5, UGC story 2.1)
    // ===================================================================

    public function test_can_access_ugc_is_false_for_free_tier(): void
    {
        $face = Face::factory()->create();

        $this->assertFalse($this->service->canAccessUgc($face));
    }

    public function test_can_access_ugc_is_true_for_starter_pro_elite_active(): void
    {
        foreach (['starter', 'pro', 'elite'] as $tierState) {
            $face = Face::factory()->create();
            FaceSubscription::factory()->{$tierState}()->active()->create(['face_id' => $face->id]);

            $this->assertTrue(
                $this->service->canAccessUgc($face),
                "An active {$tierState} subscription must grant UGC access."
            );
        }
    }

    public function test_can_access_ugc_is_false_for_expired_subscription(): void
    {
        $face = Face::factory()->create();
        FaceSubscription::factory()->starter()->expired()->create(['face_id' => $face->id]);

        $this->assertFalse($this->service->canAccessUgc($face));
    }

    public function test_can_access_ugc_is_false_for_pending_payment_subscription(): void
    {
        $face = Face::factory()->create();
        FaceSubscription::factory()->pendingPayment()->create(['face_id' => $face->id]);

        $this->assertFalse($this->service->canAccessUgc($face));
    }

    public function test_can_access_ugc_is_false_for_cancelled_subscription(): void
    {
        $face = Face::factory()->create();
        FaceSubscription::factory()->starter()->cancelled()->create(['face_id' => $face->id]);

        $this->assertFalse($this->service->canAccessUgc($face));
    }

    public function test_can_access_ugc_is_false_for_failed_subscription(): void
    {
        $face = Face::factory()->create();
        FaceSubscription::factory()->starter()->failed()->create(['face_id' => $face->id]);

        $this->assertFalse($this->service->canAccessUgc($face));
    }

    public function test_can_access_ugc_is_false_for_active_status_with_past_expiry(): void
    {
        $face = Face::factory()->create();
        FaceSubscription::factory()->elite()->active()->create([
            'face_id' => $face->id,
            'expires_at' => now()->subDay(),
        ]);

        $this->assertFalse($this->service->canAccessUgc($face));
    }

    public function test_can_access_ugc_is_false_when_suspended_even_if_subscribed(): void
    {
        $face = Face::factory()->create();
        FaceSubscription::factory()->starter()->active()->create(['face_id' => $face->id]);

        $suspendedService = new class extends FaceEntitlementService
        {
            public function isUgcSuspended(Face $face): bool
            {
                return true;
            }
        };

        $this->assertTrue($this->service->canAccessUgc($face));
        $this->assertFalse($suspendedService->canAccessUgc($face));
    }

    public function test_is_ugc_suspended_defaults_to_false(): void
    {
        $face = Face::factory()->create();

        $this->assertFalse($this->service->isUgcSuspended($face));
    }

    public function test_is_ugc_suspended_true_with_active_row(): void
    {
        $face = Face::factory()->create();
        UgcSuspension::create([
            'face_id' => $face->id,
            'shipment_id' => null,
            'reason' => UgcSuspensionReason::UnboxingDeadlineMissed,
            'appeal_status' => UgcSuspensionAppealStatus::None,
            'suspended_at' => now(),
        ]);

        $this->assertTrue($this->service->isUgcSuspended($face));
    }

    public function test_is_ugc_suspended_false_after_reactivation(): void
    {
        $face = Face::factory()->create();
        UgcSuspension::create([
            'face_id' => $face->id,
            'shipment_id' => null,
            'reason' => UgcSuspensionReason::UnboxingDeadlineMissed,
            'appeal_status' => UgcSuspensionAppealStatus::None,
            'suspended_at' => now()->subDays(2),
            'reactivated_at' => now(),
        ]);

        $this->assertFalse($this->service->isUgcSuspended($face));
    }

    // ===================================================================
    // capabilitiesForTier() — explicit per-tier matrix lookup (FP-2.3)
    // ===================================================================

    public function test_capabilities_for_tier_returns_correct_matrix_for_every_tier(): void
    {
        $cases = [
            [FaceSubscriptionTier::Free, 1, 0, 0, 0, false, 0.15, 4, false], // FP-3.1a: Découverte 0.10 → 0.15
            [FaceSubscriptionTier::Starter, 2, 1, 0, 0, true, 0.10, 3, false],
            [FaceSubscriptionTier::Pro, 4, 1, 1, 0, true, 0.10, 2, false],
            [FaceSubscriptionTier::Elite, 6, 1, 2, 1, true, 0.05, 1, true],
        ];

        foreach ($cases as [$tier, $photos, $pres, $acting, $ugc, $ugcAccess, $commission, $sort, $badge]) {
            $caps = $this->service->capabilitiesForTier($tier);

            $this->assertSame($tier, $caps->tier);
            $this->assertSame($photos, $caps->maxAlbumPhotos);
            $this->assertSame($pres, $caps->maxPresentationVideos);
            $this->assertSame($acting, $caps->maxActingVideos);
            $this->assertSame($ugc, $caps->maxUgcVideos);
            $this->assertSame($ugcAccess, $caps->ugcAccess);
            $this->assertSame($commission, $caps->commissionRate);
            $this->assertSame($sort, $caps->sortPriority);
            $this->assertSame($badge, $caps->hasEliteBadge);
        }
    }
}
