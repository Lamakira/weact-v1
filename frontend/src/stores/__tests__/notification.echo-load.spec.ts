import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

const { state, mockPrivate, mockLeave, mockGetUnreadCount } = vi.hoisted(() => ({
  state: { failImport: true },
  mockPrivate: vi.fn(),
  mockLeave: vi.fn(),
  mockGetUnreadCount: vi.fn(),
}))

vi.mock('@/plugins/echo', () => {
  if (state.failImport) throw new Error('Failed to fetch dynamically imported module')
  return {
    echo: {
      private: (...args: unknown[]) => mockPrivate(...args),
      leave: (...args: unknown[]) => mockLeave(...args),
      connector: { options: { auth: { headers: {} } }, pusher: { connection: { bind: vi.fn(), unbind: vi.fn() } } },
    },
  }
})

vi.mock('@/features/notification/services/notificationApi', () => ({
  notificationApi: {
    getUnreadCount: (...args: unknown[]) => mockGetUnreadCount(...args),
    getNotifications: vi.fn(),
    markAsRead: vi.fn(),
    markAllAsRead: vi.fn(),
  },
}))
vi.mock('@/services/apiClient', () => ({
  default: {},
  getAuthToken: vi.fn(() => null),
  setAuthToken: vi.fn(),
  removeAuthToken: vi.fn(),
}))
vi.mock('@/utils/csrf', () => ({ getXsrfTokenFromCookie: vi.fn(() => null) }))

import { useNotificationStore } from '../notification'
import { useAuthStore } from '../auth'

describe('notification store - chargement du client temps réel', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    vi.useFakeTimers()
    vi.spyOn(console, 'error').mockImplementation(() => {})
    mockGetUnreadCount.mockResolvedValue({ count: 3 })
    useAuthStore().setUser({ id: 7, email: 'a@b.c' } as never)
  })

  afterEach(() => {
    useNotificationStore().unsubscribe()
    vi.useRealTimers()
    vi.restoreAllMocks()
  })

  it('garde focus et poll de sécurité actifs si le chargement échoue, puis retente au focus suivant', async () => {
    state.failImport = true
    const store = useNotificationStore()

    await store.subscribe()
    expect(store.isSubscribed).toBe(false)
    expect(mockPrivate).not.toHaveBeenCalled()

    // Le poll de sécurité continue d'alimenter le compteur
    await vi.advanceTimersByTimeAsync(120_000)
    expect(mockGetUnreadCount).toHaveBeenCalledTimes(1)

    // Réseau revenu : le focus suivant (hors fenêtre de 30 s) retente l'abonnement
    state.failImport = false
    mockPrivate.mockReturnValue({ listen: vi.fn().mockReturnThis(), error: vi.fn().mockReturnThis() })
    window.dispatchEvent(new Event('focus'))
    await vi.advanceTimersByTimeAsync(0)

    expect(mockPrivate).toHaveBeenCalledWith('App.Models.User.7')
  })

  it('abandonne un abonnement périmé si unsubscribe survient pendant le chargement', async () => {
    state.failImport = false
    mockPrivate.mockReturnValue({ listen: vi.fn().mockReturnThis(), error: vi.fn().mockReturnThis() })
    const store = useNotificationStore()

    const pending = store.subscribe()
    store.unsubscribe()
    await pending

    expect(mockPrivate).not.toHaveBeenCalled()
    expect(store.isSubscribed).toBe(false)
  })

  it('un login d\'un autre utilisateur pendant le chargement invalide l\'appel périmé', async () => {
    state.failImport = false
    const errorHandlers: Array<() => void> = []
    mockPrivate.mockImplementation(() => ({
      listen: vi.fn().mockReturnThis(),
      error: vi.fn((cb: () => void) => { errorHandlers.push(cb); return undefined }),
    }))
    const store = useNotificationStore()
    const auth = useAuthStore()

    const stale = store.subscribe()
    store.unsubscribe()
    auth.setUser({ id: 8, email: 'b@b.c' } as never)
    const fresh = store.subscribe()
    await Promise.all([stale, fresh])

    // Seul l'abonnement de l'utilisateur 8 a été créé
    expect(mockPrivate).toHaveBeenCalledTimes(1)
    expect(mockPrivate).toHaveBeenCalledWith('App.Models.User.8')
  })
})
