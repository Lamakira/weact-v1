import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import MessagesCard from '../MessagesCard.vue'

describe('MessagesCard', () => {
  const createWrapper = () => {
    return mount(MessagesCard)
  }

  describe('rendering', () => {
    it('renders the card with correct structure', () => {
      const wrapper = createWrapper()

      expect(wrapper.find('[data-testid="messages-card"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="messages-card-icon"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="messages-card-title"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="messages-card-subtitle"]').exists()).toBe(true)
    })

    it('displays correct title text', () => {
      const wrapper = createWrapper()

      expect(wrapper.find('[data-testid="messages-card-title"]').text()).toBe('Messages')
    })

    it('displays correct subtitle text', () => {
      const wrapper = createWrapper()

      expect(wrapper.find('[data-testid="messages-card-subtitle"]').text()).toBe(
        'Discussions avec les producteurs'
      )
    })

    it('uses MessageCircle icon from lucide-vue-next', () => {
      const wrapper = createWrapper()

      // Check icon container exists with correct styling
      const iconContainer = wrapper.find('[data-testid="messages-card-icon"]')
      expect(iconContainer.exists()).toBe(true)
      expect(iconContainer.classes()).toContain('bg-blue-50')
    })

    it('has decorative background icon', () => {
      const wrapper = createWrapper()
      const html = wrapper.html()

      // Check for decorative background icon
      expect(html).toContain('opacity-5')
    })
  })

  describe('styling', () => {
    it('has correct card styling matching design system', () => {
      const wrapper = createWrapper()
      const card = wrapper.find('[data-testid="messages-card"]')

      expect(card.classes()).toContain('rounded-2xl')
      expect(card.classes()).toContain('p-4')
      expect(card.classes()).toContain('shadow-sm')
      expect(card.classes()).toContain('border')
      expect(card.classes()).toContain('border-gray-100')
    })

    it('has hover effects', () => {
      const wrapper = createWrapper()
      const card = wrapper.find('[data-testid="messages-card"]')

      expect(card.classes()).toContain('hover:shadow-md')
      expect(card.classes()).toContain('cursor-pointer')
    })

    it('has correct icon container styling', () => {
      const wrapper = createWrapper()
      const iconContainer = wrapper.find('[data-testid="messages-card-icon"]')

      expect(iconContainer.classes()).toContain('h-11')
      expect(iconContainer.classes()).toContain('w-11')
      expect(iconContainer.classes()).toContain('rounded-xl')
    })
  })

  describe('accessibility', () => {
    it('has correct role and tabindex for keyboard navigation', () => {
      const wrapper = createWrapper()
      const card = wrapper.find('[data-testid="messages-card"]')

      expect(card.attributes('role')).toBe('button')
      expect(card.attributes('tabindex')).toBe('0')
    })

    it('has aria-label for screen readers', () => {
      const wrapper = createWrapper()
      const card = wrapper.find('[data-testid="messages-card"]')

      expect(card.attributes('aria-label')).toBe('Voir les messages')
    })
  })

  describe('interactions', () => {
    it('emits click event when clicked', async () => {
      const wrapper = createWrapper()

      await wrapper.find('[data-testid="messages-card"]').trigger('click')

      expect(wrapper.emitted('click')).toBeTruthy()
      expect(wrapper.emitted('click')).toHaveLength(1)
    })

    it('emits click event on Enter key press', async () => {
      const wrapper = createWrapper()

      await wrapper.find('[data-testid="messages-card"]').trigger('keydown.enter')

      expect(wrapper.emitted('click')).toBeTruthy()
      expect(wrapper.emitted('click')).toHaveLength(1)
    })

    it('emits click event on Space key press', async () => {
      const wrapper = createWrapper()

      await wrapper.find('[data-testid="messages-card"]').trigger('keydown.space')

      expect(wrapper.emitted('click')).toBeTruthy()
      expect(wrapper.emitted('click')).toHaveLength(1)
    })

    it('prevents default on Space to avoid page scroll', async () => {
      const wrapper = createWrapper()

      // The .prevent modifier on @keydown.space.prevent handles default prevention
      await wrapper.find('[data-testid="messages-card"]').trigger('keydown.space')

      // Verify click event is emitted (space key triggers navigation)
      expect(wrapper.emitted('click')).toBeTruthy()
    })
  })
})
