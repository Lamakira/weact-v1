import { ref, computed } from 'vue'
import { isAxiosError } from 'axios'
import { producerApi } from '../services/producerApi'
import type { ProducerBioResult } from '../types'
import { createSharedCachedResource } from '@/lib/createSharedCachedResource'
import { formatApiError, getApiErrorDetails } from '@/services/errorFormatter'

const MAX_BIO_LENGTH = 500
const BIO_CACHE_TTL_MS = 5 * 60 * 1000

const bioResource = createSharedCachedResource<string | null>({
  key: 'producer-bio',
  initialValue: null,
  ttlMs: BIO_CACHE_TTL_MS,
  load: async () => {
    const response = await producerApi.getBio()
    return response.data.bio
  },
  getErrorMessage: () => 'Erreur lors du chargement de la bio',
})

export function useProducerBio() {
  const bio = bioResource.data
  const isLoading = bioResource.isLoading
  const isSaving = ref(false)
  const error = bioResource.error

  const charCount = computed(() => bio.value?.length ?? 0)
  const isOverLimit = computed(() => charCount.value > MAX_BIO_LENGTH)
  const remainingChars = computed(() => MAX_BIO_LENGTH - charCount.value)
  const isNearLimit = computed(() => remainingChars.value <= 50 && remainingChars.value > 0)

  async function fetchBio(): Promise<ProducerBioResult> {
    const value = await bioResource.fetch()
    return {
      success: error.value === null,
      data: { bio: value },
      message: error.value ?? undefined,
    }
  }

  async function saveBio(newBio: string | null): Promise<ProducerBioResult> {
    // Validate before sending
    if (newBio && newBio.length > MAX_BIO_LENGTH) {
      const errorMessage = `La bio ne peut pas dépasser ${MAX_BIO_LENGTH} caractères`
      error.value = errorMessage
      return { success: false, message: errorMessage }
    }

    isSaving.value = true
    error.value = null

    try {
      const response = await producerApi.updateBio(newBio)
      bioResource.setData(response.data.bio)
      return {
        success: true,
        data: response.data,
        message: response.message || 'Bio mise à jour avec succès',
      }
    } catch (err) {
      if (isAxiosError(err) && err.response?.status === 422) {
        const validationErrors = getApiErrorDetails(err)
        const validationMessage = formatApiError(err, 'Erreur de validation')
        error.value = validationErrors.bio?.[0] || validationMessage
        return { success: false, errors: validationErrors, message: error.value }
      }
      const errorMessage = 'Erreur lors de la mise à jour de la bio'
      error.value = errorMessage
      return { success: false, message: errorMessage }
    } finally {
      isSaving.value = false
    }
  }

  function clearError(): void {
    bioResource.clearError()
  }

  return {
    bio,
    isLoading,
    isSaving,
    error,
    charCount,
    isOverLimit,
    remainingChars,
    isNearLimit,
    maxLength: MAX_BIO_LENGTH,
    fetchBio,
    saveBio,
    clearError,
  }
}
