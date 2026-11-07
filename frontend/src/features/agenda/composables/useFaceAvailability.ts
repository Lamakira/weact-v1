import { computed, ref, type Ref } from 'vue'
import { agendaApi } from '../services/agendaApi'
import { addDays, monthEnd, monthStart } from '../utils/agendaDates'

/**
 * Disponibilité d'une Face vue par un Producteur, mois par mois, avec mémoire des mois déjà chargés.
 * Un échec de chargement n'est jamais bloquant : le mois reste simplement sans jours indisponibles.
 */
export function useFaceAvailability(faceId: Ref<string | null | undefined>) {
  const today = ref<string | null>(null)
  const byMonth = ref<Record<string, string[]>>({})
  const loading = ref<Record<string, boolean>>({})
  const failed = ref<Record<string, boolean>>({})
  let loadedFor: string | null = null

  function reset(): void {
    byMonth.value = {}
    loading.value = {}
    failed.value = {}
    today.value = null
    loadedFor = null
  }

  /** Charge le mois contenant `day` (une seule requête par mois et par Face). */
  async function loadMonth(day: string): Promise<void> {
    const id = faceId.value
    if (!id) return
    if (loadedFor !== id) {
      reset()
      loadedFor = id
    }
    const key = monthStart(day)
    if (byMonth.value[key] !== undefined || loading.value[key]) return
    loading.value = { ...loading.value, [key]: true }
    try {
      const response = await agendaApi.getFaceAvailability(id, key, monthEnd(key))
      if (loadedFor !== id) return
      today.value = response.data.today
      byMonth.value = { ...byMonth.value, [key]: response.data.unavailable_days }
      failed.value = { ...failed.value, [key]: false }
    } catch {
      failed.value = { ...failed.value, [key]: true }
    } finally {
      loading.value = { ...loading.value, [key]: false }
    }
  }

  /** Charge tous les mois de [start, end] (au plus `maxMonths`). */
  async function loadRange(start: string, end: string, maxMonths = 6): Promise<void> {
    const keys: string[] = []
    let cursor = monthStart(start)
    while (cursor <= end && keys.length < maxMonths) {
      keys.push(cursor)
      cursor = addDays(monthEnd(cursor), 1)
    }
    await Promise.all(keys.map((key) => loadMonth(key)))
  }

  const allUnavailable = computed(() => Object.values(byMonth.value).flat())

  function unavailableInMonth(day: string): string[] {
    return byMonth.value[monthStart(day)] ?? []
  }

  function isLoadingMonth(day: string): boolean {
    return loading.value[monthStart(day)] === true
  }

  function hasFailed(day: string): boolean {
    return failed.value[monthStart(day)] === true
  }

  return { today, allUnavailable, loadMonth, loadRange, unavailableInMonth, isLoadingMonth, hasFailed }
}
