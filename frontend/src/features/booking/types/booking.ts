/**
 * Booking feature types
 */

// Booking status enum
export const BookingStatus = {
  PENDING: 'pending',
  ACCEPTED: 'accepted',
  REFUSED: 'refused',
  PAID: 'paid',
  IN_PROGRESS: 'in_progress',
  CONFIRMED_BY_FACE: 'confirmed_by_face',
  CONFIRMED_BY_PRODUCER: 'confirmed_by_producer',
  COMPLETED: 'completed',
  CANCELLED_BY_PRODUCER: 'cancelled_by_producer',
  CANCELLED_BY_FACE: 'cancelled_by_face',
} as const

export type BookingStatusType = (typeof BookingStatus)[keyof typeof BookingStatus]

// Booking status labels (French)
export const BookingStatusLabel: Record<BookingStatusType, string> = {
  [BookingStatus.PENDING]: 'En attente',
  [BookingStatus.ACCEPTED]: 'Acceptée',
  [BookingStatus.REFUSED]: 'Refusée',
  [BookingStatus.PAID]: 'Payée',
  [BookingStatus.IN_PROGRESS]: 'En cours',
  [BookingStatus.CONFIRMED_BY_FACE]: 'Confirmée par la Face',
  [BookingStatus.CONFIRMED_BY_PRODUCER]: 'Confirmée par le Producteur',
  [BookingStatus.COMPLETED]: 'Terminée',
  [BookingStatus.CANCELLED_BY_PRODUCER]: 'Annulée par le Producteur',
  [BookingStatus.CANCELLED_BY_FACE]: 'Annulée par la Face',
}

// Commission rate (mirrors backend BookingPricing VO)
export const COMMISSION_RATE = 0.15

// Face userable data nested in BookingUser
export interface BookingFaceUserable {
  id: number
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
  id: number
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
  id: number
  face_id: number
  producer_id: number
  status: BookingStatusType
  status_label: string
  date_debut: string
  date_fin: string
  duree_heures: number
  type_contenu: string
  message: string | null
  tarif_base: number
  montant_total_producteur: number
  montant_face_recoit: number
  cancellation_reason: string | null
  fedapay_transaction_id: number | null
  payment_mode: string | null
  face?: BookingUser
  producer?: BookingUser
  can_accept: boolean
  can_refuse: boolean
  can_pay: boolean
  created_at: string
  updated_at: string
}

// Data for creating a new booking
export interface CreateBookingData {
  face_id: number
  date_debut: string
  date_fin: string
  duree_heures: number
  type_contenu: string
  message?: string
}

// Booking API response
export interface BookingResponse {
  data: Booking
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
export interface BookingPricingPreview {
  tarifBase: number
  producerCommission: number
  totalProducerPays: number
  faceCommission: number
  faceReceives: number
}

// Payment mode types
export type PaymentMode = 'mtn_open' | 'moov' | 'celtiis'

export interface PaymentModeOption {
  label: string
  value: PaymentMode
  icon: string
}

export const PAYMENT_MODES: PaymentModeOption[] = [
  { label: 'MTN MoMo', value: 'mtn_open', icon: '📱' },
  { label: 'Moov Money', value: 'moov', icon: '📱' },
  { label: 'Celtiis', value: 'celtiis', icon: '📱' },
]

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
