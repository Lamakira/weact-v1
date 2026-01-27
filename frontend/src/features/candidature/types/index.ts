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
