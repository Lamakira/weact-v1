import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useActingVideo } from '../useActingVideo'
import { faceApi } from '../../services/faceApi'
import type { ActingVideoInfo, ActingVideoResponse } from '../../types'

// Mock the faceApi module
vi.mock('../../services/faceApi', () => ({
  faceApi: {
    getActingVideo: vi.fn(),
    uploadActingVideo: vi.fn(),
    deleteActingVideo: vi.fn(),
  },
}))

// Mock the authApi error helpers
vi.mock('@/features/auth/services/authApi', () => ({
  getApiErrorDetails: vi.fn(() => ({})),
  getApiErrorMessage: vi.fn(() => 'Une erreur est survenue'),
}))

// Mock URL.createObjectURL and URL.revokeObjectURL
global.URL.createObjectURL = vi.fn(() => 'blob:mock-url')
global.URL.revokeObjectURL = vi.fn()

// Store the original createElement
const originalCreateElement = document.createElement.bind(document)

describe('useActingVideo', () => {
  const mockVideoInfo: ActingVideoInfo = {
    acting_video_url: 'http://localhost/storage/videos/faces/acting/video.mp4',
    acting_video_thumbnail_url:
      'http://localhost/storage/videos/faces/acting/thumbnails/thumbnail.jpg',
  }

  const mockResponse: ActingVideoResponse = {
    data: mockVideoInfo,
    message: "Vidéo d'acting uploadée avec succès",
  }

  // Mock HTMLVideoElement for duration validation
  beforeEach(() => {
    vi.clearAllMocks()
    vi.spyOn(document, 'createElement').mockImplementation((tag: string) => {
      if (tag === 'video') {
        const mockVideo = {
          preload: '',
          src: '',
          duration: 60, // Valid duration under 2 minutes
          onloadedmetadata: null as (() => void) | null,
          onerror: null as (() => void) | null,
        }
        // Simulate metadata load asynchronously
        setTimeout(() => {
          if (mockVideo.onloadedmetadata) {
            mockVideo.onloadedmetadata()
          }
        }, 0)
        return mockVideo as unknown as HTMLVideoElement
      }
      return originalCreateElement(tag)
    })
  })

  describe('initial state', () => {
    it('has correct initial state', () => {
      const { videoInfo, isLoading, isUploading, isDeleting, error, hasVideo, uploadProgress } =
        useActingVideo()

      expect(videoInfo.value).toBeNull()
      expect(isLoading.value).toBe(false)
      expect(isUploading.value).toBe(false)
      expect(isDeleting.value).toBe(false)
      expect(error.value).toBeNull()
      expect(hasVideo.value).toBe(false)
      expect(uploadProgress.value).toBeNull()
    })
  })

  describe('fetchVideoInfo', () => {
    it('fetches video info successfully', async () => {
      vi.mocked(faceApi.getActingVideo).mockResolvedValue(mockResponse)

      const { videoInfo, isLoading, error, fetchVideoInfo } = useActingVideo()

      await fetchVideoInfo()

      expect(videoInfo.value).toEqual(mockVideoInfo)
      expect(isLoading.value).toBe(false)
      expect(error.value).toBeNull()
      expect(faceApi.getActingVideo).toHaveBeenCalledOnce()
    })

    it('sets loading state during fetch', async () => {
      let resolvePromise: (value: ActingVideoResponse) => void
      const promise = new Promise<ActingVideoResponse>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(faceApi.getActingVideo).mockReturnValue(promise)

      const { isLoading, fetchVideoInfo } = useActingVideo()

      const fetchPromise = fetchVideoInfo()
      expect(isLoading.value).toBe(true)

      resolvePromise!(mockResponse)
      await fetchPromise

      expect(isLoading.value).toBe(false)
    })

    it('handles fetch error', async () => {
      vi.mocked(faceApi.getActingVideo).mockRejectedValue(new Error('Network error'))

      const { videoInfo, error, fetchVideoInfo } = useActingVideo()

      await fetchVideoInfo()

      expect(videoInfo.value).toBeNull()
      expect(error.value).toBe('Une erreur est survenue')
    })
  })

  describe('uploadVideo', () => {
    it('uploads video successfully', async () => {
      vi.mocked(faceApi.uploadActingVideo).mockResolvedValue(mockResponse)

      const { videoInfo, isUploading, error, uploadVideo } = useActingVideo()
      const file = new File(['test'], 'test.mp4', { type: 'video/mp4' })

      const result = await uploadVideo(file)

      expect(result.success).toBe(true)
      expect(result.data).toEqual(mockVideoInfo)
      expect(result.message).toBe("Vidéo d'acting uploadée avec succès")
      expect(videoInfo.value).toEqual(mockVideoInfo)
      expect(isUploading.value).toBe(false)
      expect(error.value).toBeNull()
    })

    it('sets uploading state during upload', async () => {
      let resolvePromise: (value: ActingVideoResponse) => void
      const promise = new Promise<ActingVideoResponse>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(faceApi.uploadActingVideo).mockReturnValue(promise)

      const { isUploading, uploadVideo } = useActingVideo()
      const file = new File(['test'], 'test.mp4', { type: 'video/mp4' })

      const uploadPromise = uploadVideo(file)

      // Wait for async duration validation to complete (mocked via setTimeout)
      await vi.waitFor(() => {
        expect(isUploading.value).toBe(true)
      })

      resolvePromise!(mockResponse)
      await uploadPromise

      expect(isUploading.value).toBe(false)
    })

    it('handles upload error', async () => {
      vi.mocked(faceApi.uploadActingVideo).mockRejectedValue(new Error('Upload failed'))

      const { error, uploadVideo } = useActingVideo()
      const file = new File(['test'], 'test.mp4', { type: 'video/mp4' })

      const result = await uploadVideo(file)

      expect(result.success).toBe(false)
      expect(result.message).toBe('Une erreur est survenue')
      expect(error.value).toBe('Une erreur est survenue')
    })
  })

  describe('deleteVideo', () => {
    it('deletes video successfully', async () => {
      const deleteResponse = { message: 'Vidéo supprimée avec succès' }
      vi.mocked(faceApi.deleteActingVideo).mockResolvedValue(deleteResponse)

      const { videoInfo, isDeleting, error, deleteVideo } = useActingVideo()

      const result = await deleteVideo()

      expect(result.success).toBe(true)
      expect(result.message).toBe('Vidéo supprimée avec succès')
      expect(videoInfo.value?.acting_video_url).toBeNull()
      expect(videoInfo.value?.acting_video_thumbnail_url).toBeNull()
      expect(isDeleting.value).toBe(false)
      expect(error.value).toBeNull()
    })

    it('sets deleting state during deletion', async () => {
      let resolvePromise: (value: { message: string }) => void
      const promise = new Promise<{ message: string }>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(faceApi.deleteActingVideo).mockReturnValue(promise)

      const { isDeleting, deleteVideo } = useActingVideo()

      const deletePromise = deleteVideo()
      expect(isDeleting.value).toBe(true)

      resolvePromise!({ message: 'Vidéo supprimée avec succès' })
      await deletePromise

      expect(isDeleting.value).toBe(false)
    })

    it('handles delete error', async () => {
      vi.mocked(faceApi.deleteActingVideo).mockRejectedValue(new Error('Delete failed'))

      const { error, deleteVideo } = useActingVideo()

      const result = await deleteVideo()

      expect(result.success).toBe(false)
      expect(result.message).toBe('Une erreur est survenue')
      expect(error.value).toBe('Une erreur est survenue')
    })
  })

  describe('validateFile', () => {
    it('validates valid MP4 file', () => {
      const { validateFile } = useActingVideo()
      const file = new File(['test'], 'test.mp4', { type: 'video/mp4' })

      const result = validateFile(file)

      expect(result.valid).toBe(true)
      expect(result.error).toBeUndefined()
    })

    it('validates valid MOV file', () => {
      const { validateFile } = useActingVideo()
      const file = new File(['test'], 'test.mov', { type: 'video/quicktime' })

      const result = validateFile(file)

      expect(result.valid).toBe(true)
      expect(result.error).toBeUndefined()
    })

    it('validates valid AVI file', () => {
      const { validateFile } = useActingVideo()
      const file = new File(['test'], 'test.avi', { type: 'video/x-msvideo' })

      const result = validateFile(file)

      expect(result.valid).toBe(true)
      expect(result.error).toBeUndefined()
    })

    it('rejects invalid file type', () => {
      const { validateFile } = useActingVideo()
      const file = new File(['test'], 'test.wmv', { type: 'video/x-ms-wmv' })

      const result = validateFile(file)

      expect(result.valid).toBe(false)
      expect(result.error).toBe('Format non supporté (MP4, MOV, AVI uniquement)')
    })

    it('rejects invalid file extension', () => {
      const { validateFile } = useActingVideo()
      const file = new File(['test'], 'test.mkv', { type: 'video/mp4' })

      const result = validateFile(file)

      expect(result.valid).toBe(false)
      expect(result.error).toBe('Format non supporté (MP4, MOV, AVI uniquement)')
    })

    it('rejects oversized file', () => {
      const { validateFile } = useActingVideo()
      // Create a small file but mock its size to be > 50MB
      const file = new File(['test'], 'test.mp4', { type: 'video/mp4' })
      Object.defineProperty(file, 'size', { value: 51 * 1024 * 1024 })

      const result = validateFile(file)

      expect(result.valid).toBe(false)
      expect(result.error).toBe('Vidéo trop volumineuse (max 50MB)')
    })

    it('rejects invalid file before calling API', async () => {
      const { uploadVideo } = useActingVideo()
      const file = new File(['test'], 'test.wmv', { type: 'video/x-ms-wmv' })

      const result = await uploadVideo(file)

      expect(result.success).toBe(false)
      expect(result.message).toBe('Format non supporté (MP4, MOV, AVI uniquement)')
      expect(faceApi.uploadActingVideo).not.toHaveBeenCalled()
    })

    it('sets error ref when validation fails', async () => {
      const { uploadVideo, error } = useActingVideo()
      const file = new File(['test'], 'test.wmv', { type: 'video/x-ms-wmv' })

      expect(error.value).toBeNull()

      await uploadVideo(file)

      expect(error.value).toBe('Format non supporté (MP4, MOV, AVI uniquement)')
    })

    it('sets error ref when file is too large', async () => {
      const { uploadVideo, error } = useActingVideo()
      // Create a small file but mock its size to be > 50MB
      const file = new File(['test'], 'test.mp4', { type: 'video/mp4' })
      Object.defineProperty(file, 'size', { value: 51 * 1024 * 1024 })

      await uploadVideo(file)

      expect(error.value).toBe('Vidéo trop volumineuse (max 50MB)')
    })
  })

  describe('computed properties', () => {
    it('hasVideo returns true when video URL exists', async () => {
      vi.mocked(faceApi.getActingVideo).mockResolvedValue(mockResponse)

      const { hasVideo, fetchVideoInfo } = useActingVideo()

      await fetchVideoInfo()

      expect(hasVideo.value).toBe(true)
    })

    it('hasVideo returns false when no video URL', async () => {
      const noVideoInfo: ActingVideoInfo = {
        acting_video_url: null,
        acting_video_thumbnail_url: null,
      }
      vi.mocked(faceApi.getActingVideo).mockResolvedValue({ data: noVideoInfo })

      const { hasVideo, fetchVideoInfo } = useActingVideo()

      await fetchVideoInfo()

      expect(hasVideo.value).toBe(false)
    })

    it('videoUrl returns correct URL', async () => {
      vi.mocked(faceApi.getActingVideo).mockResolvedValue(mockResponse)

      const { videoUrl, fetchVideoInfo } = useActingVideo()

      await fetchVideoInfo()

      expect(videoUrl.value).toBe(mockVideoInfo.acting_video_url)
    })

    it('thumbnailUrl returns correct URL', async () => {
      vi.mocked(faceApi.getActingVideo).mockResolvedValue(mockResponse)

      const { thumbnailUrl, fetchVideoInfo } = useActingVideo()

      await fetchVideoInfo()

      expect(thumbnailUrl.value).toBe(mockVideoInfo.acting_video_thumbnail_url)
    })
  })
})
