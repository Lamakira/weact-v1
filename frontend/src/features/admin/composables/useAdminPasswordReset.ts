import { ref } from 'vue'
import {
  adminAuthApi,
  getApiErrorMessage,
  type AdminResetPasswordForm,
} from '../services/adminAuthApi'

interface PasswordResetResult {
  success: boolean
  message?: string
}

/**
 * Composable for admin password reset operations (forgot + reset)
 */
export function useAdminPasswordReset() {
  const isLoading = ref(false)

  /**
   * Request a password reset link for the given email
   */
  async function forgotPassword(email: string): Promise<PasswordResetResult> {
    isLoading.value = true

    try {
      const response = await adminAuthApi.forgotPassword(email)
      return { success: true, message: response.message }
    } catch (error) {
      const message = getApiErrorMessage(error)
      return { success: false, message: message ?? 'Une erreur est survenue' }
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Reset admin password with token, email, and new password
   */
  async function resetPassword(data: AdminResetPasswordForm): Promise<PasswordResetResult> {
    isLoading.value = true

    try {
      const response = await adminAuthApi.resetPassword(data)
      return { success: true, message: response.message }
    } catch (error) {
      const message = getApiErrorMessage(error)
      return { success: false, message: message ?? 'Une erreur est survenue' }
    } finally {
      isLoading.value = false
    }
  }

  return {
    isLoading,
    forgotPassword,
    resetPassword,
  }
}
