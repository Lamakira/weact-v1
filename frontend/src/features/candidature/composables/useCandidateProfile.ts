import { ref, computed, watch, type Ref } from 'vue'
import { candidatureApi } from '../services/candidatureApi'
import type { CandidateFullProfile } from '../types'

/**
 * Composable for fetching a candidate's full profile (Producer only)
 * Handles loading states, error handling, and data management
 */
export function useCandidateProfile(faceId: Ref<number>) {
  const candidate = ref<CandidateFullProfile | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  /**
   * Computed: Full display name from prenom + nom
   */
  const displayName = computed((): string => {
    if (!candidate.value) return ''
    const { prenom, nom } = candidate.value
    return [prenom, nom].filter(Boolean).join(' ') || 'Candidat'
  })

  /**
   * Computed: Check if candidate has presentation video
   */
  const hasPresentationVideo = computed(
    (): boolean => !!candidate.value?.presentation_video_url,
  )

  /**
   * Computed: Check if candidate has acting video
   */
  const hasActingVideo = computed((): boolean => !!candidate.value?.acting_video_url)

  /**
   * Computed: Check if candidate has any videos
   */
  const hasAnyVideos = computed(
    (): boolean => hasPresentationVideo.value || hasActingVideo.value,
  )

  /**
   * Computed: Check if candidate has photos
   */
  const hasPhotos = computed(
    (): boolean => (candidate.value?.photos?.length ?? 0) > 0,
  )

  /**
   * Computed: Check if candidate has experiences
   */
  const hasExperiences = computed(
    (): boolean => (candidate.value?.experiences?.length ?? 0) > 0,
  )

  /**
   * Fetch candidate profile from API
   */
  async function fetchProfile(): Promise<void> {
    // Validate faceId
    if (!faceId.value || isNaN(faceId.value) || faceId.value <= 0) {
      error.value = 'ID de candidat invalide'
      return
    }

    isLoading.value = true
    error.value = null

    try {
      const response = await candidatureApi.getCandidateProfile(faceId.value)
      candidate.value = response.data
    } catch (err: unknown) {
      console.error('Failed to fetch candidate profile:', err)

      // Handle specific error codes
      if (err && typeof err === 'object' && 'response' in err) {
        const response = (err as { response?: { status?: number } }).response
        if (response?.status === 403) {
          error.value = 'Vous ne pouvez consulter que les profils des candidats ayant postulé à vos missions'
        } else if (response?.status === 404) {
          error.value = 'Candidat introuvable'
        } else {
          error.value = 'Impossible de charger le profil du candidat'
        }
      } else {
        error.value = 'Erreur de connexion'
      }

      candidate.value = null
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Refresh profile data
   */
  async function refresh(): Promise<void> {
    await fetchProfile()
  }

  /**
   * Retry fetching profile after error
   */
  async function retry(): Promise<void> {
    error.value = null
    await fetchProfile()
  }

  // Watch for faceId changes and refetch
  watch(
    faceId,
    (newId, oldId) => {
      if (newId !== oldId && newId > 0) {
        fetchProfile()
      }
    },
    { immediate: true },
  )

  return {
    // State
    candidate,
    isLoading,
    error,

    // Computed
    displayName,
    hasPresentationVideo,
    hasActingVideo,
    hasAnyVideos,
    hasPhotos,
    hasExperiences,

    // Methods
    fetchProfile,
    refresh,
    retry,
  }
}
