import { ref, computed, type Ref, type ComputedRef } from 'vue'
import { faceApi } from '../services/faceApi'
import type { BioLocationInfo, BioLocationResult } from '../types'
import { getApiErrorDetails, getApiErrorMessage } from '@/features/auth/services/authApi'
import { BENIN_CITY_VALUES } from '@/shared/constants/beninCities'
import { createSharedCachedResource } from '@/lib/createSharedCachedResource'

const MAX_BIO_LENGTH = 500
const BIO_LOCATION_CACHE_TTL_MS = 5 * 60 * 1000

const bioLocationResource = createSharedCachedResource<BioLocationInfo | null>({
  key: 'face-bio-location',
  initialValue: null,
  ttlMs: BIO_LOCATION_CACHE_TTL_MS,
  load: async () => {
    const response = await faceApi.getBioLocation()
    return response.data
  },
  getErrorMessage: getApiErrorMessage,
})

interface UseBioLocationReturn {
  bioLocationInfo: Ref<BioLocationInfo | null>
  isLoading: Ref<boolean>
  isSaving: Ref<boolean>
  error: Ref<string | null>
  bioCharactersRemaining: ComputedRef<number>
  fetchBioLocation: () => Promise<void>
  updateBioLocation: (data: {
    bio?: string | null
    ville?: string | null
    pays?: string | null
  }) => Promise<BioLocationResult>
  clearError: () => void
  validateBio: (bio: string | null) => { valid: boolean; error?: string }
  validateLocation: (data: {
    ville?: string | null
    pays?: string | null
  }) => { valid: boolean; error?: string }
}

/**
 * Composable for Face bio and location operations
 */
export function useBioLocation(): UseBioLocationReturn {
  const bioLocationInfo = bioLocationResource.data
  const isLoading = bioLocationResource.isLoading
  const isSaving = ref(false)
  const error = bioLocationResource.error

  // Computed properties for character counter
  const bioCharactersRemaining = computed(() => {
    const current = bioLocationInfo.value?.bio?.length ?? 0
    return MAX_BIO_LENGTH - current
  })

  /**
   * Validate bio length
   */
  function validateBio(bio: string | null): { valid: boolean; error?: string } {
    if (bio && bio.length > MAX_BIO_LENGTH) {
      return {
        valid: false,
        error: 'La bio ne peut pas dépasser 500 caractères',
      }
    }
    return { valid: true }
  }

  /**
   * Validate location fields against Benin city rules.
   */
  function validateLocation(data: {
    ville?: string | null
    pays?: string | null
  }): { valid: boolean; error?: string } {
    if (data.ville && !BENIN_CITY_VALUES.has(data.ville)) {
      return {
        valid: false,
        error: 'Veuillez sélectionner une ville du Bénin valide',
      }
    }

    if ((data.pays ?? bioLocationInfo.value?.pays ?? 'Bénin') !== 'Bénin' && data.ville) {
      return {
        valid: false,
        error: 'La ville doit être vide lorsque le pays n\'est pas "Bénin".',
      }
    }

    return { valid: true }
  }

  /**
   * Clear the current error
   */
  function clearError(): void {
    bioLocationResource.clearError()
  }

  /**
   * Fetch the current bio and location info
   */
  async function fetchBioLocation(): Promise<void> {
    await bioLocationResource.fetch()
  }

  /**
   * Update bio and/or location
   */
  async function updateBioLocation(data: {
    bio?: string | null
    ville?: string | null
    pays?: string | null
  }): Promise<BioLocationResult> {
    // Validate bio
    if (data.bio !== undefined) {
      const bioValidation = validateBio(data.bio)
      if (!bioValidation.valid) {
        error.value = bioValidation.error ?? 'Bio invalide'
        return {
          success: false,
          message: bioValidation.error,
        }
      }
    }

    // Validate location
    const locationValidation = validateLocation({
      ville: 'ville' in data ? data.ville : bioLocationInfo.value?.ville,
      pays: 'pays' in data ? data.pays : bioLocationInfo.value?.pays,
    })
    if (!locationValidation.valid) {
      error.value = locationValidation.error ?? 'Localisation invalide'
      return {
        success: false,
        message: locationValidation.error,
      }
    }

    isSaving.value = true
    error.value = null

    try {
      const response = await faceApi.updateBioLocation(data)
      bioLocationResource.setData(response.data)

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
    bioLocationInfo,
    isLoading,
    isSaving,
    error,
    bioCharactersRemaining,
    fetchBioLocation,
    updateBioLocation,
    clearError,
    validateBio,
    validateLocation,
  }
}
