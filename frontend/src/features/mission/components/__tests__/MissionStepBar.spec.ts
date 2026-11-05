import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import MissionStepBar from '../MissionStepBar.vue'

vi.stubGlobal('requestAnimationFrame', (callback: FrameRequestCallback) => {
  callback(0)
  return 0
})

const steps = [
  { key: 'recruiting', label: 'Candidatures' },
  { key: 'payment', label: 'Paiement' },
  { key: 'done', label: 'Terminée' },
]

describe('MissionStepBar', () => {
  it('coche toutes les étapes quand la mission est terminée', () => {
    const wrapper = mount(MissionStepBar, { props: { steps, current: steps.length } })
    const items = wrapper.findAll('li')

    expect(items.map((item) => item.attributes('data-state'))).toEqual(['done', 'done', 'done'])
    expect(wrapper.find('[aria-current="step"]').exists()).toBe(false)
    expect(items[2]?.findAll('span')[1]?.classes()).toContain('font-semibold')
    expect(items[2]?.findAll('span')[1]?.classes()).not.toContain('max-sm:hidden')
  })
})
