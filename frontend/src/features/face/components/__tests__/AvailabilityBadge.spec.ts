import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import AvailabilityBadge from '../AvailabilityBadge.vue'

describe('AvailabilityBadge', () => {
  describe('rendering', () => {
    it('renders the badge container', () => {
      const wrapper = mount(AvailabilityBadge, {
        props: {
          isAvailable: true,
        },
      })

      expect(wrapper.find('[data-testid="availability-badge"]').exists()).toBe(true)
    })

    it('renders the status dot', () => {
      const wrapper = mount(AvailabilityBadge, {
        props: {
          isAvailable: true,
        },
      })

      expect(wrapper.find('[data-testid="availability-badge-dot"]').exists()).toBe(true)
    })

    it('renders the status text', () => {
      const wrapper = mount(AvailabilityBadge, {
        props: {
          isAvailable: true,
        },
      })

      expect(wrapper.find('[data-testid="availability-badge-text"]').exists()).toBe(true)
    })
  })

  describe('available state', () => {
    it('displays "Disponible" when isAvailable is true', () => {
      const wrapper = mount(AvailabilityBadge, {
        props: {
          isAvailable: true,
        },
      })

      const text = wrapper.find('[data-testid="availability-badge-text"]')
      expect(text.text()).toBe('Disponible')
    })

    it('applies green styling when available', () => {
      const wrapper = mount(AvailabilityBadge, {
        props: {
          isAvailable: true,
        },
      })

      const badge = wrapper.find('[data-testid="availability-badge"]')
      expect(badge.classes()).toContain('bg-green-100')
      expect(badge.classes()).toContain('text-green-800')
    })

    it('applies green color to dot when available', () => {
      const wrapper = mount(AvailabilityBadge, {
        props: {
          isAvailable: true,
        },
      })

      const dot = wrapper.find('[data-testid="availability-badge-dot"]')
      expect(dot.classes()).toContain('bg-green-500')
    })
  })

  describe('unavailable state', () => {
    it('displays "Indisponible" when isAvailable is false', () => {
      const wrapper = mount(AvailabilityBadge, {
        props: {
          isAvailable: false,
        },
      })

      const text = wrapper.find('[data-testid="availability-badge-text"]')
      expect(text.text()).toBe('Indisponible')
    })

    it('applies gray styling when unavailable', () => {
      const wrapper = mount(AvailabilityBadge, {
        props: {
          isAvailable: false,
        },
      })

      const badge = wrapper.find('[data-testid="availability-badge"]')
      expect(badge.classes()).toContain('bg-gray-100')
      expect(badge.classes()).toContain('text-gray-600')
    })

    it('applies gray color to dot when unavailable', () => {
      const wrapper = mount(AvailabilityBadge, {
        props: {
          isAvailable: false,
        },
      })

      const dot = wrapper.find('[data-testid="availability-badge-dot"]')
      expect(dot.classes()).toContain('bg-gray-400')
    })
  })

  describe('styling', () => {
    it('has rounded-full class for pill shape', () => {
      const wrapper = mount(AvailabilityBadge, {
        props: {
          isAvailable: true,
        },
      })

      const badge = wrapper.find('[data-testid="availability-badge"]')
      expect(badge.classes()).toContain('rounded-full')
    })

    it('has inline-flex class for proper alignment', () => {
      const wrapper = mount(AvailabilityBadge, {
        props: {
          isAvailable: true,
        },
      })

      const badge = wrapper.find('[data-testid="availability-badge"]')
      expect(badge.classes()).toContain('inline-flex')
    })

    it('has transition-colors class for smooth color changes', () => {
      const wrapper = mount(AvailabilityBadge, {
        props: {
          isAvailable: true,
        },
      })

      const badge = wrapper.find('[data-testid="availability-badge"]')
      expect(badge.classes()).toContain('transition-colors')
    })
  })
})
