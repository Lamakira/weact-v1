import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import type { Notification } from '@/features/notification/types'

// Mock notificationApi
const mockGetUnreadCount = vi.fn()
const mockGetNotifications = vi.fn()
const mockMarkAsRead = vi.fn()
const mockMarkAllAsRead = vi.fn()

vi.mock('@/features/notification/services/notificationApi', () => ({
  notificationApi: {
    getUnreadCount: (...args: unknown[]) => mockGetUnreadCount(...args),
    getNotifications: (...args: unknown[]) => mockGetNotifications(...args),
    markAsRead: (...args: unknown[]) => mockMarkAsRead(...args),
    markAllAsRead: (...args: unknown[]) => mockMarkAllAsRead(...args),
  },
}))

// Mock Echo
const mockListen = vi.fn().mockReturnThis()
const mockError = vi.fn().mockReturnThis()
const mockSubscribed = vi.fn().mockReturnThis()
const mockPrivate = vi.fn(() => ({
  listen: mockListen,
  error: mockError,
  subscribed: mockSubscribed,
}))
const mockLeave = vi.fn()

vi.mock('@/plugins/echo', () => ({
  echo: {
    private: (...args: unknown[]) => mockPrivate(...args),
    leave: (...args: unknown[]) => mockLeave(...args),
    connector: {
      options: {
        auth: { headers: {} },
      },
    },
  },
}))

// Mock auth store deps
vi.mock('@/services/apiClient', () => ({
  default: {},
  getAuthToken: vi.fn(() => 'test-token'),
  setAuthToken: vi.fn(),
  removeAuthToken: vi.fn(),
}))

vi.mock('@/utils/csrf', () => ({
  getXsrfTokenFromCookie: vi.fn(() => 'test-xsrf'),
}))

import { useNotificationStore } from '../notification'
import { useAuthStore } from '../auth'

function createNotification(overrides: Partial<Notification> = {}): Notification {
  return {
    id: 'uuid-1',
    type: 'candidature_accepted',
    data: { message: 'Test notification' },
    read_at: null,
    created_at: '2026-04-11T10:00:00.000000Z',
    ...overrides,
  }
}

describe('useNotificationStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  describe('initial state', () => {
    it('starts with correct defaults', () => {
      const store = useNotificationStore()

      expect(store.unreadCount).toBe(0)
      expect(store.items).toEqual([])
      expect(store.isLoading).toBe(false)
      expect(store.isSubscribed).toBe(false)
      expect(store.hasFetchedItems).toBe(false)
    })
  })

  describe('fetchUnreadCount', () => {
    it('calls API and updates unreadCount', async () => {
      mockGetUnreadCount.mockResolvedValue({ count: 5 })
      const store = useNotificationStore()

      await store.fetchUnreadCount()

      expect(mockGetUnreadCount).toHaveBeenCalledOnce()
      expect(store.unreadCount).toBe(5)
    })

    it('logs error on failure without crashing', async () => {
      mockGetUnreadCount.mockRejectedValue(new Error('Network error'))
      const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {})
      const store = useNotificationStore()

      await store.fetchUnreadCount()

      expect(store.unreadCount).toBe(0)
      expect(consoleSpy).toHaveBeenCalled()
      consoleSpy.mockRestore()
    })
  })

  describe('fetchNotifications', () => {
    it('calls API, updates items, sets hasFetchedItems, and returns true', async () => {
      const notifications = [createNotification({ id: 'uuid-1' }), createNotification({ id: 'uuid-2' })]
      mockGetNotifications.mockResolvedValue({ data: notifications })
      const store = useNotificationStore()

      const result = await store.fetchNotifications()

      expect(result).toBe(true)
      expect(mockGetNotifications).toHaveBeenCalledWith(1)
      expect(store.items).toEqual(notifications)
      expect(store.hasFetchedItems).toBe(true)
    })

    it('returns false on API failure', async () => {
      mockGetNotifications.mockRejectedValue(new Error('Network error'))
      vi.spyOn(console, 'error').mockImplementation(() => {})
      const store = useNotificationStore()

      const result = await store.fetchNotifications()

      expect(result).toBe(false)
    })

    it('passes page parameter to API', async () => {
      mockGetNotifications.mockResolvedValue({ data: [] })
      const store = useNotificationStore()

      await store.fetchNotifications(3)

      expect(mockGetNotifications).toHaveBeenCalledWith(3)
    })
  })

  describe('markAsRead', () => {
    it('optimistically updates local state then calls API and returns true', async () => {
      const notification = createNotification({ id: 'uuid-1', read_at: null })
      mockGetNotifications.mockResolvedValue({ data: [notification] })
      mockMarkAsRead.mockResolvedValue({ data: notification })
      const store = useNotificationStore()
      store.unreadCount = 3
      await store.fetchNotifications()

      const result = await store.markAsRead('uuid-1')

      expect(result).toBe(true)
      expect(store.items[0].read_at).not.toBeNull()
      expect(store.unreadCount).toBe(2)
      expect(mockMarkAsRead).toHaveBeenCalledWith('uuid-1')
    })

    it('reverts optimistic update on API failure and returns false', async () => {
      const notification = createNotification({ id: 'uuid-1', read_at: null })
      mockGetNotifications.mockResolvedValue({ data: [notification] })
      mockMarkAsRead.mockRejectedValue(new Error('API error'))
      vi.spyOn(console, 'error').mockImplementation(() => {})
      const store = useNotificationStore()
      store.unreadCount = 3
      await store.fetchNotifications()

      const result = await store.markAsRead('uuid-1')

      expect(result).toBe(false)
      expect(store.items[0].read_at).toBeNull()
      expect(store.unreadCount).toBe(3)
    })

    it('does not decrement if notification was already read', async () => {
      const notification = createNotification({ id: 'uuid-1', read_at: '2026-04-11T09:00:00Z' })
      mockGetNotifications.mockResolvedValue({ data: [notification] })
      mockMarkAsRead.mockResolvedValue({ data: notification })
      const store = useNotificationStore()
      store.unreadCount = 2
      await store.fetchNotifications()

      await store.markAsRead('uuid-1')

      expect(store.unreadCount).toBe(2)
    })

    it('does not revert an already-read notification on API failure', async () => {
      const notification = createNotification({ id: 'uuid-1', read_at: '2026-04-11T09:00:00Z' })
      mockGetNotifications.mockResolvedValue({ data: [notification] })
      mockMarkAsRead.mockRejectedValue(new Error('API error'))
      vi.spyOn(console, 'error').mockImplementation(() => {})
      const store = useNotificationStore()
      store.unreadCount = 2
      await store.fetchNotifications()

      await store.markAsRead('uuid-1')

      expect(store.items[0].read_at).toBe('2026-04-11T09:00:00Z')
      expect(store.unreadCount).toBe(2)
    })
  })

  describe('markAllAsRead', () => {
    it('optimistically updates all items then calls API and returns true', async () => {
      const notifications = [
        createNotification({ id: 'uuid-1', read_at: null }),
        createNotification({ id: 'uuid-2', read_at: null }),
        createNotification({ id: 'uuid-3', read_at: '2026-04-11T09:00:00Z' }),
      ]
      mockGetNotifications.mockResolvedValue({ data: notifications })
      mockMarkAllAsRead.mockResolvedValue({ message: 'ok' })
      const store = useNotificationStore()
      store.unreadCount = 2
      await store.fetchNotifications()

      const result = await store.markAllAsRead()

      expect(result).toBe(true)
      expect(store.unreadCount).toBe(0)
      expect(store.items.every((n) => n.read_at !== null)).toBe(true)
      expect(mockMarkAllAsRead).toHaveBeenCalledOnce()
    })

    it('reverts optimistic update on API failure and returns false', async () => {
      const notifications = [
        createNotification({ id: 'uuid-1', read_at: null }),
        createNotification({ id: 'uuid-2', read_at: '2026-04-11T09:00:00Z' }),
      ]
      mockGetNotifications.mockResolvedValue({ data: notifications })
      mockMarkAllAsRead.mockRejectedValue(new Error('API error'))
      vi.spyOn(console, 'error').mockImplementation(() => {})
      const store = useNotificationStore()
      store.unreadCount = 1
      await store.fetchNotifications()

      const result = await store.markAllAsRead()

      expect(result).toBe(false)
      expect(store.unreadCount).toBe(1)
      expect(store.items[0].read_at).toBeNull()
      expect(store.items[1].read_at).toBe('2026-04-11T09:00:00Z')
    })
  })

  describe('subscribe', () => {
    it('sets up Echo listener on correct channel', () => {
      const store = useNotificationStore()
      const authStore = useAuthStore()
      authStore.setUser({ id: 42, email: 'test@test.com' } as never)
      authStore.setToken('test-token')

      store.subscribe()

      expect(mockPrivate).toHaveBeenCalledWith('App.Models.User.42')
      expect(mockListen).toHaveBeenCalledWith('.notification.created', expect.any(Function))
      expect(mockError).toHaveBeenCalledOnce()
      expect(mockSubscribed).toHaveBeenCalledOnce()
      expect(store.isSubscribed).toBe(false)
    })

    it('is a no-op when already subscribed (idempotent)', () => {
      const store = useNotificationStore()
      const authStore = useAuthStore()
      authStore.setUser({ id: 42, email: 'test@test.com' } as never)
      authStore.setToken('test-token')

      store.subscribe()
      store.subscribe()

      expect(mockPrivate).toHaveBeenCalledTimes(1)
    })

    it('marks the store subscribed only after channel subscription succeeds', () => {
      const store = useNotificationStore()
      const authStore = useAuthStore()
      authStore.setUser({ id: 42, email: 'test@test.com' } as never)
      authStore.setToken('test-token')

      store.subscribe()

      const subscribedHandler = mockSubscribed.mock.calls[0][0] as () => void
      subscribedHandler()

      expect(store.isSubscribed).toBe(true)
    })

    it('resets subscription state when the channel reports an error', () => {
      const store = useNotificationStore()
      const authStore = useAuthStore()
      authStore.setUser({ id: 42, email: 'test@test.com' } as never)
      authStore.setToken('test-token')

      store.subscribe()

      const subscribedHandler = mockSubscribed.mock.calls[0][0] as () => void
      subscribedHandler()
      expect(store.isSubscribed).toBe(true)

      const errorHandler = mockError.mock.calls[0][0] as () => void
      errorHandler()

      expect(store.isSubscribed).toBe(false)
      expect(mockLeave).toHaveBeenCalledWith('App.Models.User.42')
    })

    it('does not subscribe when no user is authenticated', () => {
      const store = useNotificationStore()
      const authStore = useAuthStore()
      authStore.clearAuth()

      store.subscribe()

      expect(mockPrivate).not.toHaveBeenCalled()
      expect(store.isSubscribed).toBe(false)
    })
  })

  describe('realtime event handling', () => {
    function subscribeAndGetHandler() {
      const store = useNotificationStore()
      const authStore = useAuthStore()
      authStore.setUser({ id: 42, email: 'test@test.com' } as never)
      authStore.setToken('test-token')
      store.subscribe()

      // Extract the event handler passed to .listen()
      const handler = mockListen.mock.calls[0][1] as (event: Notification) => void
      return { store, handler }
    }

    it('increments unreadCount and prepends to items when items are fetched', async () => {
      const { store, handler } = subscribeAndGetHandler()
      // Simulate items having been fetched
      mockGetNotifications.mockResolvedValue({ data: [createNotification({ id: 'existing-1' })] })
      await store.fetchNotifications()
      store.unreadCount = 1

      const newNotification = createNotification({ id: 'new-uuid' })
      handler(newNotification)

      expect(store.unreadCount).toBe(2)
      expect(store.items[0].id).toBe('new-uuid')
      expect(store.items).toHaveLength(2)
    })

    it('increments unreadCount without mutating items when items have not been fetched', () => {
      const { store, handler } = subscribeAndGetHandler()
      expect(store.hasFetchedItems).toBe(false)
      store.unreadCount = 3

      const newNotification = createNotification({ id: 'new-uuid' })
      handler(newNotification)

      expect(store.unreadCount).toBe(4)
      expect(store.items).toEqual([])
    })

    it('does not prepend or increment twice for duplicate notification id', async () => {
      const { store, handler } = subscribeAndGetHandler()
      mockGetNotifications.mockResolvedValue({ data: [] })
      await store.fetchNotifications()
      store.unreadCount = 0

      const notification = createNotification({ id: 'dup-uuid' })
      handler(notification)
      handler(notification) // duplicate

      expect(store.unreadCount).toBe(1)
      expect(store.items).toHaveLength(1)
    })

    it('does not prepend notification already present from fetchNotifications', async () => {
      const { store, handler } = subscribeAndGetHandler()
      const existing = createNotification({ id: 'known-id' })
      mockGetNotifications.mockResolvedValue({ data: [existing] })
      await store.fetchNotifications()
      store.unreadCount = 1

      // WebSocket delivers the same notification
      handler(createNotification({ id: 'known-id' }))

      expect(store.unreadCount).toBe(1) // unchanged
      expect(store.items).toHaveLength(1) // unchanged
    })
  })

  describe('unsubscribe', () => {
    it('leaves Echo channel and resets state', () => {
      const store = useNotificationStore()
      const authStore = useAuthStore()
      authStore.setUser({ id: 42, email: 'test@test.com' } as never)
      authStore.setToken('test-token')
      store.subscribe()
      store.unreadCount = 5

      store.unsubscribe()

      expect(mockLeave).toHaveBeenCalledWith('App.Models.User.42')
      expect(store.unreadCount).toBe(0)
      expect(store.items).toEqual([])
      expect(store.isSubscribed).toBe(false)
      expect(store.hasFetchedItems).toBe(false)
    })
  })

  describe('$reset', () => {
    it('resets all state to defaults', async () => {
      mockGetNotifications.mockResolvedValue({ data: [createNotification()] })
      const store = useNotificationStore()
      store.unreadCount = 10
      await store.fetchNotifications()

      store.$reset()

      expect(store.unreadCount).toBe(0)
      expect(store.items).toEqual([])
      expect(store.isLoading).toBe(false)
      expect(store.isSubscribed).toBe(false)
      expect(store.hasFetchedItems).toBe(false)
    })
  })
})
