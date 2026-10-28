import { ref } from 'vue'
import { missionApi } from '../services/missionApi'
import { getApiErrorMessage } from '@/features/auth/services/authApi'
import type { Mission } from '../types'

/**
 * Composable pour ajouter des places à une mission UGC
 * Gère l'état de chargement, l'erreur affichable et l'appel API
 */
export function useAddMissionPlaces() {
  const isAdding = ref(false)
  const error = ref<string | null>(null)

  /**
   * Ajoute `places` places à la mission
   * @returns succès, message et mission rafraîchie
   */
  async function addPlaces(
    missionId: string,
    places: number
  ): Promise<{ success: boolean; message: string; data?: Mission }> {
    isAdding.value = true
    error.value = null

    try {
      const response = await missionApi.addPlaces(missionId, places)
      return { success: true, message: response.message || '', data: response.data }
    } catch (err: unknown) {
      error.value = getApiErrorMessage(err)
      return { success: false, message: error.value }
    } finally {
      isAdding.value = false
    }
  }

  function resetError(): void {
    error.value = null
  }

  return {
    isAdding,
    error,
    addPlaces,
    resetError,
  }
}
