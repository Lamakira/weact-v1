import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useMissionsList } from '../useMissionsList'
import { missionApi } from '../../services/missionApi'
import type { Mission } from '../../types'

// Mock the mission API
vi.mock('../../services/missionApi', () => ({
  missionApi: {
    getMissions: vi.fn(),
  },
}))

// Mock getApiErrorMessage
vi.mock('@/features/auth/services/authApi', () => ({
  getApiErrorMessage: vi.fn((err) => err?.message || 'Unknown error'),
}))

// Factory for creating mock missions
function createMockMission(overrides: Partial<Mission> = {}): Mission {
  return {
    id: 1,
    titre: 'Test Mission',
    description: 'Test description',
    date_tournage: '2026-03-01',
    profil_recherche: 'Looking for talent',
    budget: 500,
    date_limite_candidature: '2026-02-15',
    nombre_faces_voulu: 3,
    type_mission: 'publicite',
    type_mission_label: 'Publicité',
    genre_voulu: 'tous',
    genre_voulu_label: 'Homme et Femme',
    lieu: 'Paris',
    duree: '1 jour',
    status: 'published',
    status_label: 'Publiée',
    is_accepting_candidatures: true,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

describe('useMissionsList', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('status filtering', () => {
    it('returns all missions when no filter is set', async () => {
      const mockMissions = [
        createMockMission({ id: 1, status: 'published' }),
        createMockMission({ id: 2, status: 'closed' }),
        createMockMission({ id: 3, status: 'completed' }),
      ]

      vi.mocked(missionApi.getMissions).mockResolvedValueOnce({
        data: mockMissions,
        message: 'Success',
      })

      const { missions, fetchMissions, statusFilter } = useMissionsList()

      await fetchMissions()

      expect(statusFilter.value).toBe('')
      expect(missions.value).toHaveLength(3)
    })

    it('filters missions by status when filter is set', async () => {
      const mockMissions = [
        createMockMission({ id: 1, status: 'published' }),
        createMockMission({ id: 2, status: 'closed' }),
        createMockMission({ id: 3, status: 'completed' }),
        createMockMission({ id: 4, status: 'published' }),
      ]

      vi.mocked(missionApi.getMissions).mockResolvedValueOnce({
        data: mockMissions,
        message: 'Success',
      })

      const { missions, fetchMissions, setStatusFilter } = useMissionsList()

      await fetchMissions()

      // Filter by published
      setStatusFilter('published')
      expect(missions.value).toHaveLength(2)
      expect(missions.value.every((m) => m.status === 'published')).toBe(true)

      // Filter by closed
      setStatusFilter('closed')
      expect(missions.value).toHaveLength(1)
      expect(missions.value[0].status).toBe('closed')

      // Clear filter
      setStatusFilter('')
      expect(missions.value).toHaveLength(4)
    })

    it('returns empty array when no missions match filter', async () => {
      const mockMissions = [
        createMockMission({ id: 1, status: 'published' }),
        createMockMission({ id: 2, status: 'published' }),
      ]

      vi.mocked(missionApi.getMissions).mockResolvedValueOnce({
        data: mockMissions,
        message: 'Success',
      })

      const { missions, fetchMissions, setStatusFilter, isEmpty } = useMissionsList()

      await fetchMissions()

      setStatusFilter('completed')
      expect(missions.value).toHaveLength(0)
      expect(isEmpty.value).toBe(true)
    })
  })

  describe('hasNoMissions computed', () => {
    it('returns true when no missions exist at all', async () => {
      vi.mocked(missionApi.getMissions).mockResolvedValueOnce({
        data: [],
        message: 'Success',
      })

      const { hasNoMissions, fetchMissions } = useMissionsList()

      await fetchMissions()

      expect(hasNoMissions.value).toBe(true)
    })

    it('returns false when missions exist even if filtered to empty', async () => {
      const mockMissions = [
        createMockMission({ id: 1, status: 'published' }),
      ]

      vi.mocked(missionApi.getMissions).mockResolvedValueOnce({
        data: mockMissions,
        message: 'Success',
      })

      const { hasNoMissions, fetchMissions, setStatusFilter, isEmpty } = useMissionsList()

      await fetchMissions()

      expect(hasNoMissions.value).toBe(false)

      // Filter to show nothing
      setStatusFilter('completed')
      expect(isEmpty.value).toBe(true)
      expect(hasNoMissions.value).toBe(false) // Still has missions, just filtered
    })
  })

  describe('allMissions vs missions', () => {
    it('allMissions contains all missions regardless of filter', async () => {
      const mockMissions = [
        createMockMission({ id: 1, status: 'published' }),
        createMockMission({ id: 2, status: 'closed' }),
        createMockMission({ id: 3, status: 'completed' }),
      ]

      vi.mocked(missionApi.getMissions).mockResolvedValueOnce({
        data: mockMissions,
        message: 'Success',
      })

      const { missions, allMissions, fetchMissions, setStatusFilter } = useMissionsList()

      await fetchMissions()

      // All missions should have 3
      expect(allMissions.value).toHaveLength(3)

      // Filter to published only
      setStatusFilter('published')
      expect(missions.value).toHaveLength(1)
      expect(allMissions.value).toHaveLength(3) // Still 3
    })
  })

  describe('reset', () => {
    it('resets statusFilter along with other state', async () => {
      const mockMissions = [
        createMockMission({ id: 1, status: 'published' }),
      ]

      vi.mocked(missionApi.getMissions).mockResolvedValueOnce({
        data: mockMissions,
        message: 'Success',
      })

      const { fetchMissions, setStatusFilter, statusFilter, reset } = useMissionsList()

      await fetchMissions()
      setStatusFilter('published')
      expect(statusFilter.value).toBe('published')

      reset()
      expect(statusFilter.value).toBe('')
    })
  })
})
