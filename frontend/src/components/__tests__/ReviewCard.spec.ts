import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ReviewCard from '../ReviewCard.vue'
import type { Review } from '@/features/rating/types'

const mockReview: Review = {
  id: 1,
  score: 5,
  comment: 'Excellent travail, très professionnel!',
  created_at: '2026-01-15T14:30:00Z',
  formatted_date: '15 janvier 2026',
  rater: {
    display_name: 'Jean Dupont',
    profile_photo_url: 'https://example.com/photo.jpg',
  },
}

describe('ReviewCard', () => {
  describe('rendering', () => {
    it('renders the review card', () => {
      const wrapper = mount(ReviewCard, {
        props: { review: mockReview },
      })

      expect(wrapper.find('[data-testid="review-card"]').exists()).toBe(true)
    })

    it('renders the rater name', () => {
      const wrapper = mount(ReviewCard, {
        props: { review: mockReview },
      })

      expect(wrapper.find('[data-testid="rater-name"]').text()).toBe('Jean Dupont')
    })

    it('renders the review date', () => {
      const wrapper = mount(ReviewCard, {
        props: { review: mockReview },
      })

      expect(wrapper.find('[data-testid="review-date"]').text()).toBe('15 janvier 2026')
    })

    it('renders the review comment', () => {
      const wrapper = mount(ReviewCard, {
        props: { review: mockReview },
      })

      expect(wrapper.find('[data-testid="review-comment"]').text()).toBe(
        'Excellent travail, très professionnel!'
      )
    })

    it('renders 5 stars', () => {
      const wrapper = mount(ReviewCard, {
        props: { review: mockReview },
      })

      for (let i = 1; i <= 5; i++) {
        expect(wrapper.find(`[data-testid="star-${i}"]`).exists()).toBe(true)
      }
    })
  })

  describe('star rating display', () => {
    it('fills all 5 stars for score 5', () => {
      const wrapper = mount(ReviewCard, {
        props: { review: { ...mockReview, score: 5 } },
      })

      for (let i = 1; i <= 5; i++) {
        expect(wrapper.find(`[data-testid="star-${i}"]`).attributes('data-filled')).toBe('true')
      }
    })

    it('fills 3 stars for score 3', () => {
      const wrapper = mount(ReviewCard, {
        props: { review: { ...mockReview, score: 3 } },
      })

      for (let i = 1; i <= 3; i++) {
        expect(wrapper.find(`[data-testid="star-${i}"]`).attributes('data-filled')).toBe('true')
      }
      for (let i = 4; i <= 5; i++) {
        expect(wrapper.find(`[data-testid="star-${i}"]`).attributes('data-filled')).toBe('false')
      }
    })

    it('fills 1 star for score 1', () => {
      const wrapper = mount(ReviewCard, {
        props: { review: { ...mockReview, score: 1 } },
      })

      expect(wrapper.find('[data-testid="star-1"]').attributes('data-filled')).toBe('true')
      for (let i = 2; i <= 5; i++) {
        expect(wrapper.find(`[data-testid="star-${i}"]`).attributes('data-filled')).toBe('false')
      }
    })
  })

  describe('comment handling', () => {
    it('shows comment when present', () => {
      const wrapper = mount(ReviewCard, {
        props: { review: mockReview },
      })

      expect(wrapper.find('[data-testid="review-comment"]').exists()).toBe(true)
    })

    it('hides comment section when comment is null', () => {
      const reviewWithoutComment: Review = {
        ...mockReview,
        comment: null,
      }

      const wrapper = mount(ReviewCard, {
        props: { review: reviewWithoutComment },
      })

      expect(wrapper.find('[data-testid="review-comment"]').exists()).toBe(false)
    })
  })

  describe('avatar display', () => {
    it('shows avatar image when profile_photo_url is present', () => {
      const wrapper = mount(ReviewCard, {
        props: { review: mockReview },
      })

      expect(wrapper.find('[data-testid="rater-avatar"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="rater-avatar-placeholder"]').exists()).toBe(false)
    })

    it('shows placeholder when profile_photo_url is null', () => {
      const reviewWithoutPhoto: Review = {
        ...mockReview,
        rater: {
          display_name: 'Jean Dupont',
          profile_photo_url: null,
        },
      }

      const wrapper = mount(ReviewCard, {
        props: { review: reviewWithoutPhoto },
      })

      expect(wrapper.find('[data-testid="rater-avatar"]').exists()).toBe(false)
      expect(wrapper.find('[data-testid="rater-avatar-placeholder"]').exists()).toBe(true)
    })
  })
})
