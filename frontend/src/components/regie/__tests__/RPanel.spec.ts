import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import RPanel from '../RPanel.vue'

describe('RPanel', () => {
  it('rend le contenu avec ring-line, rayon panneau et sans ombre portée', () => {
    const wrapper = mount(RPanel, { slots: { default: '<p>contenu</p>' } })
    const root = wrapper.find('[data-testid="r-panel"]')
    expect(root.text()).toContain('contenu')
    expect(root.classes()).toEqual(expect.arrayContaining(['ring-1', 'ring-line', 'rounded-panel', 'bg-white']))
    expect(root.classes().some((c) => c.startsWith('shadow'))).toBe(false)
  })

  it("n'affiche pas d'en-tête ni de pied sans titre ni slots", () => {
    const wrapper = mount(RPanel, { slots: { default: 'x' } })
    expect(wrapper.find('[data-testid="r-panel-header"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="r-panel-footer"]').exists()).toBe(false)
  })

  it("affiche le titre dans un h2 par défaut et les actions à droite", () => {
    const wrapper = mount(RPanel, {
      props: { title: 'Activité' },
      slots: { actions: '<button>Filtrer</button>' },
    })
    expect(wrapper.find('h2').text()).toBe('Activité')
    expect(wrapper.find('[data-testid="r-panel-header"] button').text()).toBe('Filtrer')
  })

  it('respecte headingTag', () => {
    const wrapper = mount(RPanel, { props: { title: 'T', headingTag: 'h3' } })
    expect(wrapper.find('h3').exists()).toBe(true)
    expect(wrapper.find('h2').exists()).toBe(false)
  })

  it('rend le pied quand le slot footer est fourni', () => {
    const wrapper = mount(RPanel, { slots: { footer: 'Pied' } })
    expect(wrapper.find('[data-testid="r-panel-footer"]').text()).toBe('Pied')
  })
})
