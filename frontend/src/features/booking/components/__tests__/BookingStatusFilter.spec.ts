import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import BookingStatusFilter from '../BookingStatusFilter.vue'

// Compteurs d'actions (lot D3) : store remplacé par un état réactif piloté par les tests.
const countsHolder = vi.hoisted(() => ({ counts: null as unknown as Record<string, number> }))
vi.mock('@/stores/actionCounts', async () => {
  const { reactive } = await import('vue')
  countsHolder.counts = reactive({ bookings: 0, candidatures: 0, missions: 0, livrables: 0, messages: 0 })
  return { useActionCountsStore: () => ({ counts: countsHolder.counts }) }
})

let wrapper: VueWrapper | null = null

async function openMore(w: VueWrapper): Promise<void> {
  await w.find('[data-testid="filter-more-trigger"]').trigger('keydown', { key: 'Enter' })
  await flushPromises()
}

function moreLabels(): string[] {
  return Array.from(document.body.querySelectorAll('[role="menuitem"]')).map((el) => el.textContent!.trim())
}

afterEach(() => {
  wrapper?.unmount()
  wrapper = null
  document.body.innerHTML = ''
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

  // Lot F4 : filtres réduits, statuts rares dans « Plus »
  it('F4 : 4 pastilles visibles (Tous, À traiter, En cours, Terminés) et un bouton « Plus »', () => {
    const w = mount(BookingStatusFilter, { props: { modelValue: '' } })

    expect(w.findAll('button').map((b) => b.text())).toEqual(['Tous', 'À traiter', 'En cours', 'Terminés', 'Plus'])
  })

  it('F4 : le menu « Plus » liste les statuts rares et émet la valeur choisie', async () => {
    wrapper = mount(BookingStatusFilter, { props: { modelValue: '' }, attachTo: document.body })
    await openMore(wrapper)

    expect(moreLabels()).toEqual(['En attente', 'Annulés et refusés'])

    ;(document.body.querySelector('[data-testid="filter-more-cancelled"]') as HTMLElement).click()
    await flushPromises()
    expect(wrapper.emitted('update:modelValue')).toEqual([['cancelled']])
  })

  it('F4 : le filtre choisi dans « Plus » s\'affiche dans le bouton', () => {
    const w = mount(BookingStatusFilter, { props: { modelValue: 'cancelled' } })

    expect(w.get('[data-testid="filter-more-trigger"]').text()).toBe('Plus : Annulés et refusés')
    expect(w.get('[data-testid="filter-more-trigger"]').classes()).toContain('bg-primary')
  })
})
