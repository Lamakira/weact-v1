import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useProducerDashboardStats } from '../useProducerDashboardStats'
import { dashboardApi } from '../../services/dashboardApi'
import type { ProducerDashboardStatsResponse } from '../../types'

// Mock the dashboardApi module
vi.mock('../../services/dashboardApi', () => ({
  dashboardApi: {
    getProducerStats: vi.fn(),
  },
}))

describe('useProducerDashboardStats', () => {
  // Note: in_progress counts missions with confirmed/in_progress candidatures
  // It can be different from closed (closed just means not accepting new candidatures)
  const mockStatsResponse: ProducerDashboardStatsResponse = {
    data: {
      published: 5,
      in_progress: 2, // Missions with active candidatures
      closed: 3, // Missions no longer accepting candidatures
      completed: 10,
      total_candidatures: 47, // Total candidatures received across all missions (FR56)
      unique_collaborators: 12, // Unique Faces worked with (FR57)
    },
    message: 'Dashboard stats retrieved successfully',
  }

  const mockZeroStatsResponse: ProducerDashboardStatsResponse = {
    data: {
      published: 0,
      in_progress: 0,
      closed: 0,
      completed: 0,
      total_candidatures: 0,
      unique_collaborators: 0,
    },
    message: 'Dashboard stats retrieved successfully',
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('initial state', () => {
    it('has correct initial state', () => {
      const { stats, isLoading, error, hasStats, totalMissions } = useProducerDashboardStats()

      expect(stats.value).toBeNull()
      expect(isLoading.value).toBe(false)
      expect(error.value).toBeNull()
      expect(hasStats.value).toBe(false)
      expect(totalMissions.value).toBe(0)
    })
  })

  describe('fetchStats', () => {
    it('fetches stats successfully', async () => {
      vi.mocked(dashboardApi.getProducerStats).mockResolvedValue(mockStatsResponse)

      const { stats, isLoading, error, fetchStats } = useProducerDashboardStats()

      await fetchStats()

      expect(stats.value).toEqual(mockStatsResponse.data)
      expect(isLoading.value).toBe(false)
      expect(error.value).toBeNull()
      expect(dashboardApi.getProducerStats).toHaveBeenCalledOnce()
    })

    it('fetches stats successfully with zero values', async () => {
      vi.mocked(dashboardApi.getProducerStats).mockResolvedValue(mockZeroStatsResponse)

      const { stats, fetchStats } = useProducerDashboardStats()

      await fetchStats()

      expect(stats.value?.published).toBe(0)
      expect(stats.value?.in_progress).toBe(0)
      expect(stats.value?.closed).toBe(0)
      expect(stats.value?.completed).toBe(0)
      expect(stats.value?.total_candidatures).toBe(0)
      expect(stats.value?.unique_collaborators).toBe(0)
    })

    it('fetches stats successfully with total_candidatures (FR56)', async () => {
      vi.mocked(dashboardApi.getProducerStats).mockResolvedValue(mockStatsResponse)

      const { stats, fetchStats } = useProducerDashboardStats()

      await fetchStats()

      expect(stats.value?.total_candidatures).toBe(47)
    })

    it('fetches stats successfully with unique_collaborators (FR57)', async () => {
      vi.mocked(dashboardApi.getProducerStats).mockResolvedValue(mockStatsResponse)

      const { stats, fetchStats } = useProducerDashboardStats()

      await fetchStats()

      expect(stats.value?.unique_collaborators).toBe(12)
    })

    it('sets loading state during fetch', async () => {
      let resolvePromise: (value: ProducerDashboardStatsResponse) => void
      const promise = new Promise<ProducerDashboardStatsResponse>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(dashboardApi.getProducerStats).mockReturnValue(promise)

      const { isLoading, fetchStats } = useProducerDashboardStats()

      const fetchPromise = fetchStats()
      expect(isLoading.value).toBe(true)

      resolvePromise!(mockStatsResponse)
      await fetchPromise

      expect(isLoading.value).toBe(false)
    })

    it('handles 401 error with appropriate message', async () => {
      const error401 = {
        isAxiosError: true,
        response: { status: 401 },
        code: '',
      }
      vi.mocked(dashboardApi.getProducerStats).mockRejectedValue(error401)

      const { stats, error, fetchStats } = useProducerDashboardStats()

      await fetchStats()

      expect(stats.value).toBeNull()
      expect(error.value).toBe('Veuillez vous connecter pour voir vos statistiques')
    })

    it('handles 403 error with appropriate message', async () => {
      const error403 = {
        isAxiosError: true,
        response: { status: 403 },
        code: '',
      }
      vi.mocked(dashboardApi.getProducerStats).mockRejectedValue(error403)

      const { stats, error, fetchStats } = useProducerDashboardStats()

      await fetchStats()

      expect(stats.value).toBeNull()
      expect(error.value).toBe('Accès réservé aux Producteurs')
    })

    it('handles network error with appropriate message', async () => {
      const networkError = {
        isAxiosError: true,
        response: undefined,
        code: 'ERR_NETWORK',
      }
      vi.mocked(dashboardApi.getProducerStats).mockRejectedValue(networkError)

      const { stats, error, fetchStats } = useProducerDashboardStats()

      await fetchStats()

      expect(stats.value).toBeNull()
      expect(error.value).toBe('Impossible de se connecter au serveur. Vérifiez votre connexion.')
    })

    it('handles timeout error with appropriate message', async () => {
      const timeoutError = {
        isAxiosError: true,
        response: undefined,
        code: 'ECONNABORTED',
      }
      vi.mocked(dashboardApi.getProducerStats).mockRejectedValue(timeoutError)

      const { stats, error, fetchStats } = useProducerDashboardStats()

      await fetchStats()

      expect(stats.value).toBeNull()
      expect(error.value).toBe('Impossible de se connecter au serveur. Vérifiez votre connexion.')
    })

    it('handles generic axios error with API message', async () => {
      const genericError = {
        isAxiosError: true,
        response: { status: 500, data: { error: { message: 'Erreur serveur' } } },
        code: '',
      }
      vi.mocked(dashboardApi.getProducerStats).mockRejectedValue(genericError)

      const { stats, error, fetchStats } = useProducerDashboardStats()

      await fetchStats()

      expect(stats.value).toBeNull()
      expect(error.value).toBe('Erreur serveur')
    })

    it('handles generic axios error with fallback message', async () => {
      const genericError = {
        isAxiosError: true,
        response: { status: 500, data: {} },
        code: '',
      }
      vi.mocked(dashboardApi.getProducerStats).mockRejectedValue(genericError)

      const { stats, error, fetchStats } = useProducerDashboardStats()

      await fetchStats()

      expect(stats.value).toBeNull()
      expect(error.value).toBe('Une erreur est survenue')
    })

    it('handles non-axios error with generic message', async () => {
      vi.mocked(dashboardApi.getProducerStats).mockRejectedValue(new Error('Unknown error'))

      const { stats, error, fetchStats } = useProducerDashboardStats()

      await fetchStats()

      expect(stats.value).toBeNull()
      expect(error.value).toBe('Une erreur inattendue est survenue')
    })
  })

  describe('computed properties', () => {
    it('hasStats returns true when stats are loaded', async () => {
      vi.mocked(dashboardApi.getProducerStats).mockResolvedValue(mockStatsResponse)

      const { hasStats, fetchStats } = useProducerDashboardStats()

      expect(hasStats.value).toBe(false)
      await fetchStats()
      expect(hasStats.value).toBe(true)
    })

    it('totalMissions computes correctly', async () => {
      vi.mocked(dashboardApi.getProducerStats).mockResolvedValue(mockStatsResponse)

      const { totalMissions, fetchStats } = useProducerDashboardStats()

      expect(totalMissions.value).toBe(0)
      await fetchStats()
      // 5 published + 3 closed + 10 completed = 18 (in_progress is same as closed, not double-counted)
      expect(totalMissions.value).toBe(18)
    })
  })

  describe('retry', () => {
    it('calls fetchStats when retry is invoked', async () => {
      vi.mocked(dashboardApi.getProducerStats).mockResolvedValue(mockStatsResponse)

      const { retry } = useProducerDashboardStats()

      await retry()

      expect(dashboardApi.getProducerStats).toHaveBeenCalledOnce()
    })
  })

  describe('clearError', () => {
    it('clears the error', async () => {
      vi.mocked(dashboardApi.getProducerStats).mockRejectedValue(new Error('Error'))

      const { error, fetchStats, clearError } = useProducerDashboardStats()

      await fetchStats()
      expect(error.value).not.toBeNull()

      clearError()
      expect(error.value).toBeNull()
    })
  })
})
