import { ref, computed } from 'vue'
import { faceMissionApi } from '../services/faceMissionApi'
import { getApiErrorMessage } from '@/features/auth/services/authApi'
import type { Mission, MissionFilters } from '../types'

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
  const hasFiltersApplied = ref(false)

  const isEmpty = computed(() => !isLoading.value && missions.value.length === 0)
  const hasNextPage = computed(() => currentPage.value < lastPage.value)
  const hasPrevPage = computed(() => currentPage.value > 1)

  /**
   * Fetch missions for a specific page with optional filters
   */
  async function fetchMissions(page: number = 1, filters?: MissionFilters): Promise<void> {
    isLoading.value = true
    error.value = null

    // Track if filters are applied
    hasFiltersApplied.value = !!(
      filters &&
      (filters.lieu ||
        filters.budget_min !== undefined ||
        filters.budget_max !== undefined ||
        filters.date_tournage ||
        filters.type_mission)
    )

    try {
      const response = await faceMissionApi.getAvailableMissions(page, filters)
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
   * Go to next page (requires filters to be passed for consistency)
   */
  async function nextPage(filters?: MissionFilters): Promise<void> {
    if (hasNextPage.value) {
      await fetchMissions(currentPage.value + 1, filters)
    }
  }

  /**
   * Go to previous page (requires filters to be passed for consistency)
   */
  async function prevPage(filters?: MissionFilters): Promise<void> {
    if (hasPrevPage.value) {
      await fetchMissions(currentPage.value - 1, filters)
    }
  }

  /**
   * Go to a specific page (requires filters to be passed for consistency)
   */
  async function goToPage(page: number, filters?: MissionFilters): Promise<void> {
    if (page >= 1 && page <= lastPage.value) {
      await fetchMissions(page, filters)
    }
  }

  /**
   * Refresh current page with current filters
   */
  async function refreshMissions(filters?: MissionFilters): Promise<void> {
    await fetchMissions(currentPage.value, filters)
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
    hasFiltersApplied,
    // Methods
    fetchMissions,
    nextPage,
    prevPage,
    goToPage,
    refreshMissions,
  }
}
