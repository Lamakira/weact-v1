import { ref, type Ref } from 'vue'
import { isAxiosError } from 'axios'
import { candidatureApi } from '../services/candidatureApi'
import type { Candidature } from '../types'
import { getApiErrorMessage } from '@/features/auth/services/authApi'

interface UseAcceptUgcDealReturn {
  isAccepting: Ref<boolean>
  error: Ref<string | null>
  errorCode: Ref<string | null>
  acceptUgcMission: (missionId: string) => Promise<Candidature | null>
}

/**
 * Acceptation directe d'une mission UGC (2.4) — la candidature atterrit
 * `confirmed`. `errorCode` expose le code de l'envelope backend pour router
 * les erreurs par code (paywall, MISSION_FULL…) sans parser le message.
 */
export function useAcceptUgcDeal(): UseAcceptUgcDealReturn {
  const isAccepting = ref(false)
  const error = ref<string | null>(null)
  const errorCode = ref<string | null>(null)

  async function acceptUgcMission(missionId: string): Promise<Candidature | null> {
    isAccepting.value = true
    error.value = null
    errorCode.value = null

    try {
      const response = await candidatureApi.acceptUgcMission(missionId)
      return response.data
    } catch (err) {
      error.value = getApiErrorMessage(err)
      errorCode.value = isAxiosError(err)
        ? (((err.response?.data as { error?: { code?: string } } | undefined)?.error?.code) ?? null)
        : null
      return null
    } finally {
      isAccepting.value = false
    }
  }

  return { isAccepting, error, errorCode, acceptUgcMission }
}
