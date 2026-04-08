import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory, type Router } from 'vue-router'
import PublicFacesView from '../PublicFacesView.vue'
import * as publicFacesApi from '@/features/public/services/publicFacesApi'
import type { PublicFacesResponse, PublicFace } from '@/features/public/services/publicFacesApi'

vi.mock('@/features/public/services/publicFacesApi', () => ({
  fetchPublicFaces: vi.fn(),
  fetchFilterOptions: vi.fn().mockResolvedValue({
    data: {
      categories: [],
      niches: [],
      cities: [],
    },
  }),
}))

vi.mock('@/composables/useScrollReveal', () => ({
  useScrollReveal: () => ({
    reinit: vi.fn(),
  }),
}))

const mockFaces: PublicFace[] = [
  {
    id: 1,
    username: 'adjoua-dossou',
    prenom: 'Adjoua',
    nom: 'Dossou',
    ville: 'Cotonou',
    categories: [{ value: 'acteur', label: 'Acteur' }],
    is_available: true,
    profile_photo_thumbnail_url: 'https://example.com/photo1.jpg',
    average_rating: 4.5,
  },
  {
    id: 2,
    username: 'koffi-agbangla',
    prenom: 'Koffi',
    nom: 'Agbangla',
    ville: 'Porto-Novo',
    categories: [{ value: 'mannequin', label: 'Mannequin' }],
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
    per_page: 16,
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
        { path: '/faces/:username', name: 'public-face-profile', component: { template: '<div>Profile</div>' } },
      ],
    })

    vi.clearAllMocks()
    vi.mocked(publicFacesApi.fetchFilterOptions).mockResolvedValue({
      data: {
        categories: [],
        niches: [],
        cities: [],
      },
    })
  })

  afterEach(() => {
    vi.resetAllMocks()
  })

  const mountView = async (route = '/faces') => {
    router.push(route)
    await router.isReady()

    return mount(PublicFacesView, {
      global: {
        plugins: [router],
        stubs: {
          FaceCard: {
            props: ['face'],
            template: '<div :data-testid="`face-card-${face.id}`">{{ face.prenom }}</div>',
          },
          FilterBar: {
            template: '<div data-testid="filter-bar">FilterBar</div>',
          },
          RegistrationCta: {
            template: `
              <section data-testid="registration-cta">
                <a href="/register/face" data-testid="register-face-cta">Face</a>
                <a href="/register/producer" data-testid="register-producer-cta">Producer</a>
              </section>
            `,
          },
          Pagination: {
            name: 'Pagination',
            template: '<div data-testid="pagination-stub"></div>',
          },
        },
      },
    })
  }

  it('renders the intro copy and filter bar', async () => {
    vi.mocked(publicFacesApi.fetchPublicFaces).mockResolvedValue(mockResponse)

    const wrapper = await mountView()
    await flushPromises()

    expect(wrapper.find('[data-testid="public-faces-view"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="filter-bar"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Découvrez notre vivier de talents béninois')
  })

  it('shows loading skeletons while fetching', async () => {
    vi.mocked(publicFacesApi.fetchPublicFaces).mockReturnValue(new Promise(() => {}))

    const wrapper = await mountView()

    expect(wrapper.find('[data-testid="faces-loading"]').exists()).toBe(true)
  })

  it('shows an error state and retries the request', async () => {
    vi.mocked(publicFacesApi.fetchPublicFaces)
      .mockRejectedValueOnce(new Error('Network error'))
      .mockResolvedValueOnce(mockResponse)

    const wrapper = await mountView()
    await flushPromises()

    expect(wrapper.find('[data-testid="faces-error"]').exists()).toBe(true)

    await wrapper.find('[data-testid="faces-retry-button"]').trigger('click')
    await flushPromises()

    expect(publicFacesApi.fetchPublicFaces).toHaveBeenCalledTimes(2)
    expect(wrapper.find('[data-testid="faces-error"]').exists()).toBe(false)
  })

  it('shows the empty state and keeps the registration CTA', async () => {
    vi.mocked(publicFacesApi.fetchPublicFaces).mockResolvedValue({
      ...mockResponse,
      data: [],
      meta: { ...mockResponse.meta, total: 0 },
    })

    const wrapper = await mountView()
    await flushPromises()

    expect(wrapper.find('[data-testid="faces-empty"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Aucun talent disponible')
    expect(wrapper.find('[data-testid="registration-cta"]').exists()).toBe(true)
  })

  it('renders the faces grid, cards, count, and CTA', async () => {
    vi.mocked(publicFacesApi.fetchPublicFaces).mockResolvedValue(mockResponse)

    const wrapper = await mountView()
    await flushPromises()

    expect(wrapper.find('[data-testid="faces-grid"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="face-card-1"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="face-card-2"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('35')
    expect(wrapper.text()).toContain('talents trouvés')
    expect(wrapper.find('[data-testid="registration-cta"]').exists()).toBe(true)
  })

  it('renders pagination when multiple pages and updates the route on page change', async () => {
    vi.mocked(publicFacesApi.fetchPublicFaces).mockResolvedValue(mockResponse)

    const wrapper = await mountView()
    await flushPromises()

    expect(wrapper.find('[data-testid="faces-pagination"]').exists()).toBe(true)

    wrapper.findComponent({ name: 'Pagination' }).vm.$emit('page-change', 2)
    await flushPromises()

    expect(router.currentRoute.value.query.page).toBe('2')
  })

  it('hides pagination when there is a single page', async () => {
    vi.mocked(publicFacesApi.fetchPublicFaces).mockResolvedValue({
      ...mockResponse,
      meta: { ...mockResponse.meta, last_page: 1 },
    })

    const wrapper = await mountView()
    await flushPromises()

    expect(wrapper.find('[data-testid="faces-pagination"]').exists()).toBe(false)
  })

  it('calls the API with the current page and filters from the URL', async () => {
    vi.mocked(publicFacesApi.fetchPublicFaces).mockResolvedValue(mockResponse)

    await mountView('/faces?page=2&search=Adjoua&categorie=acteur')
    await flushPromises()

    expect(publicFacesApi.fetchPublicFaces).toHaveBeenCalledWith(2, 16, {
      categorie: 'acteur',
      niche: undefined,
      ville: undefined,
      search: 'Adjoua',
    })
  })

  it('renders registration links with the expected destinations', async () => {
    vi.mocked(publicFacesApi.fetchPublicFaces).mockResolvedValue(mockResponse)

    const wrapper = await mountView()
    await flushPromises()

    expect(wrapper.find('[data-testid="register-face-cta"]').attributes('href')).toBe('/register/face')
    expect(wrapper.find('[data-testid="register-producer-cta"]').attributes('href')).toBe('/register/producer')
  })
})
