import { describe, it, expect, vi, beforeEach } from 'vitest'
import { AxiosError } from 'axios'
import { useLandingMissions } from '../useLandingMissions'
import { landingApi } from '../../services/landingApi'
import type { MissionsApiResponse } from '../../types'

vi.mock('../../services/landingApi', () => ({
  landingApi: {
    getMissions: vi.fn(),
  },
}))

describe('useLandingMissions', () => {
  const mockResponse: MissionsApiResponse = {
    data: [
      {
        id: 1,
        slug: 'mission-test',
        titre: 'Mission Test',
        description: 'Description test',
        date_tournage: '2025-06-15',
        profil_recherche: 'Acteur',
        budget: 50000,
        date_limite_candidature: '2025-06-01',
        nombre_faces_voulu: 3,
        type_mission: 'film',
        type_mission_label: 'Film',
        genre_voulu: 'homme',
        genre_voulu_label: 'Homme',
        lieu: 'Cotonou',
        duree: '2 jours',
        status: 'published',
        status_label: 'Disponible',
        created_at: '2025-05-01T00:00:00.000000Z',
        producer: {
          id: 1,
          display_name: 'Producer Test',
          profile_photo_thumbnail_url: null,
          average_rating: 4.5,
          ratings_count: 10,
        },
      },
      {
        id: 2,
        slug: 'mission-two',
        titre: 'Mission Two',
        description: 'Second mission',
        date_tournage: null,
        profil_recherche: null,
        budget: 100000,
        date_limite_candidature: null,
        nombre_faces_voulu: 1,
        type_mission: 'pub',
        type_mission_label: 'Publicité',
        genre_voulu: 'femme',
        genre_voulu_label: 'Femme',
        lieu: 'Porto-Novo',
        duree: null,
        status: 'published',
        status_label: 'Disponible',
        created_at: '2025-05-02T00:00:00.000000Z',
        producer: null,
      },
    ],
    meta: { current_page: 1, last_page: 1, per_page: 6, total: 25 },
    message: 'Missions retrieved successfully',
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('initial state', () => {
    it('has correct initial values', () => {
      const { missions, isLoading, error, totalCount } = useLandingMissions()

      expect(missions.value).toEqual([])
      expect(isLoading.value).toBe(false)
      expect(error.value).toBeNull()
      expect(totalCount.value).toBe(0)
    })
  })

  describe('fetchMissions', () => {
    it('fetches missions successfully', async () => {
      vi.mocked(landingApi.getMissions).mockResolvedValue(mockResponse)

      const { missions, isLoading, error, totalCount, fetchMissions } = useLandingMissions()

      await fetchMissions()

      expect(missions.value).toEqual(mockResponse.data)
      expect(totalCount.value).toBe(25)
      expect(isLoading.value).toBe(false)
      expect(error.value).toBeNull()
      expect(landingApi.getMissions).toHaveBeenCalledWith({ per_page: 6, page: 1 })
    })

    it('sets isLoading during fetch', async () => {
      let resolvePromise: (value: MissionsApiResponse) => void
      vi.mocked(landingApi.getMissions).mockReturnValue(
        new Promise((resolve) => {
          resolvePromise = resolve
        }),
      )

      const { isLoading, fetchMissions } = useLandingMissions()

      const promise = fetchMissions()
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
        config: {} as unknown,
      })
      vi.mocked(landingApi.getMissions).mockRejectedValue(axiosError)

      const { error, fetchMissions } = useLandingMissions()

      await fetchMissions()

      expect(error.value).toBe('Impossible de charger les missions. Veuillez réessayer.')
    })

    it('handles network error (no response)', async () => {
      const axiosError = new AxiosError('Network Error', 'ERR_NETWORK')
      vi.mocked(landingApi.getMissions).mockRejectedValue(axiosError)

      const { error, fetchMissions } = useLandingMissions()

      await fetchMissions()

      expect(error.value).toBe('Erreur réseau. Vérifiez votre connexion internet.')
    })

    it('handles unexpected non-axios error', async () => {
      vi.mocked(landingApi.getMissions).mockRejectedValue(new Error('Unexpected'))

      const { error, fetchMissions } = useLandingMissions()

      await fetchMissions()

      expect(error.value).toBe('Une erreur inattendue est survenue.')
    })

    it('clears previous error on new fetch', async () => {
      vi.mocked(landingApi.getMissions)
        .mockRejectedValueOnce(new Error('fail'))
        .mockResolvedValueOnce(mockResponse)

      const { error, fetchMissions } = useLandingMissions()

      await fetchMissions()
      expect(error.value).not.toBeNull()

      await fetchMissions()
      expect(error.value).toBeNull()
    })
  })

  describe('retry', () => {
    it('calls fetchMissions when invoked', async () => {
      vi.mocked(landingApi.getMissions).mockResolvedValue(mockResponse)

      const { retry } = useLandingMissions()

      await retry()

      expect(landingApi.getMissions).toHaveBeenCalledOnce()
    })
  })

  describe('clearError', () => {
    it('clears the error state', async () => {
      vi.mocked(landingApi.getMissions).mockRejectedValue(new Error('fail'))

      const { error, fetchMissions, clearError } = useLandingMissions()

      await fetchMissions()
      expect(error.value).not.toBeNull()

      clearError()
      expect(error.value).toBeNull()
    })
  })
})
