import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import Pagination from '../Pagination.vue'

describe('Pagination', () => {
  const mountPagination = (props: { currentPage: number; totalPages: number; maxVisiblePages?: number }) => {
    return mount(Pagination, {
      props,
    })
  }

  describe('Rendering', () => {
    it('renders previous and next buttons', () => {
      const wrapper = mountPagination({ currentPage: 1, totalPages: 5 })

      expect(wrapper.find('[data-testid="pagination-previous"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="pagination-next"]').exists()).toBe(true)
    })

    it('renders page number buttons', () => {
      const wrapper = mountPagination({ currentPage: 1, totalPages: 5 })

      expect(wrapper.find('[data-testid="pagination-page-1"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="pagination-page-2"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="pagination-page-3"]').exists()).toBe(true)
    })

    it('highlights current page', () => {
      const wrapper = mountPagination({ currentPage: 2, totalPages: 5 })

      const currentPageBtn = wrapper.find('[data-testid="pagination-page-2"]')
      expect(currentPageBtn.classes()).toContain('bg-[#198496]')
      expect(currentPageBtn.classes()).toContain('text-white')
      expect(currentPageBtn.attributes('aria-current')).toBe('page')
    })

    it('does not highlight non-current pages', () => {
      const wrapper = mountPagination({ currentPage: 2, totalPages: 5 })

      const otherPageBtn = wrapper.find('[data-testid="pagination-page-1"]')
      expect(otherPageBtn.classes()).not.toContain('bg-[#198496]')
      expect(otherPageBtn.attributes('aria-current')).toBeUndefined()
    })
  })

  describe('Navigation buttons', () => {
    it('disables previous button on first page', () => {
      const wrapper = mountPagination({ currentPage: 1, totalPages: 5 })

      const prevBtn = wrapper.find('[data-testid="pagination-previous"]')
      expect(prevBtn.attributes('disabled')).toBeDefined()
    })

    it('enables previous button on pages after first', () => {
      const wrapper = mountPagination({ currentPage: 2, totalPages: 5 })

      const prevBtn = wrapper.find('[data-testid="pagination-previous"]')
      expect(prevBtn.attributes('disabled')).toBeUndefined()
    })

    it('disables next button on last page', () => {
      const wrapper = mountPagination({ currentPage: 5, totalPages: 5 })

      const nextBtn = wrapper.find('[data-testid="pagination-next"]')
      expect(nextBtn.attributes('disabled')).toBeDefined()
    })

    it('enables next button on pages before last', () => {
      const wrapper = mountPagination({ currentPage: 3, totalPages: 5 })

      const nextBtn = wrapper.find('[data-testid="pagination-next"]')
      expect(nextBtn.attributes('disabled')).toBeUndefined()
    })
  })

  describe('Events', () => {
    it('emits page-change event when clicking a page number', async () => {
      const wrapper = mountPagination({ currentPage: 1, totalPages: 5 })

      await wrapper.find('[data-testid="pagination-page-3"]').trigger('click')

      expect(wrapper.emitted('page-change')).toBeTruthy()
      expect(wrapper.emitted('page-change')![0]).toEqual([3])
    })

    it('emits page-change event when clicking previous', async () => {
      const wrapper = mountPagination({ currentPage: 3, totalPages: 5 })

      await wrapper.find('[data-testid="pagination-previous"]').trigger('click')

      expect(wrapper.emitted('page-change')).toBeTruthy()
      expect(wrapper.emitted('page-change')![0]).toEqual([2])
    })

    it('emits page-change event when clicking next', async () => {
      const wrapper = mountPagination({ currentPage: 3, totalPages: 5 })

      await wrapper.find('[data-testid="pagination-next"]').trigger('click')

      expect(wrapper.emitted('page-change')).toBeTruthy()
      expect(wrapper.emitted('page-change')![0]).toEqual([4])
    })

    it('does not emit event when clicking current page', async () => {
      const wrapper = mountPagination({ currentPage: 2, totalPages: 5 })

      await wrapper.find('[data-testid="pagination-page-2"]').trigger('click')

      expect(wrapper.emitted('page-change')).toBeFalsy()
    })

    it('does not emit event when clicking disabled previous', async () => {
      const wrapper = mountPagination({ currentPage: 1, totalPages: 5 })

      await wrapper.find('[data-testid="pagination-previous"]').trigger('click')

      expect(wrapper.emitted('page-change')).toBeFalsy()
    })

    it('does not emit event when clicking disabled next', async () => {
      const wrapper = mountPagination({ currentPage: 5, totalPages: 5 })

      await wrapper.find('[data-testid="pagination-next"]').trigger('click')

      expect(wrapper.emitted('page-change')).toBeFalsy()
    })
  })

  describe('Ellipsis handling', () => {
    it('shows all pages when total fits within max visible', () => {
      const wrapper = mountPagination({ currentPage: 1, totalPages: 5, maxVisiblePages: 5 })

      expect(wrapper.find('[data-testid="pagination-page-1"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="pagination-page-2"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="pagination-page-3"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="pagination-page-4"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="pagination-page-5"]').exists()).toBe(true)
      expect(wrapper.text()).not.toContain('…')
    })

    it('shows ellipsis when there are more pages than max visible', () => {
      const wrapper = mountPagination({ currentPage: 5, totalPages: 10, maxVisiblePages: 5 })

      // Should show ellipsis
      expect(wrapper.text()).toContain('…')
    })

    it('always shows first and last page', () => {
      const wrapper = mountPagination({ currentPage: 5, totalPages: 10, maxVisiblePages: 5 })

      expect(wrapper.find('[data-testid="pagination-page-1"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="pagination-page-10"]').exists()).toBe(true)
    })
  })

  describe('Accessibility', () => {
    it('has navigation role', () => {
      const wrapper = mountPagination({ currentPage: 1, totalPages: 5 })

      expect(wrapper.find('nav').exists()).toBe(true)
      expect(wrapper.find('nav').attributes('role')).toBe('navigation')
      expect(wrapper.find('nav').attributes('aria-label')).toBe('Pagination')
    })

    it('has aria-label on previous button', () => {
      const wrapper = mountPagination({ currentPage: 1, totalPages: 5 })

      expect(wrapper.find('[data-testid="pagination-previous"]').attributes('aria-label')).toBe('Page précédente')
    })

    it('has aria-label on next button', () => {
      const wrapper = mountPagination({ currentPage: 1, totalPages: 5 })

      expect(wrapper.find('[data-testid="pagination-next"]').attributes('aria-label')).toBe('Page suivante')
    })

    it('has aria-label on page buttons', () => {
      const wrapper = mountPagination({ currentPage: 1, totalPages: 5 })

      expect(wrapper.find('[data-testid="pagination-page-1"]').attributes('aria-label')).toBe('Page 1')
      expect(wrapper.find('[data-testid="pagination-page-2"]').attributes('aria-label')).toBe('Page 2')
    })

    it('has focus-visible styles on buttons', () => {
      const wrapper = mountPagination({ currentPage: 1, totalPages: 5 })

      const prevBtn = wrapper.find('[data-testid="pagination-previous"]')
      expect(prevBtn.classes()).toContain('focus-visible:ring-2')
    })
  })

  describe('Edge cases', () => {
    it('handles single page', () => {
      const wrapper = mountPagination({ currentPage: 1, totalPages: 1 })

      expect(wrapper.find('[data-testid="pagination-page-1"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="pagination-previous"]').attributes('disabled')).toBeDefined()
      expect(wrapper.find('[data-testid="pagination-next"]').attributes('disabled')).toBeDefined()
    })

    it('handles two pages', () => {
      const wrapper = mountPagination({ currentPage: 1, totalPages: 2 })

      expect(wrapper.find('[data-testid="pagination-page-1"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="pagination-page-2"]').exists()).toBe(true)
    })
  })
})
