import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ProfileCompletionIndicator from '../ProfileCompletionIndicator.vue'

describe('ProfileCompletionIndicator', () => {
  const mockMissingItems = [
    { key: 'profile_photo', label: 'Ajoutez une photo de profil' },
    { key: 'bio', label: 'Ajoutez une bio' },
    { key: 'tarifs', label: 'Ajoutez vos tarifs' },
  ]

  describe('compact variant', () => {
    it('renders compact variant by default', () => {
      const wrapper = mount(ProfileCompletionIndicator, {
        props: {
          percentage: 50,
        },
      })

      expect(wrapper.find('[data-testid="completion-compact"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="completion-full"]').exists()).toBe(false)
    })

    it('displays percentage in center of ring', () => {
      const wrapper = mount(ProfileCompletionIndicator, {
        props: {
          percentage: 71,
          variant: 'compact',
        },
      })

      const percentageText = wrapper.find('[data-testid="completion-percentage"]')
      expect(percentageText.text()).toBe('71%')
    })

    it('shows "Profil complet" when 100%', () => {
      const wrapper = mount(ProfileCompletionIndicator, {
        props: {
          percentage: 100,
          isComplete: true,
          variant: 'compact',
        },
      })

      expect(wrapper.find('[data-testid="complete-text"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="complete-text"]').text()).toBe('Profil complet')
    })

    it('does not show "Profil complet" when not 100%', () => {
      const wrapper = mount(ProfileCompletionIndicator, {
        props: {
          percentage: 50,
          isComplete: false,
          variant: 'compact',
        },
      })

      expect(wrapper.find('[data-testid="complete-text"]').exists()).toBe(false)
    })

    it('renders progress ring SVG', () => {
      const wrapper = mount(ProfileCompletionIndicator, {
        props: {
          percentage: 50,
          variant: 'compact',
        },
      })

      expect(wrapper.find('[data-testid="progress-ring"]').exists()).toBe(true)
    })
  })

  describe('full variant', () => {
    it('renders full variant when specified', () => {
      const wrapper = mount(ProfileCompletionIndicator, {
        props: {
          percentage: 50,
          variant: 'full',
        },
      })

      expect(wrapper.find('[data-testid="completion-full"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="completion-compact"]').exists()).toBe(false)
    })

    it('displays percentage text', () => {
      const wrapper = mount(ProfileCompletionIndicator, {
        props: {
          percentage: 43,
          variant: 'full',
        },
      })

      const percentageText = wrapper.find('[data-testid="completion-percentage"]')
      expect(percentageText.text()).toBe('43%')
    })

    it('renders progress bar', () => {
      const wrapper = mount(ProfileCompletionIndicator, {
        props: {
          percentage: 50,
          variant: 'full',
        },
      })

      expect(wrapper.find('[data-testid="progress-bar"]').exists()).toBe(true)
    })

    it('shows missing items list when not complete', () => {
      const wrapper = mount(ProfileCompletionIndicator, {
        props: {
          percentage: 57,
          missingItems: mockMissingItems,
          isComplete: false,
          variant: 'full',
        },
      })

      expect(wrapper.find('[data-testid="missing-items-list"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="missing-item-profile_photo"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="missing-item-bio"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="missing-item-tarifs"]').exists()).toBe(true)
    })

    it('displays correct labels for missing items', () => {
      const wrapper = mount(ProfileCompletionIndicator, {
        props: {
          percentage: 57,
          missingItems: mockMissingItems,
          isComplete: false,
          variant: 'full',
        },
      })

      const bioItem = wrapper.find('[data-testid="missing-item-bio"]')
      expect(bioItem.text()).toContain('Ajoutez une bio')
    })

    it('emits click-item event when missing item is clicked', async () => {
      const wrapper = mount(ProfileCompletionIndicator, {
        props: {
          percentage: 57,
          missingItems: mockMissingItems,
          isComplete: false,
          variant: 'full',
        },
      })

      await wrapper.find('[data-testid="missing-item-bio"]').trigger('click')

      expect(wrapper.emitted('click-item')).toBeTruthy()
      expect(wrapper.emitted('click-item')![0]).toEqual(['bio'])
    })

    it('shows complete message when 100%', () => {
      const wrapper = mount(ProfileCompletionIndicator, {
        props: {
          percentage: 100,
          missingItems: [],
          isComplete: true,
          variant: 'full',
        },
      })

      expect(wrapper.find('[data-testid="complete-message"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="missing-items-list"]').exists()).toBe(false)
    })
  })

  describe('loading state', () => {
    it('shows loading skeleton when percentage is undefined', () => {
      const wrapper = mount(ProfileCompletionIndicator, {
        props: {
          variant: 'compact',
        },
      })

      expect(wrapper.find('[data-testid="completion-loading"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="completion-compact"]').exists()).toBe(false)
    })

    it('shows loading skeleton for full variant', () => {
      const wrapper = mount(ProfileCompletionIndicator, {
        props: {
          variant: 'full',
        },
      })

      expect(wrapper.find('[data-testid="completion-loading"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="completion-full"]').exists()).toBe(false)
    })

    it('hides loading skeleton when percentage is provided', () => {
      const wrapper = mount(ProfileCompletionIndicator, {
        props: {
          percentage: 50,
          variant: 'compact',
        },
      })

      expect(wrapper.find('[data-testid="completion-loading"]').exists()).toBe(false)
    })
  })

  describe('0% state', () => {
    it('displays 0% correctly in compact variant', () => {
      const wrapper = mount(ProfileCompletionIndicator, {
        props: {
          percentage: 0,
          variant: 'compact',
        },
      })

      const percentageText = wrapper.find('[data-testid="completion-percentage"]')
      expect(percentageText.text()).toBe('0%')
    })

    it('displays 0% correctly in full variant', () => {
      const wrapper = mount(ProfileCompletionIndicator, {
        props: {
          percentage: 0,
          variant: 'full',
        },
      })

      const percentageText = wrapper.find('[data-testid="completion-percentage"]')
      expect(percentageText.text()).toBe('0%')
    })
  })

  describe('accessibility', () => {
    it('has main container with data-testid', () => {
      const wrapper = mount(ProfileCompletionIndicator, {
        props: {
          percentage: 50,
        },
      })

      expect(wrapper.find('[data-testid="profile-completion-indicator"]').exists()).toBe(true)
    })

    it('missing item buttons are focusable', () => {
      const wrapper = mount(ProfileCompletionIndicator, {
        props: {
          percentage: 57,
          missingItems: mockMissingItems,
          isComplete: false,
          variant: 'full',
        },
      })

      const buttons = wrapper.findAll('button')
      expect(buttons.length).toBe(mockMissingItems.length)
    })
  })
})
