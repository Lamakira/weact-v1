import { describe, it, expect, vi, beforeEach } from 'vitest'
import { AxiosError } from 'axios'

const mockGetTrends = vi.fn()
const mockGetRecentActivity = vi.fn()
vi.mock('../../services/adminDashboardApi', () => ({
  adminDashboardApi: {
    getTrends: (...args: unknown[]) => mockGetTrends(...args),
    getRecentActivity: (...args: unknown[]) => mockGetRecentActivity(...args),
  },
}))

import { useAdminDashboardTrends } from '../useAdminDashboardTrends'

const mockTrendsResponse = {
  data: {
    registrations: { faces_7d: 5, faces_30d: 20, producers_7d: 2, producers_30d: 8 },
    missions: { published_7d: 3, published_30d: 12, completed_7d: 1, completed_30d: 5 },
    candidatures: { submitted_7d: 10, submitted_30d: 35, acceptance_rate_30d: 66.7 },
    health: { missions_without_candidatures_30d: 2, active_users_7d: 15 },
  },
  message: 'Dashboard trends retrieved successfully',
}

const mockActivityResponse = {
  data: [
    {
      type: 'mission',
      title: 'Mission Test',
      description: 'Mission Published',
      timestamp: '2026-02-11T10:00:00.000Z',
      link: '/admin/missions/1',
    },
    {
      type: 'article',
      title: 'Article Test',
      description: 'Article Published',
      timestamp: '2026-02-11T09:00:00.000Z',
      link: '/admin/articles/1/edit',
    },
  ],
  message: 'Recent activity retrieved successfully',
}

describe('useAdminDashboardTrends', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetches trends successfully', async () => {
    mockGetTrends.mockResolvedValue(mockTrendsResponse)

    const { trends, fetchTrends } = useAdminDashboardTrends()
    expect(trends.value).toBeNull()

    await fetchTrends()

    expect(mockGetTrends).toHaveBeenCalledOnce()
    expect(trends.value?.registrations.faces_7d).toBe(5)
    expect(trends.value?.registrations.faces_30d).toBe(20)
    expect(trends.value?.missions.published_7d).toBe(3)
    expect(trends.value?.candidatures.acceptance_rate_30d).toBe(66.7)
    expect(trends.value?.health.active_users_7d).toBe(15)
  })

  it('sets loading state during trends fetch', async () => {
    let resolvePromise: (value: unknown) => void
    mockGetTrends.mockReturnValue(
      new Promise((resolve) => {
        resolvePromise = resolve
      }),
    )

    const { isTrendsLoading, fetchTrends } = useAdminDashboardTrends()
    const fetchPromise = fetchTrends()

    expect(isTrendsLoading.value).toBe(true)

    resolvePromise!(mockTrendsResponse)
    await fetchPromise

    expect(isTrendsLoading.value).toBe(false)
  })

  it('handles trends error', async () => {
    const axiosError = new AxiosError('Network Error', 'ERR_NETWORK')
    mockGetTrends.mockRejectedValue(axiosError)

    const { trendsError, fetchTrends } = useAdminDashboardTrends()
    await fetchTrends()

    expect(trendsError.value).toBe(
      'Impossible de se connecter au serveur. Vérifiez votre connexion.',
    )
  })

  it('handles 401 error for trends', async () => {
    const axiosError = new AxiosError('Unauthorized', 'ERR_BAD_REQUEST', undefined, undefined, {
      status: 401,
      data: { message: 'Unauthenticated.' },
      statusText: 'Unauthorized',
      headers: {},
      config: {} as unknown,
    })
    mockGetTrends.mockRejectedValue(axiosError)

    const { trendsError, fetchTrends } = useAdminDashboardTrends()
    await fetchTrends()

    expect(trendsError.value).toBe('Veuillez vous connecter pour voir les statistiques')
  })

  it('retries trends fetch', async () => {
    mockGetTrends.mockRejectedValueOnce(new AxiosError('Network Error', 'ERR_NETWORK'))

    const { trendsError, retryTrends } = useAdminDashboardTrends()
    await retryTrends()

    expect(trendsError.value).not.toBeNull()

    mockGetTrends.mockResolvedValueOnce(mockTrendsResponse)
    await retryTrends()

    expect(trendsError.value).toBeNull()
    expect(mockGetTrends).toHaveBeenCalledTimes(2)
  })

  it('fetches recent activity successfully', async () => {
    mockGetRecentActivity.mockResolvedValue(mockActivityResponse)

    const { recentActivity, fetchRecentActivity } = useAdminDashboardTrends()
    expect(recentActivity.value).toHaveLength(0)

    await fetchRecentActivity()

    expect(mockGetRecentActivity).toHaveBeenCalledOnce()
    expect(recentActivity.value).toHaveLength(2)
    expect(recentActivity.value[0].type).toBe('mission')
    expect(recentActivity.value[0].title).toBe('Mission Test')
    expect(recentActivity.value[1].type).toBe('article')
  })

  it('sets loading state during activity fetch', async () => {
    let resolvePromise: (value: unknown) => void
    mockGetRecentActivity.mockReturnValue(
      new Promise((resolve) => {
        resolvePromise = resolve
      }),
    )

    const { isActivityLoading, fetchRecentActivity } = useAdminDashboardTrends()
    const fetchPromise = fetchRecentActivity()

    expect(isActivityLoading.value).toBe(true)

    resolvePromise!(mockActivityResponse)
    await fetchPromise

    expect(isActivityLoading.value).toBe(false)
  })

  it('handles activity error', async () => {
    const axiosError = new AxiosError('Server Error', 'ERR_BAD_REQUEST', undefined, undefined, {
      status: 500,
      data: { message: 'Internal Server Error' },
      statusText: 'Internal Server Error',
      headers: {},
      config: {} as unknown,
    })
    mockGetRecentActivity.mockRejectedValue(axiosError)

    const { activityError, fetchRecentActivity } = useAdminDashboardTrends()
    await fetchRecentActivity()

    expect(activityError.value).toBe('Internal Server Error')
  })

  it('retries activity fetch', async () => {
    mockGetRecentActivity.mockRejectedValueOnce(new AxiosError('Network Error', 'ERR_NETWORK'))

    const { activityError, retryActivity } = useAdminDashboardTrends()
    await retryActivity()

    expect(activityError.value).not.toBeNull()

    mockGetRecentActivity.mockResolvedValueOnce(mockActivityResponse)
    await retryActivity()

    expect(activityError.value).toBeNull()
    expect(mockGetRecentActivity).toHaveBeenCalledTimes(2)
  })
})
