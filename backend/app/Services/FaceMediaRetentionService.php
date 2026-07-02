<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FaceSubscriptionStatus;
use App\Enums\FaceVideoType;
use App\Models\Face;
use App\Models\FacePhoto;
use App\Models\FaceSubscription;
use App\Models\FaceVideo;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Computes the 90-day post-termination media retention window for a Face
 * (FEAT-FP2-FR14). The retention anchor is derived from the most recent
 * paid-subscription termination event (Cancelled → cancelled_at, Expired →
 * expires_at), and `retentionUntil` adds `media_retention_days` (default 90)
 * to that anchor. `mediaPendingPurge` returns the over-quota media collections
 * only when the window has elapsed — the consumer (PurgeExpiredMediaCommand)
 * uses those collections as its delete-eligibility set.
 *
 * Stateless and read-only: no DB write, no Storage call. FaceEntitlementService
 * is injected for the canonical capabilities matrix.
 */
final class FaceMediaRetentionService
{
    public function __construct(
        private readonly FaceEntitlementService $entitlement,
    ) {}

    /**
     * Most recent paid-subscription termination date on this Face, or null if
     * the Face has never had an Expired/Cancelled paid subscription.
     *
     * Cancelled rows use `cancelled_at` (the moment entitlement was lost, per
     * the FP-2.5 chained-renewal contract `cancelled_at = new_row.starts_at`);
     * Cancelled pending-payment rows are skipped because they never granted a
     * paid entitlement window.
     * Expired rows use `expires_at` (FP-2.8 cron flips status without setting
     * cancelled_at). PendingPayment / Active / Failed are skipped — no
     * media was uploaded under those statuses, or the entitlement is current.
     *
     * Invariant guard: a Cancelled row with null `cancelled_at` (or an Expired
     * row with null `expires_at`) is data drift — we emit Log::warning so ops
     * can investigate, then exclude that row from the anchor computation
     * instead of silently absorbing the bad data.
     */
    public function retentionAnchor(Face $face): ?CarbonInterface
    {
        if ($face->relationLoaded('subscriptions')) {
            /** @var Collection<int, FaceSubscription> $subscriptions */
            $subscriptions = $face->getRelation('subscriptions');

            $terminated = $subscriptions
                ->filter(
                    fn (FaceSubscription $sub): bool => in_array(
                        $sub->status,
                        [FaceSubscriptionStatus::Expired, FaceSubscriptionStatus::Cancelled],
                        true,
                    ),
                )
                ->values();
        } else {
            $terminated = FaceSubscription::query()
                ->where('face_id', $face->getKey())
                ->whereIn('status', [
                    FaceSubscriptionStatus::Expired,
                    FaceSubscriptionStatus::Cancelled,
                ])
                ->get();
        }

        $dates = $terminated->map(function (FaceSubscription $sub) use ($face): ?CarbonInterface {
            if ($sub->status === FaceSubscriptionStatus::Cancelled) {
                if ($sub->cancelled_at === null) {
                    Log::warning('FaceSubscription invariant violation: Cancelled row has null cancelled_at', [
                        'face_id' => $face->getKey(),
                        'face_subscription_id' => $sub->getKey(),
                    ]);

                    return null;
                }

                if ($sub->starts_at === null || $sub->expires_at === null) {
                    return null;
                }

                return $sub->cancelled_at;
            }

            if ($sub->expires_at === null) {
                Log::warning('FaceSubscription invariant violation: Expired row has null expires_at', [
                    'face_id' => $face->getKey(),
                    'face_subscription_id' => $sub->getKey(),
                ]);

                return null;
            }

            return $sub->expires_at;
        })->filter();

        if ($dates->isEmpty()) {
            return null;
        }

        return $dates->max();
    }

    /**
     * Anchor + `media_retention_days` config days (default 90). Null when the
     * Face has no termination history.
     */
    public function retentionUntil(Face $face): ?CarbonInterface
    {
        $anchor = $this->retentionAnchor($face);

        if ($anchor === null) {
            return null;
        }

        $days = (int) config('face_subscription_tiers.media_retention_days', 90);

        return $anchor->copy()->addDays($days);
    }

    /**
     * Photos at `position > capabilities.maxAlbumPhotos`. Empty for a Face
     * within their current tier's quota.
     *
     * @return Collection<int, FacePhoto>
     */
    public function overQuotaPhotos(Face $face): Collection
    {
        $caps = $this->entitlement->capabilities($face);

        $photos = $face->relationLoaded('photos')
            ? $face->getRelation('photos')
            : $face->photos()->get();

        /** @var Collection<int, FacePhoto> $result */
        $result = $photos
            ->filter(fn (FacePhoto $photo): bool => $photo->position > $caps->maxAlbumPhotos)
            ->values();

        return $result;
    }

    /**
     * Acting videos at `position > capabilities.maxActingVideos`.
     *
     * @return Collection<int, FaceVideo>
     */
    public function overQuotaActingVideos(Face $face): Collection
    {
        $caps = $this->entitlement->capabilities($face);

        $videos = $face->relationLoaded('videos')
            ? $face->getRelation('videos')->where('type', FaceVideoType::Acting)
            : $face->videos()->where('type', FaceVideoType::Acting)->get();

        /** @var Collection<int, FaceVideo> $result */
        $result = $videos
            ->filter(fn (FaceVideo $video): bool => $video->position > $caps->maxActingVideos)
            ->values();

        return $result;
    }

    /**
     * UGC videos at `position > capabilities.maxUgcVideos`.
     *
     * @return Collection<int, FaceVideo>
     */
    public function overQuotaUgcVideos(Face $face): Collection
    {
        $caps = $this->entitlement->capabilities($face);

        $videos = $face->relationLoaded('videos')
            ? $face->getRelation('videos')->where('type', FaceVideoType::Ugc)
            : $face->videos()->where('type', FaceVideoType::Ugc)->get();

        /** @var Collection<int, FaceVideo> $result */
        $result = $videos
            ->filter(fn (FaceVideo $video): bool => $video->position > $caps->maxUgcVideos)
            ->values();

        return $result;
    }

    /**
     * The 3 over-quota collections, populated only when the retention window
     * has elapsed (`now() >= retentionUntil`). Empty collections when the
     * window is still open OR when the Face has no termination history.
     *
     * @return array{photos: Collection<int, FacePhoto>, acting_videos: Collection<int, FaceVideo>, ugc_videos: Collection<int, FaceVideo>}
     */
    public function mediaPendingPurge(Face $face): array
    {
        $until = $this->retentionUntil($face);

        if ($until === null || now()->lt($until)) {
            return [
                'photos' => new Collection,
                'acting_videos' => new Collection,
                'ugc_videos' => new Collection,
            ];
        }

        return [
            'photos' => $this->overQuotaPhotos($face),
            'acting_videos' => $this->overQuotaActingVideos($face),
            'ugc_videos' => $this->overQuotaUgcVideos($face),
        ];
    }
}
