import { ref } from 'vue'
import { passwordChangeApi } from '../services/passwordChangeApi'
import { getApiErrorDetails, getApiErrorMessage } from '../services/authApi'

export function usePasswordChange() {
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const fieldErrors = ref<Record<string, string[]>>({})

  async function changePassword(
    currentPassword: string,
    newPassword: string,
    newPasswordConfirmation: string,
  ): Promise<boolean> {
    isLoading.value = true
    error.value = null
    fieldErrors.value = {}

    try {
      await passwordChangeApi.changePassword(currentPassword, newPassword, newPasswordConfirmation)
      return true
    } catch (err: unknown) {
      fieldErrors.value = getApiErrorDetails(err)
      error.value = getApiErrorMessage(err)
      return false
    } finally {
      isLoading.value = false
    }
  }

  function clearError(): void {
    error.value = null
    fieldErrors.value = {}
  }

  return {
    isLoading,
    error,
    fieldErrors,
    changePassword,
    clearError,
  }
}
