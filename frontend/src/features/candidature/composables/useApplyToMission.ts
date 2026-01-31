import { ref } from 'vue'
import { isAxiosError } from 'axios'
import { candidatureApi } from '../services/candidatureApi'
import type { Candidature, ApplyToMissionResult } from '../types'

/**
 * Composable for applying to a mission
 * Handles loading state, error handling, and success feedback
 */
export function useApplyToMission() {
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const errorCode = ref<string | null>(null)
  const candidature = ref<Candidature | null>(null)
  const isSuccess = ref(false)

  /**
   * Apply to a mission with optional motivation message
   * @param missionId The mission ID to apply to
   * @param motivation Optional motivation message (max 2000 chars)
   * @returns Result object with success status and data or error
   */
  async function apply(
    missionId: number,
    motivation?: string,
  ): Promise<ApplyToMissionResult> {
    isLoading.value = true
    error.value = null
    errorCode.value = null
    isSuccess.value = false

    try {
      const response = await candidatureApi.applyToMission(missionId, {
        message_motivation: motivation || undefined,
      })

      candidature.value = response.data
      isSuccess.value = true

      return {
        success: true,
        data: response.data,
      }
    } catch (err: unknown) {
      if (isAxiosError(err)) {
        // Handle structured error response
        if (err.response?.data?.error) {
          const apiError = err.response.data.error
          error.value = apiError.message
          errorCode.value = apiError.code

          return {
            success: false,
            error: apiError,
          }
        }

        // Handle validation errors
        if (err.response?.status === 422 && err.response?.data?.errors) {
          const validationErrors = err.response.data.errors
          const firstError = Object.values(validationErrors).flat()[0] as string
          error.value = firstError || 'Erreur de validation'

          return {
            success: false,
            error: {
              code: 'VALIDATION_ERROR',
              message: firstError || 'Erreur de validation',
            },
          }
        }

        // Handle other HTTP errors
        if (err.response?.status === 401) {
          error.value = 'Veuillez vous connecter pour postuler'
          return {
            success: false,
            error: {
              code: 'UNAUTHENTICATED',
              message: error.value,
            },
          }
        }

        if (err.response?.status === 403) {
          // Check for email verification error
          if (err.response?.data?.error?.code === 'EMAIL_NOT_VERIFIED') {
            const apiError = err.response.data.error
            error.value = apiError.message
            errorCode.value = 'EMAIL_NOT_VERIFIED'
            return {
              success: false,
              error: apiError,
            }
          }

          error.value = 'Vous devez être connecté en tant que Face pour postuler'
          return {
            success: false,
            error: {
              code: 'FORBIDDEN',
              message: error.value,
            },
          }
        }

        if (err.response?.status === 404) {
          error.value = 'Mission introuvable'
          return {
            success: false,
            error: {
              code: 'NOT_FOUND',
              message: error.value,
            },
          }
        }
      }

      // Generic error
      error.value = 'Une erreur est survenue lors de l\'envoi de votre candidature'
      return {
        success: false,
        error: {
          code: 'UNKNOWN_ERROR',
          message: error.value,
        },
      }
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Reset the composable state
   */
  function reset() {
    isLoading.value = false
    error.value = null
    errorCode.value = null
    candidature.value = null
    isSuccess.value = false
  }

  return {
    isLoading,
    error,
    errorCode,
    candidature,
    isSuccess,
    apply,
    reset,
  }
}
