import { ref } from 'vue'
import {
  adminBookingDisputesApi,
  type AdminBookingDispute,
  type AdminStalePaidBooking,
  type BookingDisputeOutcome,
} from '../services/adminBookingDisputesApi'
import { getApiErrorMessage } from '../services/adminAuthApi'

export function useAdminBookingDisputes() {
  const disputes = ref<AdminBookingDispute[]>([])
  const stalePaid = ref<AdminStalePaidBooking[]>([])
  const isLoading = ref(false)
  const isResolving = ref(false)
  const error = ref<string | null>(null)
  const resolveError = ref<string | null>(null)
  const resolveSuccess = ref<string | null>(null)

  async function fetchDisputes(options: { clearResolveSuccess?: boolean } = {}): Promise<boolean> {
    const { clearResolveSuccess = true } = options
    isLoading.value = true
    error.value = null
    if (clearResolveSuccess) {
      resolveSuccess.value = null
    }

    try {
      const response = await adminBookingDisputesApi.getDisputes()
      disputes.value = response.data.disputes
      stalePaid.value = response.data.stale_paid
      return true
    } catch (err) {
      error.value = getApiErrorMessage(err) ?? 'Impossible de charger les litiges.'
      disputes.value = []
      stalePaid.value = []
      return false
    } finally {
      isLoading.value = false
    }
  }

  async function resolveDispute(
    id: string,
    outcome: BookingDisputeOutcome,
    notes: string,
  ): Promise<boolean> {
    isResolving.value = true
    resolveError.value = null
    resolveSuccess.value = null

    try {
      const response = await adminBookingDisputesApi.resolveDispute(id, outcome, notes)
      resolveSuccess.value = response.message

      const refetched = await fetchDisputes({ clearResolveSuccess: false })
      if (!refetched) {
        resolveError.value = `Litige résolu, mais la liste n'a pas pu être rafraîchie : ${error.value}. Cliquez sur Actualiser pour réessayer.`
      }

      return true
    } catch (err) {
      resolveError.value = getApiErrorMessage(err) ?? 'Impossible de résoudre le litige.'
      await fetchDisputes({ clearResolveSuccess: false })
      return false
    } finally {
      isResolving.value = false
    }
  }

  return {
    disputes,
    stalePaid,
    isLoading,
    isResolving,
    error,
    resolveError,
    resolveSuccess,
    fetchDisputes,
    resolveDispute,
  }
}
