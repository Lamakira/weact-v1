import { ref } from 'vue'
import { faceApi } from '@/features/face/services/faceApi'
import { getApiErrorMessage } from '@/features/auth/services/authApi'
import type { UgcSuspensionStatus } from '@/components/ugc'

/**
 * État de suspension UGC de la Face (écran 10A, story 5.2). Source serveur
 * autoritative + suspension-aware (PAS les capabilities cachées — D-2.2.b).
 *
 * Instances indépendantes : la bannière et la page appellent chacune
 * useUgcSuspension() (2 GET légers) ; un store partagé est une optim déférée.
 */
export function useUgcSuspension() {
  const isSuspended = ref(false)
  const suspension = ref<UgcSuspensionStatus | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  async function fetchStatus(): Promise<void> {
    isLoading.value = true
    error.value = null
    try {
      const response = await faceApi.getUgcSuspensionStatus()
      isSuspended.value = response.data.is_suspended
      suspension.value = response.data.suspension
    } catch (err: unknown) {
      error.value = getApiErrorMessage(err)
      isSuspended.value = false
      suspension.value = null
    } finally {
      isLoading.value = false
    }
  }

  return { isSuspended, suspension, isLoading, error, fetchStatus }
}
