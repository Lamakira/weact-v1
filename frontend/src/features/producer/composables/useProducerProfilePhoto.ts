import { ref, computed, type Ref, type ComputedRef } from 'vue'
import { producerApi } from '../services/producerApi'
import type { Producer, ProducerProfilePhotoResult } from '../types'
import { getApiErrorDetails, getApiErrorMessage } from '@/features/auth/services/authApi'
import { createSharedCachedResource } from '@/lib/createSharedCachedResource'

// Allowed file types
const ALLOWED_TYPES = ['image/jpeg', 'image/png']
const MAX_FILE_SIZE = 8 * 1024 * 1024 // 8MB
const PROFILE_CACHE_TTL_MS = 5 * 60 * 1000

const profileResource = createSharedCachedResource<Producer | null>({
  key: 'producer-profile',
  initialValue: null,
  ttlMs: PROFILE_CACHE_TTL_MS,
  load: async () => {
    const response = await producerApi.getProfile()
    return response.data
  },
  getErrorMessage: getApiErrorMessage,
})

interface UseProducerProfilePhotoReturn {
  profile: Ref<Producer | null>
  isLoading: Ref<boolean>
  isUploading: Ref<boolean>
  isDeleting: Ref<boolean>
  error: Ref<string | null>
  hasPhoto: ComputedRef<boolean>
  photoUrl: ComputedRef<string | null>
  thumbnailUrl: ComputedRef<string | null>
  fetchProfile: () => Promise<void>
  uploadPhoto: (file: File) => Promise<ProducerProfilePhotoResult>
  deletePhoto: () => Promise<ProducerProfilePhotoResult>
  validateFile: (file: File) => { valid: boolean; error?: string }
}

/**
 * Composable for Producer profile photo operations
 */
export function useProducerProfilePhoto(): UseProducerProfilePhotoReturn {
  const profile = profileResource.data
  const isLoading = profileResource.isLoading
  const isUploading = ref(false)
  const isDeleting = ref(false)
  const error = profileResource.error

  // Computed properties
  const hasPhoto = computed(() => !!profile.value?.profile_photo_url)
  const photoUrl = computed(() => profile.value?.profile_photo_url ?? null)
  const thumbnailUrl = computed(() => profile.value?.thumbnail_url ?? null)

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
        error: 'Le fichier ne doit pas dépasser 8 Mo',
      }
    }

    return { valid: true }
  }

  /**
   * Fetch the current producer profile
   */
  async function fetchProfile(): Promise<void> {
    await profileResource.fetch()
  }

  /**
   * Upload a new profile photo
   */
  async function uploadPhoto(file: File): Promise<ProducerProfilePhotoResult> {
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
      const response = await producerApi.uploadProfilePhoto(file)
      profileResource.setData(response.data)

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
      isUploading.value = false
    }
  }

  /**
   * Delete the current profile photo
   */
  async function deletePhoto(): Promise<ProducerProfilePhotoResult> {
    isDeleting.value = true
    error.value = null

    try {
      const response = await producerApi.deleteProfilePhoto()
      profileResource.setData(response.data)

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
      isDeleting.value = false
    }
  }

  return {
    profile,
    isLoading,
    isUploading,
    isDeleting,
    error,
    hasPhoto,
    photoUrl,
    thumbnailUrl,
    fetchProfile,
    uploadPhoto,
    deletePhoto,
    validateFile,
  }
}
