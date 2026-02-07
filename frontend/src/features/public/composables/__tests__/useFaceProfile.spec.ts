import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { useFaceProfile } from '../useFaceProfile'
import * as publicFacesApi from '../../services/publicFacesApi'
import type { PublicFaceProfile } from '../../services/publicFacesApi'

// Mock the public faces API
vi.mock('../../services/publicFacesApi', async () => {
  const actual = await vi.importActual('../../services/publicFacesApi')
  return {
    ...actual,
    fetchPublicFaceProfile: vi.fn(),
  }
})

const mockFaceProfile: PublicFaceProfile = {
  id: 1,
  username: 'adjoua',
  prenom: 'Adjoua',
  nom: 'Dossou',
  ville: 'Cotonou',
  categorie: 'acteur',
  categorie_label: 'Acteur',
  is_available: true,
  profile_photo_thumbnail_url: 'https://example.com/thumb.jpg',
  average_rating: 4.5,
  niche: 'publicite',
  niche_label: 'Publicité',
  profile_photo_url: 'https://example.com/photo.jpg',
  ratings_count: 12,
  has_album_photos: true,
  album_photos_count: 5,
  has_presentation_video: true,
  has_acting_video: false,
}

const mockFaceWithoutMedia: PublicFaceProfile = {
  id: 2,
  username: 'kofi',
  prenom: 'Kofi',
  nom: 'Mensah',
  ville: null,
  categorie: 'mannequin',
  categorie_label: 'Mannequin',
  is_available: false,
  profile_photo_thumbnail_url: null,
  average_rating: null,
  niche: null,
  niche_label: null,
  profile_photo_url: null,
  ratings_count: 0,
  has_album_photos: false,
  album_photos_count: 0,
  has_presentation_video: false,
  has_acting_video: false,
}

describe('useFaceProfile', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('initial state', () => {
    it('should have null face initially', () => {
      const { face } = useFaceProfile()
      expect(face.value).toBeNull()
    })

    it('should not be loading initially', () => {
      const { isLoading } = useFaceProfile()
      expect(isLoading.value).toBe(false)
    })

    it('should have no error initially', () => {
      const { error } = useFaceProfile()
      expect(error.value).toBeNull()
    })

    it('should not have notFound initially', () => {
      const { notFound } = useFaceProfile()
      expect(notFound.value).toBe(false)
    })

    it('should have hasAlbumPhotos as false initially', () => {
      const { hasAlbumPhotos } = useFaceProfile()
      expect(hasAlbumPhotos.value).toBe(false)
    })

    it('should have albumPhotosCount as 0 initially', () => {
      const { albumPhotosCount } = useFaceProfile()
      expect(albumPhotosCount.value).toBe(0)
    })

    it('should have hasVideos as false initially', () => {
      const { hasVideos } = useFaceProfile()
      expect(hasVideos.value).toBe(false)
    })

    it('should have videosCount as 0 initially', () => {
      const { videosCount } = useFaceProfile()
      expect(videosCount.value).toBe(0)
    })
  })

  describe('fetchFace', () => {
    it('should fetch face profile successfully', async () => {
      vi.mocked(publicFacesApi.fetchPublicFaceProfile).mockResolvedValue({
        success: true,
        profile: mockFaceProfile,
      })

      const { face, fetchFace, isLoading } = useFaceProfile()

      const promise = fetchFace('adjoua')
      expect(isLoading.value).toBe(true)

      const result = await promise
      await flushPromises()

      expect(isLoading.value).toBe(false)
      expect(face.value).toEqual(mockFaceProfile)
      expect(result.success).toBe(true)
      expect(result.profile).toEqual(mockFaceProfile)
    })

    it('should handle 404 not found error', async () => {
      vi.mocked(publicFacesApi.fetchPublicFaceProfile).mockResolvedValue({
        success: false,
        notFound: true,
        error: 'Face non trouvée',
      })

      const { face, fetchFace, notFound, error } = useFaceProfile()

      const result = await fetchFace('nonexistent-user')
      await flushPromises()

      expect(face.value).toBeNull()
      expect(notFound.value).toBe(true)
      expect(error.value).toBe('Face non trouvée')
      expect(result.success).toBe(false)
      expect(result.notFound).toBe(true)
    })

    it('should handle network error', async () => {
      vi.mocked(publicFacesApi.fetchPublicFaceProfile).mockResolvedValue({
        success: false,
        error: 'Une erreur est survenue. Veuillez réessayer.',
      })

      const { face, fetchFace, error, notFound } = useFaceProfile()

      const result = await fetchFace('adjoua')
      await flushPromises()

      expect(face.value).toBeNull()
      expect(notFound.value).toBe(false)
      expect(error.value).toBe('Une erreur est survenue. Veuillez réessayer.')
      expect(result.success).toBe(false)
    })

    it('should reset state before fetching', async () => {
      // First, set up some error state
      vi.mocked(publicFacesApi.fetchPublicFaceProfile).mockResolvedValueOnce({
        success: false,
        notFound: true,
        error: 'Face non trouvée',
      })

      const { fetchFace, notFound, error, face } = useFaceProfile()

      await fetchFace('adjoua')
      expect(notFound.value).toBe(true)

      // Now fetch successfully
      vi.mocked(publicFacesApi.fetchPublicFaceProfile).mockResolvedValueOnce({
        success: true,
        profile: mockFaceProfile,
      })

      await fetchFace('kofi')
      await flushPromises()

      expect(notFound.value).toBe(false)
      expect(error.value).toBeNull()
      expect(face.value).toEqual(mockFaceProfile)
    })
  })

  describe('clearFace', () => {
    it('should clear all state', async () => {
      vi.mocked(publicFacesApi.fetchPublicFaceProfile).mockResolvedValue({
        success: true,
        profile: mockFaceProfile,
      })

      const { face, fetchFace, clearFace, error, notFound } = useFaceProfile()

      await fetchFace('adjoua')
      await flushPromises()

      expect(face.value).not.toBeNull()

      clearFace()

      expect(face.value).toBeNull()
      expect(error.value).toBeNull()
      expect(notFound.value).toBe(false)
    })
  })

  describe('computed properties', () => {
    it('should compute hasAlbumPhotos correctly when face has photos', async () => {
      vi.mocked(publicFacesApi.fetchPublicFaceProfile).mockResolvedValue({
        success: true,
        profile: mockFaceProfile,
      })

      const { fetchFace, hasAlbumPhotos, albumPhotosCount } = useFaceProfile()

      await fetchFace('adjoua')
      await flushPromises()

      expect(hasAlbumPhotos.value).toBe(true)
      expect(albumPhotosCount.value).toBe(5)
    })

    it('should compute hasAlbumPhotos correctly when face has no photos', async () => {
      vi.mocked(publicFacesApi.fetchPublicFaceProfile).mockResolvedValue({
        success: true,
        profile: mockFaceWithoutMedia,
      })

      const { fetchFace, hasAlbumPhotos, albumPhotosCount } = useFaceProfile()

      await fetchFace('kofi')
      await flushPromises()

      expect(hasAlbumPhotos.value).toBe(false)
      expect(albumPhotosCount.value).toBe(0)
    })

    it('should compute hasVideos correctly when face has presentation video', async () => {
      vi.mocked(publicFacesApi.fetchPublicFaceProfile).mockResolvedValue({
        success: true,
        profile: mockFaceProfile,
      })

      const { fetchFace, hasVideos, videosCount } = useFaceProfile()

      await fetchFace('adjoua')
      await flushPromises()

      expect(hasVideos.value).toBe(true)
      expect(videosCount.value).toBe(1) // Only presentation video
    })

    it('should compute hasVideos correctly when face has both videos', async () => {
      const faceWithBothVideos = {
        ...mockFaceProfile,
        has_acting_video: true,
      }

      vi.mocked(publicFacesApi.fetchPublicFaceProfile).mockResolvedValue({
        success: true,
        profile: faceWithBothVideos,
      })

      const { fetchFace, hasVideos, videosCount } = useFaceProfile()

      await fetchFace('adjoua')
      await flushPromises()

      expect(hasVideos.value).toBe(true)
      expect(videosCount.value).toBe(2)
    })

    it('should compute hasVideos correctly when face has no videos', async () => {
      vi.mocked(publicFacesApi.fetchPublicFaceProfile).mockResolvedValue({
        success: true,
        profile: mockFaceWithoutMedia,
      })

      const { fetchFace, hasVideos, videosCount } = useFaceProfile()

      await fetchFace('kofi')
      await flushPromises()

      expect(hasVideos.value).toBe(false)
      expect(videosCount.value).toBe(0)
    })
  })
})
