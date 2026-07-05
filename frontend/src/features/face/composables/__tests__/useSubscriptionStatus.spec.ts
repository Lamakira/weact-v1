import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useSubscriptionStatus, FREE_CAPABILITIES } from '../useSubscriptionStatus'
import { faceApi } from '../../services/faceApi'
import { resetAllSharedCachedResources } from '@/lib/createSharedCachedResource'
import type {
  FaceSubscriptionTier,
  SubscriptionCurrent,
  SubscriptionCta,
  SubscriptionOffer,
  SubscriptionStatusData,
  TierCapabilities,
} from '../../types'

vi.mock('../../services/faceApi', () => ({
  faceApi: {
    getSubscriptionStatus: vi.fn(),
  },
}))

vi.mock('@/features/auth/services/authApi', () => ({
  getApiErrorMessage: vi.fn(() => 'Une erreur est survenue'),
}))

const CAPABILITIES: Record<FaceSubscriptionTier, TierCapabilities> = {
  free: {
    max_album_photos: 1,
    max_presentation_videos: 0,
    max_acting_videos: 0,
    max_ugc_videos: 0,
    ugc_access: false,
    commission_rate: 0.15,
    sort_priority: 4,
    has_elite_badge: false,
  },
  starter: {
    max_album_photos: 2,
    max_presentation_videos: 1,
    max_acting_videos: 0,
    max_ugc_videos: 0,
    ugc_access: true,
    commission_rate: 0.1,
    sort_priority: 3,
    has_elite_badge: false,
  },
  pro: {
    max_album_photos: 4,
    max_presentation_videos: 1,
    max_acting_videos: 1,
    max_ugc_videos: 0,
    ugc_access: true,
    commission_rate: 0.1,
    sort_priority: 2,
    has_elite_badge: false,
  },
  elite: {
    max_album_photos: 6,
    max_presentation_videos: 1,
    max_acting_videos: 2,
    max_ugc_videos: 1,
    ugc_access: true,
    commission_rate: 0.05,
    sort_priority: 1,
    has_elite_badge: true,
  },
}

const PRICES: Record<FaceSubscriptionTier, number> = {
  free: 0,
  starter: 12000,
  pro: 25000,
  elite: 40000,
}

function buildOffers(): SubscriptionOffer[] {
  return (['free', 'starter', 'pro', 'elite'] as FaceSubscriptionTier[]).map((tier) => ({
    tier,
    price: PRICES[tier],
    currency: 'XOF',
    capabilities: CAPABILITIES[tier],
  }))
}

function statusData(
  currentOverrides: Partial<SubscriptionCurrent> = {},
  ctaOverrides: Partial<SubscriptionCta> = {},
): SubscriptionStatusData {
  const tier = currentOverrides.tier ?? 'free'
  return {
    current: {
      tier,
      plan: currentOverrides.plan ?? null,
      status: currentOverrides.status ?? 'free',
      starts_at: currentOverrides.starts_at ?? null,
      expires_at: currentOverrides.expires_at ?? null,
      cancelled_at: currentOverrides.cancelled_at ?? null,
      capabilities: currentOverrides.capabilities ?? CAPABILITIES[tier],
    },
    offers: buildOffers(),
    cta: {
      upgrade_available: ctaOverrides.upgrade_available ?? true,
      downgrade_available: ctaOverrides.downgrade_available ?? true,
      renew_available: ctaOverrides.renew_available ?? true,
    },
  }
}

describe('useSubscriptionStatus (FP-2.7 tier-aware contract)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    resetAllSharedCachedResources()
  })

  it('fetchStatus calls faceApi.getSubscriptionStatus once and populates data', async () => {
    const payload = statusData({ tier: 'pro', plan: 'pro', status: 'active' })
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({ data: payload })

    const { data, fetchStatus } = useSubscriptionStatus()
    await fetchStatus()

    expect(faceApi.getSubscriptionStatus).toHaveBeenCalledOnce()
    expect(data.value).toEqual(payload)
  })

  it('exposes safe free-tier fallbacks before any fetch resolves', () => {
    const { data, tier, statusValue, capabilities, maxAlbumPhotos, currentPlan } =
      useSubscriptionStatus()

    expect(data.value).toBeNull()
    expect(tier.value).toBe('free')
    expect(statusValue.value).toBe('free')
    expect(capabilities.value).toEqual(FREE_CAPABILITIES)
    expect(maxAlbumPhotos.value).toBe(1)
    expect(currentPlan.value).toBeNull()
  })

  it('reflects an active Pro subscription via the tier computeds', async () => {
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData({
        tier: 'pro',
        plan: 'pro',
        status: 'active',
        expires_at: '2027-05-22T10:00:00Z',
      }),
    })

    const { fetchStatus, tier, statusValue, maxAlbumPhotos, currentPlan } = useSubscriptionStatus()
    await fetchStatus()

    expect(tier.value).toBe('pro')
    expect(statusValue.value).toBe('active')
    expect(maxAlbumPhotos.value).toBe(4)
    expect(currentPlan.value).toBe('pro')
  })

  it('reflects an expired Pro subscription — entitlement drops to free, plan retained', async () => {
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData({
        tier: 'free',
        plan: 'pro',
        status: 'expired',
        expires_at: '2026-01-10T10:00:00Z',
        capabilities: CAPABILITIES.free,
      }),
    })

    const { fetchStatus, tier, statusValue, currentPlan, maxAlbumPhotos } = useSubscriptionStatus()
    await fetchStatus()

    expect(tier.value).toBe('free')
    expect(statusValue.value).toBe('expired')
    expect(currentPlan.value).toBe('pro')
    expect(maxAlbumPhotos.value).toBe(1)
  })

  it('exposes the four tier offers in ascending order', async () => {
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({ data: statusData() })

    const { fetchStatus, offers } = useSubscriptionStatus()
    await fetchStatus()

    expect(offers.value.map((o) => o.tier)).toEqual(['free', 'starter', 'pro', 'elite'])
    expect(offers.value[2].price).toBe(25000)
  })

  it('passes the cta block through unchanged', async () => {
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData({}, { upgrade_available: false, downgrade_available: false, renew_available: false }),
    })

    const { fetchStatus, cta } = useSubscriptionStatus()
    await fetchStatus()

    expect(cta.value).toEqual({
      upgrade_available: false,
      downgrade_available: false,
      renew_available: false,
    })
  })

  it('shares one underlying data ref across consumers (singleton per key)', async () => {
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({
      data: statusData({ tier: 'elite', plan: 'elite', status: 'active' }),
    })

    const first = useSubscriptionStatus()
    await first.fetchStatus()
    const second = useSubscriptionStatus()

    expect(second.data).toBe(first.data)
    expect(second.tier.value).toBe('elite')
    expect(faceApi.getSubscriptionStatus).toHaveBeenCalledTimes(1)
  })

  it('invalidateStatus + refreshStatus triggers a fresh API call', async () => {
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({ data: statusData() })

    const { fetchStatus, refreshStatus, invalidateStatus } = useSubscriptionStatus()
    await fetchStatus()
    expect(faceApi.getSubscriptionStatus).toHaveBeenCalledTimes(1)

    invalidateStatus()
    await refreshStatus()

    expect(faceApi.getSubscriptionStatus).toHaveBeenCalledTimes(2)
  })

  it('sets error and falls back to the free tier when the request rejects', async () => {
    vi.mocked(faceApi.getSubscriptionStatus).mockRejectedValue(new Error('boom'))

    const { fetchStatus, error, tier, capabilities } = useSubscriptionStatus()
    await fetchStatus()

    expect(error.value).toBe('Une erreur est survenue')
    expect(tier.value).toBe('free')
    expect(capabilities.value).toEqual(FREE_CAPABILITIES)
  })
})
