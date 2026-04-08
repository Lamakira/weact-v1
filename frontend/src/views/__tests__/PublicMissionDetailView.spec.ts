import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import PublicMissionDetailView from '../PublicMissionDetailView.vue'
import * as publicMissionsApi from '@/features/public/services/publicMissionsApi'
import type { PublicMission } from '@/features/public/services/publicMissionsApi'

const mockParams: Record<string, string> = { slug: 'casting-publicite-mtn' }
const mockReplace = vi.fn()

vi.mock('vue-router', () => ({
  useRoute: () => ({
    params: mockParams,
  }),
  useRouter: () => ({
    replace: mockReplace,
  }),
}))

vi.mock('@vueuse/core', () => ({
  useTitle: vi.fn(),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    isFace: false,
  }),
}))

vi.mock('@/components/report/ReportButton.vue', () => ({
  default: {
    template: '<button data-testid="report-button">Report</button>',
    props: ['reportableType', 'reportableId'],
  },
}))

vi.mock('@/features/public/services/publicMissionsApi', async () => {
  const actual = await vi.importActual<typeof publicMissionsApi>(
    '@/features/public/services/publicMissionsApi'
  )
  return {
    ...actual,
    fetchPublicMissionDetail: vi.fn(),
  }
})

vi.mock('@/components/ui/skeleton', () => ({
  Skeleton: { template: '<div class="skeleton"></div>' },
}))

const mockMission: PublicMission = {
  id: 42,
  slug: 'casting-publicite-mtn',
  titre: 'Casting publicité MTN',
  description: 'Recherche comédien(ne) pour spot TV',
  date_tournage: '2026-03-15',
  profil_recherche: 'Jeune femme 20-30 ans',
  budget: 150000,
  date_limite_candidature: '2026-12-31',
  nombre_faces_voulu: 3,
  type_mission: 'publicite',
  type_mission_label: 'Publicité',
  type_mission_autre: null,
  genre_voulu: 'femme',
  genre_voulu_label: 'Femme',
  lieu: 'Cotonou, Bénin',
  duree: '2 journées (16h)',
  status: 'published',
  status_label: 'Publiée',
  created_at: '2026-02-01T10:00:00+00:00',
  producer: {
    id: 1,
    display_name: 'Studio Cotonou',
    profile_photo_thumbnail_url: 'https://example.com/thumb.jpg',
    average_rating: 4.5,
    ratings_count: 12,
  },
}

function mountView() {
  return mount(PublicMissionDetailView, {
    global: {
      stubs: {
        RouterLink: {
          template: '<a v-bind="$attrs" :href="to"><slot /></a>',
          props: ['to'],
          inheritAttrs: false,
        },
      },
    },
  })
}

describe('PublicMissionDetailView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockParams.slug = 'casting-publicite-mtn'
  })

  it('renders mission detail on successful load', async () => {
    vi.mocked(publicMissionsApi.fetchPublicMissionDetail).mockResolvedValue({
      success: true,
      mission: mockMission,
    })

    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.find('[data-testid="mission-detail"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Casting publicité MTN')
    expect(publicMissionsApi.fetchPublicMissionDetail).toHaveBeenCalledWith('casting-publicite-mtn')
    expect(mockReplace).not.toHaveBeenCalled()
  })

  it('shows the loading state before the API resolves', async () => {
    let resolvePromise: (value: unknown) => void
    const pendingPromise = new Promise((resolve) => {
      resolvePromise = resolve
    })

    vi.mocked(publicMissionsApi.fetchPublicMissionDetail).mockReturnValue(
      pendingPromise as ReturnType<typeof publicMissionsApi.fetchPublicMissionDetail>
    )

    const wrapper = mountView()

    expect(wrapper.find('[data-testid="loading-state"]').exists()).toBe(true)

    resolvePromise!({ success: true, mission: mockMission })
    await flushPromises()
  })

  it('shows the not-found state for missing missions', async () => {
    vi.mocked(publicMissionsApi.fetchPublicMissionDetail).mockResolvedValue({
      success: false,
      notFound: true,
      error: 'Mission non trouvée',
    })

    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.find('[data-testid="not-found-state"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="back-to-missions-cta"]').attributes('href')).toBe('/missions')
  })

  it('shows the error state and retries the request', async () => {
    vi.mocked(publicMissionsApi.fetchPublicMissionDetail)
      .mockResolvedValueOnce({
        success: false,
        error: 'Une erreur est survenue. Veuillez réessayer.',
      })
      .mockResolvedValueOnce({
        success: true,
        mission: mockMission,
      })

    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.find('[data-testid="error-state"]').exists()).toBe(true)

    await wrapper.find('[data-testid="retry-button"]').trigger('click')
    await flushPromises()

    expect(publicMissionsApi.fetchPublicMissionDetail).toHaveBeenCalledTimes(2)
    expect(wrapper.find('[data-testid="mission-detail"]').exists()).toBe(true)
  })

  it('renders breadcrumb, CTA links, and the report button', async () => {
    vi.mocked(publicMissionsApi.fetchPublicMissionDetail).mockResolvedValue({
      success: true,
      mission: mockMission,
    })

    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.find('nav[aria-label="Breadcrumb"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="back-to-list"]').attributes('href')).toBe('/missions')
    expect(wrapper.find('[data-testid="login-cta"]').attributes('href')).toBe('/login')
    expect(wrapper.find('[data-testid="register-cta"]').attributes('href')).toBe('/register/face')
    expect(wrapper.find('[data-testid="report-button"]').exists()).toBe(true)
  })

  it('renders producer and mission metadata', async () => {
    vi.mocked(publicMissionsApi.fetchPublicMissionDetail).mockResolvedValue({
      success: true,
      mission: mockMission,
    })

    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.find('[data-testid="mission-budget"]').text()).toContain('XOF')
    expect(wrapper.find('[data-testid="mission-shooting-date"]').text()).toContain('2026')
    expect(wrapper.find('[data-testid="mission-description"]').text()).toBe('Recherche comédien(ne) pour spot TV')
    expect(wrapper.find('[data-testid="mission-profil-recherche"]').text()).toBe('Jeune femme 20-30 ans')
    expect(wrapper.find('[data-testid="mission-type-badge"]').text()).toBe('Publicité')
    expect(wrapper.find('[data-testid="mission-genre-highlight"]').text()).toContain('Genre recherché')
    expect(wrapper.find('[data-testid="mission-genre-highlight"]').text()).toContain('Femme')
    expect(wrapper.find('[data-testid="mission-duree"]').text()).toBe('2 journées (max 16h)')
    expect(wrapper.find('[data-testid="producer-name"]').text()).toBe('Studio Cotonou')
    expect(wrapper.find('[data-testid="producer-photo"]').attributes('src')).toBe('https://example.com/thumb.jpg')
    expect(wrapper.find('[data-testid="producer-rating"]').text()).toContain('12 avis')
  })

  it('falls back to the raw mission genre when genre_voulu_label is missing', async () => {
    vi.mocked(publicMissionsApi.fetchPublicMissionDetail).mockResolvedValue({
      success: true,
      mission: {
        ...mockMission,
        genre_voulu: 'femme',
        genre_voulu_label: null,
      },
    })

    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.find('[data-testid="mission-genre-highlight"]').text()).toContain('Femme')
  })

  it('shows the closed badge and hides the login CTA when the deadline has passed', async () => {
    vi.mocked(publicMissionsApi.fetchPublicMissionDetail).mockResolvedValue({
      success: true,
      mission: {
        ...mockMission,
        date_limite_candidature: '2020-01-01',
      },
    })

    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.find('[data-testid="deadline-passed-badge"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="login-cta"]').exists()).toBe(false)
  })

  it('does not call the API when the route slug is empty', async () => {
    mockParams.slug = ''

    mountView()
    await flushPromises()

    expect(publicMissionsApi.fetchPublicMissionDetail).not.toHaveBeenCalled()
  })
})
