import { ref, type Ref } from 'vue'
import {
  adminEngagementsApi,
  type AdminEngagementData,
  type AdminEngagementListParams,
} from '../services/adminEngagementsApi'
import { getApiErrorMessage } from '../services/adminAuthApi'

interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

/**
 * Composable for the admin "Faces à contacter" engagements view (read-only).
 */
export function useAdminEngagements() {
  const engagements: Ref<AdminEngagementData[]> = ref([])
  const pagination: Ref<PaginationMeta | null> = ref(null)
  const isLoading = ref(false)
  const error: Ref<string | null> = ref(null)
  let requestSequence = 0

  /**
   * Fetch the paginated list of engagements with optional type/status/search filters.
   */
  async function fetchEngagements(params?: AdminEngagementListParams): Promise<void> {
    const requestId = ++requestSequence
    isLoading.value = true
    error.value = null

    try {
      const response = await adminEngagementsApi.getEngagements(params)
      if (requestId !== requestSequence) return

      engagements.value = response.data
      pagination.value = response.meta
    } catch (err) {
      if (requestId !== requestSequence) return

      error.value = getApiErrorMessage(err) ?? 'Une erreur est survenue'
      engagements.value = []
      pagination.value = null
    } finally {
      if (requestId === requestSequence) {
        isLoading.value = false
      }
    }
  }

  return {
    engagements,
    pagination,
    isLoading,
    error,
    fetchEngagements,
  }
}
