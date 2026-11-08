import type { AgendaEvent } from '../types'

/** Présentation partagée d'un événement d'agenda (panneau du jour, « À venir », grilles). */

/** Pastille ronde de 8 px : couleur du type (jamais de barre d'accent). */
export function eventDotClass(event: AgendaEvent): string {
  if (event.state === 'pending') return 'bg-amber-500'
  if (event.state === 'done') return 'bg-teal-300'
  return event.kind === 'mission' ? 'bg-violet-700' : 'bg-teal-600'
}

/** Étiquette pleine d'une grille : couleurs portées par un élément interne (piège main.css hors @layer). */
export function eventChipClass(event: AgendaEvent): string {
  if (event.state === 'pending') return 'border-[1.5px] border-amber-500 bg-white text-amber-800'
  if (event.state === 'done') return 'bg-teal-100 text-teal-800'
  return event.kind === 'mission' ? 'bg-violet-700 text-white' : 'bg-teal-600 text-white'
}

/** « booking », « mission », « demande en attente », « booking terminé » (aria-label des jours). */
export function eventKindLabel(event: AgendaEvent): string {
  if (event.state === 'pending') return 'demande en attente'
  if (event.state === 'done') return 'booking terminé'
  return event.kind === 'mission' ? 'mission' : 'booking'
}

/** État affiché dans le détail d'un jour. */
export function eventStateLabel(event: AgendaEvent): string {
  if (event.kind === 'mission') return 'mission'
  if (event.state === 'pending') return 'demande en attente'
  if (event.state === 'done') return 'booking terminé'
  return 'booking accepté'
}

export function eventRoute(event: AgendaEvent) {
  return event.kind === 'mission'
    ? { name: 'face-mission-detail', params: { id: event.uuid } }
    : { name: 'face-booking-detail', params: { id: event.uuid } }
}

/** Le jour est modifiable : aujourd'hui ou futur, dans l'horizon. */
export function canActOnDay(day: string | null, today: string | null, horizonEnd: string | null): boolean {
  if (!day || !today) return false
  if (day < today) return false
  return !horizonEnd || day <= horizonEnd
}
