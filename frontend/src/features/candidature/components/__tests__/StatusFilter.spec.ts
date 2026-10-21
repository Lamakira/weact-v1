import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import StatusFilter from '../StatusFilter.vue'

// Compteurs d'actions (lot D3) : store remplacé par un état réactif piloté par les tests.
const countsHolder = vi.hoisted(() => ({ counts: null as unknown as Record<string, number> }))
vi.mock('@/stores/actionCounts', async () => {
  const { reactive } = await import('vue')
  countsHolder.counts = reactive({ bookings: 0, candidatures: 0, missions: 0, livrables: 0, messages: 0 })
  return { useActionCountsStore: () => ({ counts: countsHolder.counts }) }
})

describe('StatusFilter (lot D7)', () => {
  beforeEach(() => {
    countsHolder.counts.candidatures = 0
  })

  it('Face : place la pastille « À traiter » juste après « Tous » (prop with-to-process)', () => {
    const wrapper = mount(StatusFilter, { props: { modelValue: '', withToProcess: true } })

    expect(wrapper.findAll('button').map((b) => b.text()).slice(0, 3)).toEqual(['Tous', 'À traiter', 'En attente'])
  })

  it('Producer : pas de pastille « À traiter » par défaut', () => {
    const wrapper = mount(StatusFilter, { props: { modelValue: '' } })

    expect(wrapper.findAll('button').map((b) => b.text())).not.toContain('À traiter')
  })

  it('émet to_process au clic', async () => {
    const wrapper = mount(StatusFilter, { props: { modelValue: '', withToProcess: true } })

    await wrapper.findAll('button').find((b) => b.text() === 'À traiter')!.trigger('click')
    expect(wrapper.emitted('update:modelValue')).toEqual([['to_process']])
  })

  it('pastille « À traiter » : nombre de candidatures à traiter du store, rien quand c\'est 0', () => {
    const none = mount(StatusFilter, { props: { modelValue: '', withToProcess: true } })
    expect(none.find('[data-testid="to-process-count"]').exists()).toBe(false)

    countsHolder.counts.candidatures = 2
    const wrapper = mount(StatusFilter, { props: { modelValue: '', withToProcess: true } })
    const counts = wrapper.findAll('[data-testid="to-process-count"]')
    expect(counts).toHaveLength(1)
    expect(counts[0]!.text()).toBe('2')
    expect(wrapper.findAll('button').find((b) => b.text().startsWith('À traiter'))!.find('[data-testid="to-process-count"]').exists()).toBe(true)
  })
})
