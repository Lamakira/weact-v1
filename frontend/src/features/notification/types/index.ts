/**
 * Notification feature types
 */

// Notification types enum
export const NotificationType = {
  // Candidature domain (existing — do not remove)
  CANDIDATURE_ACCEPTED: 'candidature_accepted',
  CANDIDATURE_REJECTED: 'candidature_rejected',
  // Booking domain (b6-2)
  BOOKING_RECEIVED: 'booking_received',
  BOOKING_ACCEPTED: 'booking_accepted',
  BOOKING_REFUSED: 'booking_refused',
  BOOKING_PAID: 'booking_paid',
  BOOKING_CONFIRMATION_PENDING: 'booking_confirmation_pending',
  BOOKING_WALLET_CREDITED: 'booking_wallet_credited',
  BOOKING_COMPLETED: 'booking_completed',
  BOOKING_CANCELLED: 'booking_cancelled',
} as const

export type NotificationTypeValue = (typeof NotificationType)[keyof typeof NotificationType]

// Notification data structure (varies by type — fields are optional to support both domains)
export interface NotificationData {
  message: string
  // Candidature domain fields (optional)
  mission_title?: string
  candidature_id?: number
  // Booking domain fields (optional)
  booking_id?: number
  url?: string
}

// Notification from API
export interface Notification {
  id: number
  type: string
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
