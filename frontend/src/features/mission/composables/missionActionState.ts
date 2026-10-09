import type { Mission } from '../types'

export interface MissionActionState {
  isUgc: boolean
  canEdit: boolean
  canPayCommission: boolean
  canDelete: boolean
  canClose: boolean
  canReopen: boolean
  canComplete: boolean
  canValidateAttendance: boolean
}

/**
 * Which actions a producer can take on a mission (same rules as MissionCard).
 *
 * - A UGC mission always carries a `commission_ugc` (null for standard missions).
 * - UGC missions are never editable (backend `UpdateMissionRequest` rejects type_mission='ugc').
 * - A STANDARD mission also sits in `pending_payment` during its escrow checkout, so the
 *   commission CTA must be guarded on `isUgc` (otherwise it 403s server-side).
 * - Without a verified email only delete stays available.
 */
export function getMissionActionState(mission: Mission, emailVerified: boolean): MissionActionState {
  const isUgc = mission.commission_ugc !== null && mission.commission_ugc !== undefined

  return {
    isUgc,
    canEdit: emailVerified && ['draft', 'published'].includes(mission.status) && !isUgc,
    canPayCommission: emailVerified && mission.status === 'pending_payment' && isUgc,
    canDelete: ['draft', 'published'].includes(mission.status),
    canClose: emailVerified && mission.status === 'published',
    canReopen: emailVerified && mission.status === 'closed' && !mission.has_paid_payment,
    canComplete: emailVerified && mission.status === 'closed',
    canValidateAttendance:
      emailVerified &&
      mission.has_paid_payment &&
      ['closed', 'pending_attendance_validation'].includes(mission.status),
  }
}
