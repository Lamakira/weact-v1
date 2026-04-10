import { ref, type Ref } from 'vue'
import { faceApi } from '../services/faceApi'
import type { TarifsInfo, TarifsResult, TarifsFormData } from '../types'
import { getApiErrorDetails, getApiErrorMessage } from '@/features/auth/services/authApi'
import { createSharedCachedResource } from '@/lib/createSharedCachedResource'

const MAX_TARIF_HORAIRE = 10000000
const MAX_TARIF_JOURNALIER = 100000000
const TARIFS_CACHE_TTL_MS = 5 * 60 * 1000

const tarifsResource = createSharedCachedResource<TarifsInfo | null>({
  key: 'face-tarifs',
  initialValue: null,
  ttlMs: TARIFS_CACHE_TTL_MS,
  load: async () => {
    const response = await faceApi.getTarifs()
    return response.data
  },
  getErrorMessage: getApiErrorMessage,
})

interface UseTarifsReturn {
  tarifsInfo: Ref<TarifsInfo | null>
  isLoading: Ref<boolean>
  isSaving: Ref<boolean>
  error: Ref<string | null>
  fetchTarifs: () => Promise<void>
  updateTarifs: (data: TarifsFormData) => Promise<TarifsResult>
  clearError: () => void
  validateTarifHoraire: (tarif: number | null) => { valid: boolean; error?: string }
  validateTarifJournalier: (tarif: number | null) => { valid: boolean; error?: string }
  validateTarifsConsistency: (
    tarifHoraire: number | null,
    tarifJournalier: number | null,
  ) => { valid: boolean; error?: string }
}

/**
 * Composable for Face tarifs operations
 */
export function useTarifs(): UseTarifsReturn {
  const tarifsInfo = tarifsResource.data
  const isLoading = tarifsResource.isLoading
  const isSaving = ref(false)
  const error = tarifsResource.error

  /**
   * Validate tarif demi-journée (half-day rate in XOF)
   */
  function validateTarifHoraire(tarif: number | null): { valid: boolean; error?: string } {
    if (tarif === null || tarif === undefined) {
      return { valid: true }
    }
    if (!Number.isInteger(tarif)) {
      return {
        valid: false,
        error: 'Le tarif demi-journée doit être un nombre entier',
      }
    }
    if (tarif < 0) {
      return {
        valid: false,
        error: 'Le tarif demi-journée doit être positif',
      }
    }
    if (tarif > MAX_TARIF_HORAIRE) {
      return {
        valid: false,
        error: 'Le tarif demi-journée ne peut pas dépasser 10 000 000 XOF',
      }
    }
    return { valid: true }
  }

  /**
   * Validate tarif journalier (daily rate in XOF)
   */
  function validateTarifJournalier(tarif: number | null): { valid: boolean; error?: string } {
    if (tarif === null || tarif === undefined) {
      return { valid: true }
    }
    if (!Number.isInteger(tarif)) {
      return {
        valid: false,
        error: 'Le tarif journalier doit être un nombre entier',
      }
    }
    if (tarif < 0) {
      return {
        valid: false,
        error: 'Le tarif journalier doit être positif',
      }
    }
    if (tarif > MAX_TARIF_JOURNALIER) {
      return {
        valid: false,
        error: 'Le tarif journalier ne peut pas dépasser 100 000 000 XOF',
      }
    }
    return { valid: true }
  }

  /**
   * Validate that daily rate is >= hourly rate when both are provided
   */
  function validateTarifsConsistency(
    tarifHoraire: number | null,
    tarifJournalier: number | null,
  ): { valid: boolean; error?: string } {
    if (
      tarifHoraire !== null &&
      tarifJournalier !== null &&
      tarifJournalier < tarifHoraire
    ) {
      return {
        valid: false,
        error: 'Le tarif journalier doit être supérieur ou égal au tarif demi-journée',
      }
    }
    return { valid: true }
  }

  /**
   * Clear the current error
   */
  function clearError(): void {
    tarifsResource.clearError()
  }

  /**
   * Fetch the current tarifs
   */
  async function fetchTarifs(): Promise<void> {
    await tarifsResource.fetch()
  }

  /**
   * Update tarifs
   */
  async function updateTarifs(data: TarifsFormData): Promise<TarifsResult> {
    // Validate tarif demi-journée
    if (data.tarif_horaire !== undefined && data.tarif_horaire !== null) {
      const tarifHoraireValidation = validateTarifHoraire(data.tarif_horaire)
      if (!tarifHoraireValidation.valid) {
        error.value = tarifHoraireValidation.error ?? 'Tarif demi-journée invalide'
        return {
          success: false,
          message: tarifHoraireValidation.error,
        }
      }
    }

    // Validate tarif journalier
    if (data.tarif_journalier !== undefined && data.tarif_journalier !== null) {
      const tarifJournalierValidation = validateTarifJournalier(data.tarif_journalier)
      if (!tarifJournalierValidation.valid) {
        error.value = tarifJournalierValidation.error ?? 'Tarif journalier invalide'
        return {
          success: false,
          message: tarifJournalierValidation.error,
        }
      }
    }

    // Validate consistency: daily rate should be >= hourly rate
    const consistencyValidation = validateTarifsConsistency(
      data.tarif_horaire,
      data.tarif_journalier,
    )
    if (!consistencyValidation.valid) {
      error.value = consistencyValidation.error ?? 'Tarifs incohérents'
      return {
        success: false,
        message: consistencyValidation.error,
      }
    }

    isSaving.value = true
    error.value = null

    try {
      const response = await faceApi.updateTarifs(data)
      tarifsResource.setData(response.data)

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
    tarifsInfo,
    isLoading,
    isSaving,
    error,
    fetchTarifs,
    updateTarifs,
    clearError,
    validateTarifHoraire,
    validateTarifJournalier,
    validateTarifsConsistency,
  }
}
