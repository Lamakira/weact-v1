/**
 * Candidature feature types
 */

// Candidature status enum
export const CandidatureStatus = {
  PENDING: 'pending',
  ACCEPTED: 'accepted',
  CONFIRMED: 'confirmed',
  IN_PROGRESS: 'in_progress',
  COMPLETED: 'completed',
  REJECTED: 'rejected',
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
}

// Candidature data from API
export interface Candidature {
  id: number
  mission_id: number
  face_id: number
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
  id: number
  titre: string
  date_tournage: string
  lieu: string
  budget: number
}

// Producer summary for candidature list (nested in FaceCandidature)
export interface ProducerSummary {
  id: number
  display_name: string
  type: 'agency' | 'particulier'
  profile_photo_url: string | null
}

// Face candidature for list view (includes mission and producer summary)
export interface FaceCandidature {
  id: number
  status: CandidatureStatusType
  status_label: string
  message_motivation: string | null
  created_at: string
  mission: MissionSummary
  producer: ProducerSummary
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
}

// Status filter option
export interface StatusFilterOption {
  value: CandidatureStatusType | ''
  label: string
}
