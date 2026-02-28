import { ref, watch, type Ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { candidatureApi } from '@/features/candidature/services/candidatureApi'
import type { CandidateFullProfile } from '@/features/candidature/types'
import axios from 'axios'

export type AccessLevel = 'guest' | 'face_user' | 'producer_with_access'

/**
 * Composable that determines the access level for a public Face profile page.
 *
 * - Guest → locked teasers with "S'inscrire pour voir"
 * - Face user → message "Profil complet réservé aux producteurs"
 * - Producer → full profile (any producer can view any Face)
 */
export function usePublicFaceAccess(faceId: Ref<number | null>) {
  const authStore = useAuthStore()

  const accessLevel = ref<AccessLevel>('guest')
  const fullProfile = ref<CandidateFullProfile | null>(null)
  const isLoadingFullProfile = ref(false)

  async function resolveAccess(id: number): Promise<void> {
    // Not authenticated → guest
    if (!authStore.isAuthenticated) {
      accessLevel.value = 'guest'
      fullProfile.value = null
      return
    }

    // Authenticated as Face → face_user
    if (authStore.isFace) {
      accessLevel.value = 'face_user'
      fullProfile.value = null
      return
    }

    // Authenticated as Producer → try fetching full profile
    if (authStore.isProducer) {
      isLoadingFullProfile.value = true
      try {
        const response = await candidatureApi.getCandidateProfile(id)
        fullProfile.value = response.data
        accessLevel.value = 'producer_with_access'
      } catch (err) {
        fullProfile.value = null
        if (axios.isAxiosError(err) && err.response?.status === 401) {
          // Token expired → fallback to guest
          accessLevel.value = 'guest'
        } else {
          // Generic error — fallback to guest so locked teasers show
          accessLevel.value = 'guest'
        }
      } finally {
        isLoadingFullProfile.value = false
      }
      return
    }

    // Fallback
    accessLevel.value = 'guest'
    fullProfile.value = null
  }

  // Watch faceId to auto-trigger when public profile loads
  watch(
    faceId,
    (newId) => {
      if (newId && newId > 0) {
        resolveAccess(newId)
      } else {
        accessLevel.value = 'guest'
        fullProfile.value = null
      }
    },
    { immediate: true },
  )

  return {
    accessLevel,
    fullProfile,
    isLoadingFullProfile,
  }
}
