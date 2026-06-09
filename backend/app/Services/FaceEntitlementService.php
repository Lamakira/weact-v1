<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FaceSubscriptionTier;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\ValueObjects\TierCapabilities;
use WeakMap;

/**
 * Single source of truth for Face subscription entitlements.
 *
 * `capabilities()` is the canonical API: it returns the full tier matrix
 * (quotas, ugc access, commission rate, sort priority, elite badge) driven
 * entirely by config/face_subscription_tiers.php. A Face with no active paid
 * subscription resolves to the Free tier.
 *
 * Resolution prefers an eager-loaded `activeSubscription` relation when
 * present; otherwise it issues a single targeted query. Results are memoized
 * per Face object on this instance via WeakMap so long-running commands do
 * not retain stale entries when PHP reuses object ids after model objects
 * are destroyed. The service is intentionally NOT a container singleton.
 */
class FaceEntitlementService
{
    /** @var WeakMap<Face, TierCapabilities> */
    private WeakMap $capabilitiesMemo;

    public function __construct()
    {
        $this->capabilitiesMemo = new WeakMap;
    }

    /**
     * Canonical entitlement matrix for this Face's current tier.
     */
    public function capabilities(Face $face): TierCapabilities
    {
        if (isset($this->capabilitiesMemo[$face])) {
            return $this->capabilitiesMemo[$face];
        }

        $subscription = $this->resolveActiveSubscription($face);
        $tier = $subscription?->plan->tier() ?? FaceSubscriptionTier::Free;

        return $this->capabilitiesMemo[$face] = $this->buildCapabilities($tier);
    }

    /**
     * Canonical entitlement matrix for an explicit tier — used to expose the
     * four tier offers (FP-2.3 status endpoint) without resolving a Face.
     * Goes through the same config-driven, validated build path as capabilities().
     */
    public function capabilitiesForTier(FaceSubscriptionTier $tier): TierCapabilities
    {
        return $this->buildCapabilities($tier);
    }

    /**
     * FR5/AR10 — gate des opportunités UGC : souscription active Starter+ requise,
     * et Face non suspendue.
     */
    public function canAccessUgc(Face $face): bool
    {
        return $this->capabilities($face)->ugcAccess
            && ! $this->isUgcSuspended($face);
    }

    /**
     * Point d'extension épic 5 (table `ugc_suspensions`, story 5.1) : la
     * suspension douce UGC n'existe pas encore — toujours false dans ce slice.
     */
    public function isUgcSuspended(Face $face): bool
    {
        return false;
    }

    /**
     * Resolve the Face's active, unexpired subscription — preferring the
     * eager-loaded `activeSubscription` relation, re-validated defensively.
     *
     * The query-path ordering mirrors the `Face::activeSubscription` HasOne
     * `ofMany(['expires_at' => 'max', 'id' => 'max'])` selection so the
     * preloaded and query paths resolve the same row when subscriptions overlap.
     */
    private function resolveActiveSubscription(Face $face): ?FaceSubscription
    {
        if ($face->relationLoaded('activeSubscription')) {
            $candidate = $face->getRelation('activeSubscription');

            return ($candidate instanceof FaceSubscription && $candidate->isActive())
                ? $candidate
                : null;
        }

        return FaceSubscription::query()
            ->where('face_id', $face->getKey())
            ->active()
            ->orderByDesc('expires_at')
            ->orderByDesc('id')
            ->first();
    }

    private function buildCapabilities(FaceSubscriptionTier $tier): TierCapabilities
    {
        $caps = config('face_subscription_tiers.tiers.'.$tier->value.'.capabilities');

        $requiredKeys = [
            'max_album_photos',
            'max_presentation_videos',
            'max_acting_videos',
            'max_ugc_videos',
            'ugc_access',
            'commission_rate',
            'sort_priority',
            'has_elite_badge',
        ];

        // Fail loudly on a missing/stale config rather than crashing on a null
        // array access or silently coercing absent keys to 0/false.
        if (! is_array($caps) || array_diff($requiredKeys, array_keys($caps)) !== []) {
            throw new \RuntimeException(
                "Incomplete capabilities config for tier '{$tier->value}' — "
                .'check config/face_subscription_tiers.php (run `php artisan config:clear` if the config cache is stale).'
            );
        }

        $commissionRate = $caps['commission_rate'];
        $commissionRateValue = is_numeric($commissionRate) ? (float) $commissionRate : null;

        if ($commissionRateValue === null || ! is_finite($commissionRateValue) || $commissionRateValue < 0 || $commissionRateValue >= 1) {
            throw new \RuntimeException(
                "Invalid commission_rate for tier '{$tier->value}' — "
                .'expected a numeric rate in [0, 1) (0 inclusive, 1 exclusive) in config/face_subscription_tiers.php; '
                .'a rate of 1 (100 %) would leave the Face with zero earnings.'
            );
        }

        return new TierCapabilities(
            tier: $tier,
            maxAlbumPhotos: (int) $caps['max_album_photos'],
            maxPresentationVideos: (int) $caps['max_presentation_videos'],
            maxActingVideos: (int) $caps['max_acting_videos'],
            maxUgcVideos: (int) $caps['max_ugc_videos'],
            ugcAccess: (bool) $caps['ugc_access'],
            commissionRate: $commissionRateValue,
            sortPriority: (int) $caps['sort_priority'],
            hasEliteBadge: (bool) $caps['has_elite_badge'],
        );
    }
}
