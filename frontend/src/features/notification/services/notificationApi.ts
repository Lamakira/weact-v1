import apiClient from '@/services/apiClient'
import type {
  NotificationListResponse,
  NotificationResponse,
  UnreadCountResponse,
  MarkAllAsReadResponse,
} from '../types'

/**
 * Notification API service
 * Endpoints for Face users to manage their notifications
 */
export const notificationApi = {
  /**
   * Get paginated list of notifications
   * @param page Page number (default 1)
   * @returns Paginated notifications
   */
  async getNotifications(page: number = 1): Promise<NotificationListResponse> {
    const response = await apiClient.get<NotificationListResponse>(
      `/me/notifications?page=${page}`,
    )
    return response.data
  },

  /**
   * Get the count of unread notifications
   * @returns Unread count object
   */
  async getUnreadCount(): Promise<UnreadCountResponse> {
    const response = await apiClient.get<UnreadCountResponse>('/me/notifications/unread-count')
    return response.data
  },

  /**
   * Mark a notification as read
   * @param notificationId The notification ID to mark as read
   * @returns Updated notification with success message
   */
  async markAsRead(notificationId: string): Promise<NotificationResponse> {
    const response = await apiClient.post<NotificationResponse>(
      `/me/notifications/${notificationId}/read`,
    )
    return response.data
  },

  /**
   * Mark all notifications as read
   * @returns Success message
   */
  async markAllAsRead(): Promise<MarkAllAsReadResponse> {
    const response = await apiClient.post<MarkAllAsReadResponse>('/me/notifications/read-all')
    return response.data
  },
}
