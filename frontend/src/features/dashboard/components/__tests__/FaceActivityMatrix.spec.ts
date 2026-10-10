import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import FaceActivityMatrix from '../FaceActivityMatrix.vue'
import type { BookingMonthlyStats, MonthlyStats } from '../../types'

vi.mock('vue-router', () => ({
  RouterLink: { template: '<a><slot /></a>', props: ['to'] },
}))

function cand(month: string, v: Partial<MonthlyStats>): MonthlyStats {
  return { month, pending: 0, accepted: 0, confirmed: 0, in_progress: 0, completed: 0, rejected: 0, ...v }
}
function book(month: string, v: Partial<BookingMonthlyStats>): BookingMonthlyStats {
  return { month, pending: 0, accepted: 0, in_progress: 0, completed: 0, ...v }
}

const stats = { pending: 1, accepted: 2, in_progress: 3, completed: 4 }

function cell(wrapper: ReturnType<typeof mount>, row: string, column: string) {
  return wrapper.find(`[data-testid="r-kpi-table"] [data-row="${row}"] [data-cell="${column}"]`)
}

describe('FaceActivityMatrix', () => {
  it('affiche un delta négatif avec un vrai signe moins', () => {
    const wrapper = mount(FaceActivityMatrix, {
      props: {
        stats,
        bookingStats: stats,
        candidaturesByMonth: [cand('2026-09', { pending: 4 }), cand('2026-10', { pending: 1 })],
      },
    })

    expect(cell(wrapper, 'candidatures', 'pending').text()).toContain('−3 vs mois dernier')
  })

  it('classe une candidature « confirmed » dans « en cours » pour le delta', () => {
    const wrapper = mount(FaceActivityMatrix, {
      props: {
        stats,
        bookingStats: stats,
        candidaturesByMonth: [cand('2026-09', {}), cand('2026-10', { confirmed: 1, in_progress: 1 })],
      },
    })

    expect(cell(wrapper, 'candidatures', 'in_progress').text()).toContain('+2 vs mois dernier')
  })

  it('n’affiche aucun delta sans deux mois de série ni quand tout est à zéro', () => {
    const wrapper = mount(FaceActivityMatrix, {
      props: {
        stats,
        bookingStats: stats,
        candidaturesByMonth: [cand('2026-10', { pending: 2 })],
        bookingsByMonth: [book('2026-09', {}), book('2026-10', {})],
      },
    })

    expect(cell(wrapper, 'candidatures', 'pending').text()).not.toContain('vs mois dernier')
    expect(cell(wrapper, 'bookings', 'pending').text()).not.toContain('vs mois dernier')
  })

  it('calcule le delta des bookings depuis leur propre série', () => {
    const wrapper = mount(FaceActivityMatrix, {
      props: {
        stats,
        bookingStats: stats,
        bookingsByMonth: [book('2026-09', { completed: 1 }), book('2026-10', { completed: 4 })],
      },
    })

    expect(cell(wrapper, 'bookings', 'completed').text()).toContain('+3 vs mois dernier')
  })

  it('limite la tendance aux 6 derniers mois (7 mois renvoyés par l’API)', () => {
    const series = ['04', '05', '06', '07', '08', '09', '10'].map((m, i) =>
      cand(`2026-${m}`, { pending: i + 1 }),
    )
    const wrapper = mount(FaceActivityMatrix, {
      props: { stats, bookingStats: stats, candidaturesByMonth: series },
    })

    const bars = wrapper.find('[data-row="candidatures"] [data-testid="r-mini-bars"]')
    expect(bars.findAll('rect')).toHaveLength(6)
    expect(bars.findAll('rect').at(-1)!.attributes('data-current')).toBe('true')
  })

  it('affiche un squelette tant que rien n’est chargé', () => {
    const wrapper = mount(FaceActivityMatrix, {
      props: { stats: null, bookingStats: null, isLoading: true },
    })

    expect(wrapper.find('[data-testid="face-activity-skeleton"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="r-kpi-matrix"]').exists()).toBe(false)
  })

  it('affiche des zéros (pas de tirets) pour une Face sans activité', () => {
    const wrapper = mount(FaceActivityMatrix, { props: { stats: null, bookingStats: null } })

    expect(cell(wrapper, 'candidatures', 'pending').text()).toContain('0')
  })
})
