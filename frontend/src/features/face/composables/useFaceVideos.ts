import { computed, ref, type ComputedRef, type Ref } from 'vue'
import { faceApi } from '../services/faceApi'
import type {
  FaceVideo,
  FaceVideoType,
  FaceVideoDeleteResult,
  FaceVideoUploadResult,
  VideoUploadProgress,
} from '../types'
import { getApiErrorDetails, getApiErrorMessage } from '@/features/auth/services/authApi'
import { createSharedCachedResource } from '@/lib/createSharedCachedResource'

const ALLOWED_TYPES = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/avi']
const ALLOWED_EXTENSIONS = ['.mp4', '.mov', '.avi']
const MAX_FILE_SIZE = 50 * 1024 * 1024 // 50MB
const MAX_DURATION_SECONDS = 120 // 2 minutes
const DURATION_PROBE_TIMEOUT_MS = 10_000 // safety net if the browser fires neither metadata nor error
const FACE_VIDEOS_CACHE_TTL_MS = 5 * 60 * 1000

const faceVideosResource = createSharedCachedResource<FaceVideo[]>({
  key: 'face-videos',
  initialValue: [],
  ttlMs: FACE_VIDEOS_CACHE_TTL_MS,
  load: async () => {
    const response = await faceApi.listFaceVideos()
    return response.data
  },
  getErrorMessage: getApiErrorMessage,
})

interface UseFaceVideosReturn {
  videos: Ref<FaceVideo[]>
  actingVideos: ComputedRef<FaceVideo[]>
  ugcVideos: ComputedRef<FaceVideo[]>
  isLoading: Ref<boolean>
  isUploading: Ref<boolean>
  uploadingType: Ref<FaceVideoType | null>
  isDeleting: Ref<boolean>
  deletingType: Ref<FaceVideoType | null>
  error: Ref<string | null>
  errorType: Ref<FaceVideoType | null>
  uploadProgress: Ref<VideoUploadProgress | null>
  fetchVideos: () => Promise<void>
  uploadVideo: (type: FaceVideoType, file: File) => Promise<FaceVideoUploadResult>
  deleteVideo: (videoId: string) => Promise<FaceVideoDeleteResult>
  validateFile: (file: File) => { valid: boolean; error?: string }
  validateDuration: (file: File) => Promise<{ valid: boolean; error?: string }>
}

/**
 * Composable for Face portfolio videos (acting + ugc, typed FP-2.2.1 API).
 */
export function useFaceVideos(): UseFaceVideosReturn {
  const videos = faceVideosResource.data
  const isLoading = faceVideosResource.isLoading
  const isUploading = ref(false)
  // Tracks which type (acting | ugc) is currently uploading so the two parent-side
  // <FaceVideoUpload> instances can show the progress overlay only on the right one.
  const uploadingType = ref<FaceVideoType | null>(null)
  const isDeleting = ref(false)
  // Same per-type isolation for deletes (the delete overlay / disabled state must
  // only apply to the section whose video is being removed).
  const deletingType = ref<FaceVideoType | null>(null)
  const error = faceVideosResource.error
  // Per-type tagging of the last error so the parent can scope the red banner to
  // the section that actually failed (acting or ugc) — without this, both
  // <FaceVideoUpload> instances bound to the singleton error.ref would render
  // the same banner.
  const errorType = ref<FaceVideoType | null>(null)
  const uploadProgress = ref<VideoUploadProgress | null>(null)

  const actingVideos = computed(() => videos.value.filter((v) => v.type === 'acting'))
  const ugcVideos = computed(() => videos.value.filter((v) => v.type === 'ugc'))

  function validateFile(file: File): { valid: boolean; error?: string } {
    const fileName = file.name.toLowerCase()
    const hasValidExtension = ALLOWED_EXTENSIONS.some((ext) => fileName.endsWith(ext))
    if (!hasValidExtension) {
      return { valid: false, error: 'Format non supporté (MP4, MOV, AVI uniquement)' }
    }
    if (!ALLOWED_TYPES.includes(file.type)) {
      return { valid: false, error: 'Format non supporté (MP4, MOV, AVI uniquement)' }
    }
    if (file.size > MAX_FILE_SIZE) {
      return { valid: false, error: 'Vidéo trop volumineuse (max 50MB)' }
    }
    return { valid: true }
  }

  async function validateDuration(file: File): Promise<{ valid: boolean; error?: string }> {
    return new Promise((resolve) => {
      const video = document.createElement('video')
      video.preload = 'metadata'

      let settled = false
      const settleOnce = (result: { valid: boolean; error?: string }): void => {
        if (settled) return
        settled = true
        clearTimeout(timeoutId)
        URL.revokeObjectURL(video.src)
        resolve(result)
      }

      // Safety net : if the browser fires neither onloadedmetadata nor onerror
      // (corrupt file, exotic codec, hardware acceleration race), the Promise
      // would otherwise hang forever — defer to server validation.
      const timeoutId = setTimeout(() => {
        settleOnce({ valid: true })
      }, DURATION_PROBE_TIMEOUT_MS)

      video.onloadedmetadata = () => {
        if (Number.isFinite(video.duration) && video.duration > MAX_DURATION_SECONDS) {
          settleOnce({ valid: false, error: 'Vidéo trop longue (max 2 minutes)' })
        } else {
          settleOnce({ valid: true })
        }
      }

      video.onerror = () => {
        // If we can't read metadata, let the server validate.
        settleOnce({ valid: true })
      }

      video.src = URL.createObjectURL(file)
    })
  }

  async function fetchVideos(): Promise<void> {
    await faceVideosResource.fetch()
  }

  async function uploadVideo(
    type: FaceVideoType,
    file: File,
  ): Promise<FaceVideoUploadResult> {
    // P5 — concurrent-upload guard. The two parent-side <FaceVideoUpload>
    // instances disable their own CTA via the per-type isProcessing computeds,
    // but the OTHER type's CTA is not disabled (uploadingType !== that type).
    // A user could click acting upload, then ugc upload, racing both calls
    // and clobbering uploadingType/uploadProgress.
    if (isUploading.value) {
      const message = 'Un envoi est déjà en cours, veuillez patienter.'
      error.value = message
      errorType.value = type
      return { success: false, message }
    }

    const fileValidation = validateFile(file)
    if (!fileValidation.valid) {
      error.value = fileValidation.error ?? 'Fichier invalide'
      errorType.value = type
      return { success: false, message: fileValidation.error }
    }

    const durationValidation = await validateDuration(file)
    if (!durationValidation.valid) {
      error.value = durationValidation.error ?? 'Durée invalide'
      errorType.value = type
      return { success: false, message: durationValidation.error }
    }

    isUploading.value = true
    uploadingType.value = type
    error.value = null
    errorType.value = null
    uploadProgress.value = { loaded: 0, total: file.size, percentage: 0 }

    try {
      const response = await faceApi.uploadFaceVideo(type, file, (progress) => {
        uploadProgress.value = progress
      })
      faceVideosResource.mutate((current) => [...current, response.data])

      return {
        success: true,
        data: response.data,
        message: response.message,
      }
    } catch (err) {
      const errors = getApiErrorDetails(err)
      const message = getApiErrorMessage(err)
      error.value = message
      errorType.value = type
      return { success: false, errors, message }
    } finally {
      isUploading.value = false
      uploadingType.value = null
      uploadProgress.value = null
    }
  }

  async function deleteVideo(videoId: string): Promise<FaceVideoDeleteResult> {
    // Capture the type up-front (the target row is still in the cache here) so we
    // can drive the per-type deletingType ref AND know which same-type slice to
    // renumber once the API call resolves.
    const targetType = videos.value.find((v) => v.id === videoId)?.type ?? null

    // P5 — concurrent-delete guard. Mirrors uploadVideo guard.
    if (isDeleting.value) {
      const message = 'Une suppression est déjà en cours, veuillez patienter.'
      error.value = message
      errorType.value = targetType
      return { success: false, message }
    }

    isDeleting.value = true
    deletingType.value = targetType
    error.value = null
    errorType.value = null

    try {
      const response = await faceApi.deleteFaceVideo(videoId)

      const current = videos.value
      const toDelete = current.find((v) => v.id === videoId)
      if (toDelete) {
        const deletedType = toDelete.type
        const remaining = current.filter((v) => v.id !== videoId)
        const remainingSameType = remaining
          .filter((v) => v.type === deletedType)
          .sort((a, b) => a.position - b.position)
          .map((v, idx) => ({ ...v, position: idx + 1 }))
        const remainingOtherTypes = remaining.filter((v) => v.type !== deletedType)
        faceVideosResource.setData([...remainingOtherTypes, ...remainingSameType])
      }

      return { success: true, message: response.message }
    } catch (err) {
      const errors = getApiErrorDetails(err)
      const message = getApiErrorMessage(err)
      error.value = message
      errorType.value = targetType
      return { success: false, errors, message }
    } finally {
      isDeleting.value = false
      deletingType.value = null
    }
  }

  return {
    videos,
    actingVideos,
    ugcVideos,
    isLoading,
    isUploading,
    uploadingType,
    isDeleting,
    deletingType,
    error,
    errorType,
    uploadProgress,
    fetchVideos,
    uploadVideo,
    deleteVideo,
    validateFile,
    validateDuration,
  }
}
