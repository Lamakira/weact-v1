import { ref, computed } from 'vue'
import { isAxiosError } from 'axios'
import type { CandidatureStatusType, FaceCandidature } from '../types'
import { candidatureApi } from '../services/candidatureApi'
import { getApiErrorMessage } from '@/features/auth/services/authApi'

/**
 * Composable for fetching and managing Face's candidatures list
 */
export function useFaceCandidatures() {
  const candidatures = ref<FaceCandidature[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  // Pagination state
  const currentPage = ref(1)
  const lastPage = ref(1)
  const total = ref(0)
  const perPage = ref(15)

  // Filter state
  const statusFilter = ref<CandidatureStatusType | ''>('')

  // Request tracking to prevent race conditions
  let currentRequestId = 0

  // Computed
  const hasNextPage = computed(() => currentPage.value < lastPage.value)
  const hasPrevPage = computed(() => currentPage.value > 1)
  const isEmpty = computed(() => candidatures.value.length === 0 && !isLoading.value)

  /**
   * Fetch candidatures with current page and filter
   * Uses request tracking to prevent race conditions when filter changes rapidly
   */
  async function fetchCandidatures(page: number = 1): Promise<void> {
    const requestId = ++currentRequestId
    isLoading.value = true
    error.value = null

    try {
      const response = await candidatureApi.getCandidatures(page, statusFilter.value)

      // Ignore stale responses from previous requests
      if (requestId !== currentRequestId) return

      candidatures.value = response.data
      currentPage.value = response.meta.current_page
      lastPage.value = response.meta.last_page
      total.value = response.meta.total
      perPage.value = response.meta.per_page
    } catch (err: unknown) {
      // Ignore errors from stale requests
      if (requestId !== currentRequestId) return

      if (isAxiosError(err)) {
        if (err.response?.status === 401) {
          error.value = 'Veuillez vous connecter pour voir vos candidatures'
        } else if (err.response?.status === 403) {
          error.value = 'Accès réservé aux Faces'
        } else {
          error.value = getApiErrorMessage(err)
        }
      } else {
        error.value = 'Une erreur inattendue est survenue'
      }
    } finally {
      // Only update loading state if this is the current request
      if (requestId === currentRequestId) {
        isLoading.value = false
      }
    }
  }

  /**
   * Go to next page
   */
  async function nextPage(): Promise<void> {
    if (hasNextPage.value) {
      await fetchCandidatures(currentPage.value + 1)
    }
  }

  /**
   * Go to previous page
   */
  async function prevPage(): Promise<void> {
    if (hasPrevPage.value) {
      await fetchCandidatures(currentPage.value - 1)
    }
  }

  /**
   * Go to specific page
   */
  async function goToPage(page: number): Promise<void> {
    if (page >= 1 && page <= lastPage.value) {
      await fetchCandidatures(page)
    }
  }

  /**
   * Set status filter and refetch from page 1
   */
  async function setStatusFilter(status: CandidatureStatusType | ''): Promise<void> {
    statusFilter.value = status
    await fetchCandidatures(1)
  }

  /**
   * Clear filter and refetch
   */
  async function clearFilter(): Promise<void> {
    await setStatusFilter('')
  }

  /**
   * Refresh current page
   */
  async function refresh(): Promise<void> {
    await fetchCandidatures(currentPage.value)
  }

  return {
    // State
    candidatures,
    isLoading,
    error,
    // Pagination
    currentPage,
    lastPage,
    total,
    perPage,
    hasNextPage,
    hasPrevPage,
    isEmpty,
    // Filter
    statusFilter,
    // Actions
    fetchCandidatures,
    nextPage,
    prevPage,
    goToPage,
    setStatusFilter,
    clearFilter,
    refresh,
  }
}
