import { ref, computed } from 'vue'
import {
  fetchPublicFaceProfile,
  type PublicFaceProfile,
  type PublicFaceProfileResult,
} from '../services/publicFacesApi'

/**
 * Composable for fetching and displaying public Face profiles.
 *
 * Note: This composable creates NEW state for each component instance (non-singleton).
 * Each call to useFaceProfile() returns independent refs. This is intentional
 * as different components may need to display different face profiles.
 */
export function useFaceProfile() {
  const face = ref<PublicFaceProfile | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const notFound = ref(false)

  // Computed: Check if face has album photos
  const hasAlbumPhotos = computed(() => face.value?.has_album_photos ?? false)

  // Computed: Album photos count
  const albumPhotosCount = computed(() => face.value?.album_photos_count ?? 0)

  // Computed: Check if face has any videos
  const hasVideos = computed(
    () =>
      (face.value?.has_presentation_video ?? false) ||
      (face.value?.has_acting_video ?? false)
  )

  // Computed: Count of available videos
  const videosCount = computed(() => {
    let count = 0
    if (face.value?.has_presentation_video) count++
    if (face.value?.has_acting_video) count++
    return count
  })

  /**
   * Fetch a Face's public profile by username
   */
  async function fetchFace(username: string): Promise<PublicFaceProfileResult> {
    isLoading.value = true
    error.value = null
    notFound.value = false
    face.value = null

    try {
      const result = await fetchPublicFaceProfile(username)

      if (result.success && result.profile) {
        face.value = result.profile
      } else {
        if (result.notFound) {
          notFound.value = true
        }
        error.value = result.error ?? null
      }

      return result
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Clear the current face data
   */
  function clearFace(): void {
    face.value = null
    error.value = null
    notFound.value = false
  }

  return {
    // State
    face,
    isLoading,
    error,
    notFound,
    // Computed
    hasAlbumPhotos,
    albumPhotosCount,
    hasVideos,
    videosCount,
    // Actions
    fetchFace,
    clearFace,
  }
}
