import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useSubscriptionStatus } from '../useSubscriptionStatus'
import { faceApi } from '../../services/faceApi'
import { resetSharedCachedResourcesForTests } from '@/lib/createSharedCachedResource'
import type { SubscriptionStatusInfo, SubscriptionStatusResponse } from '../../types'

vi.mock('../../services/faceApi', () => ({
  faceApi: {
    getSubscriptionStatus: vi.fn(),
  },
}))

vi.mock('@/features/auth/services/authApi', () => ({
  getApiErrorMessage: vi.fn(() => 'Une erreur est survenue'),
}))

function freeStatus(): SubscriptionStatusInfo {
  return {
    status: 'free',
    plan: null,
    starts_at: null,
    expires_at: null,
    cancelled_at: null,
    is_premium: false,
    is_featured_by_subscription: false,
    can_renew: true,
    subscription_id: null,
    entitlements: {
      album_upload_limit: 2,
      public_album_photo_limit: 2,
      current_album_photo_count: 0,
      public_album_photo_count: 0,
      locked_album_photo_count: 0,
      can_upload_acting_video: false,
      has_acting_video: false,
      is_acting_video_publicly_visible: false,
    },
    annual_plan: {
      amount: 50000,
      currency: 'XOF',
      provider: 'fedapay',
      is_available: true,
    },
  }
}

function activeStatus(): SubscriptionStatusInfo {
  return {
    ...freeStatus(),
    status: 'active',
    plan: 'annual_premium',
    starts_at: '2026-05-14T10:00:00Z',
    expires_at: '2027-05-14T10:00:00Z',
    is_premium: true,
    is_featured_by_subscription: true,
    can_renew: false,
    subscription_id: 'sub_123',
    entitlements: {
      album_upload_limit: 4,
      public_album_photo_limit: 4,
      current_album_photo_count: 2,
      public_album_photo_count: 2,
      locked_album_photo_count: 0,
      can_upload_acting_video: true,
      has_acting_video: true,
      is_acting_video_publicly_visible: true,
    },
  }
}

function pendingStatus(): SubscriptionStatusInfo {
  return {
    ...freeStatus(),
    status: 'pending_payment',
    plan: 'annual_premium',
    can_renew: false,
    subscription_id: 'sub_pending',
    annual_plan: {
      amount: 50000,
      currency: 'XOF',
      provider: 'fedapay',
      is_available: false,
    },
  }
}

describe('useSubscriptionStatus', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    resetSharedCachedResourcesForTests()
  })

  it('fetchStatus calls faceApi.getSubscriptionStatus once and populates status ref', async () => {
    const payload: SubscriptionStatusResponse = { data: freeStatus() }
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue(payload)

    const { status, fetchStatus } = useSubscriptionStatus()
    await fetchStatus()

    expect(faceApi.getSubscriptionStatus).toHaveBeenCalledOnce()
    expect(status.value).toEqual(payload.data)
  })

  it('exposes safe free-tier defaults before any fetch resolves', () => {
    const {
      status,
      isPremium,
      albumUploadLimit,
      publicAlbumPhotoLimit,
      canUploadActingVideo,
      canRenew,
      planIsAvailable,
    } = useSubscriptionStatus()

    expect(status.value).toBeNull()
    expect(isPremium.value).toBe(false)
    expect(albumUploadLimit.value).toBe(2)
    expect(publicAlbumPhotoLimit.value).toBe(2)
    expect(canUploadActingVideo.value).toBe(false)
    expect(canRenew.value).toBe(true)
    expect(planIsAvailable.value).toBe(false)
  })

  it('reflects active premium state via computeds', async () => {
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({ data: activeStatus() })

    const {
      fetchStatus,
      isPremium,
      statusValue,
      albumUploadLimit,
      publicAlbumPhotoLimit,
      canUploadActingVideo,
    } = useSubscriptionStatus()

    await fetchStatus()

    expect(statusValue.value).toBe('active')
    expect(isPremium.value).toBe(true)
    expect(albumUploadLimit.value).toBe(4)
    expect(publicAlbumPhotoLimit.value).toBe(4)
    expect(canUploadActingVideo.value).toBe(true)
  })

  it('reflects pending_payment state via computeds', async () => {
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({ data: pendingStatus() })

    const {
      fetchStatus,
      statusValue,
      canRenew,
      planIsAvailable,
      albumUploadLimit,
    } = useSubscriptionStatus()

    await fetchStatus()

    expect(statusValue.value).toBe('pending_payment')
    expect(canRenew.value).toBe(false)
    expect(planIsAvailable.value).toBe(false)
    expect(albumUploadLimit.value).toBe(2)
  })

  it('shares the same status ref across consumers (singleton)', async () => {
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({ data: activeStatus() })

    const first = useSubscriptionStatus()
    await first.fetchStatus()
    const second = useSubscriptionStatus()

    expect(second.status).toBe(first.status)
    expect(second.status.value?.status).toBe('active')
    expect(faceApi.getSubscriptionStatus).toHaveBeenCalledTimes(1)
  })

  it('invalidateStatus + fetchStatus triggers a fresh API call', async () => {
    vi.mocked(faceApi.getSubscriptionStatus).mockResolvedValue({ data: freeStatus() })

    const { fetchStatus, invalidateStatus } = useSubscriptionStatus()
    await fetchStatus()

    invalidateStatus()
    await fetchStatus()

    expect(faceApi.getSubscriptionStatus).toHaveBeenCalledTimes(2)
  })

  it('sets error.value when getSubscriptionStatus rejects', async () => {
    vi.mocked(faceApi.getSubscriptionStatus).mockRejectedValue(new Error('boom'))

    const { fetchStatus, error } = useSubscriptionStatus()
    await fetchStatus()

    expect(error.value).toBe('Une erreur est survenue')
  })
})
