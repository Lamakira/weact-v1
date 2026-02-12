import { describe, it, expect, vi, beforeEach } from 'vitest'
import { AxiosError } from 'axios'
import { useLandingFaces } from '../useLandingFaces'
import { landingApi } from '../../services/landingApi'
import type { FacesApiResponse, LandingFace } from '../../types'

vi.mock('../../services/landingApi', () => ({
  landingApi: {
    getFaces: vi.fn(),
  },
}))

function makeFace(overrides: Partial<LandingFace> = {}): LandingFace {
  return {
    id: 1,
    username: 'face1',
    prenom: 'Alice',
    nom: 'Dupont',
    ville: 'Cotonou',
    categorie: 'acteur',
    categorie_label: 'Acteur',
    is_available: true,
    profile_photo_url: 'https://example.com/photo.jpg',
    profile_photo_thumbnail_url: 'https://example.com/thumb.jpg',
    average_rating: 4.0,
    ...overrides,
  }
}

describe('useLandingFaces', () => {
  const faceWithPhoto = makeFace({ id: 1, prenom: 'Alice' })
  const faceWithoutPhoto = makeFace({
    id: 2,
    prenom: 'Bob',
    profile_photo_url: null,
  })
  const anotherFaceWithPhoto = makeFace({ id: 3, prenom: 'Charlie' })

  const mockResponse: FacesApiResponse = {
    data: [faceWithPhoto, faceWithoutPhoto, anotherFaceWithPhoto],
    meta: { current_page: 1, last_page: 1, per_page: 30, total: 50 },
    message: 'Faces retrieved successfully',
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('initial state', () => {
    it('has correct initial values', () => {
      const { faces, isLoading, error, totalCount } = useLandingFaces()

      expect(faces.value).toEqual([])
      expect(isLoading.value).toBe(false)
      expect(error.value).toBeNull()
      expect(totalCount.value).toBe(0)
    })
  })

  describe('fetchFaces', () => {
    it('fetches faces and filters out those without profile photo', async () => {
      vi.mocked(landingApi.getFaces).mockResolvedValue(mockResponse)

      const { faces, fetchFaces } = useLandingFaces()

      await fetchFaces()

      // Should filter out faceWithoutPhoto (profile_photo_url === null)
      expect(faces.value).toHaveLength(2)
      expect(faces.value.map((f) => f.id)).toEqual([1, 3])
      expect(landingApi.getFaces).toHaveBeenCalledWith({ per_page: 30, page: 1 })
    })

    it('stores totalCount from API meta', async () => {
      vi.mocked(landingApi.getFaces).mockResolvedValue(mockResponse)

      const { totalCount, fetchFaces } = useLandingFaces()

      await fetchFaces()

      expect(totalCount.value).toBe(50)
    })

    it('sets isLoading during fetch', async () => {
      let resolvePromise: (value: FacesApiResponse) => void
      vi.mocked(landingApi.getFaces).mockReturnValue(
        new Promise((resolve) => {
          resolvePromise = resolve
        }),
      )

      const { isLoading, fetchFaces } = useLandingFaces()

      const promise = fetchFaces()
      expect(isLoading.value).toBe(true)

      resolvePromise!(mockResponse)
      await promise

      expect(isLoading.value).toBe(false)
    })

    it('handles API error with response', async () => {
      const axiosError = new AxiosError('Server error', '500', undefined, undefined, {
        status: 500,
        data: {},
        statusText: 'Internal Server Error',
        headers: {},
        config: {} as any,
      })
      vi.mocked(landingApi.getFaces).mockRejectedValue(axiosError)

      const { error, fetchFaces } = useLandingFaces()

      await fetchFaces()

      expect(error.value).toBe('Impossible de charger les profils. Veuillez réessayer.')
    })

    it('handles network error (no response)', async () => {
      const axiosError = new AxiosError('Network Error', 'ERR_NETWORK')
      vi.mocked(landingApi.getFaces).mockRejectedValue(axiosError)

      const { error, fetchFaces } = useLandingFaces()

      await fetchFaces()

      expect(error.value).toBe('Erreur réseau. Vérifiez votre connexion internet.')
    })

    it('handles unexpected non-axios error', async () => {
      vi.mocked(landingApi.getFaces).mockRejectedValue(new Error('Unexpected'))

      const { error, fetchFaces } = useLandingFaces()

      await fetchFaces()

      expect(error.value).toBe('Une erreur inattendue est survenue.')
    })
  })

  describe('retry', () => {
    it('calls fetchFaces when invoked', async () => {
      vi.mocked(landingApi.getFaces).mockResolvedValue(mockResponse)

      const { retry } = useLandingFaces()

      await retry()

      expect(landingApi.getFaces).toHaveBeenCalledOnce()
    })
  })

  describe('clearError', () => {
    it('clears the error state', async () => {
      vi.mocked(landingApi.getFaces).mockRejectedValue(new Error('fail'))

      const { error, fetchFaces, clearError } = useLandingFaces()

      await fetchFaces()
      expect(error.value).not.toBeNull()

      clearError()
      expect(error.value).toBeNull()
    })
  })
})
