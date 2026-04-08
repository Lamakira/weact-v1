import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import WalletCard from '../WalletCard.vue'

function mountCard(props: Record<string, unknown> = {}) {
  return mount(WalletCard, {
    props,
    global: {
      stubs: {
        RouterLink: {
          template: '<a v-bind="$attrs" :href="typeof to === \'string\' ? to : to.name"><slot /></a>',
          props: ['to'],
          inheritAttrs: false,
        },
      },
    },
  })
}

describe('WalletCard', () => {
  it('renders the card container, icon, label, and default balance', () => {
    const wrapper = mountCard()

    expect(wrapper.find('[data-testid="wallet-card"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="wallet-card-icon"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="wallet-card-label"]').text()).toBe('Solde portefeuille')
    expect(wrapper.find('[data-testid="wallet-card-value"]').text()).toBe('0')
    expect(wrapper.text()).toContain('XOF')
  })

  it('renders as a link to the wallet page', () => {
    const wrapper = mountCard()

    expect(wrapper.find('[data-testid="wallet-card"]').attributes('href')).toBe('face-wallet')
  })

  it('uses the current aria-label and interactive styling', () => {
    const wrapper = mountCard()
    const card = wrapper.find('[data-testid="wallet-card"]')

    expect(card.attributes('aria-label')).toBe('Portefeuille: 0 XOF')
    expect(card.classes()).toContain('bg-white')
    expect(card.classes()).toContain('rounded-2xl')
    expect(card.classes()).toContain('shadow-sm')
  })

  it('formats a custom balance for display', () => {
    const wrapper = mountCard({ balance: 1250000 })

    expect(wrapper.find('[data-testid="wallet-card-value"]').text()).toBe('1 250 000')
  })
})
