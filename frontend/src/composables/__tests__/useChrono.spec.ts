import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { effectScope, nextTick } from 'vue'
import { useChrono, type UseChronoReturn } from '../useChrono'

// Fenêtre type : start 2026-06-12T12:00Z → deadline 2026-06-19T12:00Z (7 jours).
const START = '2026-06-12T12:00:00Z'
const DEADLINE = '2026-06-19T12:00:00Z'

// useNow enregistre son intervalle à la création : fake timers AVANT d'instancier
// le composable (piège n°7), dans un effectScope pour le cleanup de useNow.
function makeChrono(
  start: string | null | undefined,
  deadline: string | null | undefined,
): { chrono: UseChronoReturn; stop: () => void } {
  const scope = effectScope()
  const chrono = scope.run(() => useChrono(() => start, () => deadline))!
  return { chrono, stop: () => scope.stop() }
}

describe('useChrono', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-06-15T12:00:00Z'))
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('returns progress 0 while timestamps are missing', () => {
    const noDeadline = makeChrono(START, null)
    expect(noDeadline.chrono.progress.value).toBe(0)
    expect(noDeadline.chrono.isExpired.value).toBe(false)
    expect(noDeadline.chrono.remainingLabel.value).toBe('')
    noDeadline.stop()

    const noStart = makeChrono(null, DEADLINE)
    expect(noStart.chrono.progress.value).toBe(0)
    noStart.stop()

    const invalid = makeChrono('not-a-date', DEADLINE)
    expect(invalid.chrono.progress.value).toBe(0)
    invalid.stop()
  })

  it('derives progress from the two server timestamps', () => {
    // Au 2026-06-15T12:00Z : 3 jours écoulés sur 7.
    const { chrono, stop } = makeChrono(START, DEADLINE)
    expect(chrono.progress.value).toBeCloseTo(3 / 7, 5)
    expect(chrono.isExpired.value).toBe(false)
    stop()
  })

  it('clamps progress to 1 and flags isExpired past the deadline', () => {
    vi.setSystemTime(new Date('2026-06-20T12:00:00Z'))
    const { chrono, stop } = makeChrono(START, DEADLINE)
    expect(chrono.progress.value).toBe(1)
    expect(chrono.isExpired.value).toBe(true)
    expect(chrono.remainingLabel.value).toBe('0j')
    stop()
  })

  it('formats remaining days as Xj', () => {
    // 4 jours restants au 2026-06-15T12:00Z.
    const { chrono, stop } = makeChrono(START, DEADLINE)
    expect(chrono.remainingLabel.value).toBe('4j')
    stop()
  })

  it('formats remaining hours as Xh under 24h', () => {
    vi.setSystemTime(new Date('2026-06-19T02:00:00Z'))
    const { chrono, stop } = makeChrono(START, DEADLINE)
    expect(chrono.remainingLabel.value).toBe('10h')
    stop()
  })

  it('formats remaining minutes as Xmin under 1h', () => {
    vi.setSystemTime(new Date('2026-06-19T11:30:00Z'))
    const { chrono, stop } = makeChrono(START, DEADLINE)
    expect(chrono.remainingLabel.value).toBe('30min')
    stop()
  })

  it('ticks forward as time advances', async () => {
    // Borne franche : à 11:30 → 30min ; le tick 60 s capture now à 11:31 → 29min.
    vi.setSystemTime(new Date('2026-06-19T11:30:00Z'))
    const { chrono, stop } = makeChrono(START, DEADLINE)
    expect(chrono.remainingLabel.value).toBe('30min')

    vi.advanceTimersByTime(61_000)
    await nextTick()
    expect(chrono.remainingLabel.value).toBe('29min')
    stop()
  })
})
