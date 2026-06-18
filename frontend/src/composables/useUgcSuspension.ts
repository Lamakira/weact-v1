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
  // [5.4] état des actions resume/appeal (écran 10A)
  const isActing = ref(false)
  const actionError = ref<string | null>(null)

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

  /**
   * [5.4] Terminer en retard : POST resume (5.3). true=succès (la page navigue,
   * pas de refetch) ; false=échec (actionError peuplé via l'enveloppe 422).
   */
  async function resume(): Promise<boolean> {
    isActing.value = true
    actionError.value = null
    try {
      await faceApi.resumeUgcSuspension()
      return true
    } catch (err: unknown) {
      actionError.value = getApiErrorMessage(err)
      return false
    } finally {
      isActing.value = false
    }
  }

  /**
   * [5.4] Faire appel : POST appeal (5.3) puis refetch (appeal_status → pending).
   * true=succès ; false=échec (actionError peuplé).
   */
  async function appeal(): Promise<boolean> {
    isActing.value = true
    actionError.value = null
    try {
      await faceApi.appealUgcSuspension()
      await fetchStatus()
      return true
    } catch (err: unknown) {
      actionError.value = getApiErrorMessage(err)
      return false
    } finally {
      isActing.value = false
    }
  }

  return { isSuspended, suspension, isLoading, error, fetchStatus, isActing, actionError, resume, appeal }
}
