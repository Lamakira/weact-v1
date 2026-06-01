import { describe, it, expect } from 'vitest'
import { resolveQuotaUpsellTarget, MEDIA_NOUN } from '../mediaQuotaUpsell'
import type { SubscriptionOffer, TierCapabilities } from '@/features/face/types'

function caps(p: Partial<TierCapabilities>): TierCapabilities {
  return {
    max_album_photos: 0,
    max_presentation_videos: 0,
    max_acting_videos: 0,
    max_ugc_videos: 0,
    ugc_access: false,
    commission_rate: 0.1,
    sort_priority: 0,
    has_elite_badge: false,
    ...p,
  }
}

// Mirrors backend/config/face_subscription_tiers.php (the four media quotas).
const OFFERS: SubscriptionOffer[] = [
  { tier: 'free', price: 0, currency: 'XOF', capabilities: caps({ max_album_photos: 1 }) },
  { tier: 'starter', price: 12000, currency: 'XOF', capabilities: caps({ max_album_photos: 2, max_presentation_videos: 1 }) },
  { tier: 'pro', price: 25000, currency: 'XOF', capabilities: caps({ max_album_photos: 4, max_presentation_videos: 1, max_acting_videos: 1 }) },
  { tier: 'elite', price: 40000, currency: 'XOF', capabilities: caps({ max_album_photos: 6, max_presentation_videos: 1, max_acting_videos: 2, max_ugc_videos: 1 }) },
]

describe('resolveQuotaUpsellTarget', () => {
  it('album: each non-elite tier points to the next tier with more photos', () => {
    expect(resolveQuotaUpsellTarget('max_album_photos', 'free', 1, OFFERS)).toEqual({ tier: 'starter', tierName: 'Starter', quota: 2 })
    expect(resolveQuotaUpsellTarget('max_album_photos', 'starter', 2, OFFERS)).toEqual({ tier: 'pro', tierName: 'Pro', quota: 4 })
    expect(resolveQuotaUpsellTarget('max_album_photos', 'pro', 4, OFFERS)).toEqual({ tier: 'elite', tierName: 'Élite', quota: 6 })
  })

  it('album: Élite (top tier) has no upsell', () => {
    expect(resolveQuotaUpsellTarget('max_album_photos', 'elite', 6, OFFERS)).toBeNull()
  })

  it('presentation: unlock from free, then null once capped at 1', () => {
    expect(resolveQuotaUpsellTarget('max_presentation_videos', 'free', 0, OFFERS)).toEqual({ tier: 'starter', tierName: 'Starter', quota: 1 })
    expect(resolveQuotaUpsellTarget('max_presentation_videos', 'starter', 1, OFFERS)).toBeNull()
    expect(resolveQuotaUpsellTarget('max_presentation_videos', 'pro', 1, OFFERS)).toBeNull()
  })

  it('acting: skips Starter (still 0) and targets the next tier that actually adds acting', () => {
    expect(resolveQuotaUpsellTarget('max_acting_videos', 'free', 0, OFFERS)).toEqual({ tier: 'pro', tierName: 'Pro', quota: 1 })
    expect(resolveQuotaUpsellTarget('max_acting_videos', 'starter', 0, OFFERS)).toEqual({ tier: 'pro', tierName: 'Pro', quota: 1 })
    expect(resolveQuotaUpsellTarget('max_acting_videos', 'pro', 1, OFFERS)).toEqual({ tier: 'elite', tierName: 'Élite', quota: 2 })
    expect(resolveQuotaUpsellTarget('max_acting_videos', 'elite', 2, OFFERS)).toBeNull()
  })

  it('ugc: every lower tier targets Élite', () => {
    expect(resolveQuotaUpsellTarget('max_ugc_videos', 'free', 0, OFFERS)).toEqual({ tier: 'elite', tierName: 'Élite', quota: 1 })
    expect(resolveQuotaUpsellTarget('max_ugc_videos', 'pro', 0, OFFERS)).toEqual({ tier: 'elite', tierName: 'Élite', quota: 1 })
    expect(resolveQuotaUpsellTarget('max_ugc_videos', 'elite', 1, OFFERS)).toBeNull()
  })

  it('returns null when offers have not loaded yet', () => {
    expect(resolveQuotaUpsellTarget('max_album_photos', 'free', 1, [])).toBeNull()
  })
})

describe('MEDIA_NOUN', () => {
  it('pluralizes all four nouns by count (singular at 0/1, plural above)', () => {
    expect(MEDIA_NOUN.max_album_photos(2)).toBe('photos')
    expect(MEDIA_NOUN.max_album_photos(1)).toBe('photo')
    expect(MEDIA_NOUN.max_acting_videos(2)).toBe("vidéos d'acting")
    expect(MEDIA_NOUN.max_acting_videos(1)).toBe("vidéo d'acting")
    expect(MEDIA_NOUN.max_presentation_videos(1)).toBe('vidéo de présentation')
    expect(MEDIA_NOUN.max_presentation_videos(2)).toBe('vidéos de présentation')
    expect(MEDIA_NOUN.max_ugc_videos(1)).toBe('vidéo UGC')
    expect(MEDIA_NOUN.max_ugc_videos(2)).toBe('vidéos UGC')
  })
})
