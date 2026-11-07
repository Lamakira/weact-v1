import { computed, ref } from 'vue'
import { businessTodayIso } from '@/lib/dates'
import { formatApiError } from '@/services/errorFormatter'
import { agendaApi } from '../services/agendaApi'
import type { AgendaEvent, UnavailableDaysPayload } from '../types'
import { addMonths, buildDayStates, monthEnd, monthStart } from '../utils/agendaDates'

/**
 * Agenda de la Face : un mois chargé à la fois. « Aujourd'hui » vient du serveur (jour métier) dès la
 * première réponse ; avant elle, le mois affiché est celui du jour métier du Bénin.
 */
export function useFaceAgenda() {
  const month = ref(monthStart(businessTodayIso()))
  const today = ref<string | null>(null)
  const unavailableDays = ref<string[]>([])
  const events = ref<AgendaEvent[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const isSaving = ref(false)

  // Les réponses d'un mois quitté ne doivent pas écraser celles du mois affiché.
  let requestId = 0

  const from = computed(() => monthStart(month.value))
  const to = computed(() => monthEnd(month.value))
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
    } catch (err) {
      if (current !== requestId) return
      error.value = formatApiError(err, "Impossible de charger l'agenda.")
    } finally {
      if (current === requestId) isLoading.value = false
    }
  }

  async function goToMonth(delta: number): Promise<void> {
    month.value = addMonths(month.value, delta)
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
    dayStates,
    isLoading,
    isSaving,
    error,
    load,
    previousMonth: () => goToMonth(-1),
    nextMonth: () => goToMonth(1),
    updateUnavailable,
  }
}
