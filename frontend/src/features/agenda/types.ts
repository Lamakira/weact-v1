/** Calendrier des Faces : types du contrat d'API (`/face/agenda`, `/producer/candidates/{face}/availability`). */

export type AgendaEventKind = 'booking' | 'mission'

/** pending = demande en attente, engaged = engagé, done = booking terminé. */
export type AgendaEventState = 'pending' | 'engaged' | 'done'

export interface AgendaEvent {
  kind: AgendaEventKind
  /** uuid du booking ou de la mission. */
  uuid: string
  title: string
  /** Valeur brute du statut côté serveur. */
  status: string
  state: AgendaEventState
  /** AAAA-MM-JJ (jour métier). */
  start: string
  end: string
  counterpart: string
  /** Montant net que la Face reçoit (F CFA) ; null si inconnu. */
  amount_face?: number | null
  /** true si cet argent est en séquestre bloqué pour la Face. */
  secured?: boolean
  /** HH:MM, heure métier (booking uniquement). */
  start_time?: string | null
  end_time?: string | null
}

export interface AgendaEarnings {
  amount: number
  count: number
}

export interface AgendaData {
  from: string
  to: string
  today: string
  unavailable_days: string[]
  events: AgendaEvent[]
  /** Gains nets encore en séquestre, toutes dates confondues. */
  upcoming_earnings?: AgendaEarnings
}

export interface AgendaResponse {
  data: AgendaData
}

export interface AvailabilityData {
  from: string
  to: string
  today: string
  unavailable_days: string[]
}

export interface AvailabilityResponse {
  data: AvailabilityData
}

export interface UnavailableDaysPayload {
  add?: string[]
  remove?: string[]
}

export interface UnavailableDaysResponse {
  data: { unavailable_days: string[] }
  message?: string
}

/** États visuels d'un jour, du plus prioritaire au moins prioritaire : booking > mission > pending > done > blocked. */
export type DayState = 'booking' | 'mission' | 'pending' | 'done' | 'blocked'

/** Types affichables du calendrier ordinateur (cases à cocher « Afficher »). */
export type AgendaFilterKey = 'bookings' | 'missions' | 'pending' | 'blocked' | 'done'
export type AgendaFilters = Record<AgendaFilterKey, boolean>

export type AgendaView = 'month' | 'week' | 'list'
