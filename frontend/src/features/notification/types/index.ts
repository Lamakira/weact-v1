/**
 * Notification feature types
 */

// Notification types enum
export const NotificationType = {
  CANDIDATURE_ACCEPTED: 'candidature_accepted',
  CANDIDATURE_REJECTED: 'candidature_rejected',
} as const

export type NotificationTypeValue = (typeof NotificationType)[keyof typeof NotificationType]

// Notification data structure (varies by type)
export interface NotificationData {
  mission_title: string
  candidature_id: number
  message: string
}

// Notification from API
export interface Notification {
  id: number
  type: NotificationTypeValue
  data: NotificationData
  read_at: string | null
  created_at: string
}

// Paginated notifications response
export interface NotificationListResponse {
  data: Notification[]
  links: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

// Single notification response (for mark as read)
export interface NotificationResponse {
  data: Notification
  message?: string
}

// Unread count response
export interface UnreadCountResponse {
  count: number
}

// Mark all as read response
export interface MarkAllAsReadResponse {
  message: string
}
