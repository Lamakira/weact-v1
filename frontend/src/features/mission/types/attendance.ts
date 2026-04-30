/**
 * Attendance feature types (FIX-26.7)
 *
 * Mirrors the backend payload returned by the FIX-26.4 Producer endpoints
 * `GET /producer/missions/{uuid}/attendance-form` and
 * `POST /producer/missions/{uuid}/validate-attendance`.
 */

export const AttendanceStatus = {
  PENDING: 'pending',
  PRESENT: 'present',
  ABSENT: 'absent',
  DISPUTED: 'disputed',
} as const
export type AttendanceStatusType = (typeof AttendanceStatus)[keyof typeof AttendanceStatus]

export const EscrowStatus = {
  PENDING: 'pending',
  LOCKED: 'locked',
  RELEASED: 'released',
  REFUNDED: 'refunded',
} as const
export type EscrowStatusType = (typeof EscrowStatus)[keyof typeof EscrowStatus]

export interface AttendanceEntry {
  id: number
  face: { id: string; display_name: string; profile_photo_url: string | null }
  montant_face_recoit: number
  attendance_status: AttendanceStatusType
  attendance_status_label: string
  escrow_status: EscrowStatusType
  escrow_status_label: string
  released_at: string | null
  refunded_at: string | null
  notified_at: string | null
}

export interface AttendanceFormResponse {
  data: {
    mission: {
      id: string
      titre: string
      status: string
      status_label: string
      date_tournage: string | null
    }
    payment: { montant_total_producteur: number; nombre_faces_retenues: number }
    entries: AttendanceEntry[]
  }
}

export type AttendanceDecision = 'present' | 'absent'

export interface ValidateAttendancePayload {
  entries: Array<{ entry_id: number; status: AttendanceDecision }>
}

export interface ValidateAttendanceResponse {
  data: {
    mission: {
      id: string
      titre: string
      status: string
      status_label: string
      date_tournage: string | null
    }
    entries: AttendanceEntry[]
  }
  message: string
}
