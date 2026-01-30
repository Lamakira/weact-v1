import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import MissionsQuickAccessCard from '../MissionsQuickAccessCard.vue'

describe('MissionsQuickAccessCard', () => {
  describe('rendering with count > 0', () => {
    it('renders the card container', () => {
      const wrapper = mount(MissionsQuickAccessCard, {
        props: {
          count: 12,
          isLoading: false,
        },
      })

      expect(wrapper.find('[data-testid="missions-quick-access-card"]').exists()).toBe(true)
    })

    it('renders the search icon', () => {
      const wrapper = mount(MissionsQuickAccessCard, {
        props: {
          count: 12,
          isLoading: false,
        },
      })

      const iconContainer = wrapper.find('[data-testid="missions-card-icon"]')
      expect(iconContainer.exists()).toBe(true)
      expect(iconContainer.find('svg').exists()).toBe(true)
    })

    it('renders the title "Voir les missions"', () => {
      const wrapper = mount(MissionsQuickAccessCard, {
        props: {
          count: 12,
          isLoading: false,
        },
      })

      const title = wrapper.find('[data-testid="missions-card-title"]')
      expect(title.exists()).toBe(true)
      expect(title.text()).toBe('Voir les missions')
    })

    it('renders the subtitle', () => {
      const wrapper = mount(MissionsQuickAccessCard, {
        props: {
          count: 12,
          isLoading: false,
        },
      })

      const subtitle = wrapper.find('[data-testid="missions-card-subtitle"]')
      expect(subtitle.exists()).toBe(true)
      expect(subtitle.text()).toBe('Découvrez les opportunités disponibles')
    })

    it('renders the count badge when count > 0', () => {
      const wrapper = mount(MissionsQuickAccessCard, {
        props: {
          count: 12,
          isLoading: false,
        },
      })

      const badge = wrapper.find('[data-testid="missions-card-badge"]')
      expect(badge.exists()).toBe(true)
      expect(badge.text()).toBe('12')
    })

    it('renders correct count value in badge', () => {
      const wrapper = mount(MissionsQuickAccessCard, {
        props: {
          count: 99,
          isLoading: false,
        },
      })

      const badge = wrapper.find('[data-testid="missions-card-badge"]')
      expect(badge.text()).toBe('99')
    })
  })

  describe('rendering with count = 0', () => {
    it('does not render count badge when count is 0', () => {
      const wrapper = mount(MissionsQuickAccessCard, {
        props: {
          count: 0,
          isLoading: false,
        },
      })

      expect(wrapper.find('[data-testid="missions-card-badge"]').exists()).toBe(false)
    })

    it('still renders the card container', () => {
      const wrapper = mount(MissionsQuickAccessCard, {
        props: {
          count: 0,
          isLoading: false,
        },
      })

      expect(wrapper.find('[data-testid="missions-quick-access-card"]').exists()).toBe(true)
    })

    it('still renders title and subtitle', () => {
      const wrapper = mount(MissionsQuickAccessCard, {
        props: {
          count: 0,
          isLoading: false,
        },
      })

      expect(wrapper.find('[data-testid="missions-card-title"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="missions-card-subtitle"]').exists()).toBe(true)
    })
  })

  describe('loading state', () => {
    it('shows loading skeleton for badge when loading', () => {
      const wrapper = mount(MissionsQuickAccessCard, {
        props: {
          count: 12,
          isLoading: true,
        },
      })

      expect(wrapper.find('[data-testid="missions-card-badge-loading"]').exists()).toBe(true)
    })

    it('does not show count badge when loading', () => {
      const wrapper = mount(MissionsQuickAccessCard, {
        props: {
          count: 12,
          isLoading: true,
        },
      })

      expect(wrapper.find('[data-testid="missions-card-badge"]').exists()).toBe(false)
    })

    it('renders title and subtitle while loading', () => {
      const wrapper = mount(MissionsQuickAccessCard, {
        props: {
          count: 0,
          isLoading: true,
        },
      })

      expect(wrapper.find('[data-testid="missions-card-title"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="missions-card-subtitle"]').exists()).toBe(true)
    })
  })

  describe('click interaction', () => {
    it('emits click event when card is clicked', async () => {
      const wrapper = mount(MissionsQuickAccessCard, {
        props: {
          count: 12,
          isLoading: false,
        },
      })

      await wrapper.find('[data-testid="missions-quick-access-card"]').trigger('click')

      expect(wrapper.emitted('click')).toHaveLength(1)
    })

    it('emits click event when Enter key is pressed', async () => {
      const wrapper = mount(MissionsQuickAccessCard, {
        props: {
          count: 12,
          isLoading: false,
        },
      })

      await wrapper.find('[data-testid="missions-quick-access-card"]').trigger('keydown.enter')

      expect(wrapper.emitted('click')).toHaveLength(1)
    })

    it('emits click event when Space key is pressed', async () => {
      const wrapper = mount(MissionsQuickAccessCard, {
        props: {
          count: 12,
          isLoading: false,
        },
      })

      await wrapper.find('[data-testid="missions-quick-access-card"]').trigger('keydown.space')

      expect(wrapper.emitted('click')).toHaveLength(1)
    })
  })

  describe('accessibility', () => {
    it('has aria-label for screen readers', () => {
      const wrapper = mount(MissionsQuickAccessCard, {
        props: {
          count: 12,
          isLoading: false,
        },
      })

      const card = wrapper.find('[data-testid="missions-quick-access-card"]')
      expect(card.attributes('aria-label')).toBe('Voir les missions disponibles')
    })

    it('has role="button" for semantic structure', () => {
      const wrapper = mount(MissionsQuickAccessCard, {
        props: {
          count: 12,
          isLoading: false,
        },
      })

      const card = wrapper.find('[data-testid="missions-quick-access-card"]')
      expect(card.attributes('role')).toBe('button')
    })

    it('has tabindex="0" for keyboard navigation', () => {
      const wrapper = mount(MissionsQuickAccessCard, {
        props: {
          count: 12,
          isLoading: false,
        },
      })

      const card = wrapper.find('[data-testid="missions-quick-access-card"]')
      expect(card.attributes('tabindex')).toBe('0')
    })
  })

  describe('styling', () => {
    it('has white background', () => {
      const wrapper = mount(MissionsQuickAccessCard, {
        props: {
          count: 12,
          isLoading: false,
        },
      })

      const card = wrapper.find('[data-testid="missions-quick-access-card"]')
      expect(card.classes()).toContain('bg-white')
    })

    it('has rounded-2xl border radius', () => {
      const wrapper = mount(MissionsQuickAccessCard, {
        props: {
          count: 12,
          isLoading: false,
        },
      })

      const card = wrapper.find('[data-testid="missions-quick-access-card"]')
      expect(card.classes()).toContain('rounded-2xl')
    })

    it('has cursor-pointer class for clickable appearance', () => {
      const wrapper = mount(MissionsQuickAccessCard, {
        props: {
          count: 12,
          isLoading: false,
        },
      })

      const card = wrapper.find('[data-testid="missions-quick-access-card"]')
      expect(card.classes()).toContain('cursor-pointer')
    })

    it('has shadow-sm', () => {
      const wrapper = mount(MissionsQuickAccessCard, {
        props: {
          count: 12,
          isLoading: false,
        },
      })

      const card = wrapper.find('[data-testid="missions-quick-access-card"]')
      expect(card.classes()).toContain('shadow-sm')
    })

    it('has hover:shadow-md transition', () => {
      const wrapper = mount(MissionsQuickAccessCard, {
        props: {
          count: 12,
          isLoading: false,
        },
      })

      const card = wrapper.find('[data-testid="missions-quick-access-card"]')
      expect(card.classes()).toContain('hover:shadow-md')
      expect(card.classes()).toContain('transition-all')
    })
  })
})
