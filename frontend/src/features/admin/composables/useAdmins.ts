import { ref, type Ref } from 'vue'
import {
  adminsApi,
  type AdminData,
  type CreateAdminForm,
  type UpdateAdminForm,
} from '../services/adminsApi'
import { getApiErrorDetails, getApiErrorMessage } from '../services/adminAuthApi'

interface AdminActionResult {
  success: boolean
  errors?: Record<string, string[]>
  message?: string
}

interface PaginationMeta {
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
  const admin: Ref<AdminData | null> = ref(null)
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

  /**
   * Fetch a single admin by ID
   */
  async function fetchAdmin(id: string): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await adminsApi.getAdmin(id)
      admin.value = response.data
    } catch (err) {
      error.value = getApiErrorMessage(err) ?? 'Une erreur est survenue'
      admin.value = null
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Update an admin's fields
   */
  async function updateAdmin(id: string, data: UpdateAdminForm): Promise<AdminActionResult> {
    isLoading.value = true

    try {
      const response = await adminsApi.updateAdmin(id, data)
      admin.value = response.data
      return { success: true, message: response.message }
    } catch (err) {
      const errors = getApiErrorDetails(err)
      const message = getApiErrorMessage(err)
      return { success: false, errors, message }
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Delete an admin account
   */
  async function deleteAdmin(id: string): Promise<AdminActionResult> {
    isLoading.value = true

    try {
      const response = await adminsApi.deleteAdmin(id)
      return { success: true, message: response.message }
    } catch (err) {
      const message = getApiErrorMessage(err)
      return { success: false, message }
    } finally {
      isLoading.value = false
    }
  }

  return {
    admins,
    admin,
    pagination,
    isLoading,
    error,
    fetchAdmins,
    fetchAdmin,
    createAdmin,
    updateAdmin,
    deleteAdmin,
  }
}
