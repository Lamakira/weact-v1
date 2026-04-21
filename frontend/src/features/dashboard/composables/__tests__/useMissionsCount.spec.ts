import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useMissionsCount } from '../useMissionsCount'
import { dashboardApi } from '../../services/dashboardApi'
import type { MissionsCountResponse } from '../../types'

// Mock the dashboardApi module
vi.mock('../../services/dashboardApi', () => ({
  dashboardApi: {
    getAvailableMissionsCount: vi.fn(),
  },
}))

describe('useMissionsCount', () => {
  const mockCountResponse: MissionsCountResponse = {
    data: { count: 12 },
    message: 'Available missions count retrieved successfully',
  }

  const mockZeroCountResponse: MissionsCountResponse = {
    data: { count: 0 },
    message: 'Available missions count retrieved successfully',
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('initial state', () => {
    it('has correct initial state', () => {
      const { count, isLoading, error } = useMissionsCount()

      expect(count.value).toBe(0)
      expect(isLoading.value).toBe(false)
      expect(error.value).toBeNull()
    })
  })

  describe('fetchMissionsCount', () => {
    it('fetches count successfully with missions available', async () => {
      vi.mocked(dashboardApi.getAvailableMissionsCount).mockResolvedValue(mockCountResponse)

      const { count, isLoading, error, fetchMissionsCount } = useMissionsCount()

      await fetchMissionsCount()

      expect(count.value).toBe(12)
      expect(isLoading.value).toBe(false)
      expect(error.value).toBeNull()
      expect(dashboardApi.getAvailableMissionsCount).toHaveBeenCalledOnce()
    })

    it('fetches count successfully with zero missions', async () => {
      vi.mocked(dashboardApi.getAvailableMissionsCount).mockResolvedValue(mockZeroCountResponse)

      const { count, fetchMissionsCount } = useMissionsCount()

      await fetchMissionsCount()

      expect(count.value).toBe(0)
    })

    it('sets loading state during fetch', async () => {
      let resolvePromise: (value: MissionsCountResponse) => void
      const promise = new Promise<MissionsCountResponse>((resolve) => {
        resolvePromise = resolve
      })
      vi.mocked(dashboardApi.getAvailableMissionsCount).mockReturnValue(promise)

      const { isLoading, fetchMissionsCount } = useMissionsCount()

      const fetchPromise = fetchMissionsCount()
      expect(isLoading.value).toBe(true)

      resolvePromise!(mockCountResponse)
      await fetchPromise

      expect(isLoading.value).toBe(false)
    })

    it('handles 401 error with appropriate message', async () => {
      const error401 = {
        isAxiosError: true,
        response: { status: 401 },
        code: '',
      }
      vi.mocked(dashboardApi.getAvailableMissionsCount).mockRejectedValue(error401)

      const { count, error, fetchMissionsCount } = useMissionsCount()

      await fetchMissionsCount()

      expect(count.value).toBe(0)
      expect(error.value).toBe('Veuillez vous connecter')
    })

    it('handles 403 error with appropriate message', async () => {
      const error403 = {
        isAxiosError: true,
        response: { status: 403 },
        code: '',
      }
      vi.mocked(dashboardApi.getAvailableMissionsCount).mockRejectedValue(error403)

      const { count, error, fetchMissionsCount } = useMissionsCount()

      await fetchMissionsCount()

      expect(count.value).toBe(0)
      expect(error.value).toBe('Accès réservé aux Faces')
    })

    it('handles network error with appropriate message', async () => {
      const networkError = {
        isAxiosError: true,
        response: undefined,
        code: 'ERR_NETWORK',
      }
      vi.mocked(dashboardApi.getAvailableMissionsCount).mockRejectedValue(networkError)

      const { count, error, fetchMissionsCount } = useMissionsCount()

      await fetchMissionsCount()

      expect(count.value).toBe(0)
      expect(error.value).toBe('Impossible de se connecter au serveur')
    })

    it('handles timeout error with appropriate message', async () => {
      const timeoutError = {
        isAxiosError: true,
        response: undefined,
        code: 'ECONNABORTED',
      }
      vi.mocked(dashboardApi.getAvailableMissionsCount).mockRejectedValue(timeoutError)

      const { count, error, fetchMissionsCount } = useMissionsCount()

      await fetchMissionsCount()

      expect(count.value).toBe(0)
      expect(error.value).toBe('Impossible de se connecter au serveur')
    })

    it('handles generic axios error with API message', async () => {
      const genericError = {
        isAxiosError: true,
        response: { status: 500, data: { error: { message: 'Erreur serveur' } } },
        code: '',
      }
      vi.mocked(dashboardApi.getAvailableMissionsCount).mockRejectedValue(genericError)

      const { count, error, fetchMissionsCount } = useMissionsCount()

      await fetchMissionsCount()

      expect(count.value).toBe(0)
      expect(error.value).toBe('Erreur serveur')
    })

    it('handles generic axios error with fallback message', async () => {
      const genericError = {
        isAxiosError: true,
        response: { status: 500, data: {} },
        code: '',
      }
      vi.mocked(dashboardApi.getAvailableMissionsCount).mockRejectedValue(genericError)

      const { count, error, fetchMissionsCount } = useMissionsCount()

      await fetchMissionsCount()

      expect(count.value).toBe(0)
      expect(error.value).toBe('Le serveur est temporairement indisponible. Veuillez réessayer.')
    })

    it('handles non-axios error with generic message', async () => {
      vi.mocked(dashboardApi.getAvailableMissionsCount).mockRejectedValue(new Error('Unknown error'))

      const { count, error, fetchMissionsCount } = useMissionsCount()

      await fetchMissionsCount()

      expect(count.value).toBe(0)
      expect(error.value).toBe('Une erreur inattendue est survenue')
    })

    it('keeps count at 0 on error (graceful failure)', async () => {
      // First set a valid count
      vi.mocked(dashboardApi.getAvailableMissionsCount).mockResolvedValueOnce(mockCountResponse)

      const { count, fetchMissionsCount } = useMissionsCount()

      await fetchMissionsCount()
      expect(count.value).toBe(12)

      // Then simulate error - count should reset to 0
      vi.mocked(dashboardApi.getAvailableMissionsCount).mockRejectedValueOnce(new Error('Error'))

      await fetchMissionsCount()
      expect(count.value).toBe(0)
    })
  })

  describe('retry', () => {
    it('calls fetchMissionsCount when retry is invoked', async () => {
      vi.mocked(dashboardApi.getAvailableMissionsCount).mockResolvedValue(mockCountResponse)

      const { retry } = useMissionsCount()

      await retry()

      expect(dashboardApi.getAvailableMissionsCount).toHaveBeenCalledOnce()
    })
  })

  describe('clearError', () => {
    it('clears the error', async () => {
      vi.mocked(dashboardApi.getAvailableMissionsCount).mockRejectedValue(new Error('Error'))

      const { error, fetchMissionsCount, clearError } = useMissionsCount()

      await fetchMissionsCount()
      expect(error.value).not.toBeNull()

      clearError()
      expect(error.value).toBeNull()
    })
  })
})
