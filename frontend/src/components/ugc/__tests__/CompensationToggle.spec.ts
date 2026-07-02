import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import CompensationToggle from '../CompensationToggle.vue'
import type { UgcCompensationType } from '../ugc'

const mountToggle = (modelValue: UgcCompensationType = 'product') =>
  mount(CompensationToggle, { props: { modelValue } })

describe('CompensationToggle', () => {
  it('renders both compensation options', () => {
    const wrapper = mountToggle()
    expect(wrapper.text()).toContain('Produit seul')
    expect(wrapper.text()).toContain('Produit + argent')
  })

  it('emits update:modelValue with "hybrid" when the hybrid option is clicked', async () => {
    const wrapper = mountToggle('product')

    await wrapper.find('[data-testid="compensation-hybrid"]').trigger('click')

    const emitted = wrapper.emitted('update:modelValue')
    expect(emitted).toBeTruthy()
    expect(emitted![0]).toEqual(['hybrid'])
  })

  it('marks the active option with the white/shadow state', () => {
    const wrapper = mountToggle('product')

    const productBtn = wrapper.find('[data-testid="compensation-product"]')
    const hybridBtn = wrapper.find('[data-testid="compensation-hybrid"]')

    expect(productBtn.classes()).toContain('bg-white')
    expect(productBtn.attributes('aria-pressed')).toBe('true')
    expect(hybridBtn.classes()).not.toContain('bg-white')
  })
})
