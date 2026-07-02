<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\FaceSubscriptionStatus;
use App\Models\Face;
use App\Models\FacePhoto;
use App\Models\FaceSubscription;
use App\Models\FaceVideo;
use App\Models\User;
use App\Services\FaceMediaRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class FaceMediaRetentionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeFaceWithUser(): Face
    {
        $face = Face::factory()->create();
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        return $face;
    }

    public function test_retention_anchor_is_null_for_face_with_no_paid_termination(): void
    {
        $face = $this->makeFaceWithUser();

        FaceSubscription::factory()->pro()->active()->create([
            'face_id' => $face->id,
        ]);

        $service = $this->app->make(FaceMediaRetentionService::class);

        $this->assertNull($service->retentionAnchor($face));
        $this->assertNull($service->retentionUntil($face));
    }

    public function test_retention_anchor_returns_expires_at_for_single_expired_row(): void
    {
        $face = $this->makeFaceWithUser();

        $expiredAt = now()->subDays(30);
        $expired = FaceSubscription::factory()->pro()->expired()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subYear()->subDays(30),
            'expires_at' => $expiredAt,
        ]);

        $service = $this->app->make(FaceMediaRetentionService::class);

        $anchor = $service->retentionAnchor($face);
        $this->assertNotNull($anchor);
        $this->assertTrue($anchor->equalTo($expired->fresh()->expires_at));
    }

    public function test_retention_anchor_uses_preloaded_subscriptions_relation_without_querying(): void
    {
        $face = $this->makeFaceWithUser();

        $expired = FaceSubscription::factory()->pro()->expired()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subYear()->subDays(30),
            'expires_at' => now()->subDays(30),
        ]);

        $loadedFace = Face::query()->with('subscriptions')->findOrFail($face->id);
        $service = $this->app->make(FaceMediaRetentionService::class);

        DB::enableQueryLog();
        $anchor = $service->retentionAnchor($loadedFace);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertNotNull($anchor);
        $this->assertTrue($anchor->equalTo($expired->fresh()->expires_at));
        $this->assertCount(0, $queries);
    }

    public function test_retention_anchor_returns_cancelled_at_for_single_cancelled_row(): void
    {
        $face = $this->makeFaceWithUser();

        $cancelledAt = now()->subDays(45);
        $futureExpiresAt = now()->addDays(300);
        $cancelled = FaceSubscription::factory()->pro()->cancelled()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subDays(65),
            'expires_at' => $futureExpiresAt,
            'cancelled_at' => $cancelledAt,
        ]);

        $service = $this->app->make(FaceMediaRetentionService::class);

        $anchor = $service->retentionAnchor($face);
        $this->assertNotNull($anchor);
        // Anchor MUST be cancelled_at, NOT the future expires_at — the
        // entitlement-loss moment is cancelled_at per the FP-2.5 chained-renewal
        // contract.
        $this->assertTrue($anchor->equalTo($cancelled->fresh()->cancelled_at));
        $this->assertFalse($anchor->equalTo($cancelled->fresh()->expires_at));
    }

    public function test_retention_anchor_returns_max_termination_date_across_multiple_rows(): void
    {
        $face = $this->makeFaceWithUser();

        // Sub A: Pro Cancelled ~1 year ago.
        FaceSubscription::factory()->pro()->cancelled()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subYear()->subDays(30),
            'expires_at' => now()->subMonths(11),
            'cancelled_at' => now()->subYear(),
        ]);

        // Sub B: Pro Expired 2 months ago.
        FaceSubscription::factory()->pro()->expired()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subYear()->subMonths(2),
            'expires_at' => now()->subMonths(2),
        ]);

        // Sub C: Starter Cancelled yesterday — the most recent termination.
        $subC = FaceSubscription::factory()->starter()->cancelled()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subMonths(2),
            'expires_at' => now()->addMonths(11),
            'cancelled_at' => now()->subDay(),
        ]);

        $service = $this->app->make(FaceMediaRetentionService::class);

        $anchor = $service->retentionAnchor($face);
        $this->assertNotNull($anchor);
        $this->assertTrue($anchor->equalTo($subC->fresh()->cancelled_at));
    }

    public function test_retention_anchor_ignores_pending_active_and_failed_rows(): void
    {
        $face = $this->makeFaceWithUser();

        // 3 non-terminated rows that must NOT pollute the anchor.
        FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $face->id,
        ]);
        FaceSubscription::factory()->pro()->active()->create([
            'face_id' => $face->id,
        ]);
        FaceSubscription::factory()->starter()->failed()->create([
            'face_id' => $face->id,
        ]);

        // 1 Cancelled-historical row from 2 years ago — the only valid anchor source.
        $historical = FaceSubscription::factory()->pro()->cancelled()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subYears(3),
            'expires_at' => now()->subYears(2),
            'cancelled_at' => now()->subYears(2),
        ]);

        $service = $this->app->make(FaceMediaRetentionService::class);

        $anchor = $service->retentionAnchor($face);
        $this->assertNotNull($anchor);
        $this->assertTrue($anchor->equalTo($historical->fresh()->cancelled_at));
    }

    public function test_retention_anchor_ignores_cancelled_pending_payment_rows(): void
    {
        $face = $this->makeFaceWithUser();

        $oldPaidTermination = FaceSubscription::factory()->pro()->expired()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subYear()->subDays(100),
            'expires_at' => now()->subDays(100),
        ]);

        FaceSubscription::factory()->pendingPayment()->create([
            'face_id' => $face->id,
            'status' => FaceSubscriptionStatus::Cancelled,
            'cancelled_at' => now()->subDay(),
        ]);

        $service = $this->app->make(FaceMediaRetentionService::class);

        $anchor = $service->retentionAnchor($face);
        $this->assertNotNull($anchor);
        $this->assertTrue(
            $anchor->equalTo($oldPaidTermination->fresh()->expires_at),
            'Cancelled pending-payment rows must not reset the paid media-retention anchor.',
        );
    }

    public function test_retention_until_is_anchor_plus_default_90_days(): void
    {
        $face = $this->makeFaceWithUser();

        $sub = FaceSubscription::factory()->pro()->expired()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subYear()->subDays(30),
            'expires_at' => now()->subDays(30),
        ]);

        $service = $this->app->make(FaceMediaRetentionService::class);

        $until = $service->retentionUntil($face);
        $this->assertNotNull($until);
        // Compare against the DB-read anchor + 90 days so both sides share the
        // same second-precision rounding (MySQL DATETIME drops sub-seconds).
        $expected = $sub->fresh()->expires_at->copy()->addDays(90);
        $this->assertTrue($until->equalTo($expected));
    }

    public function test_retention_until_respects_config_override(): void
    {
        config(['face_subscription_tiers.media_retention_days' => 30]);

        $face = $this->makeFaceWithUser();

        $sub = FaceSubscription::factory()->pro()->expired()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subYear()->subDays(30),
            'expires_at' => now()->subDays(30),
        ]);

        $service = $this->app->make(FaceMediaRetentionService::class);

        $until = $service->retentionUntil($face);
        $this->assertNotNull($until);
        // anchor + 30 days = (now - 30 days) + 30 days = now (boundary).
        $expected = $sub->fresh()->expires_at->copy()->addDays(30);
        $this->assertTrue($until->equalTo($expected));
    }

    public function test_over_quota_photos_returns_photos_above_current_tier_quota(): void
    {
        // Case 1: Pro-Expired Face → Free tier (max=1) with 4 photos → 3 over-quota.
        $face1 = $this->makeFaceWithUser();
        FaceSubscription::factory()->pro()->expired()->create([
            'face_id' => $face1->id,
            'expires_at' => now()->subDays(30),
        ]);
        FacePhoto::factory()->createSequentialForFace($face1, 4);

        $service = $this->app->make(FaceMediaRetentionService::class);

        $overQuota = $service->overQuotaPhotos($face1);
        $this->assertCount(3, $overQuota);
        $positions = $overQuota->pluck('position')->all();
        sort($positions);
        $this->assertSame([2, 3, 4], $positions);

        // Case 2: Élite-Active Face (max=6) with 6 photos → 0 over-quota.
        $face2 = $this->makeFaceWithUser();
        FaceSubscription::factory()->elite()->active()->create([
            'face_id' => $face2->id,
        ]);
        FacePhoto::factory()->createSequentialForFace($face2, 6);

        $this->assertCount(0, $service->overQuotaPhotos($face2));
    }

    public function test_over_quota_acting_and_ugc_videos_filter_by_type_and_position(): void
    {
        // Case 1: Élite-Expired → Free (max_acting=0, max_ugc=0) with 2 acting + 1 ugc.
        $face1 = $this->makeFaceWithUser();
        FaceSubscription::factory()->elite()->expired()->create([
            'face_id' => $face1->id,
            'expires_at' => now()->subDays(30),
        ]);
        FaceVideo::factory()->acting()->position(1)->create(['face_id' => $face1->id]);
        FaceVideo::factory()->acting()->position(2)->create(['face_id' => $face1->id]);
        FaceVideo::factory()->ugc()->position(1)->create(['face_id' => $face1->id]);

        $service = $this->app->make(FaceMediaRetentionService::class);

        $this->assertCount(2, $service->overQuotaActingVideos($face1));
        $this->assertCount(1, $service->overQuotaUgcVideos($face1));

        // Case 2: Active Pro (max_acting=1, max_ugc=0) with 2 acting + 1 ugc → 1 acting + 1 ugc over.
        $face2 = $this->makeFaceWithUser();
        FaceSubscription::factory()->pro()->active()->create([
            'face_id' => $face2->id,
        ]);
        FaceVideo::factory()->acting()->position(1)->create(['face_id' => $face2->id]);
        FaceVideo::factory()->acting()->position(2)->create(['face_id' => $face2->id]);
        FaceVideo::factory()->ugc()->position(1)->create(['face_id' => $face2->id]);

        $this->assertCount(1, $service->overQuotaActingVideos($face2));
        $this->assertSame(2, $service->overQuotaActingVideos($face2)->first()?->position);
        $this->assertCount(1, $service->overQuotaUgcVideos($face2));
    }

    public function test_media_pending_purge_is_empty_within_window_and_populated_after(): void
    {
        $face = $this->makeFaceWithUser();

        FaceSubscription::factory()->pro()->expired()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subYear()->subDays(89),
            'expires_at' => now()->subDays(89),
        ]);
        FacePhoto::factory()->createSequentialForFace($face, 4);

        $service = $this->app->make(FaceMediaRetentionService::class);

        // Within window (89d < 90d).
        $inventory = $service->mediaPendingPurge($face);
        $this->assertCount(0, $inventory['photos']);
        $this->assertCount(0, $inventory['acting_videos']);
        $this->assertCount(0, $inventory['ugc_videos']);

        // Travel +2 days → now >= anchor + 90d (boundary crossed).
        $this->travelTo(now()->addDays(2));

        $inventory = $service->mediaPendingPurge($face);
        $this->assertCount(3, $inventory['photos']);
        $this->assertCount(0, $inventory['acting_videos']);
        $this->assertCount(0, $inventory['ugc_videos']);
    }

    public function test_media_pending_purge_at_exact_boundary_purges(): void
    {
        // Pin the boundary contract documented in mediaPendingPurge():
        // when now() === retentionUntil, the window IS considered elapsed
        // (lt-not-lte semantics). Without this test, swapping lt for lte
        // would silently flip the boundary behavior.
        $face = $this->makeFaceWithUser();

        $anchorAt = now()->subDays(90); // anchor + 90 days = now (exact boundary).
        FaceSubscription::factory()->pro()->expired()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subYear()->subDays(90),
            'expires_at' => $anchorAt,
        ]);
        FacePhoto::factory()->createSequentialForFace($face, 4);

        $service = $this->app->make(FaceMediaRetentionService::class);

        // Freeze time at the exact retentionUntil moment.
        $this->travelTo($service->retentionUntil($face));

        $inventory = $service->mediaPendingPurge($face);
        $this->assertCount(
            3,
            $inventory['photos'],
            'At exact boundary (now() === retentionUntil), window must be considered elapsed.',
        );
    }

    public function test_retention_anchor_warns_when_cancelled_row_has_null_cancelled_at(): void
    {
        $face = $this->makeFaceWithUser();

        // Invariant violation: Cancelled row with null cancelled_at. Cannot use
        // the factory's ->cancelled() state (it sets cancelled_at), so build the
        // raw enum status directly.
        $invalid = FaceSubscription::factory()->pro()->create([
            'face_id' => $face->id,
            'status' => FaceSubscriptionStatus::Cancelled,
            'starts_at' => now()->subDays(120),
            'expires_at' => now()->addDays(245),
            'cancelled_at' => null,
        ]);

        // A second well-formed Expired row gives a legitimate anchor so we can
        // verify the bad row is logged AND excluded (anchor falls back to it).
        $valid = FaceSubscription::factory()->starter()->expired()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subYear()->subDays(60),
            'expires_at' => now()->subDays(60),
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context) use ($face, $invalid): bool {
                return $message === 'FaceSubscription invariant violation: Cancelled row has null cancelled_at'
                    && ($context['face_id'] ?? null) === $face->getKey()
                    && ($context['face_subscription_id'] ?? null) === $invalid->getKey();
            });

        $service = $this->app->make(FaceMediaRetentionService::class);

        $anchor = $service->retentionAnchor($face);
        $this->assertNotNull($anchor, 'Anchor must fall back to the well-formed Expired row.');
        $this->assertTrue($anchor->equalTo($valid->fresh()->expires_at));
    }

    public function test_retention_anchor_warns_when_expired_row_has_null_expires_at(): void
    {
        $face = $this->makeFaceWithUser();

        $invalid = FaceSubscription::factory()->pro()->create([
            'face_id' => $face->id,
            'status' => FaceSubscriptionStatus::Expired,
            'starts_at' => now()->subYear(),
            'expires_at' => null,
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context) use ($face, $invalid): bool {
                return $message === 'FaceSubscription invariant violation: Expired row has null expires_at'
                    && ($context['face_id'] ?? null) === $face->getKey()
                    && ($context['face_subscription_id'] ?? null) === $invalid->getKey();
            });

        $service = $this->app->make(FaceMediaRetentionService::class);

        // Only invariant-violating row on the Face → anchor null.
        $this->assertNull($service->retentionAnchor($face));
    }
}
