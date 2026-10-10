/**
 * Association statut métier -> ton du point d'état « Régie ».
 * Les libellés viennent des maps existantes (BookingStatusLabel, etc.) :
 * aucun wording n'est inventé ici.
 */
import {
  BookingStatus,
  BookingStatusLabel,
  type BookingStatusType,
} from '@/features/booking/types/booking'
import {
  MissionStatus,
  MissionStatusLabel,
  type MissionStatusType,
} from '@/features/mission/types/mission'
import {
  CandidatureStatus,
  CandidatureStatusLabel,
  type CandidatureStatusType,
} from '@/features/candidature/types'

export type StatusTone = 'pending' | 'progress' | 'success' | 'done' | 'danger' | 'neutral'

export interface StatusDot {
  tone: StatusTone
  label: string
}

export const bookingTones: Record<BookingStatusType, StatusTone> = {
  [BookingStatus.PENDING]: 'pending',
  [BookingStatus.ACCEPTED]: 'success',
  [BookingStatus.REFUSED]: 'danger',
  [BookingStatus.PAID]: 'success',
  [BookingStatus.COMMISSION_PAID]: 'success',
  [BookingStatus.IN_PROGRESS]: 'progress',
  [BookingStatus.CONFIRMED_BY_FACE]: 'progress',
  [BookingStatus.CONFIRMED_BY_PRODUCER]: 'progress',
  [BookingStatus.COMPLETED]: 'done',
  [BookingStatus.EXPIRED]: 'neutral',
  [BookingStatus.CANCELLED_BY_PRODUCER]: 'danger',
  [BookingStatus.CANCELLED_BY_FACE]: 'danger',
  [BookingStatus.NO_SHOW]: 'danger',
}

export const missionTones: Record<MissionStatusType, StatusTone> = {
  [MissionStatus.DRAFT]: 'neutral',
  [MissionStatus.PUBLISHED]: 'success',
  [MissionStatus.PENDING_PAYMENT]: 'pending',
  [MissionStatus.CLOSED]: 'pending',
  [MissionStatus.PENDING_ATTENDANCE_VALIDATION]: 'progress',
  [MissionStatus.COMPLETED]: 'done',
}

export const candidatureTones: Record<CandidatureStatusType, StatusTone> = {
  [CandidatureStatus.PENDING]: 'pending',
  [CandidatureStatus.ACCEPTED]: 'success',
  [CandidatureStatus.CONFIRMED]: 'success',
  [CandidatureStatus.IN_PROGRESS]: 'progress',
  [CandidatureStatus.COMPLETED]: 'done',
  [CandidatureStatus.REJECTED]: 'danger',
  [CandidatureStatus.CANCELLED]: 'neutral',
}

export function bookingStatusDot(status: BookingStatusType): StatusDot {
  return { tone: bookingTones[status] ?? 'neutral', label: BookingStatusLabel[status] ?? status }
}

export function missionStatusDot(status: MissionStatusType): StatusDot {
  return { tone: missionTones[status] ?? 'neutral', label: MissionStatusLabel[status] ?? status }
}

export function candidatureStatusDot(status: CandidatureStatusType): StatusDot {
  return {
    tone: candidatureTones[status] ?? 'neutral',
    label: CandidatureStatusLabel[status] ?? status,
  }
}
