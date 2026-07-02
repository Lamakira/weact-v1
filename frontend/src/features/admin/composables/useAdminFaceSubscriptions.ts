import { ref, type Ref } from 'vue'
import {
  adminFaceSubscriptionsApi,
  type AdminFaceSubscription,
  type ActivatePayload,
  type ExtendPayload,
  type CancelPayload,
  type CorrectPayload,
  type ChangeTierPayload,
} from '../services/adminFaceSubscriptionsApi'
import {
  getApiErrorCode,
} from '@/services/errorFormatter'
import {
  getApiErrorDetails,
  getApiErrorMessage,
} from '../services/adminAuthApi'

export interface AdminSubscriptionActionResult {
  success: boolean
  errors?: Record<string, string[]>
  message?: string
  code?: string | null
}

interface FaceDisplay {
  id: string
  display_name: string
}

export function useAdminFaceSubscriptions() {
  const subscriptions: Ref<AdminFaceSubscription[]> = ref([])
  const faceDisplay: Ref<FaceDisplay | null> = ref(null)
  const isLoading = ref(false)
  const error: Ref<string | null> = ref(null)
  let fetchRequestId = 0
  const controllers = new Set<AbortController>()

  function createController(): AbortController {
    const controller = new AbortController()
    controllers.add(controller)
    return controller
  }

  function releaseController(controller: AbortController): void {
    controllers.delete(controller)
  }

  function abortRequests(): void {
    fetchRequestId += 1
    controllers.forEach((controller) => controller.abort())
    controllers.clear()
    isLoading.value = false
  }

  function isUnauthorizedError(err: unknown): boolean {
    return (
      typeof err === 'object' &&
      err !== null &&
      'response' in err &&
      typeof (err as { response?: { status?: unknown } }).response === 'object' &&
      (err as { response?: { status?: unknown } }).response?.status === 401
    )
  }

  function isAbortError(err: unknown): boolean {
    return (
      typeof err === 'object' &&
      err !== null &&
      ('name' in err || 'code' in err) &&
      ((err as { name?: unknown }).name === 'CanceledError' ||
        (err as { code?: unknown }).code === 'ERR_CANCELED')
    )
  }

  async function fetchSubscriptions(faceId: string): Promise<void> {
    const requestId = ++fetchRequestId
    const controller = createController()
    isLoading.value = true
    error.value = null
    try {
      const response = await adminFaceSubscriptionsApi.index(faceId, {
        signal: controller.signal,
      })
      if (requestId !== fetchRequestId) {
        return
      }
      subscriptions.value = response.data.subscriptions
      faceDisplay.value = response.data.face
    } catch (err) {
      if (isAbortError(err)) {
        throw err
      }
      if (isUnauthorizedError(err)) {
        throw err
      }
      if (requestId !== fetchRequestId) {
        return
      }
      error.value = getApiErrorMessage(err) ?? 'Une erreur est survenue'
    } finally {
      if (requestId === fetchRequestId) {
        isLoading.value = false
      }
      releaseController(controller)
    }
  }

  function toFailureResult(err: unknown): AdminSubscriptionActionResult {
    if (isAbortError(err)) {
      throw err
    }

    if (isUnauthorizedError(err)) {
      throw err
    }

    return {
      success: false,
      errors: getApiErrorDetails(err),
      message: getApiErrorMessage(err),
      code: getApiErrorCode(err),
    }
  }

  // Mutations intentionally do NOT toggle `isLoading`. That ref drives the
  // section's list-vs-spinner branch; each modal owns its own `*Submitting`
  // ref for button-disable. Toggling here would unmount the list under the
  // open modal and flash a spinner on success between mutate and refetch.
  async function activate(
    faceId: string,
    payload: ActivatePayload,
  ): Promise<AdminSubscriptionActionResult> {
    const controller = createController()
    try {
      const response = await adminFaceSubscriptionsApi.activate(faceId, payload, {
        signal: controller.signal,
      })
      return { success: true, message: response.message }
    } catch (err) {
      return toFailureResult(err)
    } finally {
      releaseController(controller)
    }
  }

  async function extend(
    subscriptionId: string,
    payload: ExtendPayload,
  ): Promise<AdminSubscriptionActionResult> {
    const controller = createController()
    try {
      const response = await adminFaceSubscriptionsApi.extend(subscriptionId, payload, {
        signal: controller.signal,
      })
      return { success: true, message: response.message }
    } catch (err) {
      return toFailureResult(err)
    } finally {
      releaseController(controller)
    }
  }

  async function cancel(
    subscriptionId: string,
    payload: CancelPayload,
  ): Promise<AdminSubscriptionActionResult> {
    const controller = createController()
    try {
      const response = await adminFaceSubscriptionsApi.cancel(subscriptionId, payload, {
        signal: controller.signal,
      })
      return { success: true, message: response.message }
    } catch (err) {
      return toFailureResult(err)
    } finally {
      releaseController(controller)
    }
  }

  async function correct(
    subscriptionId: string,
    payload: CorrectPayload,
  ): Promise<AdminSubscriptionActionResult> {
    const controller = createController()
    try {
      const response = await adminFaceSubscriptionsApi.correct(subscriptionId, payload, {
        signal: controller.signal,
      })
      return { success: true, message: response.message }
    } catch (err) {
      return toFailureResult(err)
    } finally {
      releaseController(controller)
    }
  }

  async function changeTier(
    subscriptionId: string,
    payload: ChangeTierPayload,
  ): Promise<AdminSubscriptionActionResult> {
    const controller = createController()
    try {
      const response = await adminFaceSubscriptionsApi.changeTier(subscriptionId, payload, {
        signal: controller.signal,
      })
      return { success: true, message: response.message }
    } catch (err) {
      return toFailureResult(err)
    } finally {
      releaseController(controller)
    }
  }

  return {
    subscriptions,
    faceDisplay,
    isLoading,
    error,
    fetchSubscriptions,
    abortRequests,
    activate,
    extend,
    cancel,
    correct,
    changeTier,
  }
}
