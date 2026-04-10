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
  BOOKING_EXPIRED: 'booking_expired',
  BOOKING_PAYMENT_REMINDER: 'booking_payment_reminder',
  BOOKING_RATING_RECEIVED: 'booking_rating_received',
  // Mission domain
  MISSION_CLOSED_PENDING_CANDIDATURE: 'mission_closed_pending_candidature',
  SHOOTING_DAY_REMINDER: 'shooting_day_reminder',
} as const

// Notification data structure (varies by type — fields are optional to support both domains)
export interface NotificationData {
  message: string
  // Candidature domain fields (optional)
  mission_title?: string
  mission_id?: string
  candidature_id?: string
  // Booking domain fields (optional)
  booking_id?: string
  score?: number
  url?: string
}

// Notification from API
export interface Notification {
  id: string
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
