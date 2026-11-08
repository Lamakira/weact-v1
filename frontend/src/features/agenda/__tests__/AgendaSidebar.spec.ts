import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AgendaSidebar from '../components/AgendaSidebar.vue'
import type { AgendaEvent, AgendaFilters } from '../types'
import { formatMoney } from '@/utils/money'

const FILTERS: AgendaFilters = { bookings: true, missions: true, pending: true, blocked: true, done: false }

function event(partial: Partial<AgendaEvent>): AgendaEvent {
  return {
    kind: 'booking',
    uuid: 'u',
    title: 'Clip',
    status: 'accepted',
    state: 'engaged',
    start: '2026-11-18',
    end: '2026-11-20',
    counterpart: 'Studio',
    ...partial,
  }
}

const events = [
  event({ uuid: 'b1' }),
  event({ uuid: 'b2', start: '2026-11-25', end: '2026-11-25' }),
  event({ uuid: 'm1', kind: 'mission', start: '2026-11-24', end: '2026-11-24' }),
  event({ uuid: 'p1', state: 'pending', start: '2026-11-26', end: '2026-11-26' }),
  event({ uuid: 'd1', state: 'done', start: '2026-11-02', end: '2026-11-03' }),
  event({ uuid: 'out', start: '2026-12-05', end: '2026-12-06' }),
]

function mountSidebar(props: Record<string, unknown> = {}) {
  return mount(AgendaSidebar, {
    props: {
      month: '2026-11-01',
      today: '2026-11-08',
      events,
      unavailableDays: ['2026-11-12', '2026-11-13'],
      filters: FILTERS,
      earnings: { amount: 75000, count: 2 },
      ...props,
    },
  })
}

const text = (wrapper: ReturnType<typeof mountSidebar>, testid: string) => wrapper.get(`[data-testid="${testid}"]`).text()

describe('AgendaSidebar', () => {
  it('affiche les compteurs du mois par type, hors événements d\'un autre mois', () => {
    const wrapper = mountSidebar()
    expect(text(wrapper, 'count-bookings')).toBe('2')
    expect(text(wrapper, 'count-missions')).toBe('1')
    expect(text(wrapper, 'count-pending')).toBe('1')
    expect(text(wrapper, 'count-blocked')).toBe('2')
    expect(text(wrapper, 'count-done')).toBe('1')
  })

  it('coche tout sauf Terminés par défaut et émet toggle', async () => {
    const wrapper = mountSidebar()
    const box = (key: string) => wrapper.get<HTMLInputElement>(`[data-testid="filter-${key}"]`)
    expect(box('bookings').element.checked).toBe(true)
    expect(box('done').element.checked).toBe(false)

    await box('done').trigger('change')
    expect(wrapper.emitted('toggle')).toEqual([['done']])
  })

  it('calcule jours bookés, bloqués et libres', () => {
    const wrapper = mountSidebar()
    // Engagés : 18, 19, 20, 24, 25 = 5 ; libres : 23 jours du 8 au 30 - 5 - 2 bloqués = 16.
    expect(text(wrapper, 'stat-booked')).toBe('5')
    expect(text(wrapper, 'stat-blocked')).toBe('2')
    expect(text(wrapper, 'stat-free')).toBe('16')
  })

  it('affiche les gains formatés, le nombre d\'engagements et la mention de versement', () => {
    const wrapper = mountSidebar()
    expect(text(wrapper, 'earnings-amount')).toBe(formatMoney(75000))
    expect(text(wrapper, 'earnings-count')).toBe('2 engagements en séquestre')
    expect(wrapper.text()).toContain('Versés sur votre portefeuille après le tournage.')

    const single = mountSidebar({ earnings: { amount: 30000, count: 1 } })
    expect(text(single, 'earnings-count')).toBe('1 engagement en séquestre')
  })

  it('affiche « Aucun gain en attente » à 0 ou sans donnée', () => {
    expect(text(mountSidebar({ earnings: { amount: 0, count: 0 } }), 'earnings-empty')).toBe('Aucun gain en attente')
    const none = mountSidebar({ earnings: null })
    expect(text(none, 'earnings-empty')).toBe('Aucun gain en attente')
    expect(none.find('[data-testid="earnings-amount"]').exists()).toBe(false)
  })

  it('émet block-days depuis le bouton principal', async () => {
    const wrapper = mountSidebar()
    expect(wrapper.text()).toContain('ou glissez sur le calendrier')
    await wrapper.get('[data-testid="agenda-block-days-lg"]').trigger('click')
    expect(wrapper.emitted('block-days')).toHaveLength(1)
  })
})
