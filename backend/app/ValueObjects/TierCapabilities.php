<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Enums\FaceSubscriptionTier;

/**
 * Immutable entitlement matrix for one Face subscription tier.
 * Built by FaceEntitlementService from config/face_subscription_tiers.php.
 */
final readonly class TierCapabilities
{
    public function __construct(
        public FaceSubscriptionTier $tier,
        public int $maxAlbumPhotos,
        public int $maxPresentationVideos,
        public int $maxActingVideos,
        public int $maxUgcVideos,
        public bool $ugcAccess,
        public float $commissionRate,
        public int $sortPriority,
        public bool $hasEliteBadge,
    ) {}
}
