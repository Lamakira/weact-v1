import { ref, computed, type Ref, type ComputedRef } from 'vue'
import { faceApi } from '../services/faceApi'
import type { FacePhoto, AlbumPhotoResult } from '../types'
import { getApiErrorDetails, getApiErrorMessage } from '@/features/auth/services/authApi'

// Maximum number of photos in the album
const MAX_PHOTOS = 4

// Allowed file types
const ALLOWED_TYPES = ['image/jpeg', 'image/png']
const MAX_FILE_SIZE = 5 * 1024 * 1024 // 5MB

export interface UsePhotoAlbumReturn {
  photos: Ref<FacePhoto[]>
  isLoading: Ref<boolean>
  isUploading: Ref<boolean>
  isDeleting: Ref<boolean>
  isReordering: Ref<boolean>
  error: Ref<string | null>
  photoCount: ComputedRef<number>
  canAddMore: ComputedRef<boolean>
  isFull: ComputedRef<boolean>
  fetchPhotos: () => Promise<void>
  addPhoto: (file: File) => Promise<AlbumPhotoResult>
  deletePhoto: (photoId: number) => Promise<AlbumPhotoResult>
  reorderPhotos: (newOrder: number[]) => Promise<void>
  validateFile: (file: File) => { valid: boolean; error?: string }
}

/**
 * Composable for Face album photo operations
 */
export function usePhotoAlbum(): UsePhotoAlbumReturn {
  const photos = ref<FacePhoto[]>([])
  const isLoading = ref(false)
  const isUploading = ref(false)
  const isDeleting = ref(false)
  const isReordering = ref(false)
  const error = ref<string | null>(null)

  // Computed properties
  const photoCount = computed(() => photos.value.length)
  const canAddMore = computed(() => photos.value.length < MAX_PHOTOS)
  const isFull = computed(() => photos.value.length >= MAX_PHOTOS)

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
   * Fetch all album photos
   */
  async function fetchPhotos(): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const response = await faceApi.getAlbumPhotos()
      photos.value = response.data
    } catch (err) {
      error.value = getApiErrorMessage(err)
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Add a new photo to the album
   */
  async function addPhoto(file: File): Promise<AlbumPhotoResult> {
    // Check album limit first
    if (isFull.value) {
      const errorMessage = `Maximum ${MAX_PHOTOS} photos atteint`
      error.value = errorMessage
      return {
        success: false,
        message: errorMessage,
      }
    }

    // Validate file
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
      const response = await faceApi.addAlbumPhoto(file)
      photos.value.push(response.data)

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
   * Delete a photo from the album
   */
  async function deletePhoto(photoId: number): Promise<AlbumPhotoResult> {
    isDeleting.value = true
    error.value = null

    try {
      await faceApi.deleteAlbumPhoto(photoId)

      // Remove from local state
      const index = photos.value.findIndex((p) => p.id === photoId)
      if (index !== -1) {
        photos.value.splice(index, 1)
      }

      // Update positions locally
      photos.value = photos.value.map((photo, idx) => ({
        ...photo,
        position: idx + 1,
      }))

      return {
        success: true,
        message: 'Photo supprimée de l\'album',
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

  /**
   * Reorder photos in the album
   */
  async function reorderPhotos(newOrder: number[]): Promise<void> {
    isReordering.value = true
    error.value = null

    // Store original order in case of failure
    const originalPhotos = [...photos.value]

    // Optimistically update local state
    const reorderedPhotos = newOrder
      .map((id, index) => {
        const photo = photos.value.find((p) => p.id === id)
        if (photo) {
          return { ...photo, position: index + 1 }
        }
        return null
      })
      .filter((p): p is FacePhoto => p !== null)

    photos.value = reorderedPhotos

    try {
      const response = await faceApi.reorderAlbumPhotos(newOrder)
      photos.value = response.data
    } catch (err) {
      // Rollback on failure
      photos.value = originalPhotos
      error.value = getApiErrorMessage(err)
    } finally {
      isReordering.value = false
    }
  }

  return {
    photos,
    isLoading,
    isUploading,
    isDeleting,
    isReordering,
    error,
    photoCount,
    canAddMore,
    isFull,
    fetchPhotos,
    addPhoto,
    deletePhoto,
    reorderPhotos,
    validateFile,
  }
}
