import { ref } from 'vue'
import { isAxiosError } from 'axios'
import type { LandingFace } from '../types'
import { landingApi } from '../services/landingApi'

const PAGE_SIZE = 30
const MAX_PAGES = 3

export function useLandingFaces() {
  const faces = ref<LandingFace[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const totalCount = ref(0)

  async function fetchFaces(): Promise<void> {
    isLoading.value = true
    error.value = null
    try {
      const photoFaces: LandingFace[] = []
      let page = 1
      let lastPage = 1

      do {
        const response = await landingApi.getFaces({ per_page: PAGE_SIZE, page })
        totalCount.value = response.meta.total
        lastPage = response.meta.last_page

        photoFaces.push(
          ...response.data.filter((face) => face.profile_photo_url !== null),
        )

        page += 1
      } while (
        photoFaces.length < PAGE_SIZE &&
        page <= lastPage &&
        page <= MAX_PAGES
      )

      faces.value = photoFaces
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
