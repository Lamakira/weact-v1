import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import BookingStatusFilter from '../BookingStatusFilter.vue'

// Compteurs d'actions (lot D3) : store remplacé par un état réactif piloté par les tests.
const countsHolder = vi.hoisted(() => ({ counts: null as unknown as Record<string, number> }))
vi.mock('@/stores/actionCounts', async () => {
  const { reactive } = await import('vue')
  countsHolder.counts = reactive({ bookings: 0, candidatures: 0, missions: 0, livrables: 0, messages: 0 })
  return { useActionCountsStore: () => ({ counts: countsHolder.counts }) }
})

describe('BookingStatusFilter (lot D3)', () => {
  beforeEach(() => {
    countsHolder.counts.bookings = 0
  })

  it('aucune pastille de nombre quand rien n\'est à traiter', () => {
    const wrapper = mount(BookingStatusFilter, { props: { modelValue: '' } })

    expect(wrapper.find('[data-testid="to-process-count"]').exists()).toBe(false)
    expect(wrapper.findAll('button').map((b) => b.text()).slice(0, 2)).toEqual(['Tous', 'À traiter'])
  })

  it('affiche le nombre de bookings à traiter du store sur la seule pastille « À traiter »', () => {
    countsHolder.counts.bookings = 4
    const wrapper = mount(BookingStatusFilter, { props: { modelValue: '' } })

    const counts = wrapper.findAll('[data-testid="to-process-count"]')
    expect(counts).toHaveLength(1)
    expect(counts[0]!.text()).toBe('4')
    expect(wrapper.findAll('button')[1]!.find('[data-testid="to-process-count"]').exists()).toBe(true)
  })
})
