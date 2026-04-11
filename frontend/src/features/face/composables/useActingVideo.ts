import { ref, computed, type Ref, type ComputedRef } from 'vue'
import { faceApi } from '../services/faceApi'
import type { ActingVideoInfo, ActingVideoResult, VideoUploadProgress } from '../types'
import { getApiErrorDetails, getApiErrorMessage } from '@/features/auth/services/authApi'
import { createSharedCachedResource } from '@/lib/createSharedCachedResource'

// Allowed video file types
const ALLOWED_TYPES = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/avi']
const ALLOWED_EXTENSIONS = ['.mp4', '.mov', '.avi']
const MAX_FILE_SIZE = 50 * 1024 * 1024 // 50MB
const MAX_DURATION_SECONDS = 120 // 2 minutes
const ACTING_VIDEO_CACHE_TTL_MS = 5 * 60 * 1000

const actingVideoResource = createSharedCachedResource<ActingVideoInfo | null>({
  key: 'face-acting-video',
  initialValue: null,
  ttlMs: ACTING_VIDEO_CACHE_TTL_MS,
  load: async () => {
    const response = await faceApi.getActingVideo()
    return response.data
  },
  getErrorMessage: getApiErrorMessage,
})

interface UseActingVideoReturn {
  videoInfo: Ref<ActingVideoInfo | null>
  isLoading: Ref<boolean>
  isUploading: Ref<boolean>
  isDeleting: Ref<boolean>
  error: Ref<string | null>
  uploadProgress: Ref<VideoUploadProgress | null>
  hasVideo: ComputedRef<boolean>
  videoUrl: ComputedRef<string | null>
  thumbnailUrl: ComputedRef<string | null>
  fetchVideoInfo: () => Promise<void>
  uploadVideo: (file: File) => Promise<ActingVideoResult>
  deleteVideo: () => Promise<ActingVideoResult>
  validateFile: (file: File) => { valid: boolean; error?: string }
  validateDuration: (file: File) => Promise<{ valid: boolean; error?: string }>
}

/**
 * Composable for Face acting video operations
 */
export function useActingVideo(): UseActingVideoReturn {
  const videoInfo = actingVideoResource.data
  const isLoading = actingVideoResource.isLoading
  const isUploading = ref(false)
  const isDeleting = ref(false)
  const error = actingVideoResource.error
  const uploadProgress = ref<VideoUploadProgress | null>(null)

  // Computed properties
  const hasVideo = computed(() => !!videoInfo.value?.acting_video_url)
  const videoUrl = computed(() => videoInfo.value?.acting_video_url ?? null)
  const thumbnailUrl = computed(() => videoInfo.value?.acting_video_thumbnail_url ?? null)

  /**
   * Validate file type and size before upload
   */
  function validateFile(file: File): { valid: boolean; error?: string } {
    // Check file extension
    const fileName = file.name.toLowerCase()
    const hasValidExtension = ALLOWED_EXTENSIONS.some((ext) => fileName.endsWith(ext))
    if (!hasValidExtension) {
      return {
        valid: false,
        error: 'Format non supporté (MP4, MOV, AVI uniquement)',
      }
    }

    // Check MIME type
    if (!ALLOWED_TYPES.includes(file.type)) {
      return {
        valid: false,
        error: 'Format non supporté (MP4, MOV, AVI uniquement)',
      }
    }

    // Check file size
    if (file.size > MAX_FILE_SIZE) {
      return {
        valid: false,
        error: 'Vidéo trop volumineuse (max 50MB)',
      }
    }

    return { valid: true }
  }

  /**
   * Validate video duration (client-side check before upload)
   */
  async function validateDuration(file: File): Promise<{ valid: boolean; error?: string }> {
    return new Promise((resolve) => {
      const video = document.createElement('video')
      video.preload = 'metadata'

      video.onloadedmetadata = () => {
        URL.revokeObjectURL(video.src)
        if (video.duration > MAX_DURATION_SECONDS) {
          resolve({
            valid: false,
            error: 'Vidéo trop longue (max 2 minutes)',
          })
        } else {
          resolve({ valid: true })
        }
      }

      video.onerror = () => {
        URL.revokeObjectURL(video.src)
        // If we can't read metadata, let the server validate
        resolve({ valid: true })
      }

      video.src = URL.createObjectURL(file)
    })
  }

  /**
   * Fetch the current acting video info
   */
  async function fetchVideoInfo(): Promise<void> {
    await actingVideoResource.fetch()
  }

  /**
   * Upload a new acting video
   */
  async function uploadVideo(file: File): Promise<ActingVideoResult> {
    // Validate file first
    const fileValidation = validateFile(file)
    if (!fileValidation.valid) {
      error.value = fileValidation.error ?? 'Fichier invalide'
      return {
        success: false,
        message: fileValidation.error,
      }
    }

    // Validate duration (client-side)
    const durationValidation = await validateDuration(file)
    if (!durationValidation.valid) {
      error.value = durationValidation.error ?? 'Durée invalide'
      return {
        success: false,
        message: durationValidation.error,
      }
    }

    isUploading.value = true
    error.value = null
    uploadProgress.value = { loaded: 0, total: file.size, percentage: 0 }

    try {
      const response = await faceApi.uploadActingVideo(file, (progress) => {
        uploadProgress.value = progress
      })
      actingVideoResource.setData(response.data)

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
      uploadProgress.value = null
    }
  }

  /**
   * Delete the current acting video
   */
  async function deleteVideo(): Promise<ActingVideoResult> {
    isDeleting.value = true
    error.value = null

    try {
      const response = await faceApi.deleteActingVideo()
      const emptyVideoInfo = {
        acting_video_url: null,
        acting_video_thumbnail_url: null,
      }
      actingVideoResource.setData(emptyVideoInfo)

      return {
        success: true,
        data: emptyVideoInfo,
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
    videoInfo,
    isLoading,
    isUploading,
    isDeleting,
    error,
    uploadProgress,
    hasVideo,
    videoUrl,
    thumbnailUrl,
    fetchVideoInfo,
    uploadVideo,
    deleteVideo,
    validateFile,
    validateDuration,
  }
}
