import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ProfileCompletionCard from '../ProfileCompletionCard.vue'

describe('ProfileCompletionCard', () => {
  const createWrapper = (props = {}) => {
    return mount(ProfileCompletionCard, {
      props: {
        percentage: 50,
        missingCount: 3,
        isLoading: false,
        ...props,
      },
    })
  }

  describe('rendering', () => {
    it('renders the card with correct structure', () => {
      const wrapper = createWrapper()

      expect(wrapper.find('[data-testid="profile-completion-card"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="profile-completion-icon"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="profile-completion-badge"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="profile-completion-title"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="profile-completion-subtitle"]').exists()).toBe(true)
    })

    it('displays correct title text', () => {
      const wrapper = createWrapper()

      expect(wrapper.find('[data-testid="profile-completion-title"]').text()).toBe(
        'Complétion du profil'
      )
    })

    it('displays percentage in badge', () => {
      const wrapper = createWrapper({ percentage: 57 })

      expect(wrapper.find('[data-testid="profile-completion-badge"]').text()).toBe('57%')
    })

    it('uses UserCheck icon from lucide-vue-next', () => {
      const wrapper = createWrapper()

      // Check icon container exists with correct styling
      const iconContainer = wrapper.find('[data-testid="profile-completion-icon"]')
      expect(iconContainer.exists()).toBe(true)
      expect(iconContainer.classes()).toContain('h-11')
      expect(iconContainer.classes()).toContain('w-11')
    })

    it('has decorative background icon', () => {
      const wrapper = createWrapper()
      const html = wrapper.html()

      // Check for decorative background icon
      expect(html).toContain('opacity-5')
    })
  })

  describe('missing items state', () => {
    it('shows missing count text for single item', () => {
      const wrapper = createWrapper({
        percentage: 86,
        missingCount: 1,
      })

      expect(wrapper.find('[data-testid="profile-completion-subtitle"]').text()).toBe(
        '1 élément manquant'
      )
    })

    it('shows missing count text for multiple items', () => {
      const wrapper = createWrapper({
        percentage: 57,
        missingCount: 3,
      })

      expect(wrapper.find('[data-testid="profile-completion-subtitle"]').text()).toBe(
        '3 éléments manquants'
      )
    })
  })

  describe('complete state', () => {
    it('shows "Profil complet" when percentage is 100', () => {
      const wrapper = createWrapper({
        percentage: 100,
        missingCount: 0,
      })

      expect(wrapper.find('[data-testid="profile-completion-subtitle"]').text()).toBe(
        'Profil complet'
      )
    })

    it('shows green badge when percentage is 100', () => {
      const wrapper = createWrapper({
        percentage: 100,
        missingCount: 0,
      })

      const badge = wrapper.find('[data-testid="profile-completion-badge"]')
      expect(badge.classes()).toContain('bg-green-100')
      expect(badge.classes()).toContain('text-green-700')
    })
  })

  describe('badge color based on percentage', () => {
    it('shows red badge when percentage is 0-49%', () => {
      const wrapper = createWrapper({ percentage: 30, missingCount: 5 })
      const badge = wrapper.find('[data-testid="profile-completion-badge"]')

      expect(badge.classes()).toContain('bg-red-100')
      expect(badge.classes()).toContain('text-red-700')
    })

    it('shows yellow badge when percentage is 50-79%', () => {
      const wrapper = createWrapper({ percentage: 57, missingCount: 3 })
      const badge = wrapper.find('[data-testid="profile-completion-badge"]')

      expect(badge.classes()).toContain('bg-yellow-100')
      expect(badge.classes()).toContain('text-yellow-700')
    })

    it('shows orange badge when percentage is 80-99%', () => {
      const wrapper = createWrapper({ percentage: 85, missingCount: 1 })
      const badge = wrapper.find('[data-testid="profile-completion-badge"]')

      expect(badge.classes()).toContain('bg-orange-100')
      expect(badge.classes()).toContain('text-orange-700')
    })

    it('shows green badge when percentage is 100%', () => {
      const wrapper = createWrapper({ percentage: 100, missingCount: 0 })
      const badge = wrapper.find('[data-testid="profile-completion-badge"]')

      expect(badge.classes()).toContain('bg-green-100')
      expect(badge.classes()).toContain('text-green-700')
    })
  })

  describe('clickable indication', () => {
    it('shows "Voir" action indicator', () => {
      const wrapper = createWrapper()

      const action = wrapper.find('[data-testid="profile-completion-action"]')
      expect(action.exists()).toBe(true)
      expect(action.text()).toContain('Voir')
    })
  })

  describe('loading state', () => {
    it('shows badge skeleton when isLoading is true', () => {
      const wrapper = createWrapper({
        isLoading: true,
      })

      expect(wrapper.find('[data-testid="profile-completion-badge-loading"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="profile-completion-badge"]').exists()).toBe(false)
    })

    it('shows subtitle skeleton when isLoading is true', () => {
      const wrapper = createWrapper({
        isLoading: true,
      })

      expect(wrapper.find('[data-testid="profile-completion-subtitle-loading"]').exists()).toBe(
        true
      )
      expect(wrapper.find('[data-testid="profile-completion-subtitle"]').exists()).toBe(false)
    })
  })

  describe('styling', () => {
    it('has correct card styling matching design system', () => {
      const wrapper = createWrapper()
      const card = wrapper.find('[data-testid="profile-completion-card"]')

      expect(card.classes()).toContain('rounded-2xl')
      expect(card.classes()).toContain('p-4')
      expect(card.classes()).toContain('shadow-sm')
      expect(card.classes()).toContain('border')
      expect(card.classes()).toContain('border-gray-100')
    })

    it('has hover effects', () => {
      const wrapper = createWrapper()
      const card = wrapper.find('[data-testid="profile-completion-card"]')

      expect(card.classes()).toContain('hover:shadow-md')
      expect(card.classes()).toContain('cursor-pointer')
    })

    it('has correct icon container styling', () => {
      const wrapper = createWrapper()
      const iconContainer = wrapper.find('[data-testid="profile-completion-icon"]')

      expect(iconContainer.classes()).toContain('h-11')
      expect(iconContainer.classes()).toContain('w-11')
      expect(iconContainer.classes()).toContain('rounded-xl')
    })
  })

  describe('accessibility', () => {
    it('has correct role and tabindex for keyboard navigation', () => {
      const wrapper = createWrapper()
      const card = wrapper.find('[data-testid="profile-completion-card"]')

      expect(card.attributes('role')).toBe('button')
      expect(card.attributes('tabindex')).toBe('0')
    })

    it('has aria-label for screen readers', () => {
      const wrapper = createWrapper()
      const card = wrapper.find('[data-testid="profile-completion-card"]')

      expect(card.attributes('aria-label')).toBe('Compléter mon profil')
    })
  })

  describe('interactions', () => {
    it('emits click event when clicked', async () => {
      const wrapper = createWrapper()

      await wrapper.find('[data-testid="profile-completion-card"]').trigger('click')

      expect(wrapper.emitted('click')).toBeTruthy()
      expect(wrapper.emitted('click')).toHaveLength(1)
    })

    it('emits click event on Enter key press', async () => {
      const wrapper = createWrapper()

      await wrapper.find('[data-testid="profile-completion-card"]').trigger('keydown.enter')

      expect(wrapper.emitted('click')).toBeTruthy()
      expect(wrapper.emitted('click')).toHaveLength(1)
    })

    it('emits click event on Space key press', async () => {
      const wrapper = createWrapper()

      await wrapper.find('[data-testid="profile-completion-card"]').trigger('keydown.space')

      expect(wrapper.emitted('click')).toBeTruthy()
      expect(wrapper.emitted('click')).toHaveLength(1)
    })
  })
})
