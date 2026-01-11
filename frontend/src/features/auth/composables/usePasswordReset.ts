import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { authApi, getApiErrorMessage } from '../services/authApi'
import type { ResetPasswordData } from '../types'

/**
 * Composable for password reset functionality
 */
export function usePasswordReset() {
  const router = useRouter()

  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const successMessage = ref<string | null>(null)

  /**
   * Request a password reset email
   */
  async function forgotPassword(email: string): Promise<boolean> {
    isLoading.value = true
    error.value = null
    successMessage.value = null

    try {
      await authApi.forgotPassword(email)
      successMessage.value = 'Email envoyé'
      return true
    } catch (err) {
      error.value = getApiErrorMessage(err)
      return false
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Reset password with token
   */
  async function resetPassword(data: ResetPasswordData): Promise<boolean> {
    isLoading.value = true
    error.value = null
    successMessage.value = null

    try {
      await authApi.resetPassword(data)
      successMessage.value = 'Mot de passe réinitialisé avec succès'
      // Redirect to login after successful reset
      await router.push({
        path: '/login',
        query: { message: 'password-reset-success' },
      })
      return true
    } catch (err) {
      error.value = getApiErrorMessage(err)
      return false
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Clear all state
   */
  function clearState(): void {
    isLoading.value = false
    error.value = null
    successMessage.value = null
  }

  return {
    isLoading,
    error,
    successMessage,
    forgotPassword,
    resetPassword,
    clearState,
  }
}
