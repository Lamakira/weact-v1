import { ref } from 'vue'
import { isAxiosError } from 'axios'
import type { Mission, MissionCandidature } from '../types'
import { faceMissionApi } from '../services/faceMissionApi'
import { getApiErrorMessage } from '@/features/auth/services/authApi'

/**
 * Composable for fetching and managing mission detail state
 */
export function useMissionDetail() {
  const mission = ref<Mission | null>(null)
  const candidature = ref<MissionCandidature | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const notFound = ref(false)
  const ugcPaywall = ref(false)
  const ugcPaywallMessage = ref<string | null>(null)
  // Face abonnée mais suspendue de l'UGC (403 UGC_SUSPENDED) : même affichage que le paywall, autre destination.
  const ugcSuspended = ref(false)

  /**
   * Fetch mission detail by ID
   * @param id The mission ID
   */
  async function fetchMission(id: string, options: { silent?: boolean } = {}): Promise<void> {
    // `silent` : relecture en place (pas de spinner plein écran) — la page reste montée et ses transitions jouent.
    const silent = options.silent === true
    isLoading.value = !silent
    if (!silent) error.value = null
    notFound.value = false
    ugcPaywall.value = false
    ugcPaywallMessage.value = null
    ugcSuspended.value = false

    try {
      const response = await faceMissionApi.getMissionDetail(id)
      mission.value = response.data
      candidature.value = response.candidature ?? null
    } catch (err: unknown) {
      if (isAxiosError(err) && err.response?.status === 404) {
        notFound.value = true
      } else if (
        isAxiosError(err) &&
        err.response?.status === 403 &&
        ['UGC_SUBSCRIPTION_REQUIRED', 'UGC_SUSPENDED'].includes(
          (err.response.data as { error?: { code?: string; message?: string } } | undefined)?.error?.code ?? '',
        )
      ) {
        ugcSuspended.value =
          (err.response.data as { error?: { code?: string } }).error?.code === 'UGC_SUSPENDED'
        // Gate UGC 2.1 (FR5) : la redirection /pricing est exécutée par la page.
        ugcPaywall.value = true
        ugcPaywallMessage.value =
          (err.response.data as { error?: { message?: string } }).error?.message ?? null
      } else if (!(silent && mission.value)) {
        // Relecture silencieuse échouée : on garde l'état affiché plutôt que de le remplacer par une erreur.
        error.value = getApiErrorMessage(err)
      }
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Update candidature after successful application
   * @param newCandidature The newly created candidature
   */
  function setCandidature(newCandidature: MissionCandidature): void {
    candidature.value = newCandidature
  }

  return {
    mission,
    candidature,
    isLoading,
    error,
    notFound,
    ugcPaywall,
    ugcPaywallMessage,
    ugcSuspended,
    fetchMission,
    setCandidature,
  }
}
