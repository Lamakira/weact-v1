import { ref, type Ref } from 'vue'
import { faceApi } from '../services/faceApi'
import type { PhysicalCharacteristicsInfo, PhysicalCharacteristicsResult } from '../types'
import { getApiErrorDetails, getApiErrorMessage } from '@/features/auth/services/authApi'

const MIN_TAILLE = 50
const MAX_TAILLE = 300
const MIN_POIDS = 20
const MAX_POIDS = 500

export interface UsePhysicalCharacteristicsReturn {
  physicalCharacteristicsInfo: Ref<PhysicalCharacteristicsInfo | null>
  isLoading: Ref<boolean>
  isSaving: Ref<boolean>
  error: Ref<string | null>
  fetchPhysicalCharacteristics: () => Promise<void>
  updatePhysicalCharacteristics: (data: {
    taille?: number | null
    poids?: number | null
  }) => Promise<PhysicalCharacteristicsResult>
  clearError: () => void
  validateTaille: (taille: number | null) => { valid: boolean; error?: string }
  validatePoids: (poids: number | null) => { valid: boolean; error?: string }
}

/**
 * Composable for Face physical characteristics operations
 */
export function usePhysicalCharacteristics(): UsePhysicalCharacteristicsReturn {
  const physicalCharacteristicsInfo = ref<PhysicalCharacteristicsInfo | null>(null)
  const isLoading = ref(false)
  const isSaving = ref(false)
  const error = ref<string | null>(null)

  /**
   * Validate taille (height in cm)
   */
  function validateTaille(taille: number | null): { valid: boolean; error?: string } {
    if (taille === null || taille === undefined) {
      return { valid: true }
    }
    if (!Number.isInteger(taille)) {
      return {
        valid: false,
        error: 'La taille doit être un nombre entier',
      }
    }
    if (taille < 0) {
      return {
        valid: false,
        error: 'La valeur doit être positive',
      }
    }
    if (taille < MIN_TAILLE || taille > MAX_TAILLE) {
      return {
        valid: false,
        error: 'La taille doit être entre 50 et 300 cm',
      }
    }
    return { valid: true }
  }

  /**
   * Validate poids (weight in kg)
   */
  function validatePoids(poids: number | null): { valid: boolean; error?: string } {
    if (poids === null || poids === undefined) {
      return { valid: true }
    }
    if (!Number.isInteger(poids)) {
      return {
        valid: false,
        error: 'Le poids doit être un nombre entier',
      }
    }
    if (poids < 0) {
      return {
        valid: false,
        error: 'La valeur doit être positive',
      }
    }
    if (poids < MIN_POIDS || poids > MAX_POIDS) {
      return {
        valid: false,
        error: 'Le poids doit être entre 20 et 500 kg',
      }
    }
    return { valid: true }
  }

  /**
   * Clear the current error
   */
  function clearError(): void {
    error.value = null
  }

  /**
   * Fetch the current physical characteristics
   */
  async function fetchPhysicalCharacteristics(): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await faceApi.getPhysicalCharacteristics()
      physicalCharacteristicsInfo.value = response.data
    } catch (err) {
      error.value = getApiErrorMessage(err)
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Update physical characteristics
   */
  async function updatePhysicalCharacteristics(data: {
    taille?: number | null
    poids?: number | null
  }): Promise<PhysicalCharacteristicsResult> {
    // Validate taille
    if (data.taille !== undefined) {
      const tailleValidation = validateTaille(data.taille)
      if (!tailleValidation.valid) {
        error.value = tailleValidation.error ?? 'Taille invalide'
        return {
          success: false,
          message: tailleValidation.error,
        }
      }
    }

    // Validate poids
    if (data.poids !== undefined) {
      const poidsValidation = validatePoids(data.poids)
      if (!poidsValidation.valid) {
        error.value = poidsValidation.error ?? 'Poids invalide'
        return {
          success: false,
          message: poidsValidation.error,
        }
      }
    }

    isSaving.value = true
    error.value = null

    try {
      const response = await faceApi.updatePhysicalCharacteristics(data)
      physicalCharacteristicsInfo.value = response.data

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
    physicalCharacteristicsInfo,
    isLoading,
    isSaving,
    error,
    fetchPhysicalCharacteristics,
    updatePhysicalCharacteristics,
    clearError,
    validateTaille,
    validatePoids,
  }
}
