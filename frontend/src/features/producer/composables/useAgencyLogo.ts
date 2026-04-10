import { ref, computed, type Ref, type ComputedRef } from 'vue'
import { producerApi } from '../services/producerApi'
import type { AgencyLogoResult } from '../types'
import { getApiErrorDetails, getApiErrorMessage } from '@/features/auth/services/authApi'
import { createSharedCachedResource } from '@/lib/createSharedCachedResource'

// Allowed file types
const ALLOWED_TYPES = ['image/jpeg', 'image/png']
const MAX_FILE_SIZE = 2 * 1024 * 1024 // 2MB (per FR24)
const AGENCY_LOGO_CACHE_TTL_MS = 5 * 60 * 1000

interface AgencyLogoState {
  logoUrl: string | null
  thumbnailUrl: string | null
}

const agencyLogoResource = createSharedCachedResource<AgencyLogoState>({
  key: 'producer-agency-logo',
  initialValue: {
    logoUrl: null,
    thumbnailUrl: null,
  },
  ttlMs: AGENCY_LOGO_CACHE_TTL_MS,
  load: async () => {
    const response = await producerApi.getLogo()

    return {
      logoUrl: response.data.agency_logo_url,
      thumbnailUrl: response.data.agency_logo_thumbnail_url,
    }
  },
  getErrorMessage: getApiErrorMessage,
})

interface UseAgencyLogoReturn {
  logoUrl: Ref<string | null>
  thumbnailUrl: Ref<string | null>
  isLoading: Ref<boolean>
  isUploading: Ref<boolean>
  isDeleting: Ref<boolean>
  error: Ref<string | null>
  hasLogo: ComputedRef<boolean>
  fetchLogo: () => Promise<AgencyLogoResult>
  uploadLogo: (file: File) => Promise<AgencyLogoResult>
  deleteLogo: () => Promise<AgencyLogoResult>
  validateFile: (file: File) => { valid: boolean; error?: string }
}

/**
 * Composable for Agency logo operations (Agency producers only)
 */
export function useAgencyLogo(): UseAgencyLogoReturn {
  const logoUrl = computed({
    get: () => agencyLogoResource.data.value.logoUrl,
    set: (value: string | null) => {
      agencyLogoResource.mutate((current) => ({
        ...current,
        logoUrl: value,
      }))
    },
  }) as Ref<string | null>
  const thumbnailUrl = computed({
    get: () => agencyLogoResource.data.value.thumbnailUrl,
    set: (value: string | null) => {
      agencyLogoResource.mutate((current) => ({
        ...current,
        thumbnailUrl: value,
      }))
    },
  }) as Ref<string | null>
  const isLoading = agencyLogoResource.isLoading
  const isUploading = ref(false)
  const isDeleting = ref(false)
  const error = agencyLogoResource.error

  // Computed properties
  const hasLogo = computed(() => !!logoUrl.value)

  /**
   * Validate file before upload
   */
  function validateFile(file: File): { valid: boolean; error?: string } {
    if (!ALLOWED_TYPES.includes(file.type)) {
      return {
        valid: false,
        error: 'Le fichier doit être au format JPG ou PNG',
      }
    }

    if (file.size > MAX_FILE_SIZE) {
      return {
        valid: false,
        error: 'Le logo ne peut pas dépasser 2 Mo',
      }
    }

    return { valid: true }
  }

  /**
   * Fetch the current agency logo
   */
  async function fetchLogo(): Promise<AgencyLogoResult> {
    const state = await agencyLogoResource.fetch()

    return {
      success: error.value === null,
      data: {
        agency_logo_url: state.logoUrl,
        agency_logo_thumbnail_url: state.thumbnailUrl,
      },
      message: error.value ?? undefined,
    }
  }

  /**
   * Upload a new agency logo
   */
  async function uploadLogo(file: File): Promise<AgencyLogoResult> {
    // Validate file first
    const validation = validateFile(file)
    if (!validation.valid) {
      error.value = validation.error ?? 'Fichier invalide'
      return {
        success: false,
        message: validation.error,
      }
    }

    isUploading.value = true
    error.value = null

    try {
      const response = await producerApi.uploadLogo(file)
      agencyLogoResource.setData({
        logoUrl: response.data.agency_logo_url,
        thumbnailUrl: response.data.agency_logo_thumbnail_url,
      })

      return {
        success: true,
        data: response.data,
        message: response.message || 'Logo de l\'agence mis à jour',
      }
    } catch (err) {
      const errors = getApiErrorDetails(err)
      const message = getApiErrorMessage(err)
      // Use specific field error if available, otherwise use generic message
      error.value = errors?.logo?.[0] || message

      return {
        success: false,
        errors,
        message: error.value,
      }
    } finally {
      isUploading.value = false
    }
  }

  /**
   * Delete the current agency logo
   */
  async function deleteLogo(): Promise<AgencyLogoResult> {
    isDeleting.value = true
    error.value = null

    try {
      const response = await producerApi.deleteLogo()
      agencyLogoResource.setData({
        logoUrl: null,
        thumbnailUrl: null,
      })

      return {
        success: true,
        data: response.data,
        message: response.message || 'Logo de l\'agence supprimé',
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
      isDeleting.value = false
    }
  }

  return {
    logoUrl,
    thumbnailUrl,
    isLoading,
    isUploading,
    isDeleting,
    error,
    hasLogo,
    fetchLogo,
    uploadLogo,
    deleteLogo,
    validateFile,
  }
}
