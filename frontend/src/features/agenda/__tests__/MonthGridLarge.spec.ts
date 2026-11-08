import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import MonthGridLarge from '../components/MonthGridLarge.vue'
import type { AgendaEvent, AgendaFilters } from '../types'

const TODAY = '2026-11-08'
const FILTERS: AgendaFilters = { bookings: true, missions: true, pending: true, blocked: true, done: false }

function event(partial: Partial<AgendaEvent>): AgendaEvent {
  return {
    kind: 'booking',
    uuid: 'u',
    title: 'Clip vidéo',
    status: 'accepted',
    state: 'engaged',
    start: '2026-11-19',
    end: '2026-11-19',
    counterpart: 'Studio',
    ...partial,
  }
}

function mountGrid(props: Record<string, unknown> = {}) {
  return mount(MonthGridLarge, {
    attachTo: document.body,
    props: { month: '2026-11-01', today: TODAY, events: [], unavailableDays: [], filters: FILTERS, ...props },
  })
}

const cell = (wrapper: ReturnType<typeof mountGrid>, iso: string) => wrapper.get(`[data-testid="cell-${iso}"]`)

describe('MonthGridLarge', () => {
  it('affiche 42 cases, aujourd\'hui dans une pastille sombre', () => {
    const wrapper = mountGrid()
    expect(wrapper.findAll('[role="gridcell"]')).toHaveLength(42)
    expect(wrapper.get('[data-testid="cell-today"]').text()).toBe('8')
    expect(wrapper.get('[data-testid="cell-today"]').classes()).toContain('bg-slate-900')
    wrapper.unmount()
  })

  it('affiche au plus 3 étiquettes puis « +N »', () => {
    const events = ['a', 'b', 'c', 'd', 'e'].map((uuid) => event({ uuid, title: `Titre ${uuid}` }))
    const wrapper = mountGrid({ events })
    const target = cell(wrapper, '2026-11-19')
    expect(target.findAll('[data-testid="agenda-chip"]')).toHaveLength(3)
    expect(target.get('[data-testid="cell-more"]').text()).toBe('+2')
    wrapper.unmount()
  })

  it('met le texte le premier jour et le lundi, une barre pleine les autres jours', () => {
    const wrapper = mountGrid({ events: [event({ start: '2026-11-19', end: '2026-11-24' })] })
    const text = (iso: string) => cell(wrapper, iso).get('[data-testid="agenda-chip"]').attributes('data-with-text')
    expect(text('2026-11-19')).toBe('true')
    expect(text('2026-11-20')).toBe('false')
    expect(text('2026-11-23')).toBe('true')
    expect(text('2026-11-24')).toBe('false')
    expect(cell(wrapper, '2026-11-19').text()).toContain('Clip vidéo')
    expect(cell(wrapper, '2026-11-20').text()).not.toContain('Clip vidéo')
    wrapper.unmount()
  })

  it('applique les filtres : Terminés masqué par défaut, types décochés absents, Indisponible masquable', async () => {
    const events = [
      event({ uuid: 'd', state: 'done', start: '2026-11-03', end: '2026-11-03', title: 'Passé' }),
      event({ uuid: 'm', kind: 'mission', start: '2026-11-24', end: '2026-11-24', title: 'Pub' }),
    ]
    const wrapper = mountGrid({ events, unavailableDays: ['2026-11-12'] })
    expect(cell(wrapper, '2026-11-03').findAll('[data-testid="agenda-chip"]')).toHaveLength(0)
    expect(cell(wrapper, '2026-11-24').text()).toContain('Pub')
    expect(cell(wrapper, '2026-11-12').text()).toContain('Indisponible')
    expect(cell(wrapper, '2026-11-12').get('span').classes()).toContain('bg-slate-100')

    await wrapper.setProps({ filters: { ...FILTERS, done: true, missions: false, blocked: false } })
    expect(cell(wrapper, '2026-11-03').findAll('[data-testid="agenda-chip"]')).toHaveLength(1)
    expect(cell(wrapper, '2026-11-24').findAll('[data-testid="agenda-chip"]')).toHaveLength(0)
    expect(cell(wrapper, '2026-11-12').text()).not.toContain('Indisponible')
    wrapper.unmount()
  })

  it('ajoute le titre des événements à l\'aria-label du jour', () => {
    const wrapper = mountGrid({ events: [event({})] })
    expect(cell(wrapper, '2026-11-19').attributes('aria-label')).toBe('Jeudi 19 novembre, booking Clip vidéo')
    wrapper.unmount()
  })

  it('émet select-day sur un simple clic', async () => {
    const wrapper = mountGrid()
    await cell(wrapper, '2026-11-15').trigger('click')
    expect(wrapper.emitted('select-day')).toEqual([['2026-11-15']])
    wrapper.unmount()
  })

  it('glisse de jour touchable à jour touchable en sautant les jours non touchables', async () => {
    const wrapper = mountGrid({
      events: [event({ start: '2026-11-17', end: '2026-11-17' })],
      unavailableDays: ['2026-11-16'],
    })
    await cell(wrapper, '2026-11-15').trigger('mousedown', { button: 0 })
    await cell(wrapper, '2026-11-18').trigger('mouseenter')
    await cell(wrapper, '2026-11-18').trigger('mouseup')

    expect(wrapper.emitted('select-range')).toEqual([[['2026-11-15', '2026-11-18'], false]])
    expect(wrapper.emitted('select-day')).toBeUndefined()
    wrapper.unmount()
  })

  it('ne démarre pas de glisser sur un jour passé ou engagé', async () => {
    const wrapper = mountGrid({ events: [event({ start: '2026-11-17', end: '2026-11-17' })] })
    await cell(wrapper, '2026-11-17').trigger('mousedown', { button: 0 })
    await cell(wrapper, '2026-11-20').trigger('mouseenter')
    await cell(wrapper, '2026-11-20').trigger('mouseup')
    expect(wrapper.emitted('select-range')).toBeUndefined()
    wrapper.unmount()
  })

  it('étend la sélection avec Maj + clic, depuis le dernier jour choisi', async () => {
    const wrapper = mountGrid({ selectionMode: true, selected: ['2026-11-10'] })
    await cell(wrapper, '2026-11-13').trigger('click', { shiftKey: true })
    expect(wrapper.emitted('select-range')).toEqual([
      [['2026-11-10', '2026-11-11', '2026-11-12', '2026-11-13'], true],
    ])
    wrapper.unmount()
  })
})
