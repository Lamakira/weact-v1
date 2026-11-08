import { ref, watch } from 'vue'
import type { AgendaFilterKey, AgendaFilters, AgendaView } from '../types'

const FILTERS_KEY = 'weact:agenda:filters'
const VIEW_KEY = 'weact:agenda:view'

export const FILTER_KEYS: AgendaFilterKey[] = ['bookings', 'missions', 'pending', 'blocked', 'done']
const VIEWS: AgendaView[] = ['month', 'week', 'list']

export function defaultFilters(): AgendaFilters {
  return { bookings: true, missions: true, pending: true, blocked: true, done: false }
}

function readFilters(): AgendaFilters {
  const filters = defaultFilters()
  try {
    const parsed: unknown = JSON.parse(localStorage.getItem(FILTERS_KEY) ?? 'null')
    if (parsed && typeof parsed === 'object') {
      for (const key of FILTER_KEYS) {
        const value = (parsed as Record<string, unknown>)[key]
        if (typeof value === 'boolean') filters[key] = value
      }
    }
  } catch {
    // Stockage indisponible ou JSON invalide : valeurs par défaut.
  }
  return filters
}

function readView(): AgendaView {
  try {
    const stored = localStorage.getItem(VIEW_KEY)
    if (stored && (VIEWS as string[]).includes(stored)) return stored as AgendaView
  } catch {
    // Stockage indisponible : vue Mois.
  }
  return 'month'
}

function write(key: string, value: string): void {
  try {
    localStorage.setItem(key, value)
  } catch {
    // Quota ou navigation privée : le choix reste valable pour la session.
  }
}

/** Filtres « Afficher » et vue (Mois / Semaine / Liste) de l'agenda ordinateur, mémorisés dans localStorage. */
export function useAgendaFilters() {
  const filters = ref<AgendaFilters>(readFilters())
  const view = ref<AgendaView>(readView())

  watch(filters, (value) => write(FILTERS_KEY, JSON.stringify(value)), { deep: true })
  watch(view, (value) => write(VIEW_KEY, value))

  function toggle(key: AgendaFilterKey): void {
    filters.value = { ...filters.value, [key]: !filters.value[key] }
  }

  return { filters, view, toggle }
}
