/**
 * Booking feature types
 */

import type { Shipment } from '@/components/ugc'

// Booking status enum
export const BookingStatus = {
  PENDING: 'pending',
  ACCEPTED: 'accepted',
  REFUSED: 'refused',
  PAID: 'paid',
  COMMISSION_PAID: 'commission_paid',
  IN_PROGRESS: 'in_progress',
  CONFIRMED_BY_FACE: 'confirmed_by_face',
  CONFIRMED_BY_PRODUCER: 'confirmed_by_producer',
  COMPLETED: 'completed',
  EXPIRED: 'expired',
  CANCELLED_BY_PRODUCER: 'cancelled_by_producer',
  CANCELLED_BY_FACE: 'cancelled_by_face',
  NO_SHOW: 'no_show',
} as const

export type BookingStatusType = (typeof BookingStatus)[keyof typeof BookingStatus]

// Booking status labels (French)
export const BookingStatusLabel: Record<BookingStatusType, string> = {
  [BookingStatus.PENDING]: 'En attente',
  [BookingStatus.ACCEPTED]: 'Acceptée',
  [BookingStatus.REFUSED]: 'Refusée',
  [BookingStatus.PAID]: 'Payée',
  [BookingStatus.COMMISSION_PAID]: 'Commission payée',
  [BookingStatus.IN_PROGRESS]: 'En cours',
  [BookingStatus.CONFIRMED_BY_FACE]: 'Confirmée par la Face',
  [BookingStatus.CONFIRMED_BY_PRODUCER]: 'Confirmée par le Producteur',
  [BookingStatus.COMPLETED]: 'Terminée',
  [BookingStatus.EXPIRED]: 'Expiré',
  [BookingStatus.CANCELLED_BY_PRODUCER]: 'Annulée par le Producteur',
  [BookingStatus.CANCELLED_BY_FACE]: 'Annulée par la Face',
  [BookingStatus.NO_SHOW]: 'Absence signalée',
}

// Commission rate (mirrors backend BookingPricing VO)
const COMMISSION_RATE = 0.10

export const PRODUCER_CANCELLATION_REASONS = [
  { value: 'schedule_conflict', label: "Conflit d'agenda" },
  { value: 'acceptance_expired', label: "Durée d'acceptation dépassée" },
  { value: 'other', label: 'Autre raison' },
] as const

export const FACE_CANCELLATION_REASONS = [
  { value: 'schedule_conflict', label: "Conflit d'agenda" },
  { value: 'other', label: 'Autre raison' },
] as const

const LEGACY_CANCELLATION_REASON_LABELS: Record<string, string> = {
  price_disagreement: 'Désaccord sur le prix',
}

export type CancellationReasonValue = (typeof PRODUCER_CANCELLATION_REASONS)[number]['value']

export interface BookingCancellationPayload {
  reason: CancellationReasonValue
  customReason?: string
}

// Statuses where a Producer can cancel
export const CANCELLABLE_BY_PRODUCER_STATUSES: BookingStatusType[] = [
  BookingStatus.PENDING,
  BookingStatus.ACCEPTED,
  BookingStatus.PAID,
]

// Statuses where a Face can cancel
export const CANCELLABLE_BY_FACE_STATUSES: BookingStatusType[] = [
  BookingStatus.ACCEPTED,
  BookingStatus.PAID,
]

export function getCancellationReasonLabel(reason: string | null | undefined): string {
  if (!reason) return ''
  const match = PRODUCER_CANCELLATION_REASONS.find((item) => item.value === reason)
    ?? FACE_CANCELLATION_REASONS.find((item) => item.value === reason)

  return match?.label ?? LEGACY_CANCELLATION_REASON_LABELS[reason] ?? reason
}

export interface BookingRating {
  id: number
  booking_id: number
  score: number
  comment: string | null
  created_at: string
  rater: { id: number; name: string; photo_url: string | null }
  rated: { id: number; name: string; photo_url: string | null }
}

// Face userable data nested in BookingUser
export interface BookingFaceUserable {
  id: string
  nom: string
  prenom: string
  username: string
  profile_photo_url: string | null
  thumbnail_url: string | null
  average_rating: number | null
  ratings_count: number
}

// Producer userable data nested in BookingUser
export interface BookingProducerUserable {
  id: string
  display_name: string
  profile_photo_url: string | null
  thumbnail_url: string | null
  average_rating: number | null
  ratings_count: number
}

// User embedded in Booking response
export interface BookingUser {
  id: number
  email: string
  userable_type: string
  userable_id: number
  userable?: BookingFaceUserable | BookingProducerUserable
}

// Booking data from API
export interface Booking {
  id: string
  realtime_channel_key: number
  face_id: number
  producer_id: number
  status: BookingStatusType
  status_label: string
  // null for UGC dotations (no shoot date / duration — the Face films at home)
  date_debut: string | null
  date_fin: string | null
  duree_heures: number | null
  type_contenu: string
  // UGC fields (null for non-UGC bookings) — mirrors BookingResource (story 1.1)
  type_compensation: string | null
  type_compensation_label: string | null
  nom_produit: string | null
  valeur_produit: number | null
  nombre_videos: number | null
  montant_remuneration: number | null
  commission_ugc: number | null
  lieu: string | null
  message: string | null
  tarif_base: number
  montant_total_producteur: number
  montant_face_recoit: number
  cancellation_reason: string | null
  custom_cancellation_reason: string | null
  fedapay_transaction_id: number | null
  payment_mode: string | null
  accepted_at: string | null
  face?: BookingUser
  producer?: BookingUser
  can_accept: boolean
  can_refuse: boolean
  can_pay: boolean
  can_rate: boolean
  my_rating: BookingRating | null
  // Expédition UGC (story 3.2) — `whenLoaded` backend : la clé est OMISE quand la
  // relation n'est pas chargée ou n'existe pas (jamais `null` explicite).
  shipment?: Shipment
  created_at: string
  updated_at: string
}

// Data for creating a new booking
export interface CreateBookingData {
  face_id: string
  // Shoot fields are omitted for UGC dotations (sent only for cash bookings)
  date_debut?: string
  date_fin?: string
  duree_heures?: number
  type_contenu: string
  lieu?: string
  message?: string
  // UGC fields (sent only when type_contenu === 'UGC')
  type_compensation?: 'product' | 'hybrid'
  nom_produit?: string
  valeur_produit?: number
  nombre_videos?: number
  montant_remuneration?: number
}

// Booking API response
export interface BookingResponse {
  data: Booking
  message?: string
}

export interface BookingRatingResponse {
  data: BookingRating
  message?: string
}

// Booking filter status for list view
export type BookingFilterStatus = '' | 'pending' | 'active' | 'completed' | 'cancelled'

// Booking filter label mapping (French)
export const BookingFilterLabel: Record<BookingFilterStatus, string> = {
  '': 'Tous',
  pending: 'En attente',
  active: 'Actifs',
  completed: 'Terminés',
  cancelled: 'Annulés',
}

// Paginated booking list response
export interface BookingListResponse {
  data: Booking[]
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

// Booking create result
export interface BookingCreateResult {
  success: boolean
  data?: Booking
  errors?: Record<string, string[]>
  message?: string
  errorCode?: string | null
}

// Pricing preview (calculated client-side for UX)
interface BookingPricingPreview {
  tarifBase: number
  producerCommission: number
  totalProducerPays: number
  faceCommission: number
  faceReceives: number
}

// Payment status for frontend tracking
export type PaymentStatus = 'idle' | 'waiting' | 'confirmed' | 'failed'

/**
 * Calculate booking pricing preview (mirrors backend BookingPricing VO)
 */
export function calculatePricingPreview(tarifBase: number): BookingPricingPreview {
  const producerCommission = Math.round(tarifBase * COMMISSION_RATE)
  const faceCommission = Math.round(tarifBase * COMMISSION_RATE)
  return {
    tarifBase,
    producerCommission,
    totalProducerPays: tarifBase + producerCommission,
    faceCommission,
    faceReceives: tarifBase - faceCommission,
  }
}

// ============================================
// CHAT TYPES
// ============================================

// Message from REST API (includes is_own_message computed by server)
export interface BookingMessage {
  id: number
  booking_id: number
  sender_id: number
  sender_name: string
  content: string
  is_own_message: boolean
  created_at: string // ISO 8601
}

// Message from WebSocket broadcast (no is_own_message — computed client-side)
export interface BookingMessageBroadcast {
  id: number
  booking_id: number
  sender_id: number
  sender_name: string
  content: string
  created_at: string // ISO 8601
}

// Paginated message list from REST API
export interface BookingMessageListResponse {
  data: BookingMessage[]
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

// Single message creation response
export interface BookingMessageResponse {
  data: BookingMessage
  message: string
}

// Chat-eligible statuses (can VIEW messages — includes completed for read-only)
export const CHAT_VIEW_STATUSES: BookingStatusType[] = [
  BookingStatus.PAID,
  BookingStatus.IN_PROGRESS,
  BookingStatus.CONFIRMED_BY_FACE,
  BookingStatus.CONFIRMED_BY_PRODUCER,
  BookingStatus.COMPLETED,
]

// Chat-eligible statuses for UGC bookings — miroir de BookingPolicy::viewMessages
// branche UGC (3.1 AC8) : visible dès Accepted, lecture seule à Completed.
// NE PAS élargir CHAT_VIEW_STATUSES (cash) : la policy backend refuse accepted cash.
export const UGC_CHAT_VIEW_STATUSES: BookingStatusType[] = [
  BookingStatus.ACCEPTED,
  BookingStatus.COMPLETED,
]
