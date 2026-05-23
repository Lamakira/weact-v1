import { describe, it, expect, vi, beforeEach } from 'vitest'
import { usePhotoAlbum } from '../usePhotoAlbum'
import { faceApi } from '../../services/faceApi'
import type { FacePhoto, FacePhotosResponse, FacePhotoResponse } from '../../types'

// Mock the faceApi module
vi.mock('../../services/faceApi', () => ({
  faceApi: {
    getAlbumPhotos: vi.fn(),
    addAlbumPhoto: vi.fn(),
    deleteAlbumPhoto: vi.fn(),
    reorderAlbumPhotos: vi.fn(),
  },
}))

// Mock the authApi error helpers
vi.mock('@/features/auth/services/authApi', () => ({
  getApiErrorDetails: vi.fn(() => ({})),
  getApiErrorMessage: vi.fn(() => 'Une erreur est survenue'),
}))

describe('usePhotoAlbum', () => {
  const mockPhoto1: FacePhoto = {
    id: 1,
    photo_url: 'http://localhost/storage/avatars/faces/albums/photo1.jpg',
    thumbnail_url: 'http://localhost/storage/avatars/faces/albums/thumbnails/photo1.jpg',
    position: 1,
  }

  const mockPhoto2: FacePhoto = {
    id: 2,
    photo_url: 'http://localhost/storage/avatars/faces/albums/photo2.jpg',
    thumbnail_url: 'http://localhost/storage/avatars/faces/albums/thumbnails/photo2.jpg',
    position: 2,
  }

  const mockPhotosResponse: FacePhotosResponse = {
    data: [mockPhoto1, mockPhoto2],
    message: 'Photos récupérées',
  }

  const mockPhotoResponse: FacePhotoResponse = {
    data: mockPhoto1,
    message: 'Photo ajoutée à l\'album',
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('initial state', () => {
    it('has correct initial state', () => {
      const {
        photos,
        isLoading,
        isUploading,
        isDeleting,
        isReordering,
        error,
        photoCount,
        canAddMore,
        isFull,
      } = usePhotoAlbum()

      expect(photos.value).toEqual([])
      expect(isLoading.value).toBe(false)
      expect(isUploading.value).toBe(false)
      expect(isDeleting.value).toBe(false)
      expect(isReordering.value).toBe(false)
      expect(error.value).toBeNull()
      expect(photoCount.value).toBe(0)
      expect(canAddMore.value).toBe(true)
      expect(isFull.value).toBe(false)
    })
  })

  describe('fetchPhotos', () => {
    it('fetches photos successfully', async () => {
      vi.mocked(faceApi.getAlbumPhotos).mockResolvedValue(mockPhotosResponse)

      const { photos, isLoading, error, fetchPhotos } = usePhotoAlbum()

      await fetchPhotos()

      expect(photos.value).toEqual([mockPhoto1, mockPhoto2])
      expect(isLoading.value).toBe(false)
      expect(error.value).toBeNull()
      expect(faceApi.getAlbumPhotos).toHaveBeenCalledOnce()
    })

    it('sets loading state during fetch', async () => {
      let resolvePromise: (value: FacePhotosResponse) => void
      const promise = new Promise<FacePhotosResponse>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(faceApi.getAlbumPhotos).mockReturnValue(promise)

      const { isLoading, fetchPhotos } = usePhotoAlbum()

      const fetchPromise = fetchPhotos()
      expect(isLoading.value).toBe(true)

      resolvePromise!(mockPhotosResponse)
      await fetchPromise

      expect(isLoading.value).toBe(false)
    })

    it('handles fetch error', async () => {
      vi.mocked(faceApi.getAlbumPhotos).mockRejectedValue(new Error('Network error'))

      const { photos, error, fetchPhotos } = usePhotoAlbum()

      await fetchPhotos()

      expect(photos.value).toEqual([])
      expect(error.value).toBe('Une erreur est survenue')
    })
  })

  describe('addPhoto', () => {
    it('adds photo successfully', async () => {
      vi.mocked(faceApi.addAlbumPhoto).mockResolvedValue(mockPhotoResponse)

      const { photos, isUploading, error, addPhoto } = usePhotoAlbum()
      const file = new File(['test'], 'test.jpg', { type: 'image/jpeg' })

      const result = await addPhoto(file)

      expect(result.success).toBe(true)
      expect(result.data).toEqual(mockPhoto1)
      expect(result.message).toBe('Photo ajoutée à l\'album')
      expect(photos.value).toContainEqual(mockPhoto1)
      expect(isUploading.value).toBe(false)
      expect(error.value).toBeNull()
    })

    it('sets uploading state during upload', async () => {
      let resolvePromise: (value: FacePhotoResponse) => void
      const promise = new Promise<FacePhotoResponse>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(faceApi.addAlbumPhoto).mockReturnValue(promise)

      const { isUploading, addPhoto } = usePhotoAlbum()
      const file = new File(['test'], 'test.jpg', { type: 'image/jpeg' })

      const uploadPromise = addPhoto(file)
      expect(isUploading.value).toBe(true)

      resolvePromise!(mockPhotoResponse)
      await uploadPromise

      expect(isUploading.value).toBe(false)
    })

    it('delegates the album-full check to the backend (no client-side cap)', async () => {
      // After FP-1.7 the composable no longer enforces the album cap client-side.
      // The backend's AlbumQuotaReachedException (HTTP 422) is the canonical signal.
      vi.mocked(faceApi.addAlbumPhoto).mockRejectedValue(new Error('Quota reached'))

      const { photos, addPhoto, error } = usePhotoAlbum()
      photos.value = [
        { ...mockPhoto1, id: 1, position: 1 },
        { ...mockPhoto1, id: 2, position: 2 },
        { ...mockPhoto1, id: 3, position: 3 },
        { ...mockPhoto1, id: 4, position: 4 },
      ]

      const file = new File(['test'], 'test.jpg', { type: 'image/jpeg' })

      const result = await addPhoto(file)

      expect(faceApi.addAlbumPhoto).toHaveBeenCalledOnce()
      expect(result.success).toBe(false)
      expect(result.message).toBe('Une erreur est survenue')
      expect(error.value).toBe('Une erreur est survenue')
    })

    it('handles upload error', async () => {
      vi.mocked(faceApi.addAlbumPhoto).mockRejectedValue(new Error('Upload failed'))

      const { error, addPhoto } = usePhotoAlbum()
      const file = new File(['test'], 'test.jpg', { type: 'image/jpeg' })

      const result = await addPhoto(file)

      expect(result.success).toBe(false)
      expect(result.message).toBe('Une erreur est survenue')
      expect(error.value).toBe('Une erreur est survenue')
    })
  })

  describe('deletePhoto', () => {
    it('deletes photo successfully', async () => {
      vi.mocked(faceApi.deleteAlbumPhoto).mockResolvedValue(undefined)

      const { photos, isDeleting, error, deletePhoto } = usePhotoAlbum()
      photos.value = [mockPhoto1, mockPhoto2]

      const result = await deletePhoto(mockPhoto1.id)

      expect(result.success).toBe(true)
      expect(result.message).toBe('Photo supprimée de l\'album')
      expect(photos.value).not.toContainEqual(mockPhoto1)
      expect(photos.value).toHaveLength(1)
      expect(isDeleting.value).toBe(false)
      expect(error.value).toBeNull()
    })

    it('sets deleting state during deletion', async () => {
      let resolvePromise: () => void
      const promise = new Promise<void>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(faceApi.deleteAlbumPhoto).mockReturnValue(promise)

      const { photos, isDeleting, deletePhoto } = usePhotoAlbum()
      photos.value = [mockPhoto1]

      const deletePromise = deletePhoto(mockPhoto1.id)
      expect(isDeleting.value).toBe(true)

      resolvePromise!()
      await deletePromise

      expect(isDeleting.value).toBe(false)
    })

    it('handles delete error', async () => {
      vi.mocked(faceApi.deleteAlbumPhoto).mockRejectedValue(new Error('Delete failed'))

      const { photos, error, deletePhoto } = usePhotoAlbum()
      photos.value = [mockPhoto1]

      const result = await deletePhoto(mockPhoto1.id)

      expect(result.success).toBe(false)
      expect(result.message).toBe('Une erreur est survenue')
      expect(error.value).toBe('Une erreur est survenue')
    })
  })

  describe('reorderPhotos', () => {
    it('reorders photos successfully', async () => {
      const reorderedResponse: FacePhotosResponse = {
        data: [mockPhoto2, mockPhoto1],
        message: 'Ordre mis à jour',
      }
      vi.mocked(faceApi.reorderAlbumPhotos).mockResolvedValue(reorderedResponse)

      const { photos, isReordering, error, reorderPhotos } = usePhotoAlbum()
      photos.value = [mockPhoto1, mockPhoto2]

      await reorderPhotos([mockPhoto2.id, mockPhoto1.id])

      expect(photos.value).toEqual([mockPhoto2, mockPhoto1])
      expect(isReordering.value).toBe(false)
      expect(error.value).toBeNull()
    })
  })

  describe('validateFile', () => {
    it('validates valid JPEG file', () => {
      const { validateFile } = usePhotoAlbum()
      const file = new File(['test'], 'test.jpg', { type: 'image/jpeg' })

      const result = validateFile(file)

      expect(result.valid).toBe(true)
      expect(result.error).toBeUndefined()
    })

    it('validates valid PNG file', () => {
      const { validateFile } = usePhotoAlbum()
      const file = new File(['test'], 'test.png', { type: 'image/png' })

      const result = validateFile(file)

      expect(result.valid).toBe(true)
      expect(result.error).toBeUndefined()
    })

    it('rejects invalid file type', () => {
      const { validateFile } = usePhotoAlbum()
      const file = new File(['test'], 'test.gif', { type: 'image/gif' })

      const result = validateFile(file)

      expect(result.valid).toBe(false)
      expect(result.error).toBe('Le fichier doit être au format JPG ou PNG')
    })

    it('rejects oversized file', () => {
      const { validateFile } = usePhotoAlbum()
      // Create a file larger than 8MB
      const largeContent = new Array(9 * 1024 * 1024).fill('a').join('')
      const file = new File([largeContent], 'test.jpg', { type: 'image/jpeg' })

      const result = validateFile(file)

      expect(result.valid).toBe(false)
      expect(result.error).toBe('Le fichier ne doit pas dépasser 8 Mo')
    })

    it('sets error ref when validation fails', async () => {
      const { addPhoto, error } = usePhotoAlbum()
      const file = new File(['test'], 'test.gif', { type: 'image/gif' })

      expect(error.value).toBeNull()

      await addPhoto(file)

      expect(error.value).toBe('Le fichier doit être au format JPG ou PNG')
    })

    it('sets error ref when file is too large', async () => {
      const { addPhoto, error } = usePhotoAlbum()
      const largeContent = new Array(9 * 1024 * 1024).fill('a').join('')
      const file = new File([largeContent], 'test.jpg', { type: 'image/jpeg' })

      await addPhoto(file)

      expect(error.value).toBe('Le fichier ne doit pas dépasser 8 Mo')
    })
  })

  describe('computed properties', () => {
    it('photoCount returns correct count', async () => {
      vi.mocked(faceApi.getAlbumPhotos).mockResolvedValue(mockPhotosResponse)

      const { photoCount, fetchPhotos } = usePhotoAlbum()

      await fetchPhotos()

      expect(photoCount.value).toBe(2)
    })

    it('canAddMore returns true one photo under the absolute ceiling of 6', () => {
      const { photos, canAddMore } = usePhotoAlbum()
      photos.value = [
        { ...mockPhoto1, id: 1, position: 1 },
        { ...mockPhoto1, id: 2, position: 2 },
        { ...mockPhoto1, id: 3, position: 3 },
        { ...mockPhoto1, id: 4, position: 4 },
        { ...mockPhoto1, id: 5, position: 5 },
      ]

      expect(canAddMore.value).toBe(true)
    })

    it('canAddMore returns false at the absolute ceiling of 6', () => {
      const { photos, canAddMore } = usePhotoAlbum()
      photos.value = [
        { ...mockPhoto1, id: 1, position: 1 },
        { ...mockPhoto1, id: 2, position: 2 },
        { ...mockPhoto1, id: 3, position: 3 },
        { ...mockPhoto1, id: 4, position: 4 },
        { ...mockPhoto1, id: 5, position: 5 },
        { ...mockPhoto1, id: 6, position: 6 },
      ]

      expect(canAddMore.value).toBe(false)
    })

    it('isFull returns true at the absolute ceiling of 6', () => {
      const { photos, isFull } = usePhotoAlbum()
      photos.value = [
        { ...mockPhoto1, id: 1, position: 1 },
        { ...mockPhoto1, id: 2, position: 2 },
        { ...mockPhoto1, id: 3, position: 3 },
        { ...mockPhoto1, id: 4, position: 4 },
        { ...mockPhoto1, id: 5, position: 5 },
        { ...mockPhoto1, id: 6, position: 6 },
      ]

      expect(isFull.value).toBe(true)
    })

    it('isFull returns false one photo under the absolute ceiling of 6', () => {
      const { photos, isFull } = usePhotoAlbum()
      photos.value = [
        { ...mockPhoto1, id: 1, position: 1 },
        { ...mockPhoto1, id: 2, position: 2 },
        { ...mockPhoto1, id: 3, position: 3 },
        { ...mockPhoto1, id: 4, position: 4 },
        { ...mockPhoto1, id: 5, position: 5 },
      ]

      expect(isFull.value).toBe(false)
    })
  })
})
