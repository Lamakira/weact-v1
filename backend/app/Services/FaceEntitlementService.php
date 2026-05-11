<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FaceSubscriptionPlan;
use App\Enums\FaceSubscriptionStatus;
use App\Models\Face;
use App\Models\FaceSubscription;

/**
 * Single source of truth for Face premium entitlements.
 *
 * Entitlements are derived from an active, unexpired annual premium subscription.
 * Free / pending / expired / cancelled / failed / past-expiry Faces receive the
 * default free tier limits and are not considered premium.
 *
 * Consumers should call this service instead of inferring premium state from
 * the raw `face_subscriptions` rows or from `faces.is_featured`.
 *
 * If the caller has already eager-loaded the `activeSubscription` relation on
 * the Face model (e.g. `Face::with('activeSubscription')`), the service will
 * read that preloaded value and skip an extra query. Otherwise it performs
 * a single targeted lookup.
 */
class FaceEntitlementService
{
    public const FREE_ALBUM_UPLOAD_LIMIT = 2;

    public const FREE_PUBLIC_ALBUM_LIMIT = 2;

    public const PREMIUM_ALBUM_UPLOAD_LIMIT = 4;

    public const PREMIUM_PUBLIC_ALBUM_LIMIT = 4;

    /**
     * Maximum number of album photos this Face is allowed to upload.
     */
    public function albumUploadLimit(Face $face): int
    {
        return $this->isPremium($face)
            ? self::PREMIUM_ALBUM_UPLOAD_LIMIT
            : self::FREE_ALBUM_UPLOAD_LIMIT;
    }

    /**
     * Maximum number of album photos this Face is allowed to expose publicly.
     */
    public function publicAlbumPhotoLimit(Face $face): int
    {
        return $this->isPremium($face)
            ? self::PREMIUM_PUBLIC_ALBUM_LIMIT
            : self::FREE_PUBLIC_ALBUM_LIMIT;
    }

    /**
     * Whether this Face currently holds an active, unexpired annual premium subscription.
     */
    public function isPremium(Face $face): bool
    {
        return $this->resolveActivePremiumSubscription($face) !== null;
    }

    /**
     * Whether this Face is allowed to upload an acting video.
     *
     * Mirrors `isPremium()` because the presentation video is always public for
     * every Face — only the acting video is gated behind the annual premium tier.
     */
    public function canUploadActingVideo(Face $face): bool
    {
        return $this->isPremium($face);
    }

    /**
     * Whether this Face's featured placement is granted by an active paid subscription.
     *
     * This is independent from the legacy `faces.is_featured` admin flag, which
     * remains under manual admin control.
     */
    public function isFeaturedBySubscription(Face $face): bool
    {
        return $this->isPremium($face);
    }

    /**
     * Resolve the single active, unexpired annual premium subscription for this
     * Face, preferring the preloaded `activeSubscription` relation when present.
     */
    private function resolveActivePremiumSubscription(Face $face): ?FaceSubscription
    {
        if ($face->relationLoaded('activeSubscription')) {
            $candidate = $face->getRelation('activeSubscription');

            return $this->qualifiesAsPremium($candidate) ? $candidate : null;
        }

        $candidate = $face->subscriptions()
            ->active()
            ->where('plan', FaceSubscriptionPlan::AnnualPremium)
            ->first();

        return $candidate;
    }

    private function qualifiesAsPremium(?FaceSubscription $subscription): bool
    {
        if ($subscription === null) {
            return false;
        }

        return $subscription->plan === FaceSubscriptionPlan::AnnualPremium
            && $subscription->status === FaceSubscriptionStatus::Active
            && $subscription->expires_at !== null
            && $subscription->expires_at->isFuture();
    }
}
