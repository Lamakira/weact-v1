import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import FilterBar from '../FilterBar.vue'

describe('FilterBar', () => {
  const mountFilterBar = () => {
    return mount(FilterBar)
  }

  describe('Rendering', () => {
    it('renders the filter bar container', () => {
      const wrapper = mountFilterBar()

      expect(wrapper.find('[data-testid="filter-bar"]').exists()).toBe(true)
    })

    it('renders search input', () => {
      const wrapper = mountFilterBar()

      expect(wrapper.find('[data-testid="filter-search"]').exists()).toBe(true)
    })

    it('renders city filter dropdown', () => {
      const wrapper = mountFilterBar()

      expect(wrapper.find('[data-testid="filter-city"]').exists()).toBe(true)
    })

    it('renders category filter dropdown', () => {
      const wrapper = mountFilterBar()

      expect(wrapper.find('[data-testid="filter-category"]').exists()).toBe(true)
    })

    it('renders niche filter dropdown', () => {
      const wrapper = mountFilterBar()

      expect(wrapper.find('[data-testid="filter-niche"]').exists()).toBe(true)
    })

    it('renders advanced filters button', () => {
      const wrapper = mountFilterBar()

      expect(wrapper.find('[data-testid="filter-advanced"]').exists()).toBe(true)
    })

    it('shows "Bientôt" badge on advanced filters', () => {
      const wrapper = mountFilterBar()

      expect(wrapper.text()).toContain('Bientôt')
    })
  })

  describe('Disabled state (Story 11-5 placeholder)', () => {
    it('search input is disabled', () => {
      const wrapper = mountFilterBar()

      const searchInput = wrapper.find('[data-testid="filter-search"]')
      expect(searchInput.attributes('disabled')).toBeDefined()
    })

    it('city filter is disabled', () => {
      const wrapper = mountFilterBar()

      const citySelect = wrapper.find('[data-testid="filter-city"]')
      expect(citySelect.attributes('disabled')).toBeDefined()
    })

    it('category filter is disabled', () => {
      const wrapper = mountFilterBar()

      const categorySelect = wrapper.find('[data-testid="filter-category"]')
      expect(categorySelect.attributes('disabled')).toBeDefined()
    })

    it('niche filter is disabled', () => {
      const wrapper = mountFilterBar()

      const nicheSelect = wrapper.find('[data-testid="filter-niche"]')
      expect(nicheSelect.attributes('disabled')).toBeDefined()
    })

    it('advanced filters button is disabled', () => {
      const wrapper = mountFilterBar()

      const advancedBtn = wrapper.find('[data-testid="filter-advanced"]')
      expect(advancedBtn.attributes('disabled')).toBeDefined()
    })
  })

  describe('Placeholder options', () => {
    it('city dropdown has placeholder options', () => {
      const wrapper = mountFilterBar()

      const citySelect = wrapper.find('[data-testid="filter-city"]')
      const options = citySelect.findAll('option')

      expect(options.length).toBeGreaterThan(1)
      expect(options[0].text()).toBe('Ville')
    })

    it('category dropdown has placeholder options', () => {
      const wrapper = mountFilterBar()

      const categorySelect = wrapper.find('[data-testid="filter-category"]')
      const options = categorySelect.findAll('option')

      expect(options.length).toBeGreaterThan(1)
      expect(options[0].text()).toBe('Catégorie')
    })

    it('niche dropdown has placeholder options', () => {
      const wrapper = mountFilterBar()

      const nicheSelect = wrapper.find('[data-testid="filter-niche"]')
      const options = nicheSelect.findAll('option')

      expect(options.length).toBeGreaterThan(1)
      expect(options[0].text()).toBe('Niche')
    })
  })

  describe('Accessibility', () => {
    it('search input has placeholder text', () => {
      const wrapper = mountFilterBar()

      const searchInput = wrapper.find('[data-testid="filter-search"]')
      expect(searchInput.attributes('placeholder')).toBe('Rechercher un talent...')
    })

    it('disabled elements have title explaining unavailability', () => {
      const wrapper = mountFilterBar()

      const searchInput = wrapper.find('[data-testid="filter-search"]')
      expect(searchInput.attributes('title')).toContain('prochainement')

      const citySelect = wrapper.find('[data-testid="filter-city"]')
      expect(citySelect.attributes('title')).toContain('prochainement')
    })

    it('has cursor-not-allowed class on disabled dropdowns', () => {
      const wrapper = mountFilterBar()

      const citySelect = wrapper.find('[data-testid="filter-city"]')
      expect(citySelect.classes()).toContain('cursor-not-allowed')
    })
  })

  describe('Styling', () => {
    it('has rounded border styling', () => {
      const wrapper = mountFilterBar()

      const container = wrapper.find('[data-testid="filter-bar"]')
      expect(container.classes()).toContain('rounded-2xl')
      expect(container.classes()).toContain('border')
    })

    it('has responsive layout classes', () => {
      const wrapper = mountFilterBar()

      // Check for flex layout that adjusts at lg breakpoint
      const flexContainer = wrapper.find('.flex.flex-col.gap-4.lg\\:flex-row')
      expect(flexContainer.exists()).toBe(true)
    })
  })
})
