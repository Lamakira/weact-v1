/**
 * Candidature feature types
 */

import type { Shipment } from '@/components/ugc'

// Candidature status enum
export const CandidatureStatus = {
  PENDING: 'pending',
  ACCEPTED: 'accepted',
  CONFIRMED: 'confirmed',
  IN_PROGRESS: 'in_progress',
  COMPLETED: 'completed',
  REJECTED: 'rejected',
  CANCELLED: 'cancelled',
} as const

export type CandidatureStatusType = (typeof CandidatureStatus)[keyof typeof CandidatureStatus]

// Candidature status labels (French)
export const CandidatureStatusLabel: Record<CandidatureStatusType, string> = {
  [CandidatureStatus.PENDING]: 'En attente',
  [CandidatureStatus.ACCEPTED]: 'Acceptée',
  [CandidatureStatus.CONFIRMED]: 'Confirmée',
  [CandidatureStatus.IN_PROGRESS]: 'En cours',
  [CandidatureStatus.COMPLETED]: 'Terminée',
  [CandidatureStatus.REJECTED]: 'Refusée',
  [CandidatureStatus.CANCELLED]: 'Annulée',
}

// Candidature data from API
export interface Candidature {
  id: string
  mission_id: string
  face_id: string
  status: CandidatureStatusType
  status_label: string
  message_motivation: string | null
  created_at: string
  updated_at: string
}

// Candidature API response (single)
export interface CandidatureResponse {
  data: Candidature
  message?: string
}

// Apply to mission request data
export interface ApplyToMissionData {
  message_motivation?: string
}

// Apply to mission result
export interface ApplyToMissionResult {
  success: boolean
  data?: Candidature
  error?: {
    code: string
    message: string
  }
}

// Mission summary for candidature list (nested in FaceCandidature)
export interface MissionSummary {
  id: string
  titre: string
  date_tournage: string | null // null pour l'UGC (champs tournage masqués, ugc-8-1)
  lieu: string | null // idem
  budget: number
  /**
   * Discriminant UGC (D-8.3.a) : absent/null = standard, 'product'|'hybrid' = UGC.
   * Optionnel volontairement : le backend l'envoie toujours (null pour le cash),
   * mais l'optionalité évite de casser les fixtures `FaceCandidature` des specs
   * existants — le runtime `!= null` traite absent ET null comme cash.
   */
  type_compensation?: string | null
}

// Producer summary for candidature list (nested in FaceCandidature)
export interface ProducerSummary {
  id: string
  display_name: string
  type: 'agency' | 'particulier'
  profile_photo_url: string | null
}

// Face candidature for list view (includes mission and producer summary)
export interface FaceCandidature {
  id: string
  status: CandidatureStatusType
  status_label: string
  message_motivation: string | null
  created_at: string
  mission: MissionSummary
  producer: ProducerSummary
  conversation_id: string | null
  /** Expédition UGC (whenLoaded — clé OMISE hors deal UGC expédié, 3.3 AC8). */
  shipment?: Shipment
}

// Paginated candidatures response for Face list view
export interface FaceCandidatureListResponse {
  data: FaceCandidature[]
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

// Status badge color mapping
export const CandidatureStatusColor: Record<CandidatureStatusType, string> = {
  [CandidatureStatus.PENDING]: 'yellow',
  [CandidatureStatus.ACCEPTED]: 'blue',
  [CandidatureStatus.CONFIRMED]: 'green',
  [CandidatureStatus.IN_PROGRESS]: 'purple',
  [CandidatureStatus.COMPLETED]: 'emerald',
  [CandidatureStatus.REJECTED]: 'red',
  [CandidatureStatus.CANCELLED]: 'gray',
}

// Face summary for Producer candidature list (nested in ProducerCandidature)
export interface FaceSummary {
  id: string
  display_name: string
  profile_photo_url: string | null
  category: string | null
  city: string | null
  tarif_horaire: number | null
  tarif_journalier: number | null
  has_elite_badge: boolean
}

// Producer candidature for list view (includes face summary)
export interface ProducerCandidature {
  id: string
  status: CandidatureStatusType
  status_label: string
  message_motivation: string | null
  created_at: string
  conversation_id: string | null
  face: FaceSummary
  // Expédition UGC (story 3.2) — `whenLoaded` backend : clé OMISE si absente.
  shipment?: Shipment
}

// Paginated candidatures response for Producer list view
export interface ProducerCandidatureListResponse {
  data: ProducerCandidature[]
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

// Face photo for photo gallery
export interface FacePhoto {
  id: string
  photo_url: string
  thumbnail_url: string
  position: number
}

// Face experience for experience list
export interface FaceExperience {
  id: string
  titre: string
  description: string | null
  date_debut: string | null
  date_fin: string | null
  is_ongoing: boolean
  formatted_period: string | null
}

// Candidate full profile for Producer view (complete Face data)
export interface CandidateFullProfile {
  id: string
  nom: string
  prenom: string
  username: string | null
  sexe: string | null
  sexe_label: string | null
  age: number | null
  nationalite: string | null
  langues: string[] | null
  profile_photo_url: string | null
  thumbnail_url: string | null
  presentation_video_url: string | null
  presentation_video_thumbnail_url: string | null
  acting_video_url: string | null
  acting_video_thumbnail_url: string | null
  bio: string | null
  ville: string | null
  pays: string | null
  formatted_location: string | null
  taille: number | null
  poids: number | null
  categories: Array<{ value: string; label: string }>
  niches: Array<{ value: string; label: string }>
  tarif_horaire: number | null
  tarif_journalier: number | null
  formatted_tarif_horaire: string | null
  formatted_tarif_journalier: string | null
  is_available: boolean
  availability_badge: string
  availability_badge_color: string
  profile_completion_percentage: number
  profile_completion_is_complete: boolean
  average_rating: number | null
  ratings_count: number
  experiences: FaceExperience[]
  has_elite_badge: boolean
  photos: FacePhoto[]
}

// API response for candidate full profile
export interface CandidateFullProfileResponse {
  data: CandidateFullProfile
}
