import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { Notification } from '@/features/notification/types'
import { notificationApi } from '@/features/notification/services/notificationApi'
import { echo } from '@/plugins/echo'
import { useAuthStore } from '@/stores/auth'
import { getAuthToken } from '@/services/apiClient'
import { getXsrfTokenFromCookie } from '@/utils/csrf'

interface EchoChannel {
  listen: (event: string, callback: (payload: Notification) => void) => EchoChannel
  error?: (callback: () => void) => EchoChannel
  subscribed?: (callback: () => void) => EchoChannel
}

interface EchoConnection {
  bind: (event: 'connected', callback: () => void) => void
  unbind: (event: 'connected', callback: () => void) => void
}

let focusHandler: (() => void) | null = null
let reconnectHandler: (() => void) | null = null
let safetyPollIntervalId: ReturnType<typeof setInterval> | null = null

function getEchoConnection(): EchoConnection | null {
  const connection = echo.connector?.pusher?.connection
  if (
    !connection
    || typeof connection.bind !== 'function'
    || typeof connection.unbind !== 'function'
  ) {
    return null
  }

  return connection as EchoConnection
}

export const useNotificationStore = defineStore('notification', () => {
  // State
  const unreadCount = ref(0)
  const items = ref<Notification[]>([])
  const isLoading = ref(false)
  const isSubscribed = ref(false)

  // Internal lifecycle state
  const hasFetchedItems = ref(false)
  const isSubscribing = ref(false)
  const knownIds = new Set<string>()

  // Actions
  async function fetchUnreadCount(): Promise<void> {
    try {
      const response = await notificationApi.getUnreadCount()
      unreadCount.value = response.count
    } catch (error) {
      console.error('[NotificationStore] Failed to fetch unread count:', error)
    }
  }

  async function fetchNotifications(page: number = 1): Promise<boolean> {
    isLoading.value = true
    try {
      const response = await notificationApi.getNotifications(page)
      items.value = response.data
      hasFetchedItems.value = true

      // Hydrate knownIds from fetched list
      knownIds.clear()
      for (const item of response.data) {
        knownIds.add(item.id)
      }
      return true
    } catch (error) {
      console.error('[NotificationStore] Failed to fetch notifications:', error)
      return false
    } finally {
      isLoading.value = false
    }
  }

  async function markAsRead(id: string): Promise<boolean> {
    // Optimistic local update
    const notification = items.value.find((n) => n.id === id)
    const previousReadAt = notification?.read_at ?? null
    const didOptimisticUpdate = !!notification && !notification.read_at

    if (notification && !notification.read_at) {
      notification.read_at = new Date().toISOString()
      unreadCount.value = Math.max(0, unreadCount.value - 1)
    }

    try {
      await notificationApi.markAsRead(id)
      return true
    } catch (error) {
      console.error('[NotificationStore] Failed to mark as read:', error)
      // Revert optimistic update on failure
      if (notification && didOptimisticUpdate) {
        notification.read_at = previousReadAt
        unreadCount.value += 1
      }
      return false
    }
  }

  async function markAllAsRead(): Promise<boolean> {
    // Optimistic local update
    const previousUnreadCount = unreadCount.value
    const previousReadAts: Array<string | null> = items.value.map((n) => n.read_at ?? null)
    const now = new Date().toISOString()
    items.value.forEach((n) => {
      if (!n.read_at) {
        n.read_at = now
      }
    })
    unreadCount.value = 0

    try {
      await notificationApi.markAllAsRead()
      return true
    } catch (error) {
      console.error('[NotificationStore] Failed to mark all as read:', error)
      // Revert optimistic update on failure
      items.value.forEach((n, i) => {
        n.read_at = previousReadAts[i] ?? null
      })
      unreadCount.value = previousUnreadCount
      return false
    }
  }

  function startFocusListener(): void {
    if (typeof window === 'undefined' || focusHandler) return

    focusHandler = () => {
      void fetchUnreadCount()

      if (hasFetchedItems.value) {
        void fetchNotifications()
      }
    }

    window.addEventListener('focus', focusHandler)
  }

  function stopFocusListener(): void {
    if (typeof window === 'undefined' || !focusHandler) return

    window.removeEventListener('focus', focusHandler)
    focusHandler = null
  }

  function startReconnectListener(): void {
    const connection = getEchoConnection()
    if (!connection || reconnectHandler) return

    reconnectHandler = () => {
      void fetchUnreadCount()

      if (hasFetchedItems.value) {
        void fetchNotifications()
      }
    }

    connection.bind('connected', reconnectHandler)
  }

  function stopReconnectListener(): void {
    if (!reconnectHandler) return

    const connection = getEchoConnection()
    if (connection) {
      connection.unbind('connected', reconnectHandler)
    }

    reconnectHandler = null
  }

  function startSafetyPoll(): void {
    if (safetyPollIntervalId) return

    safetyPollIntervalId = setInterval(() => {
      void fetchUnreadCount()
    }, 120_000)
  }

  function stopSafetyPoll(): void {
    if (!safetyPollIntervalId) return

    clearInterval(safetyPollIntervalId)
    safetyPollIntervalId = null
  }

  function subscribe(): void {
    if (isSubscribed.value || isSubscribing.value) return

    const authStore = useAuthStore()
    const userId = authStore.user?.id
    if (!userId) return

    isSubscribing.value = true
    startFocusListener()
    startSafetyPoll()

    // Refresh Echo auth headers from current token/cookie
    const token = getAuthToken()
    const xsrfToken = getXsrfTokenFromCookie()
    echo.connector.options.auth = {
      headers: {
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
        Accept: 'application/json',
      },
    }

    try {
      const channel = echo.private(`App.Models.User.${userId}`) as EchoChannel

      channel
      .listen('.notification.created', (event: Notification) => {
        // Deduplication: ignore if already known
        if (knownIds.has(event.id)) return

        knownIds.add(event.id)
        unreadCount.value += 1

        // Only prepend to items if the list has already been fetched
        if (hasFetchedItems.value) {
          items.value.unshift(event)
        }
      })

      startReconnectListener()

      channel.error?.(() => {
        isSubscribing.value = false
        isSubscribed.value = false
        stopFocusListener()
        stopReconnectListener()
        stopSafetyPoll()

        try {
          echo.leave(`App.Models.User.${userId}`)
        } catch {
          // Ignore cleanup errors after a failed subscription attempt
        }
      })

      if (typeof channel.subscribed === 'function') {
        channel.subscribed(() => {
          isSubscribing.value = false
          isSubscribed.value = true
        })
      } else {
        isSubscribing.value = false
        isSubscribed.value = true
      }
    } catch (error) {
      isSubscribing.value = false
      isSubscribed.value = false
      stopFocusListener()
      stopReconnectListener()
      stopSafetyPoll()
      console.error('[NotificationStore] Failed to subscribe to notifications channel:', error)
    }
  }

  function unsubscribe(): void {
    stopFocusListener()
    stopReconnectListener()
    stopSafetyPoll()

    const authStore = useAuthStore()
    const userId = authStore.user?.id
    if (userId) {
      echo.leave(`App.Models.User.${userId}`)
    }

    $reset()
  }

  function $reset(): void {
    unreadCount.value = 0
    items.value = []
    isLoading.value = false
    isSubscribed.value = false
    hasFetchedItems.value = false
    isSubscribing.value = false
    knownIds.clear()
  }

  return {
    // State
    unreadCount,
    items,
    isLoading,
    isSubscribed,
    hasFetchedItems,
    // Actions
    fetchUnreadCount,
    fetchNotifications,
    markAsRead,
    markAllAsRead,
    subscribe,
    unsubscribe,
    $reset,
  }
})
