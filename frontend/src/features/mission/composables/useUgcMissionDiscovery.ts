import { ref, computed } from 'vue'
import { faceMissionApi } from '../services/faceMissionApi'
import { getApiErrorMessage } from '@/features/auth/services/authApi'
import type { UgcDiscoveryItem, UgcPaywallMeta } from '../types'

/**
 * Découverte des missions UGC (écran 6A). Le gating d'affichage est piloté
 * par meta.can_access_ugc de la réponse — JAMAIS par les capabilities front
 * (D-2.2.b, action rétro capability-constants).
 */
export function useUgcMissionDiscovery() {
  const items = ref<UgcDiscoveryItem[]>([])
  const canAccessUgc = ref(false)
  const paywall = ref<UgcPaywallMeta | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const currentPage = ref(1)
  const lastPage = ref(1)
  const totalCount = ref(0)

  const isEmpty = computed(() => !isLoading.value && items.value.length === 0)
  const hasNextPage = computed(() => currentPage.value < lastPage.value)
  const hasPrevPage = computed(() => currentPage.value > 1)

  async function fetchMissions(page: number = 1): Promise<void> {
    isLoading.value = true
    error.value = null
    try {
      const response = await faceMissionApi.getUgcMissions(page)
      items.value = response.data
      canAccessUgc.value = response.meta.can_access_ugc
      paywall.value = response.meta.paywall ?? null
      currentPage.value = response.meta.current_page
      lastPage.value = response.meta.last_page
      totalCount.value = response.meta.total
    } catch (err: unknown) {
      error.value = getApiErrorMessage(err)
      items.value = []
    } finally {
      isLoading.value = false
    }
  }

  async function nextPage(): Promise<void> {
    if (hasNextPage.value) await fetchMissions(currentPage.value + 1)
  }

  async function prevPage(): Promise<void> {
    if (hasPrevPage.value) await fetchMissions(currentPage.value - 1)
  }

  async function refreshMissions(): Promise<void> {
    await fetchMissions(currentPage.value)
  }

  return {
    items,
    canAccessUgc,
    paywall,
    isLoading,
    error,
    currentPage,
    lastPage,
    totalCount,
    isEmpty,
    hasNextPage,
    hasPrevPage,
    fetchMissions,
    nextPage,
    prevPage,
    refreshMissions,
  }
}
