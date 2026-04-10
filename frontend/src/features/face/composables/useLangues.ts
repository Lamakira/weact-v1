import { ref, type Ref } from 'vue'
import { faceApi } from '../services/faceApi'
import type { LanguesInfo, LanguesResult } from '../types'
import { getApiErrorDetails, getApiErrorMessage } from '@/features/auth/services/authApi'
import { createSharedCachedResource } from '@/lib/createSharedCachedResource'

const MAX_LANGUES = 10
const MAX_LANGUE_LENGTH = 50
const LANGUES_CACHE_TTL_MS = 5 * 60 * 1000

const languesResource = createSharedCachedResource<LanguesInfo | null>({
  key: 'face-langues',
  initialValue: null,
  ttlMs: LANGUES_CACHE_TTL_MS,
  load: async () => {
    const response = await faceApi.getLangues()
    return response.data
  },
  getErrorMessage: getApiErrorMessage,
})

interface UseLanguesReturn {
  languesInfo: Ref<LanguesInfo | null>
  isLoading: Ref<boolean>
  isSaving: Ref<boolean>
  error: Ref<string | null>
  fetchLangues: () => Promise<void>
  updateLangues: (langues: string[] | null) => Promise<LanguesResult>
  clearError: () => void
  validateLangues: (langues: string[]) => { valid: boolean; error?: string }
  MAX_LANGUES: number
  MAX_LANGUE_LENGTH: number
}

/**
 * Composable for Face langues operations
 */
export function useLangues(): UseLanguesReturn {
  const languesInfo = languesResource.data
  const isLoading = languesResource.isLoading
  const isSaving = ref(false)
  const error = languesResource.error

  /**
   * Validate langues array
   */
  function validateLangues(langues: string[]): { valid: boolean; error?: string } {
    if (langues.length > MAX_LANGUES) {
      return {
        valid: false,
        error: `Vous ne pouvez pas ajouter plus de ${MAX_LANGUES} langues.`,
      }
    }
    for (const langue of langues) {
      if (langue.length > MAX_LANGUE_LENGTH) {
        return {
          valid: false,
          error: `Chaque langue ne peut pas dépasser ${MAX_LANGUE_LENGTH} caractères.`,
        }
      }
    }
    return { valid: true }
  }

  /**
   * Clear the current error
   */
  function clearError(): void {
    languesResource.clearError()
  }

  /**
   * Fetch the current langues
   */
  async function fetchLangues(): Promise<void> {
    await languesResource.fetch()
  }

  /**
   * Update langues
   */
  async function updateLangues(langues: string[] | null): Promise<LanguesResult> {
    if (langues) {
      const validation = validateLangues(langues)
      if (!validation.valid) {
        error.value = validation.error ?? 'Langues invalides'
        return {
          success: false,
          message: validation.error,
        }
      }
    }

    isSaving.value = true
    error.value = null

    try {
      const response = await faceApi.updateLangues(langues)
      languesResource.setData(response.data)

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
    languesInfo,
    isLoading,
    isSaving,
    error,
    fetchLangues,
    updateLangues,
    clearError,
    validateLangues,
    MAX_LANGUES,
    MAX_LANGUE_LENGTH,
  }
}
