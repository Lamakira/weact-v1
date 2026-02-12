import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import PerspectiveToggle from '../PerspectiveToggle.vue'

describe('PerspectiveToggle', () => {
  it('renders both Face and Producteur options', () => {
    const wrapper = mount(PerspectiveToggle, {
      props: {
        modelValue: 'face',
      },
    })

    expect(wrapper.text()).toContain('Face')
    expect(wrapper.text()).toContain('Producteur')
  })

  it('highlights Face option when modelValue is face', () => {
    const wrapper = mount(PerspectiveToggle, {
      props: {
        modelValue: 'face',
      },
    })

    const faceButton = wrapper.find('[data-testid="toggle-face"]')
    const producerButton = wrapper.find('[data-testid="toggle-producer"]')

    expect(faceButton.classes()).toContain('bg-black')
    expect(producerButton.classes()).not.toContain('bg-black')
  })

  it('highlights Producteur option when modelValue is producer', () => {
    const wrapper = mount(PerspectiveToggle, {
      props: {
        modelValue: 'producer',
      },
    })

    const faceButton = wrapper.find('[data-testid="toggle-face"]')
    const producerButton = wrapper.find('[data-testid="toggle-producer"]')

    expect(faceButton.classes()).not.toContain('bg-black')
    expect(producerButton.classes()).toContain('bg-black')
  })

  it('emits update:modelValue with producer when Producteur is clicked', async () => {
    const wrapper = mount(PerspectiveToggle, {
      props: {
        modelValue: 'face',
      },
    })

    await wrapper.find('[data-testid="toggle-producer"]').trigger('click')

    expect(wrapper.emitted('update:modelValue')).toBeTruthy()
    expect(wrapper.emitted('update:modelValue')![0]).toEqual(['producer'])
  })

  it('emits update:modelValue with face when Face is clicked', async () => {
    const wrapper = mount(PerspectiveToggle, {
      props: {
        modelValue: 'producer',
      },
    })

    await wrapper.find('[data-testid="toggle-face"]').trigger('click')

    expect(wrapper.emitted('update:modelValue')).toBeTruthy()
    expect(wrapper.emitted('update:modelValue')![0]).toEqual(['face'])
  })

  it('has proper accessibility attributes', () => {
    const wrapper = mount(PerspectiveToggle, {
      props: {
        modelValue: 'face',
      },
    })

    const container = wrapper.find('[role="tablist"]')
    expect(container.exists()).toBe(true)
    expect(container.attributes('aria-label')).toBe('Choisir votre profil')

    const faceTab = wrapper.find('[data-testid="toggle-face"]')
    const producerTab = wrapper.find('[data-testid="toggle-producer"]')

    expect(faceTab.attributes('role')).toBe('tab')
    expect(producerTab.attributes('role')).toBe('tab')

    expect(faceTab.attributes('aria-selected')).toBe('true')
    expect(producerTab.attributes('aria-selected')).toBe('false')
  })

  it('supports keyboard navigation with arrow keys', async () => {
    const wrapper = mount(PerspectiveToggle, {
      props: {
        modelValue: 'face',
      },
    })

    const faceTab = wrapper.find('[data-testid="toggle-face"]')

    // Press right arrow to move to producer
    await faceTab.trigger('keydown', { key: 'ArrowRight' })
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['producer'])
  })

  it('cycles back when pressing arrow at the end', async () => {
    const wrapper = mount(PerspectiveToggle, {
      props: {
        modelValue: 'producer',
      },
    })

    const producerTab = wrapper.find('[data-testid="toggle-producer"]')

    // Press right arrow should cycle back to face
    await producerTab.trigger('keydown', { key: 'ArrowRight' })
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['face'])
  })

  it('has correct tabindex for keyboard navigation', () => {
    const wrapper = mount(PerspectiveToggle, {
      props: {
        modelValue: 'face',
      },
    })

    const faceTab = wrapper.find('[data-testid="toggle-face"]')
    const producerTab = wrapper.find('[data-testid="toggle-producer"]')

    expect(faceTab.attributes('tabindex')).toBe('0')
    expect(producerTab.attributes('tabindex')).toBe('-1')
  })

  it('displays Je suis: label', () => {
    const wrapper = mount(PerspectiveToggle, {
      props: {
        modelValue: 'face',
      },
    })

    expect(wrapper.text()).toContain('Je suis')
  })
})
