import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import RSettingsSection from '../RSettingsSection.vue'
import RStickySaveBar from '../RStickySaveBar.vue'

describe('RSettingsSection', () => {
  it('rend titre + description à gauche et champs à droite, empilés sous md', () => {
    const wrapper = mount(RSettingsSection, {
      props: { title: 'Identité', description: 'Visible par les Faces' },
      slots: { default: '<input aria-label="Nom" />' },
    })
    expect(wrapper.find('h3').text()).toBe('Identité')
    expect(wrapper.text()).toContain('Visible par les Faces')
    expect(wrapper.find('input').exists()).toBe(true)
    expect(wrapper.classes().join(' ')).toContain('md:grid-cols-')
    expect(wrapper.classes()).not.toContain('grid-cols-2')
  })

  it('omet la description si absente', () => {
    const wrapper = mount(RSettingsSection, { props: { title: 'Sécurité' } })
    expect(wrapper.findAll('p')).toHaveLength(0)
  })
})

describe('RStickySaveBar', () => {
  it('est absente tant que rien n’est modifié', () => {
    const wrapper = mount(RStickySaveBar, { props: { dirty: false } })
    expect(wrapper.find('[data-testid="r-sticky-save-bar"]').exists()).toBe(false)
  })

  it('apparaît quand dirty, collante en bas, avec message accessible', async () => {
    const wrapper = mount(RStickySaveBar, { props: { dirty: false } })
    await wrapper.setProps({ dirty: true })
    const bar = wrapper.find('[data-testid="r-sticky-save-bar"]')
    expect(bar.exists()).toBe(true)
    expect(bar.classes()).toEqual(expect.arrayContaining(['sticky', 'bottom-0']))
    expect(bar.attributes('role')).toBe('region')
    expect(bar.find('[role="status"]').attributes('aria-live')).toBe('polite')
    expect(bar.find('[role="status"]').text()).toBe('Modifications non enregistrées')
  })

  it('émet cancel et save', async () => {
    const wrapper = mount(RStickySaveBar, { props: { dirty: true } })
    await wrapper.find('[data-testid="r-save-cancel"]').trigger('click')
    await wrapper.find('[data-testid="r-save-submit"]').trigger('click')
    expect(wrapper.emitted('cancel')).toHaveLength(1)
    expect(wrapper.emitted('save')).toHaveLength(1)
  })

  it('désactive les boutons pendant l’enregistrement', () => {
    const wrapper = mount(RStickySaveBar, { props: { dirty: true, saving: true } })
    expect(wrapper.find('[data-testid="r-save-cancel"]').attributes('disabled')).toBeDefined()
    const submit = wrapper.find('[data-testid="r-save-submit"]')
    expect(submit.attributes('disabled')).toBeDefined()
    expect(submit.attributes('aria-busy')).toBe('true')
  })
})
