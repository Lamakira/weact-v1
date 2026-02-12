import { ref } from 'vue'
import { isAxiosError } from 'axios'
import type { LandingFace } from '../types'
import { landingApi } from '../services/landingApi'

export function useLandingFaces() {
  const faces = ref<LandingFace[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const totalCount = ref(0)

  async function fetchFaces(): Promise<void> {
    isLoading.value = true
    error.value = null
    try {
      const response = await landingApi.getFaces({ per_page: 30, page: 1 })
      totalCount.value = response.meta.total
      // Filter client-side for faces that have a profile photo
      faces.value = response.data.filter(
        (face) => face.profile_photo_url !== null,
      )
    } catch (err: unknown) {
      if (isAxiosError(err)) {
        if (err.response) {
          error.value = 'Impossible de charger les profils. Veuillez réessayer.'
        } else {
          error.value = 'Erreur réseau. Vérifiez votre connexion internet.'
        }
      } else {
        error.value = 'Une erreur inattendue est survenue.'
      }
    } finally {
      isLoading.value = false
    }
  }

  async function retry(): Promise<void> {
    await fetchFaces()
  }

  function clearError(): void {
    error.value = null
  }

  return {
    faces,
    isLoading,
    error,
    totalCount,
    fetchFaces,
    retry,
    clearError,
  }
}
