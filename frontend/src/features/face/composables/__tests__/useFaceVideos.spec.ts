import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useFaceVideos } from '../useFaceVideos'
import { faceApi } from '../../services/faceApi'
import { resetAllSharedCachedResources } from '@/lib/createSharedCachedResource'
import type {
  FaceVideo,
  FaceVideosListResponse,
  FaceVideoUploadResponse,
  FaceVideoDeleteResponse,
} from '../../types'

vi.mock('../../services/faceApi', () => ({
  faceApi: {
    listFaceVideos: vi.fn(),
    uploadFaceVideo: vi.fn(),
    deleteFaceVideo: vi.fn(),
  },
}))

vi.mock('@/features/auth/services/authApi', () => ({
  getApiErrorDetails: vi.fn(() => ({})),
  getApiErrorMessage: vi.fn(() => 'Une erreur est survenue'),
}))

global.URL.createObjectURL = vi.fn(() => 'blob:mock-url')
global.URL.revokeObjectURL = vi.fn()

const originalCreateElement = document.createElement.bind(document)

const videoActing1: FaceVideo = {
  id: 'uuid-a1',
  type: 'acting',
  video_url: 'http://x/a1.mp4',
  thumbnail_url: 'http://x/a1.jpg',
  position: 1,
}

const videoActing2: FaceVideo = {
  id: 'uuid-a2',
  type: 'acting',
  video_url: 'http://x/a2.mp4',
  thumbnail_url: 'http://x/a2.jpg',
  position: 2,
}

const videoUgc1: FaceVideo = {
  id: 'uuid-u1',
  type: 'ugc',
  video_url: 'http://x/u1.mp4',
  thumbnail_url: 'http://x/u1.jpg',
  position: 1,
}

function makeValidFile(name = 'video.mp4', type = 'video/mp4', size = 1024): File {
  const file = new File(['x'.repeat(size)], name, { type })
  Object.defineProperty(file, 'size', { value: size, writable: false })
  return file
}

describe('useFaceVideos', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    resetAllSharedCachedResources()

    vi.spyOn(document, 'createElement').mockImplementation((tag: string) => {
      if (tag === 'video') {
        const mockVideo = {
          preload: '',
          src: '',
          duration: 60,
          onloadedmetadata: null as (() => void) | null,
          onerror: null as (() => void) | null,
        }
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
    it('has empty cache and all flags false', () => {
      const { videos, actingVideos, ugcVideos, isLoading, isUploading, isDeleting, error, uploadProgress, uploadingType, deletingType } =
        useFaceVideos()

      expect(videos.value).toEqual([])
      expect(actingVideos.value).toEqual([])
      expect(ugcVideos.value).toEqual([])
      expect(isLoading.value).toBe(false)
      expect(isUploading.value).toBe(false)
      expect(isDeleting.value).toBe(false)
      expect(error.value).toBeNull()
      expect(uploadProgress.value).toBeNull()
      expect(uploadingType.value).toBeNull()
      expect(deletingType.value).toBeNull()
    })
  })

  describe('fetchVideos', () => {
    it('populates the cache from listFaceVideos response', async () => {
      const response: FaceVideosListResponse = {
        data: [videoActing1, videoActing2, videoUgc1],
      }
      vi.mocked(faceApi.listFaceVideos).mockResolvedValue(response)

      const { videos, fetchVideos } = useFaceVideos()
      await fetchVideos()

      expect(faceApi.listFaceVideos).toHaveBeenCalledOnce()
      expect(videos.value).toHaveLength(3)
      expect(videos.value).toEqual([videoActing1, videoActing2, videoUgc1])
    })
  })

  describe('actingVideos / ugcVideos partitioning', () => {
    it('correctly partitions a mixed list', async () => {
      const response: FaceVideosListResponse = {
        data: [videoActing1, videoUgc1, videoActing2],
      }
      vi.mocked(faceApi.listFaceVideos).mockResolvedValue(response)

      const { actingVideos, ugcVideos, fetchVideos } = useFaceVideos()
      await fetchVideos()

      expect(actingVideos.value).toHaveLength(2)
      expect(actingVideos.value.map((v) => v.id)).toEqual(['uuid-a1', 'uuid-a2'])
      expect(ugcVideos.value).toHaveLength(1)
      expect(ugcVideos.value[0].id).toBe('uuid-u1')
    })
  })

  describe('uploadVideo', () => {
    it('appends a successful acting upload to the cache', async () => {
      const uploadResponse: FaceVideoUploadResponse = {
        data: videoActing1,
        message: 'Vidéo ajoutée avec succès.',
      }
      vi.mocked(faceApi.uploadFaceVideo).mockResolvedValue(uploadResponse)

      const { videos, uploadVideo } = useFaceVideos()
      const result = await uploadVideo('acting', makeValidFile())

      expect(result.success).toBe(true)
      expect(result.data).toEqual(videoActing1)
      expect(result.message).toBe('Vidéo ajoutée avec succès.')
      expect(videos.value).toEqual([videoActing1])
      expect(faceApi.uploadFaceVideo).toHaveBeenCalledWith(
        'acting',
        expect.any(File),
        expect.any(Function),
      )
    })

    it('appends a successful ugc upload to the cache (forwards the type param)', async () => {
      const uploadResponse: FaceVideoUploadResponse = {
        data: videoUgc1,
        message: 'Vidéo ajoutée avec succès.',
      }
      vi.mocked(faceApi.uploadFaceVideo).mockResolvedValue(uploadResponse)

      const { videos, uploadVideo } = useFaceVideos()
      const result = await uploadVideo('ugc', makeValidFile())

      expect(result.success).toBe(true)
      expect(videos.value).toEqual([videoUgc1])
      expect(faceApi.uploadFaceVideo).toHaveBeenCalledWith(
        'ugc',
        expect.any(File),
        expect.any(Function),
      )
    })

    it('rejects an invalid file extension without calling the API', async () => {
      const { uploadVideo, error } = useFaceVideos()
      const result = await uploadVideo('acting', makeValidFile('image.gif', 'image/gif'))

      expect(result.success).toBe(false)
      expect(result.message).toContain('Format non supporté')
      expect(error.value).toContain('Format non supporté')
      expect(faceApi.uploadFaceVideo).not.toHaveBeenCalled()
    })

    it('rejects an oversized file (>50MB) without calling the API', async () => {
      const { uploadVideo, error } = useFaceVideos()
      const tooLargeFile = makeValidFile('big.mp4', 'video/mp4', 51 * 1024 * 1024)
      const result = await uploadVideo('acting', tooLargeFile)

      expect(result.success).toBe(false)
      expect(result.message).toContain('trop volumineuse')
      expect(error.value).toContain('trop volumineuse')
      expect(faceApi.uploadFaceVideo).not.toHaveBeenCalled()
    })

    it('surfaces a server 422 (VIDEO_QUOTA_REACHED) error', async () => {
      const apiError = new Error('VIDEO_QUOTA_REACHED')
      vi.mocked(faceApi.uploadFaceVideo).mockRejectedValue(apiError)

      const { uploadVideo, error } = useFaceVideos()
      const result = await uploadVideo('acting', makeValidFile())

      expect(result.success).toBe(false)
      expect(result.message).toBe('Une erreur est survenue')
      expect(error.value).toBe('Une erreur est survenue')
    })
  })

  describe('deleteVideo', () => {
    it('removes a single row and renumbers the same-type slice', async () => {
      const listResponse: FaceVideosListResponse = {
        data: [videoActing1, videoActing2],
      }
      vi.mocked(faceApi.listFaceVideos).mockResolvedValue(listResponse)

      const deleteResponse: FaceVideoDeleteResponse = { message: 'Vidéo supprimée avec succès.' }
      vi.mocked(faceApi.deleteFaceVideo).mockResolvedValue(deleteResponse)

      const { videos, fetchVideos, deleteVideo } = useFaceVideos()
      await fetchVideos()

      const result = await deleteVideo('uuid-a1')

      expect(result.success).toBe(true)
      expect(result.message).toBe('Vidéo supprimée avec succès.')
      expect(videos.value).toHaveLength(1)
      // The previous position-2 acting video is renumbered to position 1.
      expect(videos.value[0]).toEqual({ ...videoActing2, position: 1 })
    })

    it('renumbers only the same-type slice, leaving other-type rows untouched', async () => {
      const listResponse: FaceVideosListResponse = {
        data: [videoActing1, videoActing2, videoUgc1],
      }
      vi.mocked(faceApi.listFaceVideos).mockResolvedValue(listResponse)

      const deleteResponse: FaceVideoDeleteResponse = { message: 'Vidéo supprimée avec succès.' }
      vi.mocked(faceApi.deleteFaceVideo).mockResolvedValue(deleteResponse)

      const { videos, actingVideos, ugcVideos, fetchVideos, deleteVideo } = useFaceVideos()
      await fetchVideos()

      await deleteVideo('uuid-a1')

      expect(videos.value).toHaveLength(2)
      expect(actingVideos.value).toHaveLength(1)
      expect(actingVideos.value[0]).toEqual({ ...videoActing2, position: 1 })
      expect(ugcVideos.value).toHaveLength(1)
      // The UGC position-1 stays at position 1.
      expect(ugcVideos.value[0]).toEqual(videoUgc1)
    })

    it('treats backend success as authoritative when the videoId is not in the local cache', async () => {
      const deleteResponse: FaceVideoDeleteResponse = { message: 'Vidéo supprimée avec succès.' }
      vi.mocked(faceApi.deleteFaceVideo).mockResolvedValue(deleteResponse)

      const { videos, error, deleteVideo } = useFaceVideos()
      // No fetch — cache is empty.
      const result = await deleteVideo('uuid-missing')

      expect(result.success).toBe(true)
      expect(result.message).toBe('Vidéo supprimée avec succès.')
      expect(videos.value).toEqual([])
      expect(error.value).toBeNull()
    })
  })

  describe('validateFile / validateDuration', () => {
    it('rejects unsupported extensions and oversized files', () => {
      const { validateFile } = useFaceVideos()
      expect(validateFile(makeValidFile('img.png', 'image/png')).valid).toBe(false)
      const big = makeValidFile('big.mp4', 'video/mp4', 51 * 1024 * 1024)
      expect(validateFile(big).valid).toBe(false)
      expect(validateFile(makeValidFile()).valid).toBe(true)
    })
  })

  describe('per-type isolation refs (P4 — UX bleeding fix)', () => {
    it('flips uploadingType to acting during an acting upload then resets to null', async () => {
      let resolveUpload: (value: FaceVideoUploadResponse) => void = () => {}
      const slowUpload = new Promise<FaceVideoUploadResponse>((resolve) => {
        resolveUpload = resolve
      })
      vi.mocked(faceApi.uploadFaceVideo).mockReturnValue(slowUpload)

      const { uploadVideo, uploadingType, isUploading } = useFaceVideos()

      const promise = uploadVideo('acting', makeValidFile())
      // Wait for validateDuration's async resolve (setTimeout 0).
      await new Promise((r) => setTimeout(r, 5))

      expect(uploadingType.value).toBe('acting')
      expect(isUploading.value).toBe(true)

      resolveUpload({ data: videoActing1 })
      await promise

      expect(uploadingType.value).toBeNull()
      expect(isUploading.value).toBe(false)
    })

    it('flips deletingType to the target row type during the delete', async () => {
      const listResponse: FaceVideosListResponse = {
        data: [videoUgc1],
      }
      vi.mocked(faceApi.listFaceVideos).mockResolvedValue(listResponse)

      let resolveDelete: (value: FaceVideoDeleteResponse) => void = () => {}
      const slowDelete = new Promise<FaceVideoDeleteResponse>((resolve) => {
        resolveDelete = resolve
      })
      vi.mocked(faceApi.deleteFaceVideo).mockReturnValue(slowDelete)

      const { fetchVideos, deleteVideo, deletingType, isDeleting } = useFaceVideos()
      await fetchVideos()

      const promise = deleteVideo('uuid-u1')

      expect(deletingType.value).toBe('ugc')
      expect(isDeleting.value).toBe(true)

      resolveDelete({ message: 'Vidéo supprimée avec succès.' })
      await promise

      expect(deletingType.value).toBeNull()
      expect(isDeleting.value).toBe(false)
    })
  })

  describe('per-type error scoping (P2 — UX bleed fix)', () => {
    it('tags errorType with the failing type so parent can scope the red banner', async () => {
      const apiError = new Error('VIDEO_QUOTA_REACHED')
      vi.mocked(faceApi.uploadFaceVideo).mockRejectedValue(apiError)

      const { uploadVideo, error, errorType } = useFaceVideos()
      const result = await uploadVideo('ugc', makeValidFile())

      expect(result.success).toBe(false)
      expect(error.value).toBe('Une erreur est survenue')
      expect(errorType.value).toBe('ugc')
    })

    it('clears errorType on a fresh upload start', async () => {
      // Seed a prior error on the acting type.
      vi.mocked(faceApi.uploadFaceVideo).mockRejectedValueOnce(new Error('boom'))
      const { uploadVideo, errorType } = useFaceVideos()
      await uploadVideo('acting', makeValidFile())
      expect(errorType.value).toBe('acting')

      // Next successful upload of ugc should reset errorType to null.
      vi.mocked(faceApi.uploadFaceVideo).mockResolvedValueOnce({ data: videoUgc1 })
      const result = await uploadVideo('ugc', makeValidFile())
      expect(result.success).toBe(true)
      expect(errorType.value).toBeNull()
    })
  })

  describe('concurrent operation guards (P5 — race fix)', () => {
    it('rejects a second uploadVideo while the first is in flight', async () => {
      let resolveFirst: (value: FaceVideoUploadResponse) => void = () => {}
      const slow = new Promise<FaceVideoUploadResponse>((resolve) => {
        resolveFirst = resolve
      })
      vi.mocked(faceApi.uploadFaceVideo).mockReturnValueOnce(slow)

      const { uploadVideo, isUploading, errorType, error } = useFaceVideos()
      const first = uploadVideo('acting', makeValidFile())
      // Wait for validateDuration's async resolve.
      await new Promise((r) => setTimeout(r, 5))
      expect(isUploading.value).toBe(true)

      const second = await uploadVideo('ugc', makeValidFile())
      expect(second.success).toBe(false)
      expect(second.message).toContain('déjà en cours')
      expect(errorType.value).toBe('ugc')
      expect(error.value).toContain('déjà en cours')
      // faceApi.uploadFaceVideo should have been called only once (the first call).
      expect(faceApi.uploadFaceVideo).toHaveBeenCalledTimes(1)

      // Clean up the in-flight first call.
      resolveFirst({ data: videoActing1 })
      await first
    })

    it('rejects a second deleteVideo while the first is in flight', async () => {
      vi.mocked(faceApi.listFaceVideos).mockResolvedValue({
        data: [videoActing1, videoActing2],
      })
      let resolveFirst: (value: FaceVideoDeleteResponse) => void = () => {}
      const slow = new Promise<FaceVideoDeleteResponse>((resolve) => {
        resolveFirst = resolve
      })
      vi.mocked(faceApi.deleteFaceVideo).mockReturnValueOnce(slow)

      const { fetchVideos, deleteVideo, isDeleting } = useFaceVideos()
      await fetchVideos()

      const first = deleteVideo('uuid-a1')
      expect(isDeleting.value).toBe(true)

      const second = await deleteVideo('uuid-a2')
      expect(second.success).toBe(false)
      expect(second.message).toContain('déjà en cours')
      expect(faceApi.deleteFaceVideo).toHaveBeenCalledTimes(1)

      resolveFirst({ message: 'ok' })
      await first
    })
  })

  describe('validateDuration timeout safety net (P4)', () => {
    it('resolves valid:true if neither onloadedmetadata nor onerror fires (timeout fallback)', async () => {
      // Switch to fake timers BEFORE we create the video element, so the
      // setTimeout(...) registered inside validateDuration is scheduled on
      // the fake clock and we can advance past 10_000 ms.
      vi.useFakeTimers()

      // Override createElement: this mock NEVER fires the metadata or error
      // callbacks, simulating a corrupt file the browser can't decode.
      vi.spyOn(document, 'createElement').mockImplementation((tag: string) => {
        if (tag === 'video') {
          const mockVideo = {
            preload: '',
            src: '',
            duration: 0,
            onloadedmetadata: null as (() => void) | null,
            onerror: null as (() => void) | null,
          }
          return mockVideo as unknown as HTMLVideoElement
        }
        return originalCreateElement(tag)
      })

      const { validateDuration } = useFaceVideos()
      const promise = validateDuration(makeValidFile())

      // Advance past the 10s safety net.
      await vi.advanceTimersByTimeAsync(10_001)
      const result = await promise

      expect(result.valid).toBe(true)
      vi.useRealTimers()
    })
  })
})
