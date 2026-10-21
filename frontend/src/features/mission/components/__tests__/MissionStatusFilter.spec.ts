import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import MissionStatusFilter from '../MissionStatusFilter.vue'

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

describe('MissionStatusFilter (lot D7)', () => {
  beforeEach(() => {
    countsHolder.counts.missions = 0
  })

  it('place la pastille « À traiter » juste après « Tous »', () => {
    const wrapper = mount(MissionStatusFilter, { props: { modelValue: '' } })

    expect(wrapper.findAll('button').map((b) => b.text()).slice(0, 3)).toEqual(['Tous', 'À traiter', 'En cours'])
  })

  it('émet to_process au clic et marque la pastille active', async () => {
    const wrapper = mount(MissionStatusFilter, { props: { modelValue: 'to_process' } })
    const pill = wrapper.findAll('button').find((b) => b.text() === 'À traiter')!

    expect(pill.classes()).toContain('bg-primary')

    await wrapper.findAll('button').find((b) => b.text() === 'Tous')!.trigger('click')
    await pill.trigger('click')
    expect(wrapper.emitted('update:modelValue')).toEqual([[''], ['to_process']])
  })

  it('pastille « À traiter » : nombre de missions à traiter du store, rien quand c\'est 0', () => {
    const none = mount(MissionStatusFilter, { props: { modelValue: '' } })
    expect(none.find('[data-testid="to-process-count"]').exists()).toBe(false)

    countsHolder.counts.missions = 3
    const wrapper = mount(MissionStatusFilter, { props: { modelValue: '' } })
    const counts = wrapper.findAll('[data-testid="to-process-count"]')
    expect(counts).toHaveLength(1)
    expect(counts[0]!.text()).toBe('3')
    expect(wrapper.findAll('button').find((b) => b.text().startsWith('À traiter'))!.find('[data-testid="to-process-count"]').exists()).toBe(true)
  })

  // Lot F4 : filtres réduits, statuts précis dans « Plus »
  it('F4 : 4 pastilles visibles (Tous, À traiter, En cours, Terminées) et un bouton « Plus »', () => {
    const w = mount(MissionStatusFilter, { props: { modelValue: '' } })

    expect(w.findAll('button').map((b) => b.text())).toEqual(['Tous', 'À traiter', 'En cours', 'Terminées', 'Plus'])
  })

  it('F4 : le menu « Plus » liste les statuts précis et émet la valeur choisie', async () => {
    wrapper = mount(MissionStatusFilter, { props: { modelValue: '' }, attachTo: document.body })
    await openMore(wrapper)

    expect(moreLabels()).toEqual(['Publiées', 'Recrutement clos'])

    ;(document.body.querySelector('[data-testid="filter-more-closed"]') as HTMLElement).click()
    await flushPromises()
    expect(wrapper.emitted('update:modelValue')).toEqual([['closed']])
  })

  it('F4 : « En cours » émet le groupe active ; le filtre de « Plus » s\'affiche dans le bouton', async () => {
    const w = mount(MissionStatusFilter, { props: { modelValue: 'closed' } })
    expect(w.get('[data-testid="filter-more-trigger"]').text()).toBe('Plus : Recrutement clos')

    await w.findAll('button').find((b) => b.text() === 'En cours')!.trigger('click')
    expect(w.emitted('update:modelValue')).toEqual([['active']])
  })
})
