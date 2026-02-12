import { ref } from 'vue'
import { emailChangeApi } from '../services/emailChangeApi'
import { getApiErrorDetails, getApiErrorMessage } from '../services/authApi'

export interface UseEmailChangeReturn {
  pendingEmail: ReturnType<typeof ref<string | null>>
  isLoading: ReturnType<typeof ref<boolean>>
  isSuccess: ReturnType<typeof ref<boolean>>
  error: ReturnType<typeof ref<string | null>>
  fieldErrors: ReturnType<typeof ref<Record<string, string[]>>>
  requestChange: (email: string, password: string) => Promise<boolean>
  cancelChange: () => Promise<boolean>
  fetchStatus: () => Promise<void>
  clearError: () => void
}

export function useEmailChange(): UseEmailChangeReturn {
  const pendingEmail = ref<string | null>(null)
  const isLoading = ref(false)
  const isSuccess = ref(false)
  const error = ref<string | null>(null)
  const fieldErrors = ref<Record<string, string[]>>({})

  async function requestChange(email: string, password: string): Promise<boolean> {
    isLoading.value = true
    isSuccess.value = false
    error.value = null
    fieldErrors.value = {}

    try {
      const result = await emailChangeApi.requestChange(email, password)
      pendingEmail.value = result.pending_email
      isSuccess.value = true
      return true
    } catch (err: unknown) {
      fieldErrors.value = getApiErrorDetails(err)
      error.value = getApiErrorMessage(err)
      return false
    } finally {
      isLoading.value = false
    }
  }

  async function cancelChange(): Promise<boolean> {
    isLoading.value = true
    error.value = null

    try {
      await emailChangeApi.cancelChange()
      pendingEmail.value = null
      return true
    } catch (err: unknown) {
      error.value = getApiErrorMessage(err)
      return false
    } finally {
      isLoading.value = false
    }
  }

  async function fetchStatus(): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const result = await emailChangeApi.getStatus()
      pendingEmail.value = result.pending_email
    } catch (err: unknown) {
      error.value = getApiErrorMessage(err)
    } finally {
      isLoading.value = false
    }
  }

  function clearError(): void {
    error.value = null
    fieldErrors.value = {}
  }

  return {
    pendingEmail,
    isLoading,
    isSuccess,
    error,
    fieldErrors,
    requestChange,
    cancelChange,
    fetchStatus,
    clearError,
  }
}
