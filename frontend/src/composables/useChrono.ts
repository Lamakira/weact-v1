import { computed, type ComputedRef } from 'vue'
import { useNow } from '@vueuse/core'

export interface UseChronoReturn {
  /** 0 (chrono au départ) → 1 (deadline atteinte), clampé. */
  progress: ComputedRef<number>
  isExpired: ComputedRef<boolean>
  /** « 3j » / « 18h » / « 45min » ; « 0j » une fois expiré ; '' sans deadline. */
  remainingLabel: ComputedRef<string>
}

/**
 * Chrono UGC (3.4, NFR3) : interpole entre DEUX timestamps serveur
 * (recu_le → unboxing_deadline_at) avec l'horloge locale. Le front ne
 * fabrique jamais de deadline (D-3.4.i) ; la couleur est dérivée du
 * progress par ChronoRing (D-3.4.g — source unique).
 */
export function useChrono(
  startAt: () => string | null | undefined,
  deadlineAt: () => string | null | undefined,
): UseChronoReturn {
  // Tick 60 s : affichage en jours/heures, pas besoin de la seconde.
  const now = useNow({ interval: 60_000 })

  const startMs = computed(() => parseIso(startAt()))
  const deadlineMs = computed(() => parseIso(deadlineAt()))

  const progress = computed(() => {
    if (startMs.value === null || deadlineMs.value === null) return 0
    const span = deadlineMs.value - startMs.value
    if (span <= 0) return 1
    return Math.min(1, Math.max(0, (now.value.getTime() - startMs.value) / span))
  })

  const isExpired = computed(
    () => deadlineMs.value !== null && now.value.getTime() >= deadlineMs.value,
  )

  const remainingLabel = computed(() => {
    if (deadlineMs.value === null) return ''
    const diff = deadlineMs.value - now.value.getTime()
    if (diff <= 0) return '0j'
    const days = Math.floor(diff / 86_400_000)
    if (days >= 1) return `${days}j`
    const hours = Math.floor(diff / 3_600_000)
    if (hours >= 1) return `${hours}h`
    return `${Math.max(1, Math.floor(diff / 60_000))}min`
  })

  return { progress, isExpired, remainingLabel }
}

function parseIso(value: string | null | undefined): number | null {
  if (!value) return null
  const ms = new Date(value).getTime()
  return Number.isFinite(ms) ? ms : null
}
