import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import RatingDisplay from '../RatingDisplay.vue'

describe('RatingDisplay', () => {
  describe('rendering', () => {
    it('renders the rating container', () => {
      const wrapper = mount(RatingDisplay, {
        props: {
          averageRating: 4.5,
          reviewCount: 10,
        },
      })

      expect(wrapper.find('[data-testid="rating-display"]').exists()).toBe(true)
    })

    it('renders the stars container', () => {
      const wrapper = mount(RatingDisplay, {
        props: {
          averageRating: 4.5,
          reviewCount: 10,
        },
      })

      expect(wrapper.find('[data-testid="rating-stars"]').exists()).toBe(true)
    })

    it('renders exactly 5 stars', () => {
      const wrapper = mount(RatingDisplay, {
        props: {
          averageRating: 4.5,
          reviewCount: 10,
        },
      })

      for (let i = 1; i <= 5; i++) {
        expect(wrapper.find(`[data-testid="rating-star-${i}"]`).exists()).toBe(true)
      }
    })

    it('renders the review text', () => {
      const wrapper = mount(RatingDisplay, {
        props: {
          averageRating: 4.5,
          reviewCount: 10,
        },
      })

      expect(wrapper.find('[data-testid="rating-text"]').exists()).toBe(true)
    })
  })

  describe('no ratings state', () => {
    it('displays "Aucun avis" when reviewCount is 0', () => {
      const wrapper = mount(RatingDisplay, {
        props: {
          averageRating: null,
          reviewCount: 0,
        },
      })

      const text = wrapper.find('[data-testid="rating-text"]')
      expect(text.text()).toBe('Aucun avis')
    })

    it('displays "Aucun avis" when averageRating is null', () => {
      const wrapper = mount(RatingDisplay, {
        props: {
          averageRating: null,
          reviewCount: 0,
        },
      })

      const text = wrapper.find('[data-testid="rating-text"]')
      expect(text.text()).toBe('Aucun avis')
    })

    it('hides stars completely when reviewCount is 0 (AC#3: avoid empty stars confusion)', () => {
      const wrapper = mount(RatingDisplay, {
        props: {
          averageRating: null,
          reviewCount: 0,
        },
      })

      // Stars container should not exist when there are no reviews
      const starsContainer = wrapper.find('[data-testid="rating-stars"]')
      expect(starsContainer.exists()).toBe(false)
    })

    it('applies italic styling for "Aucun avis" text', () => {
      const wrapper = mount(RatingDisplay, {
        props: {
          averageRating: null,
          reviewCount: 0,
        },
      })

      const text = wrapper.find('[data-testid="rating-text"]')
      expect(text.classes()).toContain('italic')
      expect(text.classes()).toContain('text-gray-400')
    })
  })

  describe('with ratings', () => {
    it('displays review count in parentheses', () => {
      const wrapper = mount(RatingDisplay, {
        props: {
          averageRating: 4.2,
          reviewCount: 12,
        },
      })

      const text = wrapper.find('[data-testid="rating-text"]')
      expect(text.text()).toBe('(12 avis)')
    })

    it('displays singular "avis" for 1 review', () => {
      const wrapper = mount(RatingDisplay, {
        props: {
          averageRating: 5,
          reviewCount: 1,
        },
      })

      const text = wrapper.find('[data-testid="rating-text"]')
      expect(text.text()).toBe('(1 avis)')
    })

    it('applies non-italic styling when there are reviews', () => {
      const wrapper = mount(RatingDisplay, {
        props: {
          averageRating: 4.5,
          reviewCount: 10,
        },
      })

      const text = wrapper.find('[data-testid="rating-text"]')
      expect(text.classes()).not.toContain('italic')
      expect(text.classes()).toContain('text-gray-600')
    })
  })

  describe('star filling logic', () => {
    it('fills all 5 stars for rating 5', () => {
      const wrapper = mount(RatingDisplay, {
        props: {
          averageRating: 5,
          reviewCount: 10,
        },
      })

      for (let i = 1; i <= 5; i++) {
        const star = wrapper.find(`[data-testid="rating-star-${i}"]`)
        expect(star.attributes('data-filled')).toBe('true')
      }
    })

    it('fills 4 stars for rating 4', () => {
      const wrapper = mount(RatingDisplay, {
        props: {
          averageRating: 4,
          reviewCount: 10,
        },
      })

      for (let i = 1; i <= 4; i++) {
        const star = wrapper.find(`[data-testid="rating-star-${i}"]`)
        expect(star.attributes('data-filled')).toBe('true')
      }
      expect(wrapper.find('[data-testid="rating-star-5"]').attributes('data-filled')).toBe('false')
    })

    it('hides stars when rating is 0 and reviewCount is 0', () => {
      const wrapper = mount(RatingDisplay, {
        props: {
          averageRating: 0,
          reviewCount: 0,
        },
      })

      // Stars container should not exist when there are no reviews
      const starsContainer = wrapper.find('[data-testid="rating-stars"]')
      expect(starsContainer.exists()).toBe(false)
    })

    it('rounds 4.5 to 5 filled stars', () => {
      const wrapper = mount(RatingDisplay, {
        props: {
          averageRating: 4.5,
          reviewCount: 10,
        },
      })

      for (let i = 1; i <= 5; i++) {
        const star = wrapper.find(`[data-testid="rating-star-${i}"]`)
        expect(star.attributes('data-filled')).toBe('true')
      }
    })

    it('rounds 4.4 to 4 filled stars', () => {
      const wrapper = mount(RatingDisplay, {
        props: {
          averageRating: 4.4,
          reviewCount: 10,
        },
      })

      for (let i = 1; i <= 4; i++) {
        const star = wrapper.find(`[data-testid="rating-star-${i}"]`)
        expect(star.attributes('data-filled')).toBe('true')
      }
      expect(wrapper.find('[data-testid="rating-star-5"]').attributes('data-filled')).toBe('false')
    })

    it('rounds 2.5 to 3 filled stars', () => {
      const wrapper = mount(RatingDisplay, {
        props: {
          averageRating: 2.5,
          reviewCount: 10,
        },
      })

      for (let i = 1; i <= 3; i++) {
        const star = wrapper.find(`[data-testid="rating-star-${i}"]`)
        expect(star.attributes('data-filled')).toBe('true')
      }
      expect(wrapper.find('[data-testid="rating-star-4"]').attributes('data-filled')).toBe('false')
      expect(wrapper.find('[data-testid="rating-star-5"]').attributes('data-filled')).toBe('false')
    })

    it('rounds 1.2 to 1 filled star', () => {
      const wrapper = mount(RatingDisplay, {
        props: {
          averageRating: 1.2,
          reviewCount: 10,
        },
      })

      expect(wrapper.find('[data-testid="rating-star-1"]').attributes('data-filled')).toBe('true')
      for (let i = 2; i <= 5; i++) {
        const star = wrapper.find(`[data-testid="rating-star-${i}"]`)
        expect(star.attributes('data-filled')).toBe('false')
      }
    })
  })

  describe('accessibility', () => {
    it('has role="img" for screen readers', () => {
      const wrapper = mount(RatingDisplay, {
        props: {
          averageRating: 4.5,
          reviewCount: 10,
        },
      })

      const container = wrapper.find('[data-testid="rating-display"]')
      expect(container.attributes('role')).toBe('img')
    })

    it('has descriptive aria-label when there are reviews', () => {
      const wrapper = mount(RatingDisplay, {
        props: {
          averageRating: 4.5,
          reviewCount: 10,
        },
      })

      const container = wrapper.find('[data-testid="rating-display"]')
      expect(container.attributes('aria-label')).toBe('Note de 4.5 sur 5 basée sur 10 avis')
    })

    it('has descriptive aria-label when there are no reviews', () => {
      const wrapper = mount(RatingDisplay, {
        props: {
          averageRating: null,
          reviewCount: 0,
        },
      })

      const container = wrapper.find('[data-testid="rating-display"]')
      expect(container.attributes('aria-label')).toBe('Aucun avis pour le moment')
    })
  })

  describe('default props', () => {
    it('defaults averageRating to null', () => {
      const wrapper = mount(RatingDisplay, {
        props: {
          reviewCount: 0,
        },
      })

      const text = wrapper.find('[data-testid="rating-text"]')
      expect(text.text()).toBe('Aucun avis')
    })

    it('defaults reviewCount to 0', () => {
      const wrapper = mount(RatingDisplay, {
        props: {
          averageRating: null,
        },
      })

      const text = wrapper.find('[data-testid="rating-text"]')
      expect(text.text()).toBe('Aucun avis')
    })
  })
})
