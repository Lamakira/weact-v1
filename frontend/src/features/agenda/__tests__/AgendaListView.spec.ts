import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import AgendaListView from '../components/AgendaListView.vue'
import type { AgendaEvent, AgendaFilters } from '../types'
import { formatMoney } from '@/utils/money'

vi.mock('vue-router', () => ({
  RouterLink: {
    name: 'RouterLink',
    props: ['to'],
    template: '<a :data-route="JSON.stringify(to)" v-bind="$attrs"><slot /></a>',
  },
}))

const FILTERS: AgendaFilters = { bookings: true, missions: true, pending: true, blocked: true, done: false }

function event(partial: Partial<AgendaEvent>): AgendaEvent {
  return {
    kind: 'booking',
    uuid: 'u',
    title: 'Clip vidéo',
    status: 'accepted',
    state: 'engaged',
    start: '2026-11-18',
    end: '2026-11-20',
    counterpart: 'Studio Lumière',
    ...partial,
  }
}

function mountList(props: Record<string, unknown> = {}) {
  return mount(AgendaListView, {
    props: { month: '2026-11-01', events: [], unavailableDays: [], filters: FILTERS, ...props },
  })
}

describe('AgendaListView', () => {
  it('regroupe par semaine avec titre, contrepartie et période', () => {
    const wrapper = mountList({
      events: [
        event({ uuid: 'm', kind: 'mission', title: 'Pub boisson', start: '2026-11-24', end: '2026-11-24', counterpart: 'Agence X' }),
        event({ uuid: 'b' }),
      ],
    })
    const weeks = wrapper.findAll('[data-testid="list-week"]')
    expect(weeks).toHaveLength(2)
    expect(weeks[0]!.text()).toContain('Semaine du 16 nov')
    expect(weeks[0]!.text()).toContain('Clip vidéo · Studio Lumière')
    expect(weeks[0]!.text()).toContain('Mer 18 au ven 20 · 3 jours')
    expect(weeks[1]!.text()).toContain('Semaine du 23 nov')
    expect(weeks[1]!.text()).toContain('Pub boisson · Agence X')
    expect(weeks[1]!.text()).toContain('Mar 24 · mission')
  })

  it('fusionne les jours bloqués consécutifs en une ligne « Indisponible »', () => {
    const wrapper = mountList({ unavailableDays: ['2026-11-12', '2026-11-13', '2026-11-14', '2026-11-20'] })
    const rows = wrapper.findAll('[data-testid="list-row"]')
    expect(rows).toHaveLength(2)
    expect(rows[0]!.text()).toContain('Indisponible')
    expect(rows[0]!.text()).toContain('Jeu 12 au sam 14 · 3 jours')
    expect(rows[1]!.text()).toContain('Ven 20 · 1 jour')
  })

  it('masque les jours bloqués et les types décochés', () => {
    const wrapper = mountList({
      events: [event({}), event({ uuid: 'd', state: 'done', start: '2026-11-02', end: '2026-11-02' })],
      unavailableDays: ['2026-11-12'],
      filters: { ...FILTERS, blocked: false },
    })
    const rows = wrapper.findAll('[data-testid="list-row"]')
    expect(rows).toHaveLength(1)
    expect(rows[0]!.text()).toContain('Clip vidéo')
  })

  it('affiche le montant net seulement si secured et amount_face', () => {
    const wrapper = mountList({
      events: [
        event({ uuid: 'sec', title: 'Séquestré', amount_face: 45000, secured: true }),
        event({ uuid: 'nosec', title: 'Non séquestré', start: '2026-11-25', end: '2026-11-25', amount_face: 60000, secured: false }),
        event({ uuid: 'nul', title: 'Sans montant', start: '2026-11-26', end: '2026-11-26', amount_face: null, secured: true }),
      ],
    })
    const amounts = wrapper.findAll('[data-testid="list-amount"]')
    expect(amounts).toHaveLength(1)
    expect(amounts[0]!.text()).toBe(formatMoney(45000))
    expect(wrapper.text()).not.toContain(formatMoney(60000))
  })

  it('affiche un message quand rien n\'est à lister', () => {
    expect(mountList().get('[data-testid="list-empty"]').text()).toBe('Rien à afficher ce mois-ci.')
  })
})
