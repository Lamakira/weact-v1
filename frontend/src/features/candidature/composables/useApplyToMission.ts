import { ref } from 'vue'
import { isAxiosError } from 'axios'
import { candidatureApi } from '../services/candidatureApi'
import { useToast } from '@/composables/useToast'
import { formatApiError, getApiErrorCode } from '@/services/errorFormatter'
import type { Candidature, ApplyToMissionResult } from '../types'

/**
 * Composable for applying to a mission
 * Handles loading state, error handling, and success feedback
 */
export function useApplyToMission() {
  const toast = useToast()
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
    missionId: string,
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
      const message = formatApiError(err, 'Une erreur est survenue lors de l\'envoi de votre candidature')
      const code = getApiErrorCode(err)

      error.value = message
      errorCode.value = code

      if (isAxiosError(err)) {
        if (code === 'gender_mismatch') {
          toast.error(message)
          return {
            success: false,
            error: {
              code,
              message,
            },
          }
        }

        if (err.response?.status === 422) {
          return {
            success: false,
            error: {
              code: code ?? 'VALIDATION_ERROR',
              message,
            },
          }
        }

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
          if (code === 'EMAIL_NOT_VERIFIED') {
            return {
              success: false,
              error: {
                code: 'EMAIL_NOT_VERIFIED',
                message,
              },
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

      return {
        success: false,
        error: {
          code: 'UNKNOWN_ERROR',
          message,
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
