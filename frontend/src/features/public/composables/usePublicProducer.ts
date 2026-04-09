import { ref, computed } from 'vue'
import { publicApi } from '../services/publicApi'
import type { PublicProducer, PublicProducerResult } from '../types'
import type { AxiosError } from 'axios'

/**
 * Composable for fetching and displaying public Producer profiles.
 *
 * Note: This composable creates NEW state for each component instance (non-singleton).
 * Each call to usePublicProducer() returns independent refs. This is intentional
 * as different components may need to display different producer profiles.
 */
export function usePublicProducer() {
  const producer = ref<PublicProducer | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const notFound = ref(false)

  // Computed: Check if producer is an agency
  const isAgency = computed(() => producer.value?.type === 'agency')

  /**
   * Fetch a Producer's public profile by ID
   */
  async function fetchProducer(id: string): Promise<PublicProducerResult> {
    isLoading.value = true
    error.value = null
    notFound.value = false
    producer.value = null

    try {
      const response = await publicApi.getProducer(id)
      producer.value = response.data

      return {
        success: true,
        producer: response.data,
      }
    } catch (err) {
      const axiosError = err as AxiosError

      // Handle 404 - Producer not found
      if (axiosError.response?.status === 404) {
        notFound.value = true
        return {
          success: false,
          notFound: true,
          error: 'Producteur introuvable',
        }
      }

      // Handle network or other errors
      const errorMessage = 'Une erreur est survenue. Veuillez réessayer.'
      error.value = errorMessage

      return {
        success: false,
        error: errorMessage,
      }
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Clear the current producer data
   */
  function clearProducer(): void {
    producer.value = null
    error.value = null
    notFound.value = false
  }

  return {
    // State
    producer,
    isLoading,
    error,
    notFound,
    // Computed
    isAgency,
    // Actions
    fetchProducer,
    clearProducer,
  }
}
