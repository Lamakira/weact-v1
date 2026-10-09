import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import RPill from '../RPill.vue'
import RPillGroup from '../RPillGroup.vue'

describe('RPill', () => {
  it('expose aria-pressed selon pressed', () => {
    expect(mount(RPill, { slots: { default: 'Toutes' } }).attributes('aria-pressed')).toBe('false')
    expect(mount(RPill, { props: { pressed: true }, slots: { default: 'Toutes' } }).attributes('aria-pressed')).toBe('true')
  })

  it('est un vrai bouton type=button et affiche le compteur', () => {
    const wrapper = mount(RPill, { props: { count: 4 }, slots: { default: 'Non lues' } })
    expect(wrapper.element.tagName).toBe('BUTTON')
    expect(wrapper.attributes('type')).toBe('button')
    expect(wrapper.text()).toContain('Non lues')
    expect(wrapper.text()).toContain('4')
  })
})

describe('RPillGroup', () => {
  const options = [
    { value: 'all', label: 'Toutes' },
    { value: 'unread', label: 'Non lues', count: 2 },
  ]

  it('rend un groupe nommé avec la pilule active pressée', () => {
    const wrapper = mount(RPillGroup, { props: { options, groupLabel: 'Filtrer', modelValue: 'unread' } })
    const group = wrapper.find('[role="group"]')
    expect(group.attributes('aria-label')).toBe('Filtrer')
    const pills = wrapper.findAll('button')
    expect(pills.map((p) => p.attributes('aria-pressed'))).toEqual(['false', 'true'])
  })

  it('émet update:modelValue au clic et au clavier (Entrée/Espace = clic natif)', async () => {
    const wrapper = mount(RPillGroup, { props: { options, groupLabel: 'Filtrer', modelValue: 'all' } })
    await wrapper.findAll('button')[1]!.trigger('click')
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['unread'])
  })
})
