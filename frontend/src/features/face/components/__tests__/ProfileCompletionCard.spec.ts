import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ProfileCompletionCard from '../ProfileCompletionCard.vue'
import { createRouter, createMemoryHistory } from 'vue-router'

// Create a mock router
const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/', component: { template: '<div>Home</div>' } },
    { path: '/face/profile', component: { template: '<div>Profile</div>' } },
  ],
})

describe('ProfileCompletionCard', () => {
  const mountWithRouter = (props = {}) => {
    return mount(ProfileCompletionCard, {
      props,
      global: {
        plugins: [router],
      },
    })
  }

  describe('rendering', () => {
    it('renders the card container', () => {
      const wrapper = mountWithRouter({
        percentage: 50,
        missingCount: 3,
      })

      expect(wrapper.find('[data-testid="profile-completion-card"]').exists()).toBe(true)
    })

    it('renders the card title', () => {
      const wrapper = mountWithRouter({
        percentage: 50,
        missingCount: 3,
      })

      expect(wrapper.text()).toContain('Complétion du profil')
    })

    it('renders ProfileCompletionIndicator child component', () => {
      const wrapper = mountWithRouter({
        percentage: 50,
        missingCount: 3,
      })

      expect(wrapper.findComponent({ name: 'ProfileCompletionIndicator' }).exists()).toBe(true)
    })
  })

  describe('missing items state', () => {
    it('shows missing count text for single item', () => {
      const wrapper = mountWithRouter({
        percentage: 86,
        missingCount: 1,
      })

      expect(wrapper.find('[data-testid="missing-count"]').text()).toBe('1 élément manquant')
    })

    it('shows missing count text for multiple items', () => {
      const wrapper = mountWithRouter({
        percentage: 57,
        missingCount: 3,
      })

      expect(wrapper.find('[data-testid="missing-count"]').text()).toBe('3 éléments manquants')
    })

    it('renders link to profile edit page', () => {
      const wrapper = mountWithRouter({
        percentage: 57,
        missingCount: 3,
      })

      const link = wrapper.find('[data-testid="complete-profile-link"]')
      expect(link.exists()).toBe(true)
      expect(link.text()).toBe('Compléter mon profil')
      expect(link.attributes('href')).toBe('/face/profile')
    })
  })

  describe('complete state', () => {
    it('shows complete badge when no missing items', () => {
      const wrapper = mountWithRouter({
        percentage: 100,
        missingCount: 0,
      })

      expect(wrapper.find('[data-testid="complete-badge"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="complete-badge"]').text()).toContain('Profil complet')
    })

    it('hides missing count when complete', () => {
      const wrapper = mountWithRouter({
        percentage: 100,
        missingCount: 0,
      })

      expect(wrapper.find('[data-testid="missing-count"]').exists()).toBe(false)
    })

    it('hides profile link when complete', () => {
      const wrapper = mountWithRouter({
        percentage: 100,
        missingCount: 0,
      })

      expect(wrapper.find('[data-testid="complete-profile-link"]').exists()).toBe(false)
    })
  })

  describe('loading state', () => {
    it('shows loading skeleton when isLoading is true', () => {
      const wrapper = mountWithRouter({
        percentage: 50,
        missingCount: 3,
        isLoading: true,
      })

      // The ProfileCompletionIndicator should show loading state
      expect(wrapper.find('.animate-pulse').exists()).toBe(true)
    })

    it('hides missing count when loading', () => {
      const wrapper = mountWithRouter({
        percentage: 50,
        missingCount: 3,
        isLoading: true,
      })

      expect(wrapper.find('[data-testid="missing-count"]').exists()).toBe(false)
    })
  })

  describe('styling', () => {
    it('has correct card styling classes', () => {
      const wrapper = mountWithRouter({
        percentage: 50,
        missingCount: 3,
      })

      const card = wrapper.find('[data-testid="profile-completion-card"]')
      expect(card.classes()).toContain('bg-white')
      expect(card.classes()).toContain('rounded-xl')
      expect(card.classes()).toContain('shadow-sm')
    })
  })
})
