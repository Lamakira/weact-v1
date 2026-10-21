import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { effectScope } from 'vue'
import { useFaceDashboardTodo } from '../useFaceDashboardTodo'
import { dashboardApi } from '../../services/dashboardApi'

vi.mock('../../services/dashboardApi', () => ({
  dashboardApi: { getFaceTodo: vi.fn() },
}))

// Le store de notifications possède le canal Echo : on n'en simule que le registre d'écouteurs.
const channel = vi.hoisted(() => ({
  listeners: new Map<string, () => void>(),
  off: null as unknown as ReturnType<typeof vi.fn>,
}))
vi.mock('@/stores/notification', () => ({
  useNotificationStore: () => ({
    onUserChannelEvent: (event: string, cb: () => void) => {
      channel.listeners.set(event, cb)
      return channel.off
    },
  }),
}))

describe('useFaceDashboardTodo', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    channel.listeners.clear()
    channel.off = vi.fn()
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

  describe('rechargement automatique', () => {
    beforeEach(() => {
      vi.useFakeTimers()
      vi.setSystemTime(new Date('2026-10-20T10:00:00Z'))
      vi.mocked(dashboardApi.getFaceTodo).mockResolvedValue({ data: [], message: 'ok' } as never)
    })

    afterEach(() => {
      vi.useRealTimers()
    })

    it('recharge au retour sur l\'onglet, au plus toutes les 30 s', () => {
      const scope = effectScope()
      scope.run(() => useFaceDashboardTodo())

      // Moins de 30 s depuis le montage : ignoré
      vi.advanceTimersByTime(10_000)
      window.dispatchEvent(new Event('focus'))
      expect(dashboardApi.getFaceTodo).not.toHaveBeenCalled()

      vi.advanceTimersByTime(25_000)
      window.dispatchEvent(new Event('focus'))
      expect(dashboardApi.getFaceTodo).toHaveBeenCalledTimes(1)

      // Aussitôt après : limité à une fois par fenêtre de 30 s
      document.dispatchEvent(new Event('visibilitychange'))
      expect(dashboardApi.getFaceTodo).toHaveBeenCalledTimes(1)

      vi.advanceTimersByTime(31_000)
      document.dispatchEvent(new Event('visibilitychange'))
      expect(dashboardApi.getFaceTodo).toHaveBeenCalledTimes(2)

      scope.stop()
    })

    it('recharge à chaque nouvelle notification temps réel', () => {
      const scope = effectScope()
      scope.run(() => useFaceDashboardTodo())

      const onNotification = channel.listeners.get('.notification.created')
      expect(onNotification).toBeTypeOf('function')

      onNotification?.()
      onNotification?.()
      expect(dashboardApi.getFaceTodo).toHaveBeenCalledTimes(2)

      scope.stop()
    })

    it('retire ses écouteurs à la destruction du scope', () => {
      const scope = effectScope()
      scope.run(() => useFaceDashboardTodo())
      scope.stop()

      expect(channel.off).toHaveBeenCalledTimes(1)
      vi.advanceTimersByTime(60_000)
      window.dispatchEvent(new Event('focus'))
      expect(dashboardApi.getFaceTodo).not.toHaveBeenCalled()
    })
  })
})
