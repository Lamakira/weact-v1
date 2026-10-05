import { ref } from 'vue'
import { passwordChangeApi } from '../services/passwordChangeApi'
import { getApiErrorCode, getApiErrorDetails, getApiErrorMessage } from '../services/authApi'

export function usePasswordChange() {
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const errorCode = ref<string | null>(null)
  const fieldErrors = ref<Record<string, string[]>>({})

  async function run(request: () => Promise<unknown>): Promise<boolean> {
    isLoading.value = true
    error.value = null
    errorCode.value = null
    fieldErrors.value = {}

    try {
      await request()
      return true
    } catch (err: unknown) {
      fieldErrors.value = getApiErrorDetails(err)
      error.value = getApiErrorMessage(err)
      errorCode.value = getApiErrorCode(err)
      return false
    } finally {
      isLoading.value = false
    }
  }

  async function changePassword(
    currentPassword: string,
    newPassword: string,
    newPasswordConfirmation: string,
  ): Promise<boolean> {
    return run(() =>
      passwordChangeApi.changePassword(currentPassword, newPassword, newPasswordConfirmation),
    )
  }

  /** First password of a password-less account, confirmed by a Google re-auth ticket. */
  async function setPassword(
    newPassword: string,
    newPasswordConfirmation: string,
    reauthToken: string,
  ): Promise<boolean> {
    return run(() =>
      passwordChangeApi.setPassword(newPassword, newPasswordConfirmation, reauthToken),
    )
  }

  function clearError(): void {
    error.value = null
    errorCode.value = null
    fieldErrors.value = {}
  }

  return {
    isLoading,
    error,
    errorCode,
    fieldErrors,
    changePassword,
    setPassword,
    clearError,
  }
}
