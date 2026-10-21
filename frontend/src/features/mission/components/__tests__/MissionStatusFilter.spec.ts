import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import MissionStatusFilter from '../MissionStatusFilter.vue'

// Compteurs d'actions (lot D3) : store remplacé par un état réactif piloté par les tests.
const countsHolder = vi.hoisted(() => ({ counts: null as unknown as Record<string, number> }))
vi.mock('@/stores/actionCounts', async () => {
  const { reactive } = await import('vue')
  countsHolder.counts = reactive({ bookings: 0, candidatures: 0, missions: 0, livrables: 0, messages: 0 })
  return { useActionCountsStore: () => ({ counts: countsHolder.counts }) }
})

describe('MissionStatusFilter (lot D7)', () => {
  beforeEach(() => {
    countsHolder.counts.missions = 0
  })

  it('place la pastille « À traiter » juste après « Tous »', () => {
    const wrapper = mount(MissionStatusFilter, { props: { modelValue: '' } })

    expect(wrapper.findAll('button').map((b) => b.text()).slice(0, 3)).toEqual(['Tous', 'À traiter', 'Publiée'])
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
})
