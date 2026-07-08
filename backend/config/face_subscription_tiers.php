<?php

declare(strict_types=1);

/*
 * Single source of truth for Face subscription tier pricing and capabilities.
 * Adding a tier or changing a price/capability requires editing ONLY this file
 * — no schema migration, no controller/service change (FEAT-FP2-NFR1).
 * `tiers` is keyed by FaceSubscriptionTier value. `free` is the implicit tier
 * for any Face without an active paid subscription (price 0).
 */

return [
    'currency' => 'XOF',
    'provider' => 'fedapay',
    'stale_pending_max_hours' => 48,
    'media_retention_days' => 90,

    'tiers' => [
        'free' => [
            'price' => 0,
            'capabilities' => [
                'max_album_photos' => 1,
                'max_presentation_videos' => 0,
                'max_acting_videos' => 0,
                'max_ugc_videos' => 0,
                'ugc_access' => false,
                'commission_rate' => 0.15,
                'sort_priority' => 4,
                // Share (%) of public-listing slots granted to this tier by the
                // nightly weighted round-robin. The four quotas MUST sum to 100
                // (fail-loud guard in FaceListingRankingService). 56/25/13/6 is
                // calibrated so the standard 16-slot page splits exactly
                // 9 élite / 4 pro / 2 starter / 1 free — Starter must visibly
                // outrank Free (PO decision 2026-07-04).
                'listing_quota' => 6,
                'has_elite_badge' => false,
            ],
        ],
        'starter' => [
            'price' => 12000,
            'capabilities' => [
                'max_album_photos' => 2,
                'max_presentation_videos' => 1,
                'max_acting_videos' => 0,
                'max_ugc_videos' => 0,
                'ugc_access' => true,
                'commission_rate' => 0.10,
                'sort_priority' => 3,
                'listing_quota' => 13,
                'has_elite_badge' => false,
            ],
        ],
        'pro' => [
            'price' => 25000,
            'capabilities' => [
                'max_album_photos' => 4,
                'max_presentation_videos' => 1,
                'max_acting_videos' => 1,
                'max_ugc_videos' => 0,
                'ugc_access' => true,
                'commission_rate' => 0.10,
                'sort_priority' => 2,
                'listing_quota' => 25,
                'has_elite_badge' => false,
            ],
        ],
        'elite' => [
            'price' => 40000,
            'capabilities' => [
                'max_album_photos' => 6,
                'max_presentation_videos' => 1,
                'max_acting_videos' => 2,
                'max_ugc_videos' => 1,
                'ugc_access' => true,
                'commission_rate' => 0.05,
                'sort_priority' => 1,
                'listing_quota' => 56,
                'has_elite_badge' => true,
            ],
        ],
    ],
];
