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
 * - UGC is detected from `type_mission === 'ugc'` (a hybrid UGC mission has no `commission_ugc`).
 * - « Terminer » is only offered when the server says it would accept it (`can_complete`,
 *   same rules as CompleteMissionRequest).
 * - UGC missions are never editable (backend `UpdateMissionRequest` rejects type_mission='ugc').
 * - A STANDARD mission also sits in `pending_payment` during its escrow checkout, so the
 *   commission CTA must be guarded on `isUgc` (otherwise it 403s server-side).
 * - Without a verified email only delete stays available.
 */
export function getMissionActionState(mission: Mission, emailVerified: boolean): MissionActionState {
  // 'ugc' est hors de l'union TS `MissionTypeType` : comparaison volontairement en string.
  const isUgc = (mission.type_mission as string) === 'ugc'

  return {
    isUgc,
    // Règle serveur (summary.can_edit) quand présente (détail) ; même règle en repli sur les listes.
    canEdit:
      emailVerified &&
      (mission.summary?.can_edit ?? (['draft', 'published'].includes(mission.status) && !isUgc)),
    canPayCommission: emailVerified && mission.status === 'pending_payment' && isUgc,
    canDelete: ['draft', 'published'].includes(mission.status),
    canClose: emailVerified && mission.status === 'published',
    canReopen: emailVerified && mission.status === 'closed' && !mission.has_paid_payment,
    canComplete: emailVerified && mission.can_complete === true,
    canValidateAttendance:
      emailVerified &&
      mission.has_paid_payment &&
      ['closed', 'pending_attendance_validation'].includes(mission.status),
  }
}
