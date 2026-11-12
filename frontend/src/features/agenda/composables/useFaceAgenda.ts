import { computed, ref } from 'vue'
import { businessTodayIso } from '@/lib/dates'
import { formatApiError } from '@/services/errorFormatter'
import { agendaApi } from '../services/agendaApi'
import type { AgendaEarnings, AgendaEvent, UnavailableDaysPayload } from '../types'
import { addDays, addMonths, buildDayStates, monthEnd, monthStart, weekStart } from '../utils/agendaDates'

/**
 * Agenda de la Face : un mois chargé à la fois. « Aujourd'hui » vient du serveur (jour métier) dès la
 * première réponse ; avant elle, le mois affiché est celui du jour métier du Bénin.
 */
export function useFaceAgenda() {
  const month = ref(monthStart(businessTodayIso()))
  const today = ref<string | null>(null)
  const unavailableDays = ref<string[]>([])
  const events = ref<AgendaEvent[]>([])
  const earnings = ref<AgendaEarnings | null>(null)
  /** Lundi de la semaine affichée (vue Semaine ordinateur) ; null hors vue Semaine. */
  const weekAnchor = ref<string | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const isSaving = ref(false)

  // Les réponses d'un mois quitté ne doivent pas écraser celles du mois affiché.
  let requestId = 0

  // Une semaine à cheval sur deux mois étend la plage chargée au-delà du mois affiché.
  const from = computed(() => {
    const first = monthStart(month.value)
    return weekAnchor.value && weekAnchor.value < first ? weekAnchor.value : first
  })
  const to = computed(() => {
    const last = monthEnd(month.value)
    const weekEnd = weekAnchor.value ? addDays(weekAnchor.value, 6) : null
    return weekEnd && weekEnd > last ? weekEnd : last
  })
  const dayStates = computed(() => buildDayStates(unavailableDays.value, events.value, from.value, to.value))

  async function load(): Promise<void> {
    const current = ++requestId
    isLoading.value = true
    error.value = null
    try {
      const response = await agendaApi.getAgenda(from.value, to.value)
      if (current !== requestId) return
      today.value = response.data.today
      unavailableDays.value = response.data.unavailable_days
      events.value = response.data.events
      earnings.value = response.data.upcoming_earnings ?? null
    } catch (err) {
      if (current !== requestId) return
      error.value = formatApiError(err, "Impossible de charger l'agenda.")
    } finally {
      if (current === requestId) isLoading.value = false
    }
  }

  async function goToMonth(delta: number): Promise<void> {
    weekAnchor.value = null
    month.value = addMonths(month.value, delta)
    await load()
  }

  /** Affiche le mois de `iso` (hors vue Semaine). */
  async function showMonth(iso: string): Promise<void> {
    weekAnchor.value = null
    month.value = monthStart(iso)
    await load()
  }

  /** Affiche la semaine (lundi au dimanche) contenant `iso`. */
  async function showWeek(iso: string): Promise<void> {
    const monday = weekStart(iso)
    weekAnchor.value = monday
    month.value = monthStart(iso)
    await load()
  }

  /** Bloque / libère des jours. Renvoie null en cas de succès, sinon le message d'erreur du serveur. */
  async function updateUnavailable(payload: UnavailableDaysPayload): Promise<string | null> {
    if (isSaving.value) return 'Une mise à jour est déjà en cours.'
    isSaving.value = true
    try {
      await agendaApi.updateUnavailableDays(payload)
    } catch (err) {
      return formatApiError(err, "Impossible de mettre l'agenda à jour.")
    } finally {
      isSaving.value = false
    }
    await load()
    return null
  }

  return {
    month,
    today,
    unavailableDays,
    events,
    earnings,
    weekAnchor,
    dayStates,
    isLoading,
    isSaving,
    error,
    load,
    previousMonth: () => goToMonth(-1),
    nextMonth: () => goToMonth(1),
    showMonth,
    showWeek,
    updateUnavailable,
  }
}
