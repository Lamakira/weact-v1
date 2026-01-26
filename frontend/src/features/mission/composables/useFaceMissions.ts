import { ref, computed } from 'vue'
import { faceMissionApi } from '../services/faceMissionApi'
import { getApiErrorMessage } from '@/features/auth/services/authApi'
import type { Mission } from '../types'

/**
 * Composable for managing Face missions browsing state
 */
export function useFaceMissions() {
  const missions = ref<Mission[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const currentPage = ref(1)
  const lastPage = ref(1)
  const totalCount = ref(0)
  const perPage = ref(12)

  const isEmpty = computed(() => !isLoading.value && missions.value.length === 0)
  const hasNextPage = computed(() => currentPage.value < lastPage.value)
  const hasPrevPage = computed(() => currentPage.value > 1)

  /**
   * Fetch missions for a specific page
   */
  async function fetchMissions(page: number = 1): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await faceMissionApi.getAvailableMissions(page)
      missions.value = response.data
      currentPage.value = response.meta.current_page
      lastPage.value = response.meta.last_page
      totalCount.value = response.meta.total
      perPage.value = response.meta.per_page
    } catch (err: unknown) {
      error.value = getApiErrorMessage(err)
      missions.value = []
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Go to next page
   */
  async function nextPage(): Promise<void> {
    if (hasNextPage.value) {
      await fetchMissions(currentPage.value + 1)
    }
  }

  /**
   * Go to previous page
   */
  async function prevPage(): Promise<void> {
    if (hasPrevPage.value) {
      await fetchMissions(currentPage.value - 1)
    }
  }

  /**
   * Go to a specific page
   */
  async function goToPage(page: number): Promise<void> {
    if (page >= 1 && page <= lastPage.value) {
      await fetchMissions(page)
    }
  }

  /**
   * Refresh current page
   */
  async function refreshMissions(): Promise<void> {
    await fetchMissions(currentPage.value)
  }

  return {
    // State
    missions,
    isLoading,
    error,
    currentPage,
    lastPage,
    totalCount,
    perPage,
    isEmpty,
    hasNextPage,
    hasPrevPage,
    // Methods
    fetchMissions,
    nextPage,
    prevPage,
    goToPage,
    refreshMissions,
  }
}
