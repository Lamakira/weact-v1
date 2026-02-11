import { ref, type Ref } from 'vue'
import {
  adminMissionsApi,
  type AdminMissionData,
  type AdminMissionListParams,
} from '../services/adminMissionsApi'
import { getApiErrorMessage } from '../services/adminAuthApi'

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

/**
 * Composable for admin mission management operations (read-only)
 */
export function useAdminMissions() {
  const missions: Ref<AdminMissionData[]> = ref([])
  const mission: Ref<AdminMissionData | null> = ref(null)
  const pagination: Ref<PaginationMeta | null> = ref(null)
  const isLoading = ref(false)
  const error: Ref<string | null> = ref(null)

  /**
   * Fetch paginated list of missions with optional search/filters
   */
  async function fetchMissions(params?: AdminMissionListParams): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await adminMissionsApi.getMissions(params)
      missions.value = response.data
      pagination.value = response.meta
    } catch (err) {
      error.value = getApiErrorMessage(err) ?? 'Une erreur est survenue'
      missions.value = []
      pagination.value = null
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Fetch a single mission by ID
   */
  async function fetchMission(id: number): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await adminMissionsApi.getMission(id)
      mission.value = response.data
    } catch (err) {
      error.value = getApiErrorMessage(err) ?? 'Une erreur est survenue'
      mission.value = null
    } finally {
      isLoading.value = false
    }
  }

  return {
    missions,
    mission,
    pagination,
    isLoading,
    error,
    fetchMissions,
    fetchMission,
  }
}
