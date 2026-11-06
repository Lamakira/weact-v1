/**
 * Changement de date d'une mission payée ou d'un booking : types du contrat d'API.
 * Les dates sont des jours AAAA-MM-JJ ; `respond_by` et `responded_at` sont en ISO 8601.
 */

export type DateChangeReason =
  | 'lieu_indisponible'
  | 'equipe_indisponible'
  | 'meteo'
  | 'imprevu_personnel'
  | 'autre'

// Pas de texte libre : le chat est fermé avant paiement, un champ libre permettrait d'échanger des contacts.
export const DATE_CHANGE_REASONS: ReadonlyArray<{ value: DateChangeReason; label: string }> = [
  { value: 'lieu_indisponible', label: 'Lieu indisponible' },
  { value: 'equipe_indisponible', label: 'Équipe indisponible' },
  { value: 'meteo', label: 'Météo' },
  { value: 'imprevu_personnel', label: 'Imprévu personnel' },
  { value: 'autre', label: 'Autre' },
]

export type DateChangeStatus = 'pending' | 'completed' | 'accepted' | 'refused' | 'expired' | 'withdrawn'
export type DateChangeResponseStatus = 'pending' | 'confirmed' | 'withdrawn' | 'expired'

export interface DateChangePayload {
  new_date: string
  reason: DateChangeReason
}

/** Une ligne de réponse par Face engagée (détail Producteur). */
export interface ProducerDateChangeResponse {
  candidature_uuid: string
  face_name: string
  status: DateChangeResponseStatus
  responded_at: string | null
}

export interface ProducerDateChangeLatest {
  old_date: string
  new_date: string
  reason_label: string
  status: DateChangeStatus
  respond_by: string
  responses: ProducerDateChangeResponse[]
}

/** `Mission.date_change` côté Producteur (détail uniquement). */
export interface ProducerMissionDateChange {
  can_change: boolean
  used: number
  max: number
  faces_to_confirm: number
  /** Dernière nouvelle date acceptée (AAAA-MM-JJ) : 30 jours après la date actuelle. */
  max_date?: string | null
  pending: boolean
  latest: ProducerDateChangeLatest | null
}

/**
 * `Mission.date_change` côté Face : null sans ligne de réponse pour sa candidature.
 * `status` est supposé être celui de SA ligne de réponse (pending, confirmed, withdrawn, expired).
 */
export interface FaceMissionDateChange {
  old_date: string
  new_date: string
  reason_label: string
  respond_by: string
  status: DateChangeResponseStatus
  conflict: boolean
  can_respond: boolean
}

export type MissionDateChange = ProducerMissionDateChange | FaceMissionDateChange

export function isProducerDateChange(value: MissionDateChange | null | undefined): value is ProducerMissionDateChange {
  return !!value && 'can_change' in value
}

export function isFaceDateChange(value: MissionDateChange | null | undefined): value is FaceMissionDateChange {
  return !!value && 'can_respond' in value
}

export interface BookingDateChangeLatest {
  old_date: string
  new_date: string
  reason_label: string
  status: DateChangeStatus
  respond_by: string
  proposed_by_me: boolean
  can_respond: boolean
  can_withdraw: boolean
  conflict: boolean
}

/** `Booking.date_change` (détail et réponses des actions seulement). */
export interface BookingDateChange {
  can_propose: boolean
  used: number
  max: number
  /** Dernière nouvelle date acceptée (AAAA-MM-JJ). */
  max_date?: string | null
  latest: BookingDateChangeLatest | null
}

export type MissionDateDecision = 'confirm' | 'withdraw'
export type BookingDateDecision = 'accept' | 'refuse'

export interface FaceDateChangeResponse {
  data: FaceMissionDateChange
  message?: string
}
