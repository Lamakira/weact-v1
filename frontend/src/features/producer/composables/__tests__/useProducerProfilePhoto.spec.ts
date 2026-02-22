import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useProducerProfilePhoto } from '../useProducerProfilePhoto'
import { producerApi } from '../../services/producerApi'
import type { Producer, ProducerProfileResponse } from '../../types'

// Mock the producerApi module
vi.mock('../../services/producerApi', () => ({
  producerApi: {
    getProfile: vi.fn(),
    uploadProfilePhoto: vi.fn(),
    deleteProfilePhoto: vi.fn(),
  },
}))

// Mock the authApi error helpers
vi.mock('@/features/auth/services/authApi', () => ({
  getApiErrorDetails: vi.fn(() => ({})),
  getApiErrorMessage: vi.fn(() => 'Une erreur est survenue'),
}))

describe('useProducerProfilePhoto', () => {
  const mockProducer: Producer = {
    id: 1,
    type: 'agency',
    agency_name: 'Test Agency',
    first_name: null,
    last_name: null,
    display_name: 'Test Agency',
    profile_photo_url: 'http://localhost/storage/avatars/producers/photo.jpg',
    thumbnail_url: 'http://localhost/storage/avatars/producers/thumbnails/photo.jpg',
    created_at: '2024-01-01T00:00:00.000Z',
    updated_at: '2024-01-01T00:00:00.000Z',
  }

  const mockResponse: ProducerProfileResponse = {
    data: mockProducer,
    message: 'Photo de profil mise à jour',
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('initial state', () => {
    it('has correct initial state', () => {
      const { profile, isLoading, isUploading, isDeleting, error, hasPhoto } = useProducerProfilePhoto()

      expect(profile.value).toBeNull()
      expect(isLoading.value).toBe(false)
      expect(isUploading.value).toBe(false)
      expect(isDeleting.value).toBe(false)
      expect(error.value).toBeNull()
      expect(hasPhoto.value).toBe(false)
    })
  })

  describe('fetchProfile', () => {
    it('fetches profile successfully', async () => {
      vi.mocked(producerApi.getProfile).mockResolvedValue(mockResponse)

      const { profile, isLoading, error, fetchProfile } = useProducerProfilePhoto()

      await fetchProfile()

      expect(profile.value).toEqual(mockProducer)
      expect(isLoading.value).toBe(false)
      expect(error.value).toBeNull()
      expect(producerApi.getProfile).toHaveBeenCalledOnce()
    })

    it('sets loading state during fetch', async () => {
      let resolvePromise: (value: ProducerProfileResponse) => void
      const promise = new Promise<ProducerProfileResponse>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(producerApi.getProfile).mockReturnValue(promise)

      const { isLoading, fetchProfile } = useProducerProfilePhoto()

      const fetchPromise = fetchProfile()
      expect(isLoading.value).toBe(true)

      resolvePromise!(mockResponse)
      await fetchPromise

      expect(isLoading.value).toBe(false)
    })

    it('handles fetch error', async () => {
      vi.mocked(producerApi.getProfile).mockRejectedValue(new Error('Network error'))

      const { profile, error, fetchProfile } = useProducerProfilePhoto()

      await fetchProfile()

      expect(profile.value).toBeNull()
      expect(error.value).toBe('Une erreur est survenue')
    })
  })

  describe('uploadPhoto', () => {
    it('uploads photo successfully', async () => {
      vi.mocked(producerApi.uploadProfilePhoto).mockResolvedValue(mockResponse)

      const { profile, isUploading, error, uploadPhoto } = useProducerProfilePhoto()
      const file = new File(['test'], 'test.jpg', { type: 'image/jpeg' })

      const result = await uploadPhoto(file)

      expect(result.success).toBe(true)
      expect(result.data).toEqual(mockProducer)
      expect(result.message).toBe('Photo de profil mise à jour')
      expect(profile.value).toEqual(mockProducer)
      expect(isUploading.value).toBe(false)
      expect(error.value).toBeNull()
    })

    it('sets uploading state during upload', async () => {
      let resolvePromise: (value: ProducerProfileResponse) => void
      const promise = new Promise<ProducerProfileResponse>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(producerApi.uploadProfilePhoto).mockReturnValue(promise)

      const { isUploading, uploadPhoto } = useProducerProfilePhoto()
      const file = new File(['test'], 'test.jpg', { type: 'image/jpeg' })

      const uploadPromise = uploadPhoto(file)
      expect(isUploading.value).toBe(true)

      resolvePromise!(mockResponse)
      await uploadPromise

      expect(isUploading.value).toBe(false)
    })

    it('handles upload error', async () => {
      vi.mocked(producerApi.uploadProfilePhoto).mockRejectedValue(new Error('Upload failed'))

      const { error, uploadPhoto } = useProducerProfilePhoto()
      const file = new File(['test'], 'test.jpg', { type: 'image/jpeg' })

      const result = await uploadPhoto(file)

      expect(result.success).toBe(false)
      expect(result.message).toBe('Une erreur est survenue')
      expect(error.value).toBe('Une erreur est survenue')
    })
  })

  describe('deletePhoto', () => {
    it('deletes photo successfully', async () => {
      const deletedProducer: Producer = {
        ...mockProducer,
        profile_photo_url: null,
        thumbnail_url: null,
      }
      const deleteResponse: ProducerProfileResponse = {
        data: deletedProducer,
        message: 'Photo de profil supprimée',
      }
      vi.mocked(producerApi.deleteProfilePhoto).mockResolvedValue(deleteResponse)

      const { profile, isDeleting, error, deletePhoto } = useProducerProfilePhoto()

      const result = await deletePhoto()

      expect(result.success).toBe(true)
      expect(result.message).toBe('Photo de profil supprimée')
      expect(profile.value).toEqual(deletedProducer)
      expect(isDeleting.value).toBe(false)
      expect(error.value).toBeNull()
    })

    it('sets deleting state during deletion', async () => {
      let resolvePromise: (value: ProducerProfileResponse) => void
      const promise = new Promise<ProducerProfileResponse>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(producerApi.deleteProfilePhoto).mockReturnValue(promise)

      const { isDeleting, deletePhoto } = useProducerProfilePhoto()

      const deletePromise = deletePhoto()
      expect(isDeleting.value).toBe(true)

      resolvePromise!(mockResponse)
      await deletePromise

      expect(isDeleting.value).toBe(false)
    })

    it('handles delete error', async () => {
      vi.mocked(producerApi.deleteProfilePhoto).mockRejectedValue(new Error('Delete failed'))

      const { error, deletePhoto } = useProducerProfilePhoto()

      const result = await deletePhoto()

      expect(result.success).toBe(false)
      expect(result.message).toBe('Une erreur est survenue')
      expect(error.value).toBe('Une erreur est survenue')
    })
  })

  describe('validateFile', () => {
    it('validates valid JPEG file', () => {
      const { validateFile } = useProducerProfilePhoto()
      const file = new File(['test'], 'test.jpg', { type: 'image/jpeg' })

      const result = validateFile(file)

      expect(result.valid).toBe(true)
      expect(result.error).toBeUndefined()
    })

    it('validates valid PNG file', () => {
      const { validateFile } = useProducerProfilePhoto()
      const file = new File(['test'], 'test.png', { type: 'image/png' })

      const result = validateFile(file)

      expect(result.valid).toBe(true)
      expect(result.error).toBeUndefined()
    })

    it('rejects invalid file type', () => {
      const { validateFile } = useProducerProfilePhoto()
      const file = new File(['test'], 'test.gif', { type: 'image/gif' })

      const result = validateFile(file)

      expect(result.valid).toBe(false)
      expect(result.error).toBe('Le fichier doit être au format JPG ou PNG')
    })

    it('rejects oversized file', () => {
      const { validateFile } = useProducerProfilePhoto()
      // Create a file larger than 8MB
      const largeContent = new Array(9 * 1024 * 1024).fill('a').join('')
      const file = new File([largeContent], 'test.jpg', { type: 'image/jpeg' })

      const result = validateFile(file)

      expect(result.valid).toBe(false)
      expect(result.error).toBe('Le fichier ne doit pas dépasser 8 Mo')
    })

    it('rejects invalid file before calling API', async () => {
      const { uploadPhoto } = useProducerProfilePhoto()
      const file = new File(['test'], 'test.gif', { type: 'image/gif' })

      const result = await uploadPhoto(file)

      expect(result.success).toBe(false)
      expect(result.message).toBe('Le fichier doit être au format JPG ou PNG')
      expect(producerApi.uploadProfilePhoto).not.toHaveBeenCalled()
    })

    it('sets error ref when validation fails', async () => {
      const { uploadPhoto, error } = useProducerProfilePhoto()
      const file = new File(['test'], 'test.gif', { type: 'image/gif' })

      expect(error.value).toBeNull()

      await uploadPhoto(file)

      expect(error.value).toBe('Le fichier doit être au format JPG ou PNG')
    })

    it('sets error ref when file is too large', async () => {
      const { uploadPhoto, error } = useProducerProfilePhoto()
      const largeContent = new Array(9 * 1024 * 1024).fill('a').join('')
      const file = new File([largeContent], 'test.jpg', { type: 'image/jpeg' })

      await uploadPhoto(file)

      expect(error.value).toBe('Le fichier ne doit pas dépasser 8 Mo')
    })
  })

  describe('computed properties', () => {
    it('hasPhoto returns true when profile has photo URL', async () => {
      vi.mocked(producerApi.getProfile).mockResolvedValue(mockResponse)

      const { hasPhoto, fetchProfile } = useProducerProfilePhoto()

      await fetchProfile()

      expect(hasPhoto.value).toBe(true)
    })

    it('hasPhoto returns false when profile has no photo URL', async () => {
      const noPhotoProducer: Producer = {
        ...mockProducer,
        profile_photo_url: null,
      }
      vi.mocked(producerApi.getProfile).mockResolvedValue({ data: noPhotoProducer })

      const { hasPhoto, fetchProfile } = useProducerProfilePhoto()

      await fetchProfile()

      expect(hasPhoto.value).toBe(false)
    })

    it('photoUrl returns correct URL', async () => {
      vi.mocked(producerApi.getProfile).mockResolvedValue(mockResponse)

      const { photoUrl, fetchProfile } = useProducerProfilePhoto()

      await fetchProfile()

      expect(photoUrl.value).toBe(mockProducer.profile_photo_url)
    })

    it('thumbnailUrl returns correct URL', async () => {
      vi.mocked(producerApi.getProfile).mockResolvedValue(mockResponse)

      const { thumbnailUrl, fetchProfile } = useProducerProfilePhoto()

      await fetchProfile()

      expect(thumbnailUrl.value).toBe(mockProducer.thumbnail_url)
    })
  })

  describe('Producer type variations', () => {
    it('handles particulier producer type', async () => {
      const particulierProducer: Producer = {
        id: 2,
        type: 'particulier',
        agency_name: null,
        first_name: 'Jean',
        last_name: 'Dupont',
        display_name: 'Jean Dupont',
        profile_photo_url: 'http://localhost/storage/avatars/producers/photo.jpg',
        thumbnail_url: 'http://localhost/storage/avatars/producers/thumbnails/photo.jpg',
        created_at: '2024-01-01T00:00:00.000Z',
        updated_at: '2024-01-01T00:00:00.000Z',
      }
      vi.mocked(producerApi.getProfile).mockResolvedValue({ data: particulierProducer })

      const { profile, fetchProfile } = useProducerProfilePhoto()

      await fetchProfile()

      expect(profile.value?.type).toBe('particulier')
      expect(profile.value?.display_name).toBe('Jean Dupont')
    })
  })
})
