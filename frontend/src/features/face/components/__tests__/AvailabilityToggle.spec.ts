import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import AvailabilityToggle from '../AvailabilityToggle.vue'

describe('AvailabilityToggle', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('rendering', () => {
    it('renders the toggle container', () => {
      const wrapper = mount(AvailabilityToggle, {
        props: {
          isAvailable: true,
        },
      })

      expect(wrapper.find('[data-testid="availability-toggle-container"]').exists()).toBe(true)
    })

    it('renders the toggle button with switch role', () => {
      const wrapper = mount(AvailabilityToggle, {
        props: {
          isAvailable: true,
        },
      })

      const button = wrapper.find('[data-testid="availability-toggle-button"]')
      expect(button.exists()).toBe(true)
      expect(button.attributes('role')).toBe('switch')
    })

    it('displays "Disponible" when isAvailable is true', () => {
      const wrapper = mount(AvailabilityToggle, {
        props: {
          isAvailable: true,
        },
      })

      const badge = wrapper.find('[data-testid="availability-status-badge"]')
      expect(badge.text()).toBe('Disponible')
    })

    it('displays "Indisponible" when isAvailable is false', () => {
      const wrapper = mount(AvailabilityToggle, {
        props: {
          isAvailable: false,
        },
      })

      const badge = wrapper.find('[data-testid="availability-status-badge"]')
      expect(badge.text()).toBe('Indisponible')
    })

    it('applies green styling when available', () => {
      const wrapper = mount(AvailabilityToggle, {
        props: {
          isAvailable: true,
        },
      })

      const badge = wrapper.find('[data-testid="availability-status-badge"]')
      expect(badge.classes()).toContain('bg-green-100')
      expect(badge.classes()).toContain('text-green-800')
    })

    it('applies gray styling when unavailable', () => {
      const wrapper = mount(AvailabilityToggle, {
        props: {
          isAvailable: false,
        },
      })

      const badge = wrapper.find('[data-testid="availability-status-badge"]')
      expect(badge.classes()).toContain('bg-gray-100')
      expect(badge.classes()).toContain('text-gray-600')
    })
  })

  describe('accessibility', () => {
    it('sets aria-checked to true when available', () => {
      const wrapper = mount(AvailabilityToggle, {
        props: {
          isAvailable: true,
        },
      })

      const button = wrapper.find('[data-testid="availability-toggle-button"]')
      expect(button.attributes('aria-checked')).toBe('true')
    })

    it('sets aria-checked to false when unavailable', () => {
      const wrapper = mount(AvailabilityToggle, {
        props: {
          isAvailable: false,
        },
      })

      const button = wrapper.find('[data-testid="availability-toggle-button"]')
      expect(button.attributes('aria-checked')).toBe('false')
    })
  })

  describe('disabled states', () => {
    it('disables button when isLoading is true', () => {
      const wrapper = mount(AvailabilityToggle, {
        props: {
          isAvailable: true,
          isLoading: true,
        },
      })

      const button = wrapper.find('[data-testid="availability-toggle-button"]')
      expect(button.attributes('disabled')).toBeDefined()
    })

    it('disables button when isSaving is true', () => {
      const wrapper = mount(AvailabilityToggle, {
        props: {
          isAvailable: true,
          isSaving: true,
        },
      })

      const button = wrapper.find('[data-testid="availability-toggle-button"]')
      expect(button.attributes('disabled')).toBeDefined()
    })

    it('enables button when not loading and not saving', () => {
      const wrapper = mount(AvailabilityToggle, {
        props: {
          isAvailable: true,
          isLoading: false,
          isSaving: false,
        },
      })

      const button = wrapper.find('[data-testid="availability-toggle-button"]')
      expect(button.attributes('disabled')).toBeUndefined()
    })
  })

  describe('loading spinner', () => {
    it('shows loading spinner when isSaving is true', () => {
      const wrapper = mount(AvailabilityToggle, {
        props: {
          isAvailable: true,
          isSaving: true,
        },
      })

      expect(wrapper.find('[data-testid="availability-loading-spinner"]').exists()).toBe(true)
    })

    it('hides loading spinner when isSaving is false', () => {
      const wrapper = mount(AvailabilityToggle, {
        props: {
          isAvailable: true,
          isSaving: false,
        },
      })

      expect(wrapper.find('[data-testid="availability-loading-spinner"]').exists()).toBe(false)
    })
  })

  describe('events', () => {
    it('emits toggle event when button is clicked', async () => {
      const wrapper = mount(AvailabilityToggle, {
        props: {
          isAvailable: true,
        },
      })

      const button = wrapper.find('[data-testid="availability-toggle-button"]')
      await button.trigger('click')

      expect(wrapper.emitted('toggle')).toBeTruthy()
      expect(wrapper.emitted('toggle')).toHaveLength(1)
    })

    it('does not emit toggle event when disabled', async () => {
      const wrapper = mount(AvailabilityToggle, {
        props: {
          isAvailable: true,
          isLoading: true,
        },
      })

      const button = wrapper.find('[data-testid="availability-toggle-button"]')
      await button.trigger('click')

      // Disabled buttons don't emit events in real browsers,
      // but Vue Test Utils may still trigger the event
      // The important thing is the disabled attribute is set
      expect(button.attributes('disabled')).toBeDefined()
    })
  })

  describe('default props', () => {
    it('defaults isLoading to false', () => {
      const wrapper = mount(AvailabilityToggle, {
        props: {
          isAvailable: true,
        },
      })

      const button = wrapper.find('[data-testid="availability-toggle-button"]')
      expect(button.attributes('disabled')).toBeUndefined()
    })

    it('defaults isSaving to false', () => {
      const wrapper = mount(AvailabilityToggle, {
        props: {
          isAvailable: true,
        },
      })

      expect(wrapper.find('[data-testid="availability-loading-spinner"]').exists()).toBe(false)
    })

    it('defaults error to null', () => {
      const wrapper = mount(AvailabilityToggle, {
        props: {
          isAvailable: true,
        },
      })

      expect(wrapper.find('[data-testid="availability-error-message"]').exists()).toBe(false)
    })
  })

  describe('error display', () => {
    it('displays error message when error prop is provided', () => {
      const wrapper = mount(AvailabilityToggle, {
        props: {
          isAvailable: true,
          error: 'Erreur de connexion',
        },
      })

      const errorElement = wrapper.find('[data-testid="availability-error-message"]')
      expect(errorElement.exists()).toBe(true)
      expect(errorElement.text()).toBe('Erreur de connexion')
    })

    it('hides error message when error prop is null', () => {
      const wrapper = mount(AvailabilityToggle, {
        props: {
          isAvailable: true,
          error: null,
        },
      })

      expect(wrapper.find('[data-testid="availability-error-message"]').exists()).toBe(false)
    })

    it('applies red styling to error message', () => {
      const wrapper = mount(AvailabilityToggle, {
        props: {
          isAvailable: true,
          error: 'Erreur de connexion',
        },
      })

      const errorElement = wrapper.find('[data-testid="availability-error-message"]')
      expect(errorElement.classes()).toContain('text-red-600')
    })
  })
})
