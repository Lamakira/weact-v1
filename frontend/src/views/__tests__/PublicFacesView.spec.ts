import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory, type Router } from 'vue-router'
import PublicFacesView from '../PublicFacesView.vue'
import * as publicFacesApi from '@/features/public/services/publicFacesApi'
import type { PublicFacesResponse, PublicFace } from '@/features/public/services/publicFacesApi'

// Mock the API module
vi.mock('@/features/public/services/publicFacesApi', () => ({
  fetchPublicFaces: vi.fn(),
}))

const mockFaces: PublicFace[] = [
  {
    id: 1,
    prenom: 'Adjoua',
    ville: 'Cotonou',
    categorie: 'acteur',
    categorie_label: 'Acteur',
    is_available: true,
    profile_photo_thumbnail_url: 'https://example.com/photo1.jpg',
    average_rating: 4.5,
  },
  {
    id: 2,
    prenom: 'Koffi',
    ville: 'Porto-Novo',
    categorie: 'mannequin',
    categorie_label: 'Mannequin',
    is_available: false,
    profile_photo_thumbnail_url: 'https://example.com/photo2.jpg',
    average_rating: 4.0,
  },
]

const mockResponse: PublicFacesResponse = {
  data: mockFaces,
  meta: {
    current_page: 1,
    last_page: 3,
    per_page: 15,
    total: 35,
  },
  message: 'Faces retrieved successfully',
}

describe('PublicFacesView', () => {
  let router: Router

  beforeEach(() => {
    router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/faces', name: 'public-faces-list', component: PublicFacesView },
        { path: '/faces/:id', name: 'public-face-profile', component: { template: '<div>Profile</div>' } },
      ],
    })

    vi.clearAllMocks()
  })

  afterEach(() => {
    vi.resetAllMocks()
  })

  const mountView = async (route = '/faces') => {
    router.push(route)
    await router.isReady()

    const wrapper = mount(PublicFacesView, {
      global: {
        plugins: [router],
        stubs: {
          // Stub complex child components
          FilterBar: { template: '<div data-testid="filter-bar">FilterBar</div>' },
        },
      },
    })

    return wrapper
  }

  describe('Page structure', () => {
    it('renders page header with title and description', async () => {
      vi.mocked(publicFacesApi.fetchPublicFaces).mockResolvedValue(mockResponse)

      const wrapper = await mountView()
      await flushPromises()

      expect(wrapper.find('[data-testid="faces-page-title"]').text()).toBe('Nos Talents')
      expect(wrapper.text()).toContain('Découvrez notre vivier de talents béninois')
    })

    it('renders the filter bar', async () => {
      vi.mocked(publicFacesApi.fetchPublicFaces).mockResolvedValue(mockResponse)

      const wrapper = await mountView()
      await flushPromises()

      expect(wrapper.find('[data-testid="filter-bar"]').exists()).toBe(true)
    })

    it('has the correct data-testid', async () => {
      vi.mocked(publicFacesApi.fetchPublicFaces).mockResolvedValue(mockResponse)

      const wrapper = await mountView()
      await flushPromises()

      expect(wrapper.find('[data-testid="public-faces-view"]').exists()).toBe(true)
    })
  })

  describe('Loading state', () => {
    it('shows loading skeletons while fetching', async () => {
      // Don't resolve the promise immediately
      vi.mocked(publicFacesApi.fetchPublicFaces).mockReturnValue(
        new Promise(() => {}) // Never resolves
      )

      const wrapper = await mountView()

      expect(wrapper.find('[data-testid="faces-loading"]').exists()).toBe(true)
    })

    it('hides loading skeletons after data loads', async () => {
      vi.mocked(publicFacesApi.fetchPublicFaces).mockResolvedValue(mockResponse)

      const wrapper = await mountView()
      await flushPromises()

      expect(wrapper.find('[data-testid="faces-loading"]').exists()).toBe(false)
    })
  })

  describe('Error state', () => {
    it('shows error message when API fails', async () => {
      vi.mocked(publicFacesApi.fetchPublicFaces).mockRejectedValue(new Error('Network error'))

      const wrapper = await mountView()
      await flushPromises()

      expect(wrapper.find('[data-testid="faces-error"]').exists()).toBe(true)
      expect(wrapper.text()).toContain('Une erreur est survenue')
    })

    it('shows retry button on error', async () => {
      vi.mocked(publicFacesApi.fetchPublicFaces).mockRejectedValue(new Error('Network error'))

      const wrapper = await mountView()
      await flushPromises()

      expect(wrapper.find('[data-testid="faces-retry-button"]').exists()).toBe(true)
    })

    it('retries API call when retry button clicked', async () => {
      vi.mocked(publicFacesApi.fetchPublicFaces)
        .mockRejectedValueOnce(new Error('Network error'))
        .mockResolvedValueOnce(mockResponse)

      const wrapper = await mountView()
      await flushPromises()

      await wrapper.find('[data-testid="faces-retry-button"]').trigger('click')
      await flushPromises()

      expect(publicFacesApi.fetchPublicFaces).toHaveBeenCalledTimes(2)
      expect(wrapper.find('[data-testid="faces-error"]').exists()).toBe(false)
    })
  })

  describe('Empty state', () => {
    it('shows empty state when no faces', async () => {
      vi.mocked(publicFacesApi.fetchPublicFaces).mockResolvedValue({
        ...mockResponse,
        data: [],
        meta: { ...mockResponse.meta, total: 0 },
      })

      const wrapper = await mountView()
      await flushPromises()

      expect(wrapper.find('[data-testid="faces-empty"]').exists()).toBe(true)
      expect(wrapper.text()).toContain('Aucun talent disponible')
    })
  })

  describe('Faces grid', () => {
    it('renders faces grid with data', async () => {
      vi.mocked(publicFacesApi.fetchPublicFaces).mockResolvedValue(mockResponse)

      const wrapper = await mountView()
      await flushPromises()

      expect(wrapper.find('[data-testid="faces-grid"]').exists()).toBe(true)
    })

    it('renders face cards for each face', async () => {
      vi.mocked(publicFacesApi.fetchPublicFaces).mockResolvedValue(mockResponse)

      const wrapper = await mountView()
      await flushPromises()

      expect(wrapper.find('[data-testid="face-card-1"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="face-card-2"]').exists()).toBe(true)
    })

    it('shows total count', async () => {
      vi.mocked(publicFacesApi.fetchPublicFaces).mockResolvedValue(mockResponse)

      const wrapper = await mountView()
      await flushPromises()

      expect(wrapper.text()).toContain('35')
      expect(wrapper.text()).toContain('talents trouvés')
    })

    it('has responsive grid classes', async () => {
      vi.mocked(publicFacesApi.fetchPublicFaces).mockResolvedValue(mockResponse)

      const wrapper = await mountView()
      await flushPromises()

      const grid = wrapper.find('[data-testid="faces-grid"]')
      expect(grid.classes()).toContain('grid-cols-2')
      expect(grid.classes()).toContain('sm:grid-cols-3')
      expect(grid.classes()).toContain('lg:grid-cols-4')
    })
  })

  describe('Pagination', () => {
    it('renders pagination when multiple pages', async () => {
      vi.mocked(publicFacesApi.fetchPublicFaces).mockResolvedValue(mockResponse)

      const wrapper = await mountView()
      await flushPromises()

      expect(wrapper.find('[data-testid="faces-pagination"]').exists()).toBe(true)
    })

    it('hides pagination when single page', async () => {
      vi.mocked(publicFacesApi.fetchPublicFaces).mockResolvedValue({
        ...mockResponse,
        meta: { ...mockResponse.meta, last_page: 1 },
      })

      const wrapper = await mountView()
      await flushPromises()

      expect(wrapper.find('[data-testid="faces-pagination"]').exists()).toBe(false)
    })

    it('loads new page when pagination changes', async () => {
      vi.mocked(publicFacesApi.fetchPublicFaces).mockResolvedValue(mockResponse)

      const wrapper = await mountView()
      await flushPromises()

      // Find the pagination component and trigger page change
      const paginationComponent = wrapper.findComponent({ name: 'Pagination' })
      paginationComponent.vm.$emit('page-change', 2)
      await flushPromises()

      // Should update URL query param
      expect(router.currentRoute.value.query.page).toBe('2')
    })
  })

  describe('API integration', () => {
    it('calls fetchPublicFaces on mount', async () => {
      vi.mocked(publicFacesApi.fetchPublicFaces).mockResolvedValue(mockResponse)

      await mountView()
      await flushPromises()

      expect(publicFacesApi.fetchPublicFaces).toHaveBeenCalledWith(1, 15, {
        categorie: undefined,
        niche: undefined,
        ville: undefined,
      })
    })

    it('uses page from URL query param', async () => {
      vi.mocked(publicFacesApi.fetchPublicFaces).mockResolvedValue(mockResponse)

      await mountView('/faces?page=2')
      await flushPromises()

      expect(publicFacesApi.fetchPublicFaces).toHaveBeenCalledWith(2, 15, {
        categorie: undefined,
        niche: undefined,
        ville: undefined,
      })
    })
  })

  describe('Accessibility', () => {
    it('has proper heading hierarchy', async () => {
      vi.mocked(publicFacesApi.fetchPublicFaces).mockResolvedValue(mockResponse)

      const wrapper = await mountView()
      await flushPromises()

      const h1 = wrapper.find('h1')
      expect(h1.exists()).toBe(true)
      expect(h1.text()).toBe('Nos Talents')
    })
  })
})
