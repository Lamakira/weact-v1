import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useAdminMissions } from '../useAdminMissions'
import { adminMissionsApi } from '../../services/adminMissionsApi'
import type {
  AdminMissionListResponse,
  AdminMissionDetailResponse,
  AdminMissionData,
} from '../../services/adminMissionsApi'

vi.mock('../../services/adminMissionsApi', () => ({
  adminMissionsApi: {
    getMissions: vi.fn(),
    getMission: vi.fn(),
  },
}))

vi.mock('../../services/adminAuthApi', () => ({
  getApiErrorMessage: vi.fn((err: unknown) => (err as { response?: { data?: { message?: unknown } } })?.response?.data?.message ?? null),
}))

function makeMission(overrides: Partial<AdminMissionData> = {}): AdminMissionData {
  return {
    id: 1,
    titre: 'Pub TV Savon',
    description: 'Une publicité pour du savon',
    date_tournage: '2026-03-01T00:00:00.000Z',
    profil_recherche: 'Jeune femme dynamique',
    budget: 150000,
    date_limite_candidature: '2026-02-20T00:00:00.000Z',
    nombre_faces_voulu: 3,
    type_mission: 'publicite',
    type_mission_label: 'Publicité',
    genre_voulu: 'femme',
    genre_voulu_label: 'Femme',
    lieu: 'Cotonou, Bénin',
    duree: '1 jour',
    status: 'published',
    status_label: 'Publiée',
    is_accepting_candidatures: true,
    candidatures_count: 5,
    producer: {
      id: 10,
      type: 'agency',
      agency_name: 'Studio Lumière',
      first_name: 'Jean',
      last_name: 'Dupont',
      display_name: 'Studio Lumière',
      profile_photo_url: null,
      thumbnail_url: null,
    },
    created_at: '2026-01-15T00:00:00.000Z',
    updated_at: '2026-01-15T00:00:00.000Z',
    ...overrides,
  }
}

describe('useAdminMissions', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('fetchMissions', () => {
    it('fetches paginated missions and sets state', async () => {
      const mockResponse: AdminMissionListResponse = {
        data: [makeMission({ id: 1 }), makeMission({ id: 2, titre: 'Clip Musical' })],
        meta: { current_page: 1, last_page: 2, per_page: 15, total: 20 },
      }
      vi.mocked(adminMissionsApi.getMissions).mockResolvedValue(mockResponse)

      const { missions, pagination, isLoading, error, fetchMissions } = useAdminMissions()

      expect(missions.value).toEqual([])
      expect(isLoading.value).toBe(false)

      const promise = fetchMissions({ page: 1 })
      expect(isLoading.value).toBe(true)

      await promise

      expect(isLoading.value).toBe(false)
      expect(error.value).toBeNull()
      expect(missions.value).toHaveLength(2)
      expect(missions.value[0].id).toBe(1)
      expect(pagination.value?.total).toBe(20)
      expect(pagination.value?.last_page).toBe(2)
    })

    it('passes search, status, and type_mission params to the API', async () => {
      vi.mocked(adminMissionsApi.getMissions).mockResolvedValue({
        data: [],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
      })

      const { fetchMissions } = useAdminMissions()
      await fetchMissions({ page: 1, search: 'savon', status: 'published', type_mission: 'publicite' })

      expect(adminMissionsApi.getMissions).toHaveBeenCalledWith({
        page: 1,
        search: 'savon',
        status: 'published',
        type_mission: 'publicite',
      })
    })

    it('handles fetch error and clears data', async () => {
      vi.mocked(adminMissionsApi.getMissions).mockRejectedValue({
        response: { data: { message: 'Erreur serveur' } },
      })

      const { missions, pagination, error, fetchMissions } = useAdminMissions()
      await fetchMissions()

      expect(error.value).toBe('Erreur serveur')
      expect(missions.value).toEqual([])
      expect(pagination.value).toBeNull()
    })
  })

  describe('fetchMission', () => {
    it('fetches a single mission by ID', async () => {
      const mockResponse: AdminMissionDetailResponse = {
        data: makeMission({ id: 42, titre: 'Tournage Film' }),
        message: 'OK',
      }
      vi.mocked(adminMissionsApi.getMission).mockResolvedValue(mockResponse)

      const { mission, error, fetchMission } = useAdminMissions()
      await fetchMission(42)

      expect(adminMissionsApi.getMission).toHaveBeenCalledWith(42)
      expect(mission.value?.id).toBe(42)
      expect(mission.value?.titre).toBe('Tournage Film')
      expect(error.value).toBeNull()
    })

    it('handles fetch mission error and clears mission', async () => {
      vi.mocked(adminMissionsApi.getMission).mockRejectedValue({
        response: { data: { message: 'Mission introuvable' } },
      })

      const { mission, error, fetchMission } = useAdminMissions()
      await fetchMission(999)

      expect(error.value).toBe('Mission introuvable')
      expect(mission.value).toBeNull()
    })
  })

  describe('loading state', () => {
    it('sets isLoading to false after each operation completes', async () => {
      vi.mocked(adminMissionsApi.getMissions).mockResolvedValue({
        data: [],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
      })

      const { isLoading, fetchMissions } = useAdminMissions()

      await fetchMissions()
      expect(isLoading.value).toBe(false)
    })

    it('sets isLoading to false even on error', async () => {
      vi.mocked(adminMissionsApi.getMissions).mockRejectedValue(new Error('Network error'))

      const { isLoading, fetchMissions } = useAdminMissions()

      await fetchMissions()
      expect(isLoading.value).toBe(false)
    })
  })
})
