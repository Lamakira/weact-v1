import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ReviewsList from '../ReviewsList.vue'
import type { Review } from '@/features/rating/types'

const mockReviews: Review[] = [
  {
    id: 1,
    score: 5,
    comment: 'Excellent!',
    created_at: '2026-01-15T14:30:00Z',
    formatted_date: '15 janvier 2026',
    rater: {
      display_name: 'Jean Dupont',
      profile_photo_url: null,
    },
  },
  {
    id: 2,
    score: 4,
    comment: 'Très bien',
    created_at: '2026-01-14T10:00:00Z',
    formatted_date: '14 janvier 2026',
    rater: {
      display_name: 'Marie Martin',
      profile_photo_url: 'https://example.com/photo.jpg',
    },
  },
]

describe('ReviewsList', () => {
  describe('loading state', () => {
    it('shows loading state when loading is true', () => {
      const wrapper = mount(ReviewsList, {
        props: {
          reviews: [],
          currentPage: 1,
          lastPage: 1,
          total: 0,
          loading: true,
        },
      })

      expect(wrapper.find('[data-testid="reviews-loading"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="reviews-empty"]').exists()).toBe(false)
    })
  })

  describe('empty state', () => {
    it('shows empty state when no reviews', () => {
      const wrapper = mount(ReviewsList, {
        props: {
          reviews: [],
          currentPage: 1,
          lastPage: 1,
          total: 0,
          loading: false,
        },
      })

      expect(wrapper.find('[data-testid="reviews-empty"]').exists()).toBe(true)
      expect(wrapper.text()).toContain('Aucune note pour le moment')
    })
  })

  describe('reviews list', () => {
    it('renders review cards for each review', () => {
      const wrapper = mount(ReviewsList, {
        props: {
          reviews: mockReviews,
          currentPage: 1,
          lastPage: 1,
          total: 2,
          loading: false,
        },
      })

      const reviewCards = wrapper.findAll('[data-testid="review-card"]')
      expect(reviewCards).toHaveLength(2)
    })

    it('shows correct total count', () => {
      const wrapper = mount(ReviewsList, {
        props: {
          reviews: mockReviews,
          currentPage: 1,
          lastPage: 2,
          total: 15,
          loading: false,
        },
      })

      expect(wrapper.text()).toContain('15 avis au total')
    })
  })

  describe('pagination', () => {
    it('shows pagination when lastPage > 1', () => {
      const wrapper = mount(ReviewsList, {
        props: {
          reviews: mockReviews,
          currentPage: 1,
          lastPage: 3,
          total: 25,
          loading: false,
        },
      })

      expect(wrapper.find('[data-testid="reviews-pagination"]').exists()).toBe(true)
    })

    it('hides pagination when only one page', () => {
      const wrapper = mount(ReviewsList, {
        props: {
          reviews: mockReviews,
          currentPage: 1,
          lastPage: 1,
          total: 2,
          loading: false,
        },
      })

      expect(wrapper.find('[data-testid="reviews-pagination"]').exists()).toBe(false)
    })

    it('disables previous button on first page', () => {
      const wrapper = mount(ReviewsList, {
        props: {
          reviews: mockReviews,
          currentPage: 1,
          lastPage: 3,
          total: 25,
          loading: false,
        },
      })

      const prevButton = wrapper.find('[data-testid="pagination-prev"]')
      expect(prevButton.attributes('disabled')).toBeDefined()
    })

    it('disables next button on last page', () => {
      const wrapper = mount(ReviewsList, {
        props: {
          reviews: mockReviews,
          currentPage: 3,
          lastPage: 3,
          total: 25,
          loading: false,
        },
      })

      const nextButton = wrapper.find('[data-testid="pagination-next"]')
      expect(nextButton.attributes('disabled')).toBeDefined()
    })

    it('emits page-change event when clicking page number', async () => {
      const wrapper = mount(ReviewsList, {
        props: {
          reviews: mockReviews,
          currentPage: 1,
          lastPage: 3,
          total: 25,
          loading: false,
        },
      })

      await wrapper.find('[data-testid="pagination-page-2"]').trigger('click')

      expect(wrapper.emitted('page-change')).toBeTruthy()
      expect(wrapper.emitted('page-change')![0]).toEqual([2])
    })

    it('emits page-change event when clicking next button', async () => {
      const wrapper = mount(ReviewsList, {
        props: {
          reviews: mockReviews,
          currentPage: 1,
          lastPage: 3,
          total: 25,
          loading: false,
        },
      })

      await wrapper.find('[data-testid="pagination-next"]').trigger('click')

      expect(wrapper.emitted('page-change')).toBeTruthy()
      expect(wrapper.emitted('page-change')![0]).toEqual([2])
    })

    it('emits page-change event when clicking previous button', async () => {
      const wrapper = mount(ReviewsList, {
        props: {
          reviews: mockReviews,
          currentPage: 2,
          lastPage: 3,
          total: 25,
          loading: false,
        },
      })

      await wrapper.find('[data-testid="pagination-prev"]').trigger('click')

      expect(wrapper.emitted('page-change')).toBeTruthy()
      expect(wrapper.emitted('page-change')![0]).toEqual([1])
    })

    it('does not emit when clicking current page', async () => {
      const wrapper = mount(ReviewsList, {
        props: {
          reviews: mockReviews,
          currentPage: 2,
          lastPage: 3,
          total: 25,
          loading: false,
        },
      })

      await wrapper.find('[data-testid="pagination-page-2"]').trigger('click')

      expect(wrapper.emitted('page-change')).toBeFalsy()
    })

    it('shows ellipsis for large page counts', () => {
      const wrapper = mount(ReviewsList, {
        props: {
          reviews: mockReviews,
          currentPage: 5,
          lastPage: 20,
          total: 200,
          loading: false,
        },
      })

      // Should have ellipsis elements
      expect(wrapper.find('[data-testid="pagination-ellipsis"]').exists()).toBe(true)
      // Should always show first and last page
      expect(wrapper.find('[data-testid="pagination-page-1"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="pagination-page-20"]').exists()).toBe(true)
    })

    it('shows all pages when total pages is 7 or less', () => {
      const wrapper = mount(ReviewsList, {
        props: {
          reviews: mockReviews,
          currentPage: 3,
          lastPage: 5,
          total: 50,
          loading: false,
        },
      })

      // Should not have ellipsis
      expect(wrapper.find('[data-testid="pagination-ellipsis"]').exists()).toBe(false)
      // Should show all 5 pages
      for (let i = 1; i <= 5; i++) {
        expect(wrapper.find(`[data-testid="pagination-page-${i}"]`).exists()).toBe(true)
      }
    })
  })
})
