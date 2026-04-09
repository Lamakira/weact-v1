import { ref, computed } from 'vue'
import { isAxiosError } from 'axios'
import type { Notification } from '../types'
import { notificationApi } from '../services/notificationApi'
import { getApiErrorMessage } from '@/features/auth/services/authApi'

/**
 * Composable for fetching and managing Face's notifications
 */
export function useNotifications() {
  const notifications = ref<Notification[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  // Pagination state
  const currentPage = ref(1)
  const lastPage = ref(1)
  const total = ref(0)

  // Unread count for badge
  const unreadCount = ref(0)

  // Request tracking to prevent race conditions
  let currentRequestId = 0

  // Computed
  const hasNextPage = computed(() => currentPage.value < lastPage.value)
  const hasPrevPage = computed(() => currentPage.value > 1)
  const isEmpty = computed(() => notifications.value.length === 0 && !isLoading.value)
  const hasUnread = computed(() => unreadCount.value > 0)

  /**
   * Fetch notifications with current page
   */
  async function fetchNotifications(page: number = 1): Promise<void> {
    const requestId = ++currentRequestId
    isLoading.value = true
    error.value = null

    try {
      const response = await notificationApi.getNotifications(page)

      // Ignore stale responses from previous requests
      if (requestId !== currentRequestId) return

      notifications.value = response.data
      currentPage.value = response.meta.current_page
      lastPage.value = response.meta.last_page
      total.value = response.meta.total
    } catch (err: unknown) {
      // Ignore errors from stale requests
      if (requestId !== currentRequestId) return

      if (isAxiosError(err)) {
        if (err.response?.status === 401) {
          error.value = 'Veuillez vous connecter pour voir vos notifications'
        } else if (err.response?.status === 403) {
          error.value = 'Accès réservé aux Faces'
        } else {
          error.value = getApiErrorMessage(err)
        }
      } else {
        error.value = 'Une erreur inattendue est survenue'
      }
    } finally {
      if (requestId === currentRequestId) {
        isLoading.value = false
      }
    }
  }

  /**
   * Fetch unread count for badge
   */
  async function fetchUnreadCount(): Promise<void> {
    try {
      const response = await notificationApi.getUnreadCount()
      unreadCount.value = response.count
    } catch {
      // Silently fail - badge will just not update
    }
  }

  /**
   * Mark a notification as read
   */
  async function markAsRead(notificationId: string): Promise<boolean> {
    try {
      await notificationApi.markAsRead(notificationId)

      // Update local state
      const notification = notifications.value.find((n) => n.id === notificationId)
      if (notification && !notification.read_at) {
        notification.read_at = new Date().toISOString()
        unreadCount.value = Math.max(0, unreadCount.value - 1)
      }

      return true
    } catch {
      return false
    }
  }

  /**
   * Mark all notifications as read
   */
  async function markAllAsRead(): Promise<boolean> {
    try {
      await notificationApi.markAllAsRead()

      // Update local state
      const now = new Date().toISOString()
      notifications.value.forEach((n) => {
        if (!n.read_at) {
          n.read_at = now
        }
      })
      unreadCount.value = 0

      return true
    } catch {
      return false
    }
  }

  /**
   * Go to next page
   */
  async function nextPage(): Promise<void> {
    if (hasNextPage.value) {
      await fetchNotifications(currentPage.value + 1)
    }
  }

  /**
   * Go to previous page
   */
  async function prevPage(): Promise<void> {
    if (hasPrevPage.value) {
      await fetchNotifications(currentPage.value - 1)
    }
  }

  /**
   * Refresh current page and unread count
   */
  async function refresh(): Promise<void> {
    await Promise.all([fetchNotifications(currentPage.value), fetchUnreadCount()])
  }

  return {
    // State
    notifications,
    isLoading,
    error,
    // Pagination
    currentPage,
    lastPage,
    total,
    hasNextPage,
    hasPrevPage,
    isEmpty,
    // Unread
    unreadCount,
    hasUnread,
    // Actions
    fetchNotifications,
    fetchUnreadCount,
    markAsRead,
    markAllAsRead,
    nextPage,
    prevPage,
    refresh,
  }
}
