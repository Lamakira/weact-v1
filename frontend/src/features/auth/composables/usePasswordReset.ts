import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { authApi, getApiErrorMessage } from '../services/authApi'
import { useToast } from '@/composables/useToast'
import type { ResetPasswordData } from '../types'

/**
 * Composable for password reset functionality
 */
export function usePasswordReset() {
  const router = useRouter()
  const toast = useToast()

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
      toast.success('Un email de réinitialisation a été envoyé')
      return true
    } catch (err) {
      const errorMessage = getApiErrorMessage(err)
      error.value = errorMessage
      toast.error(errorMessage)
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
      toast.success('Mot de passe réinitialisé avec succès')
      // Redirect to login after successful reset
      await router.push({
        path: '/login',
        query: { message: 'password-reset-success' },
      })
      return true
    } catch (err) {
      const errorMessage = getApiErrorMessage(err)
      error.value = errorMessage
      toast.error(errorMessage)
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
