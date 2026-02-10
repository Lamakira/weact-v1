import { ref, type Ref } from 'vue'
import {
  adminsApi,
  type AdminData,
  type CreateAdminForm,
} from '../services/adminsApi'
import { getApiErrorDetails, getApiErrorMessage } from '../services/adminAuthApi'

export interface AdminActionResult {
  success: boolean
  errors?: Record<string, string[]>
  message?: string
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

/**
 * Composable for admin management operations (list + create)
 */
export function useAdmins() {
  const admins: Ref<AdminData[]> = ref([])
  const pagination: Ref<PaginationMeta | null> = ref(null)
  const isLoading = ref(false)
  const error: Ref<string | null> = ref(null)

  /**
   * Fetch paginated list of admins
   */
  async function fetchAdmins(page: number = 1): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await adminsApi.getAdmins(page)
      admins.value = response.data
      pagination.value = response.meta
    } catch (err) {
      error.value = getApiErrorMessage(err) ?? 'Une erreur est survenue'
      admins.value = []
      pagination.value = null
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Create a new admin account
   */
  async function createAdmin(data: CreateAdminForm): Promise<AdminActionResult> {
    isLoading.value = true

    try {
      const response = await adminsApi.createAdmin(data)
      return { success: true, message: response.message }
    } catch (err) {
      const errors = getApiErrorDetails(err)
      const message = getApiErrorMessage(err)
      return { success: false, errors, message }
    } finally {
      isLoading.value = false
    }
  }

  return {
    admins,
    pagination,
    isLoading,
    error,
    fetchAdmins,
    createAdmin,
  }
}
