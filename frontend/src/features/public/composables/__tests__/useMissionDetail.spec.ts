import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { useMissionDetail } from '../useMissionDetail'
import * as publicMissionsApi from '../../services/publicMissionsApi'
import type { PublicMission } from '../../services/publicMissionsApi'

// Mock the public missions API
vi.mock('../../services/publicMissionsApi', async () => {
  const actual = await vi.importActual('../../services/publicMissionsApi')
  return {
    ...actual,
    fetchPublicMissionDetail: vi.fn(),
  }
})

const mockMission: PublicMission = {
  id: 42,
  slug: 'casting-publicite-mtn',
  titre: 'Casting publicité MTN',
  description: 'Recherche comédien(ne) pour spot TV',
  date_tournage: '2026-03-15',
  profil_recherche: 'Jeune femme 20-30 ans',
  budget: 150000,
  date_limite_candidature: '2026-02-28',
  nombre_faces_voulu: 3,
  type_mission: 'publicite',
  type_mission_label: 'Publicité',
  genre_voulu: 'femme',
  genre_voulu_label: 'Femme',
  lieu: 'Cotonou, Bénin',
  duree: '2 jours',
  status: 'published',
  status_label: 'Publiée',
  created_at: '2026-02-01T10:00:00+00:00',
  producer: {
    id: 1,
    display_name: 'Studio Cotonou',
    profile_photo_thumbnail_url: 'https://example.com/thumb.jpg',
    average_rating: 4.5,
    ratings_count: 12,
  },
}

describe('useMissionDetail', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('initial state', () => {
    it('should have null mission initially', () => {
      const { mission } = useMissionDetail()
      expect(mission.value).toBeNull()
    })

    it('should not be loading initially', () => {
      const { isLoading } = useMissionDetail()
      expect(isLoading.value).toBe(false)
    })

    it('should have no error initially', () => {
      const { error } = useMissionDetail()
      expect(error.value).toBeNull()
    })

    it('should not have notFound initially', () => {
      const { notFound } = useMissionDetail()
      expect(notFound.value).toBe(false)
    })
  })

  describe('fetchMission', () => {
    it('should fetch mission detail successfully', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissionDetail).mockResolvedValue({
        success: true,
        mission: mockMission,
      })

      const { mission, fetchMission, isLoading } = useMissionDetail()

      const promise = fetchMission('casting-publicite-mtn')
      expect(isLoading.value).toBe(true)

      const result = await promise
      await flushPromises()

      expect(isLoading.value).toBe(false)
      expect(mission.value).toEqual(mockMission)
      expect(result.success).toBe(true)
      expect(result.mission).toEqual(mockMission)
    })

    it('should handle 404 not found error', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissionDetail).mockResolvedValue({
        success: false,
        notFound: true,
        error: 'Mission non trouvée',
      })

      const { mission, fetchMission, notFound, error } = useMissionDetail()

      const result = await fetchMission('nonexistent-slug')
      await flushPromises()

      expect(mission.value).toBeNull()
      expect(notFound.value).toBe(true)
      expect(error.value).toBe('Mission non trouvée')
      expect(result.success).toBe(false)
      expect(result.notFound).toBe(true)
    })

    it('should handle network error', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissionDetail).mockResolvedValue({
        success: false,
        error: 'Une erreur est survenue. Veuillez réessayer.',
      })

      const { mission, fetchMission, error, notFound } = useMissionDetail()

      const result = await fetchMission('casting-publicite-mtn')
      await flushPromises()

      expect(mission.value).toBeNull()
      expect(notFound.value).toBe(false)
      expect(error.value).toBe('Une erreur est survenue. Veuillez réessayer.')
      expect(result.success).toBe(false)
    })

    it('should reset state before fetching', async () => {
      // First, set up error state
      vi.mocked(publicMissionsApi.fetchPublicMissionDetail).mockResolvedValueOnce({
        success: false,
        notFound: true,
        error: 'Mission non trouvée',
      })

      const { fetchMission, notFound, error, mission } = useMissionDetail()

      await fetchMission('nonexistent-slug')
      expect(notFound.value).toBe(true)

      // Now fetch successfully
      vi.mocked(publicMissionsApi.fetchPublicMissionDetail).mockResolvedValueOnce({
        success: true,
        mission: mockMission,
      })

      await fetchMission('casting-publicite-mtn')
      await flushPromises()

      expect(notFound.value).toBe(false)
      expect(error.value).toBeNull()
      expect(mission.value).toEqual(mockMission)
    })

    it('should set loading state during fetch', async () => {
      let resolvePromise: (value: unknown) => void
      const pendingPromise = new Promise((resolve) => {
        resolvePromise = resolve
      })

      vi.mocked(publicMissionsApi.fetchPublicMissionDetail).mockReturnValue(
        pendingPromise as ReturnType<typeof publicMissionsApi.fetchPublicMissionDetail>
      )

      const { fetchMission, isLoading } = useMissionDetail()

      expect(isLoading.value).toBe(false)

      const fetchPromise = fetchMission('casting-publicite-mtn')
      expect(isLoading.value).toBe(true)

      resolvePromise!({ success: true, mission: mockMission })
      await fetchPromise
      await flushPromises()

      expect(isLoading.value).toBe(false)
    })

    it('should call API with correct id', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissionDetail).mockResolvedValue({
        success: true,
        mission: mockMission,
      })

      const { fetchMission } = useMissionDetail()
      await fetchMission('casting-publicite-mtn')

      expect(publicMissionsApi.fetchPublicMissionDetail).toHaveBeenCalledWith('casting-publicite-mtn')
    })
  })
})
