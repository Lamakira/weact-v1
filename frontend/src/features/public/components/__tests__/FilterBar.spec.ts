import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import FilterBar from '../FilterBar.vue'
import type { FilterOption, FacesFilterParams } from '../../services/publicFacesApi'

// Mock @vueuse/core watchDebounced to fire immediately in tests
vi.mock('@vueuse/core', () => ({
  watchDebounced: (source: any, cb: any, _opts?: any) => {
    // Use a standard watch that fires immediately (no debounce in tests)
    const { watch } = require('vue')
    return watch(source, cb)
  },
}))

const mockCategories: FilterOption[] = [
  { value: 'acteur', label: 'Acteur' },
  { value: 'mannequin', label: 'Mannequin' },
]

const mockNiches: FilterOption[] = [
  { value: 'beaute', label: 'Beauté' },
  { value: 'mode', label: 'Mode' },
]

const mockCities = ['Cotonou', 'Parakou']

const defaultProps = {
  categories: mockCategories,
  niches: mockNiches,
  cities: mockCities,
  currentFilters: {} as FacesFilterParams,
}

function mountFilterBar(props = defaultProps) {
  return mount(FilterBar, { props })
}

describe('FilterBar', () => {
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

  describe('Active filter dropdowns', () => {
    it('city filter is NOT disabled', () => {
      const wrapper = mountFilterBar()
      const citySelect = wrapper.find('[data-testid="filter-city"]')
      expect(citySelect.attributes('disabled')).toBeUndefined()
    })

    it('category filter is NOT disabled', () => {
      const wrapper = mountFilterBar()
      const categorySelect = wrapper.find('[data-testid="filter-category"]')
      expect(categorySelect.attributes('disabled')).toBeUndefined()
    })

    it('niche filter is NOT disabled', () => {
      const wrapper = mountFilterBar()
      const nicheSelect = wrapper.find('[data-testid="filter-niche"]')
      expect(nicheSelect.attributes('disabled')).toBeUndefined()
    })

    it('search input is enabled (not disabled)', () => {
      const wrapper = mountFilterBar()
      const searchInput = wrapper.find('[data-testid="filter-search"]')
      expect(searchInput.attributes('disabled')).toBeUndefined()
    })

    it('advanced filters button is still disabled', () => {
      const wrapper = mountFilterBar()
      const advancedBtn = wrapper.find('[data-testid="filter-advanced"]')
      expect(advancedBtn.attributes('disabled')).toBeDefined()
    })
  })

  describe('Options from props', () => {
    it('category dropdown populates from props', () => {
      const wrapper = mountFilterBar()
      const categorySelect = wrapper.find('[data-testid="filter-category"]')
      const options = categorySelect.findAll('option')

      // Default + 2 categories
      expect(options).toHaveLength(3)
      expect(options[0].text()).toBe('Catégorie')
      expect(options[1].text()).toBe('Acteur')
      expect(options[2].text()).toBe('Mannequin')
    })

    it('niche dropdown populates from props', () => {
      const wrapper = mountFilterBar()
      const nicheSelect = wrapper.find('[data-testid="filter-niche"]')
      const options = nicheSelect.findAll('option')

      expect(options).toHaveLength(3)
      expect(options[0].text()).toBe('Niche')
      expect(options[1].text()).toBe('Beauté')
      expect(options[2].text()).toBe('Mode')
    })

    it('city dropdown populates from props', () => {
      const wrapper = mountFilterBar()
      const citySelect = wrapper.find('[data-testid="filter-city"]')
      const options = citySelect.findAll('option')

      expect(options).toHaveLength(3)
      expect(options[0].text()).toBe('Ville')
      expect(options[1].text()).toBe('Cotonou')
      expect(options[2].text()).toBe('Parakou')
    })
  })

  describe('Filter change events', () => {
    it('emits filter-change when category is selected', async () => {
      const wrapper = mountFilterBar()
      const categorySelect = wrapper.find('[data-testid="filter-category"]')

      await categorySelect.setValue('acteur')
      await categorySelect.trigger('change')

      expect(wrapper.emitted('filter-change')).toBeTruthy()
      const emitted = wrapper.emitted('filter-change')![0][0] as FacesFilterParams
      expect(emitted.categorie).toBe('acteur')
    })

    it('emits filter-change when niche is selected', async () => {
      const wrapper = mountFilterBar()
      const nicheSelect = wrapper.find('[data-testid="filter-niche"]')

      await nicheSelect.setValue('beaute')
      await nicheSelect.trigger('change')

      expect(wrapper.emitted('filter-change')).toBeTruthy()
      const emitted = wrapper.emitted('filter-change')![0][0] as FacesFilterParams
      expect(emitted.niche).toBe('beaute')
    })

    it('emits filter-change when city is selected', async () => {
      const wrapper = mountFilterBar()
      const citySelect = wrapper.find('[data-testid="filter-city"]')

      await citySelect.setValue('Cotonou')
      await citySelect.trigger('change')

      expect(wrapper.emitted('filter-change')).toBeTruthy()
      const emitted = wrapper.emitted('filter-change')![0][0] as FacesFilterParams
      expect(emitted.ville).toBe('Cotonou')
    })

    it('emits undefined for cleared filter', async () => {
      const wrapper = mountFilterBar({
        ...defaultProps,
        currentFilters: { categorie: 'acteur' },
      })
      const categorySelect = wrapper.find('[data-testid="filter-category"]')

      await categorySelect.setValue('')
      await categorySelect.trigger('change')

      expect(wrapper.emitted('filter-change')).toBeTruthy()
      const emitted = wrapper.emitted('filter-change')![0][0] as FacesFilterParams
      expect(emitted.categorie).toBeUndefined()
    })
  })

  // ─── Search Tests (Task 8) ───────────────────────────────────────

  describe('Search functionality', () => {
    it('search input has correct placeholder', () => {
      const wrapper = mountFilterBar()
      const searchInput = wrapper.find('[data-testid="filter-search"]')
      expect(searchInput.attributes('placeholder')).toBe('Rechercher un talent...')
    })

    it('typing in search emits filter-change with search param', async () => {
      const wrapper = mountFilterBar()
      const searchInput = wrapper.find('[data-testid="filter-search"]')

      await searchInput.setValue('Adjoua')

      // watchDebounced is mocked to fire immediately
      const emitted = wrapper.emitted('filter-change')
      expect(emitted).toBeTruthy()

      const lastEmit = emitted![emitted!.length - 1][0] as FacesFilterParams
      expect(lastEmit.search).toBe('Adjoua')
    })

    it('clearing search emits filter-change with search undefined', async () => {
      const wrapper = mountFilterBar({
        ...defaultProps,
        currentFilters: { search: 'Adjoua' },
      })
      const searchInput = wrapper.find('[data-testid="filter-search"]')

      await searchInput.setValue('')

      const emitted = wrapper.emitted('filter-change')
      expect(emitted).toBeTruthy()

      const lastEmit = emitted![emitted!.length - 1][0] as FacesFilterParams
      expect(lastEmit.search).toBeUndefined()
    })

    it('search input syncs from currentFilters prop', () => {
      const wrapper = mountFilterBar({
        ...defaultProps,
        currentFilters: { search: 'Kofi' },
      })
      const searchInput = wrapper.find('[data-testid="filter-search"]')
      expect((searchInput.element as HTMLInputElement).value).toBe('Kofi')
    })

    it('clear button appears when search is not empty', () => {
      const wrapper = mountFilterBar({
        ...defaultProps,
        currentFilters: { search: 'test' },
      })
      expect(wrapper.find('[data-testid="filter-search-clear"]').exists()).toBe(true)
    })

    it('clear button is hidden when search is empty', () => {
      const wrapper = mountFilterBar()
      expect(wrapper.find('[data-testid="filter-search-clear"]').exists()).toBe(false)
    })

    it('clicking clear button empties the search input', async () => {
      const wrapper = mountFilterBar({
        ...defaultProps,
        currentFilters: { search: 'test' },
      })

      await wrapper.find('[data-testid="filter-search-clear"]').trigger('click')

      const searchInput = wrapper.find('[data-testid="filter-search"]')
      expect((searchInput.element as HTMLInputElement).value).toBe('')
    })

    it('does not emit filter-change for single character (minlength guard)', async () => {
      const wrapper = mountFilterBar()
      const searchInput = wrapper.find('[data-testid="filter-search"]')

      await searchInput.setValue('A')

      // Should NOT emit filter-change because single char is below min:2
      const emitted = wrapper.emitted('filter-change')
      expect(emitted).toBeFalsy()
    })

    it('search input has maxlength attribute', () => {
      const wrapper = mountFilterBar()
      const searchInput = wrapper.find('[data-testid="filter-search"]')
      expect(searchInput.attributes('maxlength')).toBe('255')
    })

    it('search works alongside dropdown filters', async () => {
      const wrapper = mountFilterBar({
        ...defaultProps,
        currentFilters: { categorie: 'acteur' },
      })

      const searchInput = wrapper.find('[data-testid="filter-search"]')
      await searchInput.setValue('Adjoua')

      const emitted = wrapper.emitted('filter-change')
      expect(emitted).toBeTruthy()

      const lastEmit = emitted![emitted!.length - 1][0] as FacesFilterParams
      expect(lastEmit.search).toBe('Adjoua')
      expect(lastEmit.categorie).toBe('acteur')
    })
  })

  describe('Accessibility', () => {
    it('search input has placeholder text', () => {
      const wrapper = mountFilterBar()
      const searchInput = wrapper.find('[data-testid="filter-search"]')
      expect(searchInput.attributes('placeholder')).toBe('Rechercher un talent...')
    })

    it('search input has aria-label', () => {
      const wrapper = mountFilterBar()
      const searchInput = wrapper.find('[data-testid="filter-search"]')
      expect(searchInput.attributes('aria-label')).toBe('Rechercher un talent')
    })

    it('clear button has aria-label', () => {
      const wrapper = mountFilterBar({
        ...defaultProps,
        currentFilters: { search: 'test' },
      })
      const clearBtn = wrapper.find('[data-testid="filter-search-clear"]')
      expect(clearBtn.attributes('aria-label')).toBe('Effacer la recherche')
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
      const flexContainer = wrapper.find('.flex.flex-col')
      expect(flexContainer.exists()).toBe(true)
    })
  })
})
