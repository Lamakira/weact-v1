import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useProfilePhoto } from '../useProfilePhoto'
import { faceApi } from '../../services/faceApi'
import type { FaceProfile, FaceProfileResponse } from '../../types'

// Mock the faceApi module
vi.mock('../../services/faceApi', () => ({
  faceApi: {
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

describe('useProfilePhoto', () => {
  const mockProfile: FaceProfile = {
    id: 1,
    nom: 'Dupont',
    prenom: 'Jean',
    username: 'jeandupont',
    profile_photo_url: 'http://localhost/storage/avatars/faces/photo.jpg',
    thumbnail_url: 'http://localhost/storage/avatars/faces/thumbnails/photo.jpg',
  }

  const mockResponse: FaceProfileResponse = {
    data: mockProfile,
    message: 'Photo de profil mise à jour',
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('initial state', () => {
    it('has correct initial state', () => {
      const { profile, isLoading, isUploading, isDeleting, error, hasPhoto } = useProfilePhoto()

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
      vi.mocked(faceApi.getProfile).mockResolvedValue(mockResponse)

      const { profile, isLoading, error, fetchProfile } = useProfilePhoto()

      await fetchProfile()

      expect(profile.value).toEqual(mockProfile)
      expect(isLoading.value).toBe(false)
      expect(error.value).toBeNull()
      expect(faceApi.getProfile).toHaveBeenCalledOnce()
    })

    it('sets loading state during fetch', async () => {
      let resolvePromise: (value: FaceProfileResponse) => void
      const promise = new Promise<FaceProfileResponse>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(faceApi.getProfile).mockReturnValue(promise)

      const { isLoading, fetchProfile } = useProfilePhoto()

      const fetchPromise = fetchProfile()
      expect(isLoading.value).toBe(true)

      resolvePromise!(mockResponse)
      await fetchPromise

      expect(isLoading.value).toBe(false)
    })

    it('handles fetch error', async () => {
      vi.mocked(faceApi.getProfile).mockRejectedValue(new Error('Network error'))

      const { profile, error, fetchProfile } = useProfilePhoto()

      await fetchProfile()

      expect(profile.value).toBeNull()
      expect(error.value).toBe('Une erreur est survenue')
    })
  })

  describe('uploadPhoto', () => {
    it('uploads photo successfully', async () => {
      vi.mocked(faceApi.uploadProfilePhoto).mockResolvedValue(mockResponse)

      const { profile, isUploading, error, uploadPhoto } = useProfilePhoto()
      const file = new File(['test'], 'test.jpg', { type: 'image/jpeg' })

      const result = await uploadPhoto(file)

      expect(result.success).toBe(true)
      expect(result.data).toEqual(mockProfile)
      expect(result.message).toBe('Photo de profil mise à jour')
      expect(profile.value).toEqual(mockProfile)
      expect(isUploading.value).toBe(false)
      expect(error.value).toBeNull()
    })

    it('sets uploading state during upload', async () => {
      let resolvePromise: (value: FaceProfileResponse) => void
      const promise = new Promise<FaceProfileResponse>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(faceApi.uploadProfilePhoto).mockReturnValue(promise)

      const { isUploading, uploadPhoto } = useProfilePhoto()
      const file = new File(['test'], 'test.jpg', { type: 'image/jpeg' })

      const uploadPromise = uploadPhoto(file)
      expect(isUploading.value).toBe(true)

      resolvePromise!(mockResponse)
      await uploadPromise

      expect(isUploading.value).toBe(false)
    })

    it('handles upload error', async () => {
      vi.mocked(faceApi.uploadProfilePhoto).mockRejectedValue(new Error('Upload failed'))

      const { error, uploadPhoto } = useProfilePhoto()
      const file = new File(['test'], 'test.jpg', { type: 'image/jpeg' })

      const result = await uploadPhoto(file)

      expect(result.success).toBe(false)
      expect(result.message).toBe('Une erreur est survenue')
      expect(error.value).toBe('Une erreur est survenue')
    })
  })

  describe('deletePhoto', () => {
    it('deletes photo successfully', async () => {
      const deletedProfile: FaceProfile = {
        ...mockProfile,
        profile_photo_url: null,
        thumbnail_url: null,
      }
      const deleteResponse: FaceProfileResponse = {
        data: deletedProfile,
        message: 'Photo de profil supprimée',
      }
      vi.mocked(faceApi.deleteProfilePhoto).mockResolvedValue(deleteResponse)

      const { profile, isDeleting, error, deletePhoto } = useProfilePhoto()

      const result = await deletePhoto()

      expect(result.success).toBe(true)
      expect(result.message).toBe('Photo de profil supprimée')
      expect(profile.value).toEqual(deletedProfile)
      expect(isDeleting.value).toBe(false)
      expect(error.value).toBeNull()
    })

    it('sets deleting state during deletion', async () => {
      let resolvePromise: (value: FaceProfileResponse) => void
      const promise = new Promise<FaceProfileResponse>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(faceApi.deleteProfilePhoto).mockReturnValue(promise)

      const { isDeleting, deletePhoto } = useProfilePhoto()

      const deletePromise = deletePhoto()
      expect(isDeleting.value).toBe(true)

      resolvePromise!(mockResponse)
      await deletePromise

      expect(isDeleting.value).toBe(false)
    })

    it('handles delete error', async () => {
      vi.mocked(faceApi.deleteProfilePhoto).mockRejectedValue(new Error('Delete failed'))

      const { error, deletePhoto } = useProfilePhoto()

      const result = await deletePhoto()

      expect(result.success).toBe(false)
      expect(result.message).toBe('Une erreur est survenue')
      expect(error.value).toBe('Une erreur est survenue')
    })
  })

  describe('validateFile', () => {
    it('validates valid JPEG file', () => {
      const { validateFile } = useProfilePhoto()
      const file = new File(['test'], 'test.jpg', { type: 'image/jpeg' })

      const result = validateFile(file)

      expect(result.valid).toBe(true)
      expect(result.error).toBeUndefined()
    })

    it('validates valid PNG file', () => {
      const { validateFile } = useProfilePhoto()
      const file = new File(['test'], 'test.png', { type: 'image/png' })

      const result = validateFile(file)

      expect(result.valid).toBe(true)
      expect(result.error).toBeUndefined()
    })

    it('rejects invalid file type', () => {
      const { validateFile } = useProfilePhoto()
      const file = new File(['test'], 'test.gif', { type: 'image/gif' })

      const result = validateFile(file)

      expect(result.valid).toBe(false)
      expect(result.error).toBe('Le fichier doit être au format JPG ou PNG')
    })

    it('rejects oversized file', () => {
      const { validateFile } = useProfilePhoto()
      // Create a file larger than 5MB
      const largeContent = new Array(6 * 1024 * 1024).fill('a').join('')
      const file = new File([largeContent], 'test.jpg', { type: 'image/jpeg' })

      const result = validateFile(file)

      expect(result.valid).toBe(false)
      expect(result.error).toBe('Le fichier ne doit pas dépasser 5 Mo')
    })

    it('rejects invalid file before calling API', async () => {
      const { uploadPhoto } = useProfilePhoto()
      const file = new File(['test'], 'test.gif', { type: 'image/gif' })

      const result = await uploadPhoto(file)

      expect(result.success).toBe(false)
      expect(result.message).toBe('Le fichier doit être au format JPG ou PNG')
      expect(faceApi.uploadProfilePhoto).not.toHaveBeenCalled()
    })

    it('sets error ref when validation fails', async () => {
      const { uploadPhoto, error } = useProfilePhoto()
      const file = new File(['test'], 'test.gif', { type: 'image/gif' })

      expect(error.value).toBeNull()

      await uploadPhoto(file)

      expect(error.value).toBe('Le fichier doit être au format JPG ou PNG')
    })

    it('sets error ref when file is too large', async () => {
      const { uploadPhoto, error } = useProfilePhoto()
      const largeContent = new Array(6 * 1024 * 1024).fill('a').join('')
      const file = new File([largeContent], 'test.jpg', { type: 'image/jpeg' })

      await uploadPhoto(file)

      expect(error.value).toBe('Le fichier ne doit pas dépasser 5 Mo')
    })
  })

  describe('computed properties', () => {
    it('hasPhoto returns true when profile has photo URL', async () => {
      vi.mocked(faceApi.getProfile).mockResolvedValue(mockResponse)

      const { hasPhoto, fetchProfile } = useProfilePhoto()

      await fetchProfile()

      expect(hasPhoto.value).toBe(true)
    })

    it('hasPhoto returns false when profile has no photo URL', async () => {
      const noPhotoProfile: FaceProfile = {
        ...mockProfile,
        profile_photo_url: null,
      }
      vi.mocked(faceApi.getProfile).mockResolvedValue({ data: noPhotoProfile })

      const { hasPhoto, fetchProfile } = useProfilePhoto()

      await fetchProfile()

      expect(hasPhoto.value).toBe(false)
    })

    it('photoUrl returns correct URL', async () => {
      vi.mocked(faceApi.getProfile).mockResolvedValue(mockResponse)

      const { photoUrl, fetchProfile } = useProfilePhoto()

      await fetchProfile()

      expect(photoUrl.value).toBe(mockProfile.profile_photo_url)
    })

    it('thumbnailUrl returns correct URL', async () => {
      vi.mocked(faceApi.getProfile).mockResolvedValue(mockResponse)

      const { thumbnailUrl, fetchProfile } = useProfilePhoto()

      await fetchProfile()

      expect(thumbnailUrl.value).toBe(mockProfile.thumbnail_url)
    })
  })
})
