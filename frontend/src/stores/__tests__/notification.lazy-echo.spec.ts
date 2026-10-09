import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

const { echoLoaded, mockPrivate } = vi.hoisted(() => ({
  echoLoaded: vi.fn(),
  mockPrivate: vi.fn(),
}))

vi.mock('@/plugins/echo', () => {
  echoLoaded()
  return {
    echo: {
      private: (...args: unknown[]) => mockPrivate(...args),
      leave: vi.fn(),
      connector: { options: { auth: { headers: {} } }, pusher: { connection: { bind: vi.fn(), unbind: vi.fn() } } },
    },
  }
})

vi.mock('@/features/notification/services/notificationApi', () => ({
  notificationApi: { getUnreadCount: vi.fn(), getNotifications: vi.fn(), markAsRead: vi.fn(), markAllAsRead: vi.fn() },
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

describe('notification store - lazy Echo', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('does not load the realtime client on import nor for an anonymous visitor', async () => {
    const store = useNotificationStore()
    useAuthStore().clearAuth()

    await store.subscribe()
    store.unsubscribe()

    expect(echoLoaded).not.toHaveBeenCalled()
    expect(mockPrivate).not.toHaveBeenCalled()
  })

  it('loads the realtime client only when an authenticated user subscribes', async () => {
    mockPrivate.mockReturnValue({ listen: vi.fn().mockReturnThis(), error: vi.fn().mockReturnThis() })
    const store = useNotificationStore()
    useAuthStore().setUser({ id: 7, email: 'a@b.c' } as never)

    await store.subscribe()

    expect(echoLoaded).toHaveBeenCalledOnce()
    expect(mockPrivate).toHaveBeenCalledWith('App.Models.User.7')
    store.unsubscribe()
  })
})
