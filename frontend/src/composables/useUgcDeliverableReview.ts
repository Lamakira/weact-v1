import { ref, type Ref } from 'vue'
import { isAxiosError } from 'axios'
import { producerApi } from '@/features/producer/services/producerApi'
import { getApiErrorMessage } from '@/features/auth/services/authApi'
import type { Deliverable, DeliverableReviewItem } from '@/components/ugc'

interface UseUgcDeliverableReviewReturn {
  items: Ref<DeliverableReviewItem[]>
  isLoading: Ref<boolean>
  isSubmitting: Ref<boolean>
  error: Ref<string | null>
  errorCode: Ref<string | null>
  fetchPending: () => Promise<void>
  validate: (id: string) => Promise<Deliverable | null>
  reject: (id: string, note: string) => Promise<Deliverable | null>
  requestRetouche: (id: string, note: string) => Promise<Deliverable | null>
}

/**
 * Inbox de validation Producteur (5A, 4.4). Liste les livrables in_review +
 * statue (valider/rejeter/retouche). Routing d'erreur par code d'envelope
 * (INVALID_STATUS), calque useUgcShipment/useUgcDeliverable.
 */
export function useUgcDeliverableReview(): UseUgcDeliverableReviewReturn {
  const items = ref<DeliverableReviewItem[]>([])
  const isLoading = ref(false)
  const isSubmitting = ref(false)
  const error = ref<string | null>(null)
  const errorCode = ref<string | null>(null)

  function extractErrorCode(err: unknown): string | null {
    return isAxiosError(err)
      ? (((err.response?.data as { error?: { code?: string } } | undefined)?.error?.code) ?? null)
      : null
  }

  async function fetchPending(): Promise<void> {
    isLoading.value = true
    error.value = null
    errorCode.value = null
    try {
      const response = await producerApi.listDeliverablesToReview()
      items.value = response.data
    } catch (err) {
      error.value = getApiErrorMessage(err)
      errorCode.value = extractErrorCode(err)
    } finally {
      isLoading.value = false
    }
  }

  async function runAction(
    call: () => Promise<{ data: Deliverable }>,
  ): Promise<Deliverable | null> {
    isSubmitting.value = true
    error.value = null
    errorCode.value = null
    try {
      const response = await call()
      return response.data
    } catch (err) {
      error.value = getApiErrorMessage(err)
      errorCode.value = extractErrorCode(err)
      return null
    } finally {
      isSubmitting.value = false
    }
  }

  const validate = (id: string) => runAction(() => producerApi.validateDeliverable(id))
  const reject = (id: string, note: string) => runAction(() => producerApi.rejectDeliverable(id, note))
  const requestRetouche = (id: string, note: string) =>
    runAction(() => producerApi.requestDeliverableRetouche(id, note))

  return { items, isLoading, isSubmitting, error, errorCode, fetchPending, validate, reject, requestRetouche }
}
