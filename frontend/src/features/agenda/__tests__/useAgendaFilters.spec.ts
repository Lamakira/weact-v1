import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import { useAgendaFilters } from '../composables/useAgendaFilters'

describe('useAgendaFilters', () => {
  beforeEach(() => localStorage.clear())
  afterEach(() => vi.restoreAllMocks())

  it('démarre avec tout coché sauf Terminés, en vue Mois', () => {
    const { filters, view } = useAgendaFilters()
    expect(filters.value).toEqual({ bookings: true, missions: true, pending: true, blocked: true, done: false })
    expect(view.value).toBe('month')
  })

  it('mémorise filtres et vue, relus à la création suivante', async () => {
    const first = useAgendaFilters()
    first.toggle('done')
    first.toggle('missions')
    first.view.value = 'week'
    await nextTick()

    const second = useAgendaFilters()
    expect(second.filters.value).toEqual({ bookings: true, missions: false, pending: true, blocked: true, done: true })
    expect(second.view.value).toBe('week')
  })

  it('ignore une valeur stockée invalide', () => {
    localStorage.setItem('weact:agenda:filters', '{"bookings":"non","done":true}')
    localStorage.setItem('weact:agenda:view', 'calendrier')
    const { filters, view } = useAgendaFilters()
    expect(filters.value.bookings).toBe(true)
    expect(filters.value.done).toBe(true)
    expect(view.value).toBe('month')
  })

  it('retombe sur les valeurs par défaut quand localStorage lève une exception', async () => {
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('SecurityError')
    })
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('QuotaExceededError')
    })
    const { filters, view, toggle } = useAgendaFilters()
    expect(filters.value).toEqual({ bookings: true, missions: true, pending: true, blocked: true, done: false })
    expect(view.value).toBe('month')

    // L'écriture qui échoue ne casse pas le choix en cours.
    toggle('done')
    view.value = 'list'
    await nextTick()
    expect(filters.value.done).toBe(true)
    expect(view.value).toBe('list')
  })
})
