import type { FaceSubscriptionTier, SubscriptionOffer } from './types'
import { TIER_PRESENTATION } from './tierPresentation'

// The four numeric media-quota fields on TierCapabilities that drive the
// Portfolio upload sections. Each maps 1:1 to a panel in ProfileEditPage.
export type MediaQuotaKey =
  | 'max_album_photos'
  | 'max_presentation_videos'
  | 'max_acting_videos'
  | 'max_ugc_videos'

// Ascending tier order (free → starter → pro → elite). Explicit so the resolver
// never depends on the order of the offers[] array.
const TIER_RANK: Record<FaceSubscriptionTier, number> = {
  free: 0,
  starter: 1,
  pro: 2,
  elite: 3,
}

// French noun per media, pluralized by count, used in BOTH the quota line and the
// upsell line so the two stay grammatically in sync.
export const MEDIA_NOUN: Record<MediaQuotaKey, (count: number) => string> = {
  max_album_photos: (n) => (n > 1 ? 'photos' : 'photo'),
  max_presentation_videos: (n) => (n > 1 ? 'vidéos de présentation' : 'vidéo de présentation'),
  max_acting_videos: (n) => (n > 1 ? "vidéos d'acting" : "vidéo d'acting"),
  max_ugc_videos: (n) => (n > 1 ? 'vidéos UGC' : 'vidéo UGC'),
}

export interface QuotaUpsellTarget {
  tier: FaceSubscriptionTier
  tierName: string
  quota: number
}

/**
 * The LOWEST tier strictly above `currentTier` whose quota for `media` exceeds
 * `currentQuota`. Returns null on Élite (top tier) or when no higher tier
 * increases this media (e.g. presentation video stays at 1 across starter/pro/
 * elite). Quotas are read live from `offers` (FP-2.3 status endpoint) — no
 * static table to drift from config/face_subscription_tiers.php.
 */
export function resolveQuotaUpsellTarget(
  media: MediaQuotaKey,
  currentTier: FaceSubscriptionTier,
  currentQuota: number,
  offers: SubscriptionOffer[],
): QuotaUpsellTarget | null {
  const currentRank = TIER_RANK[currentTier]
  let best: QuotaUpsellTarget | null = null

  for (const offer of offers) {
    if (TIER_RANK[offer.tier] <= currentRank) continue
    const quota = offer.capabilities[media]
    if (quota <= currentQuota) continue
    if (best === null || TIER_RANK[offer.tier] < TIER_RANK[best.tier]) {
      best = { tier: offer.tier, tierName: TIER_PRESENTATION[offer.tier].name, quota }
    }
  }

  return best
}
