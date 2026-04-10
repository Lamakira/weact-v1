import { ref, type Ref } from 'vue'
import {
  adminProducersApi,
  type AdminProducerData,
  type AdminProducerListParams,
  type UpdateAdminProducerForm,
} from '../services/adminProducersApi'
import { getApiErrorDetails, getApiErrorMessage } from '../services/adminAuthApi'

interface AdminProducerActionResult {
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
 * Composable for admin producer management operations
 */
export function useAdminProducers() {
  const producers: Ref<AdminProducerData[]> = ref([])
  const producer: Ref<AdminProducerData | null> = ref(null)
  const pagination: Ref<PaginationMeta | null> = ref(null)
  const isLoading = ref(false)
  const error: Ref<string | null> = ref(null)

  /**
   * Fetch paginated list of producers with optional search/filters
   */
  async function fetchProducers(params?: AdminProducerListParams): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await adminProducersApi.getProducers(params)
      producers.value = response.data
      pagination.value = response.meta
    } catch (err) {
      error.value = getApiErrorMessage(err) ?? 'Une erreur est survenue'
      producers.value = []
      pagination.value = null
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Fetch a single producer by ID
   */
  async function fetchProducer(id: string): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await adminProducersApi.getProducer(id)
      producer.value = response.data
    } catch (err) {
      error.value = getApiErrorMessage(err) ?? 'Une erreur est survenue'
      producer.value = null
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Update a producer's admin-editable fields
   */
  async function updateProducer(
    id: string,
    data: UpdateAdminProducerForm,
  ): Promise<AdminProducerActionResult> {
    isLoading.value = true

    try {
      const response = await adminProducersApi.updateProducer(id, data)
      producer.value = response.data
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
   * Toggle a producer's account activation status
   */
  async function toggleActive(id: string): Promise<AdminProducerActionResult> {
    isLoading.value = true

    try {
      const response = await adminProducersApi.toggleActive(id)
      producer.value = response.data
      // Update the producer in the list if present
      const index = producers.value.findIndex((p) => p.id === id)
      if (index !== -1) {
        producers.value[index] = response.data
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
   * Delete a producer and its associated user
   */
  async function deleteProducer(id: string): Promise<AdminProducerActionResult> {
    isLoading.value = true

    try {
      const response = await adminProducersApi.deleteProducer(id)
      return { success: true, message: response.message }
    } catch (err) {
      const message = getApiErrorMessage(err)
      return { success: false, message }
    } finally {
      isLoading.value = false
    }
  }

  return {
    producers,
    producer,
    pagination,
    isLoading,
    error,
    fetchProducers,
    fetchProducer,
    updateProducer,
    toggleActive,
    deleteProducer,
  }
}
