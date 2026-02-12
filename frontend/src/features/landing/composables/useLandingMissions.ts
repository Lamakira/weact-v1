import { ref } from 'vue'
import { isAxiosError } from 'axios'
import type { LandingMission } from '../types'
import { landingApi } from '../services/landingApi'

type MissionStrategy = 'recent' | 'featured' | 'random'

export function useLandingMissions() {
  const missions = ref<LandingMission[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const totalCount = ref(0)

  const strategy: MissionStrategy =
    (import.meta.env.VITE_LANDING_MISSION_STRATEGY as MissionStrategy) || 'recent'

  async function fetchMissions(): Promise<void> {
    isLoading.value = true
    error.value = null
    try {
      // TODO: When 'featured' strategy is needed, add backend support and switch here
      // TODO: When 'random' strategy is needed, implement shuffle or backend param
      // For now, all strategies fall through to 'recent' (API returns created_at DESC)
      const _strategy = strategy // reserved for future use
      void _strategy

      const response = await landingApi.getMissions({ per_page: 6, page: 1 })
      missions.value = response.data
      totalCount.value = response.meta.total
    } catch (err: unknown) {
      if (isAxiosError(err)) {
        if (err.response) {
          error.value = 'Impossible de charger les missions. Veuillez réessayer.'
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
    await fetchMissions()
  }

  function clearError(): void {
    error.value = null
  }

  return {
    missions,
    isLoading,
    error,
    totalCount,
    fetchMissions,
    retry,
    clearError,
  }
}
