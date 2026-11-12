import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import MonthCalendar from '../components/MonthCalendar.vue'
import type { DayState } from '../types'

const TODAY = '2026-11-08'

function mountCalendar(props: Record<string, unknown> = {}) {
  return mount(MonthCalendar, { props: { month: '2026-11-01', today: TODAY, ...props } })
}

function cell(wrapper: ReturnType<typeof mountCalendar>, iso: string) {
  return wrapper.get(`[data-testid="day-${iso}"]`)
}

describe('MonthCalendar', () => {
  it('affiche la semaine du lundi au dimanche et 42 cases pour novembre 2026', () => {
    const wrapper = mountCalendar()
    const headers = wrapper.findAll('[role="columnheader"]').map((h) => h.text())
    expect(headers).toEqual(['L', 'M', 'M', 'J', 'V', 'S', 'D'])
    expect(wrapper.findAll('[role="gridcell"]')).toHaveLength(42)
    expect(wrapper.get('[data-testid="day-outside-2026-10-26"]').classes()).toContain('text-slate-300')
  })

  it('applique la priorité booking > mission > en attente > terminé > bloqué', () => {
    const all: DayState[] = ['blocked', 'done', 'pending', 'mission', 'booking']
    const states: Record<string, DayState[]> = {
      '2026-11-10': [...all],
      '2026-11-11': all.filter((s) => s !== 'booking'),
      '2026-11-12': all.filter((s) => s !== 'booking' && s !== 'mission'),
      '2026-11-13': ['blocked', 'done'],
      '2026-11-14': ['blocked'],
    }
    const wrapper = mountCalendar({ states })
    const inner = (iso: string) => cell(wrapper, iso).get('span')

    expect(inner('2026-11-10').classes()).toContain('bg-teal-600')
    expect(inner('2026-11-11').classes()).toContain('bg-violet-700')
    expect(inner('2026-11-12').classes()).toContain('border-amber-500')
    expect(inner('2026-11-13').classes()).toContain('bg-teal-100')
    expect(inner('2026-11-14').classes()).toEqual(expect.arrayContaining(['bg-slate-200', 'line-through']))
  })

  it('marque aujourd\'hui d\'un anneau et décrit l\'état dans l\'aria-label', () => {
    const wrapper = mountCalendar({ states: { '2026-11-19': ['booking'] } })
    expect(cell(wrapper, TODAY).get('span').classes()).toContain('ring-slate-900')
    expect(cell(wrapper, '2026-11-19').attributes('aria-label')).toBe('Jeudi 19 novembre, booking')
  })

  it('émet select-day en mode normal, jours passés compris', async () => {
    const wrapper = mountCalendar()
    await cell(wrapper, '2026-11-03').trigger('click')
    await cell(wrapper, '2026-11-20').trigger('click')
    expect(wrapper.emitted('select-day')).toEqual([['2026-11-03'], ['2026-11-20']])
  })

  it('en mode sélection, ignore les jours passés, bloqués et engagés', async () => {
    const wrapper = mountCalendar({
      selectionMode: true,
      selected: ['2026-11-25'],
      states: {
        '2026-11-12': ['blocked'],
        '2026-11-18': ['booking'],
        '2026-11-24': ['mission'],
      },
    })
    for (const iso of ['2026-11-03', '2026-11-12', '2026-11-18', '2026-11-24']) {
      expect(cell(wrapper, iso).attributes('disabled')).toBeDefined()
      await cell(wrapper, iso).trigger('click')
    }
    expect(wrapper.emitted('select-day')).toBeUndefined()

    // Aujourd'hui et un jour libre futur sont touchables.
    await cell(wrapper, TODAY).trigger('click')
    await cell(wrapper, '2026-11-20').trigger('click')
    expect(wrapper.emitted('select-day')).toEqual([[TODAY], ['2026-11-20']])

    expect(cell(wrapper, '2026-11-03').get('span').classes()).toEqual(
      expect.arrayContaining(['bg-slate-200', 'text-slate-400']),
    )
    const selected = cell(wrapper, '2026-11-25')
    expect(selected.attributes('aria-pressed')).toBe('true')
    expect(selected.get('span').classes()).toEqual(expect.arrayContaining(['bg-amber-100', 'border-amber-600']))
    expect(cell(wrapper, '2026-11-20').attributes('aria-pressed')).toBe('false')
  })

  it('en mode sélection, une demande en attente n\'engage rien : le jour reste touchable', async () => {
    const wrapper = mountCalendar({ selectionMode: true, states: { '2026-11-26': ['pending'] } })
    expect(cell(wrapper, '2026-11-26').attributes('disabled')).toBeUndefined()
    await cell(wrapper, '2026-11-26').trigger('click')
    expect(wrapper.emitted('select-day')).toEqual([['2026-11-26']])
  })

  it('respecte l\'horizon de blocage', () => {
    const wrapper = mountCalendar({ selectionMode: true, horizonEnd: '2026-11-15' })
    expect(cell(wrapper, '2026-11-15').attributes('disabled')).toBeUndefined()
    expect(cell(wrapper, '2026-11-16').attributes('disabled')).toBeDefined()
  })

  it('en lecture seule, ne rend aucun bouton : libre en bordure fine, passé en gris clair', () => {
    const wrapper = mountCalendar({
      interactive: false,
      readonlyStyle: true,
      states: { '2026-11-12': ['blocked'] },
    })
    expect(wrapper.findAll('button')).toHaveLength(0)
    expect(cell(wrapper, '2026-11-20').get('span').classes()).toContain('border-slate-200')
    expect(cell(wrapper, '2026-11-20').attributes('aria-label')).toBe('Vendredi 20 novembre, libre')
    expect(cell(wrapper, '2026-11-03').get('span').classes()).toContain('text-slate-300')
    expect(cell(wrapper, '2026-11-12').get('span').classes()).toContain('bg-slate-200')
  })
})
