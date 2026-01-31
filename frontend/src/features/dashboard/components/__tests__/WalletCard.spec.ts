import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import WalletCard from '../WalletCard.vue'

describe('WalletCard', () => {
  describe('rendering', () => {
    it('renders the card container', () => {
      const wrapper = mount(WalletCard)

      expect(wrapper.find('[data-testid="wallet-card"]').exists()).toBe(true)
    })

    it('renders the balance as "0 XOF"', () => {
      const wrapper = mount(WalletCard)

      const value = wrapper.find('[data-testid="wallet-card-value"]')
      expect(value.exists()).toBe(true)
      expect(value.text()).toBe('0')
      // XOF is in a separate span
      expect(wrapper.text()).toContain('XOF')
    })

    it('renders "Bientôt disponible" badge', () => {
      const wrapper = mount(WalletCard)

      const badge = wrapper.find('[data-testid="wallet-card-badge"]')
      expect(badge.exists()).toBe(true)
      expect(badge.text()).toBe('Bientôt disponible')
    })

    it('renders the wallet icon', () => {
      const wrapper = mount(WalletCard)

      const iconContainer = wrapper.find('[data-testid="wallet-card-icon"]')
      expect(iconContainer.exists()).toBe(true)
      // SVG icon should be rendered
      expect(iconContainer.find('svg').exists()).toBe(true)
    })

    it('renders the label "Solde portefeuille"', () => {
      const wrapper = mount(WalletCard)

      const label = wrapper.find('[data-testid="wallet-card-label"]')
      expect(label.exists()).toBe(true)
      expect(label.text()).toBe('Solde portefeuille')
    })
  })

  describe('inactive styling', () => {
    it('has opacity-75 class for muted appearance', () => {
      const wrapper = mount(WalletCard)

      const card = wrapper.find('[data-testid="wallet-card"]')
      expect(card.classes()).toContain('opacity-75')
    })

    it('has cursor-default class (no pointer cursor)', () => {
      const wrapper = mount(WalletCard)

      const card = wrapper.find('[data-testid="wallet-card"]')
      expect(card.classes()).toContain('cursor-default')
    })

    it('has select-none class to prevent text selection', () => {
      const wrapper = mount(WalletCard)

      const card = wrapper.find('[data-testid="wallet-card"]')
      expect(card.classes()).toContain('select-none')
    })
  })

  describe('no interactivity', () => {
    it('has no router-link or anchor elements', () => {
      const wrapper = mount(WalletCard)

      // Verify no navigation elements exist
      expect(wrapper.find('a').exists()).toBe(false)
      expect(wrapper.find('router-link').exists()).toBe(false)
    })

    it('has no button elements', () => {
      const wrapper = mount(WalletCard)

      // Verify no clickable button elements
      expect(wrapper.find('button').exists()).toBe(false)
    })
  })

  describe('accessibility', () => {
    it('has aria-label describing the wallet state', () => {
      const wrapper = mount(WalletCard)

      const card = wrapper.find('[data-testid="wallet-card"]')
      expect(card.attributes('aria-label')).toBe('Portefeuille: 0 XOF (Bientôt disponible)')
    })

    it('has role="region" for semantic structure', () => {
      const wrapper = mount(WalletCard)

      const card = wrapper.find('[data-testid="wallet-card"]')
      expect(card.attributes('role')).toBe('region')
    })
  })

  describe('styling consistency', () => {
    it('has white background', () => {
      const wrapper = mount(WalletCard)

      const card = wrapper.find('[data-testid="wallet-card"]')
      expect(card.classes()).toContain('bg-white')
    })

    it('has rounded-2xl border radius', () => {
      const wrapper = mount(WalletCard)

      const card = wrapper.find('[data-testid="wallet-card"]')
      expect(card.classes()).toContain('rounded-2xl')
    })

    it('has border styling', () => {
      const wrapper = mount(WalletCard)

      const card = wrapper.find('[data-testid="wallet-card"]')
      expect(card.classes()).toContain('border')
      expect(card.classes()).toContain('border-gray-100')
    })

    it('has p-4 padding', () => {
      const wrapper = mount(WalletCard)

      const card = wrapper.find('[data-testid="wallet-card"]')
      expect(card.classes()).toContain('p-4')
    })

    it('has shadow-sm for visual consistency with other cards', () => {
      const wrapper = mount(WalletCard)

      const card = wrapper.find('[data-testid="wallet-card"]')
      expect(card.classes()).toContain('shadow-sm')
    })
  })

  describe('static content (no API calls)', () => {
    it('renders without any async operations', () => {
      // This test verifies the component renders synchronously
      // without needing any data fetching
      const wrapper = mount(WalletCard)

      // Component should render immediately with all content
      expect(wrapper.find('[data-testid="wallet-card-value"]').text()).toBe('0')
      expect(wrapper.find('[data-testid="wallet-card-badge"]').text()).toBe('Bientôt disponible')
    })
  })
})
