import { describe, it, expect, vi, beforeEach } from 'vitest'
import { AxiosError } from 'axios'

// Mock adminDashboardApi
const mockGetStats = vi.fn()
vi.mock('../../services/adminDashboardApi', () => ({
  adminDashboardApi: {
    getStats: (...args: unknown[]) => mockGetStats(...args),
  },
}))

import { useAdminDashboardStats } from '../useAdminDashboardStats'

const mockStatsResponse = {
  data: {
    users: { faces: 42, producers: 15, admins: 3 },
    missions: { published: 12, closed: 8, completed: 25, draft: 3 },
    articles: { published: 10, draft: 5 },
    candidatures: { total: 150, completed: 45 },
  },
  message: 'Admin dashboard stats retrieved successfully',
}

describe('useAdminDashboardStats', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    localStorage.clear()
  })

  it('fetches stats successfully', async () => {
    mockGetStats.mockResolvedValue(mockStatsResponse)

    const { stats, hasStats, fetchStats } = useAdminDashboardStats()
    expect(hasStats.value).toBe(false)

    await fetchStats()

    expect(mockGetStats).toHaveBeenCalledOnce()
    expect(hasStats.value).toBe(true)
    expect(stats.value?.users.faces).toBe(42)
    expect(stats.value?.users.producers).toBe(15)
    expect(stats.value?.users.admins).toBe(3)
    expect(stats.value?.missions.published).toBe(12)
    expect(stats.value?.missions.closed).toBe(8)
    expect(stats.value?.missions.completed).toBe(25)
    expect(stats.value?.missions.draft).toBe(3)
    expect(stats.value?.articles.published).toBe(10)
    expect(stats.value?.articles.draft).toBe(5)
    expect(stats.value?.candidatures.total).toBe(150)
    expect(stats.value?.candidatures.completed).toBe(45)
  })

  it('sets loading state during fetch', async () => {
    let resolvePromise: (value: unknown) => void
    mockGetStats.mockReturnValue(
      new Promise((resolve) => {
        resolvePromise = resolve
      }),
    )

    const { isLoading, fetchStats } = useAdminDashboardStats()
    const fetchPromise = fetchStats()

    expect(isLoading.value).toBe(true)

    resolvePromise!(mockStatsResponse)
    await fetchPromise

    expect(isLoading.value).toBe(false)
  })

  it('handles 401 error with French message', async () => {
    const axiosError = new AxiosError('Unauthorized', 'ERR_BAD_REQUEST', undefined, undefined, {
      status: 401,
      data: { message: 'Unauthenticated.' },
      statusText: 'Unauthorized',
      headers: {},
      config: {} as unknown,
    })
    mockGetStats.mockRejectedValue(axiosError)

    const { error, fetchStats } = useAdminDashboardStats()
    await fetchStats()

    expect(error.value).toBe('Veuillez vous connecter pour voir les statistiques')
  })

  it('handles 403 error with French message', async () => {
    const axiosError = new AxiosError('Forbidden', 'ERR_BAD_REQUEST', undefined, undefined, {
      status: 403,
      data: { message: 'Forbidden.' },
      statusText: 'Forbidden',
      headers: {},
      config: {} as unknown,
    })
    mockGetStats.mockRejectedValue(axiosError)

    const { error, fetchStats } = useAdminDashboardStats()
    await fetchStats()

    expect(error.value).toBe('Accès réservé aux administrateurs')
  })

  it('handles network error', async () => {
    const axiosError = new AxiosError('Network Error', 'ERR_NETWORK')
    mockGetStats.mockRejectedValue(axiosError)

    const { error, fetchStats } = useAdminDashboardStats()
    await fetchStats()

    expect(error.value).toBe('Impossible de se connecter au serveur. Vérifiez votre connexion.')
  })

  it('handles generic axios error with API message', async () => {
    const axiosError = new AxiosError('Server Error', 'ERR_BAD_REQUEST', undefined, undefined, {
      status: 500,
      data: { message: 'Internal Server Error' },
      statusText: 'Internal Server Error',
      headers: {},
      config: {} as unknown,
    })
    mockGetStats.mockRejectedValue(axiosError)

    const { error, fetchStats } = useAdminDashboardStats()
    await fetchStats()

    expect(error.value).toBe('Internal Server Error')
  })

  it('handles non-axios error', async () => {
    mockGetStats.mockRejectedValue(new Error('Something went wrong'))

    const { error, fetchStats } = useAdminDashboardStats()
    await fetchStats()

    expect(error.value).toBe('Une erreur inattendue est survenue')
  })

  it('retry calls fetchStats again', async () => {
    mockGetStats.mockRejectedValueOnce(new AxiosError('Network Error', 'ERR_NETWORK'))

    const { error, retry } = useAdminDashboardStats()
    await retry()

    expect(error.value).toBe('Impossible de se connecter au serveur. Vérifiez votre connexion.')

    mockGetStats.mockResolvedValueOnce(mockStatsResponse)
    await retry()

    expect(error.value).toBeNull()
    expect(mockGetStats).toHaveBeenCalledTimes(2)
  })

  it('clearError resets error state', async () => {
    mockGetStats.mockRejectedValue(new AxiosError('Network Error', 'ERR_NETWORK'))

    const { error, fetchStats, clearError } = useAdminDashboardStats()
    await fetchStats()

    expect(error.value).not.toBeNull()

    clearError()

    expect(error.value).toBeNull()
  })

  it('clears previous error on new fetch', async () => {
    mockGetStats.mockRejectedValueOnce(new AxiosError('Network Error', 'ERR_NETWORK'))

    const { error, stats, fetchStats } = useAdminDashboardStats()
    await fetchStats()

    expect(error.value).not.toBeNull()

    mockGetStats.mockResolvedValueOnce(mockStatsResponse)
    await fetchStats()

    expect(error.value).toBeNull()
    expect(stats.value).not.toBeNull()
  })
})
