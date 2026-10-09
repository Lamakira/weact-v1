import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useFaceDashboardTodo } from '../useFaceDashboardTodo'
import { dashboardApi } from '../../services/dashboardApi'

vi.mock('../../services/dashboardApi', () => ({
  dashboardApi: { getFaceTodo: vi.fn() },
}))

describe('useFaceDashboardTodo', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('charge les items de la file', async () => {
    const item = {
      type: 'profile_completion',
      title: 'Compléter le profil',
      meta: null,
      urgent_meta: null,
      action_label: 'Compléter',
      url: '/face/profile',
    }
    vi.mocked(dashboardApi.getFaceTodo).mockResolvedValue({ data: [item], message: 'ok' } as never)

    const { items, error, isLoading, fetchTodo } = useFaceDashboardTodo()
    await fetchTodo()

    expect(items.value).toEqual([item])
    expect(error.value).toBeNull()
    expect(isLoading.value).toBe(false)
  })

  it('expose une erreur lisible et se relance avec retry', async () => {
    vi.mocked(dashboardApi.getFaceTodo).mockRejectedValueOnce(new Error('boom'))

    const { items, error, retry, fetchTodo } = useFaceDashboardTodo()
    await fetchTodo()

    expect(error.value).toBeTruthy()
    expect(items.value).toEqual([])

    vi.mocked(dashboardApi.getFaceTodo).mockResolvedValue({ data: [], message: 'ok' } as never)
    await retry()
    expect(error.value).toBeNull()
  })
})
