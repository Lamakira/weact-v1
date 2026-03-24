import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory, type Router } from 'vue-router'
import PublicMissionsView from '../PublicMissionsView.vue'
import * as publicMissionsApi from '@/features/public/services/publicMissionsApi'
import type {
  PublicMissionsResponse,
  PublicMission,
  PublicMissionFilters,
} from '@/features/public/services/publicMissionsApi'

// Mock the API module
vi.mock('@/features/public/services/publicMissionsApi', () => ({
  fetchPublicMissions: vi.fn(),
}))

const mockMissions: PublicMission[] = [
  {
    id: 1,
    titre: 'Publicité TV',
    description: 'Tournage publicitaire pour une marque locale',
    date_tournage: '2025-03-15',
    profil_recherche: null,
    budget: 100000,
    date_limite_candidature: '2025-02-28',
    nombre_faces_voulu: 3,
    type_mission: 'publicite',
    type_mission_label: 'Publicité',
    genre_voulu: 'mixte',
    genre_voulu_label: 'Mixte',
    lieu: 'Cotonou',
    duree: '1 jour',
    status: 'published',
    status_label: 'Publiée',
    created_at: '2025-01-15T10:00:00+00:00',
    producer: {
      id: 1,
      display_name: 'Producteur A',
      profile_photo_thumbnail_url: null,
      average_rating: 4.5,
      ratings_count: 10,
    },
  },
  {
    id: 2,
    titre: 'Court-métrage Festival',
    description: 'Court-métrage pour festival international',
    date_tournage: '2025-04-01',
    profil_recherche: 'Jeune femme',
    budget: 200000,
    date_limite_candidature: '2025-03-15',
    nombre_faces_voulu: 1,
    type_mission: 'court_metrage',
    type_mission_label: 'Court-métrage',
    genre_voulu: 'feminin',
    genre_voulu_label: 'Féminin',
    lieu: 'Porto-Novo',
    duree: '3 jours',
    status: 'published',
    status_label: 'Publiée',
    created_at: '2025-01-14T10:00:00+00:00',
    producer: null,
  },
]

const mockResponse: PublicMissionsResponse = {
  data: mockMissions,
  meta: {
    current_page: 1,
    last_page: 3,
    per_page: 15,
    total: 35,
  },
  message: 'Missions retrieved successfully',
}

describe('PublicMissionsView', () => {
  let router: Router

  beforeEach(() => {
    router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/missions', name: 'public-missions-list', component: PublicMissionsView },
        { path: '/missions/:id', name: 'public-mission-detail', component: { template: '<div>Detail</div>' } },
        { path: '/register/face', name: 'register-face', component: { template: '<div>Register Face</div>' } },
        { path: '/register/producer', name: 'register-producer', component: { template: '<div>Register Producer</div>' } },
      ],
    })

    vi.clearAllMocks()
  })

  afterEach(() => {
    vi.resetAllMocks()
  })

  const mountView = async (route = '/missions') => {
    router.push(route)
    await router.isReady()

    const wrapper = mount(PublicMissionsView, {
      global: {
        plugins: [router],
      },
    })

    return wrapper
  }

  describe('Page structure', () => {
    it('renders page header with title and description', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions).mockResolvedValue(mockResponse)

      const wrapper = await mountView()
      await flushPromises()

      expect(wrapper.find('[data-testid="missions-page-title"]').text()).toBe('Missions disponibles')
      expect(wrapper.text()).toContain('Découvrez les opportunités de casting au Bénin')
    })

    it('has the correct data-testid', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions).mockResolvedValue(mockResponse)

      const wrapper = await mountView()
      await flushPromises()

      expect(wrapper.find('[data-testid="public-missions-view"]').exists()).toBe(true)
    })
  })

  describe('Loading state', () => {
    it('shows loading skeletons while fetching', async () => {
      // Don't resolve the promise immediately
      vi.mocked(publicMissionsApi.fetchPublicMissions).mockReturnValue(
        new Promise(() => {}) // Never resolves
      )

      const wrapper = await mountView()

      expect(wrapper.find('[data-testid="missions-loading"]').exists()).toBe(true)
    })

    it('hides loading skeletons after data loads', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions).mockResolvedValue(mockResponse)

      const wrapper = await mountView()
      await flushPromises()

      expect(wrapper.find('[data-testid="missions-loading"]').exists()).toBe(false)
    })
  })

  describe('Error state', () => {
    it('shows error message when API fails', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions).mockRejectedValue(new Error('Network error'))

      const wrapper = await mountView()
      await flushPromises()

      expect(wrapper.find('[data-testid="missions-error"]').exists()).toBe(true)
      expect(wrapper.text()).toContain('Une erreur est survenue')
    })

    it('shows retry button on error', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions).mockRejectedValue(new Error('Network error'))

      const wrapper = await mountView()
      await flushPromises()

      expect(wrapper.find('[data-testid="missions-retry-button"]').exists()).toBe(true)
    })

    it('retries API call when retry button clicked', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions)
        .mockRejectedValueOnce(new Error('Network error'))
        .mockResolvedValueOnce(mockResponse)

      const wrapper = await mountView()
      await flushPromises()

      await wrapper.find('[data-testid="missions-retry-button"]').trigger('click')
      await flushPromises()

      expect(publicMissionsApi.fetchPublicMissions).toHaveBeenCalledTimes(2)
      expect(wrapper.find('[data-testid="missions-error"]').exists()).toBe(false)
    })
  })

  describe('Empty state', () => {
    it('shows empty state when no missions', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions).mockResolvedValue({
        ...mockResponse,
        data: [],
        meta: { ...mockResponse.meta, total: 0 },
      })

      const wrapper = await mountView()
      await flushPromises()

      expect(wrapper.find('[data-testid="missions-empty"]').exists()).toBe(true)
      expect(wrapper.text()).toContain('Aucune mission disponible')
    })

    it('shows filtered empty state when filters are active', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions).mockResolvedValue({
        ...mockResponse,
        data: [],
        meta: { ...mockResponse.meta, total: 0 },
      })

      const wrapper = await mountView('/missions?type_mission=film')
      await flushPromises()

      expect(wrapper.find('[data-testid="missions-empty"]').exists()).toBe(true)
      expect(wrapper.text()).toContain('Aucune mission ne correspond à vos critères')
      expect(wrapper.find('[data-testid="missions-empty-reset"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="registration-cta"]').exists()).toBe(false)
    })
  })

  describe('Missions grid', () => {
    it('renders missions grid with data', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions).mockResolvedValue(mockResponse)

      const wrapper = await mountView()
      await flushPromises()

      expect(wrapper.find('[data-testid="missions-grid"]').exists()).toBe(true)
    })

    it('renders mission cards for each mission', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions).mockResolvedValue(mockResponse)

      const wrapper = await mountView()
      await flushPromises()

      expect(wrapper.find('[data-testid="mission-card-1"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="mission-card-2"]').exists()).toBe(true)
    })

    it('shows total count', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions).mockResolvedValue(mockResponse)

      const wrapper = await mountView()
      await flushPromises()

      expect(wrapper.find('[data-testid="missions-count"]').text()).toContain('35')
    })

    it('shows filtered result wording and reset action when filters are active', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions).mockResolvedValue(mockResponse)

      const wrapper = await mountView('/missions?type_mission=film')
      await flushPromises()

      expect(wrapper.find('[data-testid="missions-count"]').text()).toContain('missions trouv')
      expect(wrapper.find('[data-testid="missions-reset-inline"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="registration-cta"]').exists()).toBe(false)
    })

    it('uses plural form for multiple missions', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions).mockResolvedValue(mockResponse)

      const wrapper = await mountView()
      await flushPromises()

      expect(wrapper.find('[data-testid="missions-count"]').text()).toContain('missions')
      expect(wrapper.find('[data-testid="missions-count"]').text()).toContain('disponibles')
    })

    it('has responsive grid classes', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions).mockResolvedValue(mockResponse)

      const wrapper = await mountView()
      await flushPromises()

      const grid = wrapper.find('[data-testid="missions-grid"]')
      expect(grid.classes()).toContain('grid-cols-1')
      expect(grid.classes()).toContain('md:grid-cols-2')
      expect(grid.classes()).toContain('lg:grid-cols-3')
    })
  })

  describe('Pagination', () => {
    it('renders pagination when multiple pages', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions).mockResolvedValue(mockResponse)

      const wrapper = await mountView()
      await flushPromises()

      expect(wrapper.find('[data-testid="missions-pagination"]').exists()).toBe(true)
    })

    it('hides pagination when single page', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions).mockResolvedValue({
        ...mockResponse,
        meta: { ...mockResponse.meta, last_page: 1 },
      })

      const wrapper = await mountView()
      await flushPromises()

      expect(wrapper.find('[data-testid="missions-pagination"]').exists()).toBe(false)
    })

    it('loads new page when pagination changes', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions).mockResolvedValue(mockResponse)

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
    it('calls fetchPublicMissions on mount', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions).mockResolvedValue(mockResponse)

      await mountView()
      await flushPromises()

      expect(publicMissionsApi.fetchPublicMissions).toHaveBeenCalledWith(1, 15, {})
    })

    it('uses page from URL query param', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions).mockResolvedValue(mockResponse)

      await mountView('/missions?page=2')
      await flushPromises()

      expect(publicMissionsApi.fetchPublicMissions).toHaveBeenCalledWith(2, 15, {})
    })

    it('hydrates filters from URL query params', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions).mockResolvedValue(mockResponse)

      await mountView('/missions?type_mission=film&lieu=Cotonou&budget_min=100000&budget_max=200000')
      await flushPromises()

      expect(publicMissionsApi.fetchPublicMissions).toHaveBeenCalledWith(1, 15, {
        type_mission: 'film',
        lieu: 'Cotonou',
        budget_min: 100000,
        budget_max: 200000,
      } satisfies PublicMissionFilters)
    })

    it('updates filters when the filter bar emits a change', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions)
        .mockResolvedValueOnce(mockResponse)
        .mockResolvedValueOnce(mockResponse)

      const wrapper = await mountView()
      await flushPromises()

      const filtersBar = wrapper.findComponent({ name: 'PublicMissionFiltersBar' })
      filtersBar.vm.$emit('filter-change', {
        type_mission: 'film',
        budget_min: 120000,
      } satisfies PublicMissionFilters)
      await flushPromises()

      expect(router.currentRoute.value.query.type_mission).toBe('film')
      expect(router.currentRoute.value.query.budget_min).toBe('120000')
      expect(router.currentRoute.value.query.page).toBeUndefined()
      expect(publicMissionsApi.fetchPublicMissions).toHaveBeenLastCalledWith(1, 15, {
        type_mission: 'film',
        budget_min: 120000,
      })
    })

    it('preserves unrelated query params when filters change', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions)
        .mockResolvedValueOnce(mockResponse)
        .mockResolvedValueOnce(mockResponse)

      const wrapper = await mountView('/missions?utm_source=campaign&page=3')
      await flushPromises()

      const filtersBar = wrapper.findComponent({ name: 'PublicMissionFiltersBar' })
      filtersBar.vm.$emit('filter-change', {
        budget_max: 150000,
      } satisfies PublicMissionFilters)
      await flushPromises()

      expect(router.currentRoute.value.query.utm_source).toBe('campaign')
      expect(router.currentRoute.value.query.budget_max).toBe('150000')
      expect(router.currentRoute.value.query.page).toBeUndefined()
    })
  })

  describe('Registration CTA', () => {
    it('renders registration CTA section after data loads', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions).mockResolvedValue(mockResponse)

      const wrapper = await mountView()
      await flushPromises()

      expect(wrapper.find('[data-testid="registration-cta"]').exists()).toBe(true)
    })

    it('renders face registration link to /register/face', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions).mockResolvedValue(mockResponse)

      const wrapper = await mountView()
      await flushPromises()

      const link = wrapper.find('[data-testid="register-face-cta"]')
      expect(link.exists()).toBe(true)
      expect(link.attributes('href')).toBe('/register/face')
    })

    it('renders producer registration link to /register/producer', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions).mockResolvedValue(mockResponse)

      const wrapper = await mountView()
      await flushPromises()

      const link = wrapper.find('[data-testid="register-producer-cta"]')
      expect(link.exists()).toBe(true)
      expect(link.attributes('href')).toBe('/register/producer')
    })

    it('does not render CTA in loading state', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions).mockReturnValue(
        new Promise(() => {})
      )

      const wrapper = await mountView()

      expect(wrapper.find('[data-testid="registration-cta"]').exists()).toBe(false)
    })

    it('does not render CTA in error state', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions).mockRejectedValue(new Error('Network error'))

      const wrapper = await mountView()
      await flushPromises()

      expect(wrapper.find('[data-testid="registration-cta"]').exists()).toBe(false)
    })

    it('renders CTA in empty state', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions).mockResolvedValue({
        ...mockResponse,
        data: [],
        meta: { ...mockResponse.meta, total: 0 },
      })

      const wrapper = await mountView()
      await flushPromises()

      expect(wrapper.find('[data-testid="registration-cta"]').exists()).toBe(true)
    })
  })

  describe('Accessibility', () => {
    it('has proper heading hierarchy', async () => {
      vi.mocked(publicMissionsApi.fetchPublicMissions).mockResolvedValue(mockResponse)

      const wrapper = await mountView()
      await flushPromises()

      const h1 = wrapper.find('h1')
      expect(h1.exists()).toBe(true)
      expect(h1.text()).toBe('Missions disponibles')
    })
  })
})
