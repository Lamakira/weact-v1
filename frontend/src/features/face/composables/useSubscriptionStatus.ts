import { computed, type ComputedRef, type Ref } from 'vue'
import { faceApi } from '../services/faceApi'
import type {
  FaceSubscriptionPlan,
  FaceSubscriptionTier,
  SubscriptionCta,
  SubscriptionCurrent,
  SubscriptionOffer,
  SubscriptionStatusData,
  SubscriptionStatusValue,
  TierCapabilities,
} from '../types'
import { getApiErrorMessage } from '@/features/auth/services/authApi'
import { createSharedCachedResource } from '@/lib/createSharedCachedResource'

const SUBSCRIPTION_STATUS_CACHE_TTL_MS = 60 * 1000

// Free-tier capability matrix — the fallback before the status endpoint has loaded
// (mirrors config/face_subscription_tiers.php → tiers.free.capabilities).
export const FREE_CAPABILITIES: TierCapabilities = {
  max_album_photos: 1,
  max_presentation_videos: 0,
  max_acting_videos: 0,
  max_ugc_videos: 0,
  ugc_access: false,
  commission_rate: 0.1,
  sort_priority: 4,
  has_elite_badge: false,
}

const EMPTY_CTA: SubscriptionCta = {
  upgrade_available: false,
  downgrade_available: false,
  renew_available: false,
}

const subscriptionStatusResource = createSharedCachedResource<SubscriptionStatusData | null>({
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
  data: Ref<SubscriptionStatusData | null>
  isLoading: Ref<boolean>
  error: Ref<string | null>
  current: ComputedRef<SubscriptionCurrent | null>
  offers: ComputedRef<SubscriptionOffer[]>
  cta: ComputedRef<SubscriptionCta>
  tier: ComputedRef<FaceSubscriptionTier>
  statusValue: ComputedRef<SubscriptionStatusValue>
  currentPlan: ComputedRef<FaceSubscriptionPlan | null>
  capabilities: ComputedRef<TierCapabilities>
  maxAlbumPhotos: ComputedRef<number>
  expiresAt: ComputedRef<string | null>
  startsAt: ComputedRef<string | null>
  cancelledAt: ComputedRef<string | null>
  fetchStatus: () => Promise<void>
  refreshStatus: () => Promise<void>
  invalidateStatus: () => void
}

export function useSubscriptionStatus(): UseSubscriptionStatusReturn {
  const data = subscriptionStatusResource.data
  const isLoading = subscriptionStatusResource.isLoading
  const error = subscriptionStatusResource.error

  const current = computed(() => data.value?.current ?? null)
  const offers = computed(() => data.value?.offers ?? [])
  const cta = computed(() => data.value?.cta ?? EMPTY_CTA)
  const tier = computed<FaceSubscriptionTier>(() => current.value?.tier ?? 'free')
  const statusValue = computed<SubscriptionStatusValue>(() => current.value?.status ?? 'free')
  const currentPlan = computed<FaceSubscriptionPlan | null>(() => current.value?.plan ?? null)
  const capabilities = computed<TierCapabilities>(
    () => current.value?.capabilities ?? FREE_CAPABILITIES,
  )
  const maxAlbumPhotos = computed(() => capabilities.value.max_album_photos)
  const expiresAt = computed(() => current.value?.expires_at ?? null)
  const startsAt = computed(() => current.value?.starts_at ?? null)
  const cancelledAt = computed(() => current.value?.cancelled_at ?? null)

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
    data,
    isLoading,
    error,
    current,
    offers,
    cta,
    tier,
    statusValue,
    currentPlan,
    capabilities,
    maxAlbumPhotos,
    expiresAt,
    startsAt,
    cancelledAt,
    fetchStatus,
    refreshStatus,
    invalidateStatus,
  }
}
