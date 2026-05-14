import { computed, type ComputedRef, type Ref } from 'vue'
import { faceApi } from '../services/faceApi'
import type { SubscriptionStatusInfo } from '../types'
import { getApiErrorMessage } from '@/features/auth/services/authApi'
import { createSharedCachedResource } from '@/lib/createSharedCachedResource'

const SUBSCRIPTION_STATUS_CACHE_TTL_MS = 60 * 1000

const subscriptionStatusResource = createSharedCachedResource<SubscriptionStatusInfo | null>({
  key: 'face-subscription-status',
  initialValue: null,
  ttlMs: SUBSCRIPTION_STATUS_CACHE_TTL_MS,
  load: async () => {
    const response = await faceApi.getSubscriptionStatus()
    return response.data
  },
  getErrorMessage: getApiErrorMessage,
})

interface UseSubscriptionStatusReturn {
  status: Ref<SubscriptionStatusInfo | null>
  isLoading: Ref<boolean>
  error: Ref<string | null>
  isPremium: ComputedRef<boolean>
  statusValue: ComputedRef<'free' | 'pending_payment' | 'active' | 'expired' | 'cancelled' | 'failed'>
  albumUploadLimit: ComputedRef<number>
  publicAlbumPhotoLimit: ComputedRef<number>
  lockedAlbumPhotoCount: ComputedRef<number>
  canUploadActingVideo: ComputedRef<boolean>
  hasActingVideo: ComputedRef<boolean>
  isActingVideoPubliclyVisible: ComputedRef<boolean>
  canRenew: ComputedRef<boolean>
  planIsAvailable: ComputedRef<boolean>
  fetchStatus: () => Promise<void>
  refreshStatus: () => Promise<void>
  invalidateStatus: () => void
}

export function useSubscriptionStatus(): UseSubscriptionStatusReturn {
  const status = subscriptionStatusResource.data
  const isLoading = subscriptionStatusResource.isLoading
  const error = subscriptionStatusResource.error

  const isPremium = computed(() => status.value?.is_premium ?? false)
  const statusValue = computed(() => status.value?.status ?? 'free')
  const albumUploadLimit = computed(() => status.value?.entitlements.album_upload_limit ?? 2)
  const publicAlbumPhotoLimit = computed(() => status.value?.entitlements.public_album_photo_limit ?? 2)
  const lockedAlbumPhotoCount = computed(() => status.value?.entitlements.locked_album_photo_count ?? 0)
  const canUploadActingVideo = computed(() => status.value?.entitlements.can_upload_acting_video ?? false)
  const hasActingVideo = computed(() => status.value?.entitlements.has_acting_video ?? false)
  const isActingVideoPubliclyVisible = computed(
    () => status.value?.entitlements.is_acting_video_publicly_visible ?? false,
  )
  const canRenew = computed(() => status.value?.can_renew ?? true)
  const planIsAvailable = computed(() => status.value?.annual_plan.is_available ?? false)

  async function fetchStatus(): Promise<void> {
    await subscriptionStatusResource.fetch()
  }

  async function refreshStatus(): Promise<void> {
    await subscriptionStatusResource.fetch({ force: true })
  }

  function invalidateStatus(): void {
    subscriptionStatusResource.invalidate()
  }

  return {
    status,
    isLoading,
    error,
    isPremium,
    statusValue,
    albumUploadLimit,
    publicAlbumPhotoLimit,
    lockedAlbumPhotoCount,
    canUploadActingVideo,
    hasActingVideo,
    isActingVideoPubliclyVisible,
    canRenew,
    planIsAvailable,
    fetchStatus,
    refreshStatus,
    invalidateStatus,
  }
}
