import { ref, type Ref } from 'vue'
import { isAxiosError } from 'axios'
import { bookingApi } from '@/features/booking/services/bookingApi'
import { candidatureApi } from '@/features/candidature/services/candidatureApi'
import { getApiErrorMessage } from '@/features/auth/services/authApi'
import type { ConfirmShipmentPayload, Shipment } from '@/components/ugc'

export type UgcShipmentOwnerKind = 'booking' | 'candidature'

interface UseUgcShipmentReturn {
  isSubmitting: Ref<boolean>
  error: Ref<string | null>
  errorCode: Ref<string | null>
  confirmShipment: (
    kind: UgcShipmentOwnerKind,
    ownerId: string,
    payload: ConfirmShipmentPayload,
  ) => Promise<Shipment | null>
  clearError: () => void
}

/**
 * Confirmation d'expédition UGC, dual-owner booking|candidature (3.2).
 * Calque useUgcCommissionPayment (placement) + useBookingActions (erreurs) :
 * le code d'envelope (ALREADY_SHIPPED…) est exposé pour le routing d'erreur.
 */
export function useUgcShipment(): UseUgcShipmentReturn {
  const isSubmitting = ref(false)
  const error = ref<string | null>(null)
  const errorCode = ref<string | null>(null)

  function clearError(): void {
    error.value = null
    errorCode.value = null
  }

  function extractErrorCode(err: unknown): string | null {
    return isAxiosError(err)
      ? (((err.response?.data as { error?: { code?: string } } | undefined)?.error?.code) ?? null)
      : null
  }

  async function confirmShipment(
    kind: UgcShipmentOwnerKind,
    ownerId: string,
    payload: ConfirmShipmentPayload,
  ): Promise<Shipment | null> {
    isSubmitting.value = true
    clearError()

    try {
      const response =
        kind === 'booking'
          ? await bookingApi.confirmShipment(ownerId, payload)
          : await candidatureApi.confirmShipment(ownerId, payload)

      return response.data
    } catch (err) {
      error.value = getApiErrorMessage(err)
      errorCode.value = extractErrorCode(err)
      return null
    } finally {
      isSubmitting.value = false
    }
  }

  return { isSubmitting, error, errorCode, confirmShipment, clearError }
}
