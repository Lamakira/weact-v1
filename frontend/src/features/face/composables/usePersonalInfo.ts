import { ref, type Ref } from 'vue'
import { faceApi } from '../services/faceApi'
import type { PersonalInfoInfo, PersonalInfoResult } from '../types'
import { getApiErrorDetails, getApiErrorMessage } from '@/features/auth/services/authApi'
import { useAuthStore } from '@/stores/auth'
import type { Face as AuthFace } from '@/features/auth/types'

export interface UsePersonalInfoReturn {
  personalInfo: Ref<PersonalInfoInfo | null>
  isLoading: Ref<boolean>
  isSaving: Ref<boolean>
  error: Ref<string | null>
  fetchPersonalInfo: () => Promise<void>
  updatePersonalInfo: (data: {
    sexe?: string | null
    date_naissance?: string | null
    nationalite?: string | null
    pays?: string | null
    show_age?: boolean
    whatsapp_number?: string | null
  }) => Promise<PersonalInfoResult>
  clearError: () => void
}

/**
 * Composable for Face personal info operations
 */
export function usePersonalInfo(): UsePersonalInfoReturn {
  const authStore = useAuthStore()
  const personalInfo = ref<PersonalInfoInfo | null>(null)
  const isLoading = ref(false)
  const isSaving = ref(false)
  const error = ref<string | null>(null)

  function getGenderLabel(sexe: string | null): string | null {
    switch (sexe) {
      case 'homme':
        return 'Homme'
      case 'femme':
        return 'Femme'
      case 'autre':
        return 'Autre'
      default:
        return null
    }
  }

  /**
   * Clear the current error
   */
  function clearError(): void {
    error.value = null
  }

  /**
   * Fetch the current personal info
   */
  async function fetchPersonalInfo(): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await faceApi.getPersonalInfo()
      personalInfo.value = response.data
    } catch (err) {
      error.value = getApiErrorMessage(err)
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Update personal info
   */
  async function updatePersonalInfo(data: {
    sexe?: string | null
    date_naissance?: string | null
    nationalite?: string | null
    pays?: string | null
    show_age?: boolean
    whatsapp_number?: string | null
  }): Promise<PersonalInfoResult> {
    isSaving.value = true
    error.value = null

    try {
      const response = await faceApi.updatePersonalInfo(data)
      personalInfo.value = response.data

      const refreshed = await authStore.refreshUser()

      if (!refreshed && authStore.user?.userable_type === 'Face' && authStore.user.userable) {
        const currentFace = authStore.user.userable as AuthFace

        authStore.setUser({
          ...authStore.user,
          userable: {
            ...currentFace,
            sexe: response.data.sexe,
            sexe_label: getGenderLabel(response.data.sexe),
          },
        })
      }

      return {
        success: true,
        data: response.data,
        message: response.message,
      }
    } catch (err) {
      const errors = getApiErrorDetails(err)
      const message = getApiErrorMessage(err)
      error.value = message

      return {
        success: false,
        errors,
        message,
      }
    } finally {
      isSaving.value = false
    }
  }

  return {
    personalInfo,
    isLoading,
    isSaving,
    error,
    fetchPersonalInfo,
    updatePersonalInfo,
    clearError,
  }
}
