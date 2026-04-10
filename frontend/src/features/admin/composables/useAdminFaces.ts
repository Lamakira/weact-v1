import { ref, type Ref } from 'vue'
import {
  adminFacesApi,
  type AdminFaceData,
  type AdminFaceListParams,
  type UpdateAdminFaceForm,
} from '../services/adminFacesApi'
import { getApiErrorDetails, getApiErrorMessage } from '../services/adminAuthApi'

interface AdminFaceActionResult {
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
 * Composable for admin face management operations
 */
export function useAdminFaces() {
  const faces: Ref<AdminFaceData[]> = ref([])
  const face: Ref<AdminFaceData | null> = ref(null)
  const pagination: Ref<PaginationMeta | null> = ref(null)
  const isLoading = ref(false)
  const error: Ref<string | null> = ref(null)

  /**
   * Fetch paginated list of faces with optional search/filters
   */
  async function fetchFaces(params?: AdminFaceListParams): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await adminFacesApi.getFaces(params)
      faces.value = response.data
      pagination.value = response.meta
    } catch (err) {
      error.value = getApiErrorMessage(err) ?? 'Une erreur est survenue'
      faces.value = []
      pagination.value = null
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Fetch a single face by ID
   */
  async function fetchFace(id: string): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await adminFacesApi.getFace(id)
      face.value = response.data
    } catch (err) {
      error.value = getApiErrorMessage(err) ?? 'Une erreur est survenue'
      face.value = null
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Update a face's admin-editable fields
   */
  async function updateFace(
    id: string,
    data: UpdateAdminFaceForm,
  ): Promise<AdminFaceActionResult> {
    isLoading.value = true

    try {
      const response = await adminFacesApi.updateFace(id, data)
      face.value = response.data
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
   * Toggle a face's account activation status
   */
  async function toggleActive(id: string): Promise<AdminFaceActionResult> {
    isLoading.value = true

    try {
      const response = await adminFacesApi.toggleActive(id)
      face.value = response.data
      // Update the face in the list if present
      const index = faces.value.findIndex((f) => f.id === id)
      if (index !== -1) {
        faces.value[index] = response.data
      }
      return { success: true, message: response.message }
    } catch (err) {
      const message = getApiErrorMessage(err)
      return { success: false, message }
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Delete a face and its associated user
   */
  async function deleteFace(id: string): Promise<AdminFaceActionResult> {
    isLoading.value = true

    try {
      const response = await adminFacesApi.deleteFace(id)
      return { success: true, message: response.message }
    } catch (err) {
      const message = getApiErrorMessage(err)
      return { success: false, message }
    } finally {
      isLoading.value = false
    }
  }

  return {
    faces,
    face,
    pagination,
    isLoading,
    error,
    fetchFaces,
    fetchFace,
    updateFace,
    toggleActive,
    deleteFace,
  }
}
