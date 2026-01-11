import { ref, computed, type Ref, type ComputedRef } from 'vue'
import { faceApi } from '../services/faceApi'
import type { FaceProfile, ProfilePhotoResult } from '../types'
import { getApiErrorDetails, getApiErrorMessage } from '@/features/auth/services/authApi'

// Allowed file types
const ALLOWED_TYPES = ['image/jpeg', 'image/png']
const MAX_FILE_SIZE = 5 * 1024 * 1024 // 5MB

export interface UseProfilePhotoReturn {
  profile: Ref<FaceProfile | null>
  isLoading: Ref<boolean>
  isUploading: Ref<boolean>
  isDeleting: Ref<boolean>
  error: Ref<string | null>
  hasPhoto: ComputedRef<boolean>
  photoUrl: ComputedRef<string | null>
  thumbnailUrl: ComputedRef<string | null>
  fetchProfile: () => Promise<void>
  uploadPhoto: (file: File) => Promise<ProfilePhotoResult>
  deletePhoto: () => Promise<ProfilePhotoResult>
  validateFile: (file: File) => { valid: boolean; error?: string }
}

/**
 * Composable for Face profile photo operations
 */
export function useProfilePhoto(): UseProfilePhotoReturn {
  const profile = ref<FaceProfile | null>(null)
  const isLoading = ref(false)
  const isUploading = ref(false)
  const isDeleting = ref(false)
  const error = ref<string | null>(null)

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
        error: 'Le fichier ne doit pas dépasser 5 Mo',
      }
    }

    return { valid: true }
  }

  /**
   * Fetch the current face profile
   */
  async function fetchProfile(): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await faceApi.getProfile()
      profile.value = response.data
    } catch (err) {
      error.value = getApiErrorMessage(err)
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Upload a new profile photo
   */
  async function uploadPhoto(file: File): Promise<ProfilePhotoResult> {
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
      const response = await faceApi.uploadProfilePhoto(file)
      profile.value = response.data

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
  async function deletePhoto(): Promise<ProfilePhotoResult> {
    isDeleting.value = true
    error.value = null

    try {
      const response = await faceApi.deleteProfilePhoto()
      profile.value = response.data

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
