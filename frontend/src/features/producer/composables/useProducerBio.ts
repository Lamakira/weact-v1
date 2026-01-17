import { ref, computed } from 'vue'
import { producerApi } from '../services/producerApi'
import type { ProducerBioResult } from '../types'
import { isAxiosError } from 'axios'

const MAX_BIO_LENGTH = 500

export function useProducerBio() {
  const bio = ref<string | null>(null)
  const isLoading = ref(false)
  const isSaving = ref(false)
  const error = ref<string | null>(null)

  const charCount = computed(() => bio.value?.length ?? 0)
  const isOverLimit = computed(() => charCount.value > MAX_BIO_LENGTH)
  const remainingChars = computed(() => MAX_BIO_LENGTH - charCount.value)
  const isNearLimit = computed(() => remainingChars.value <= 50 && remainingChars.value > 0)

  async function fetchBio(): Promise<ProducerBioResult> {
    isLoading.value = true
    error.value = null

    try {
      const response = await producerApi.getBio()
      bio.value = response.data.bio
      return { success: true, data: response.data }
    } catch (err) {
      const errorMessage = 'Erreur lors du chargement de la bio'
      error.value = errorMessage
      return { success: false, message: errorMessage }
    } finally {
      isLoading.value = false
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
      bio.value = response.data.bio
      return {
        success: true,
        data: response.data,
        message: response.message || 'Bio mise à jour avec succès',
      }
    } catch (err) {
      if (isAxiosError(err) && err.response?.status === 422) {
        const validationErrors = err.response.data.errors as Record<string, string[]>
        error.value = validationErrors.bio?.[0] || 'Erreur de validation'
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
    error.value = null
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
