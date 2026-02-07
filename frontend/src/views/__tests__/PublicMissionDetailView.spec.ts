import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import PublicMissionDetailView from '../PublicMissionDetailView.vue'
import * as publicMissionsApi from '@/features/public/services/publicMissionsApi'
import type { PublicMission } from '@/features/public/services/publicMissionsApi'

// Mock route params - mutable for different test scenarios
const mockParams: Record<string, string> = { id: '42' }

vi.mock('vue-router', () => ({
  useRoute: () => ({
    params: mockParams,
  }),
}))

vi.mock('@vueuse/core', () => ({
  useTitle: vi.fn(),
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
  titre: 'Casting publicité MTN',
  description: 'Recherche comédien(ne) pour spot TV',
  date_tournage: '2026-03-15',
  profil_recherche: 'Jeune femme 20-30 ans',
  budget: 150000,
  date_limite_candidature: '2026-12-31',
  nombre_faces_voulu: 3,
  type_mission: 'publicite',
  type_mission_label: 'Publicité',
  genre_voulu: 'femme',
  genre_voulu_label: 'Femme',
  lieu: 'Cotonou, Bénin',
  duree: '2 jours',
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

describe('PublicMissionDetailView (Integration)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockParams.id = '42'
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
    expect(publicMissionsApi.fetchPublicMissionDetail).toHaveBeenCalledWith(42)
  })

  it('shows loading state initially', async () => {
    let resolvePromise: (value: any) => void
    const pendingPromise = new Promise((resolve) => {
      resolvePromise = resolve
    })

    vi.mocked(publicMissionsApi.fetchPublicMissionDetail).mockReturnValue(
      pendingPromise as any
    )

    const wrapper = mountView()

    expect(wrapper.find('[data-testid="loading-state"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="mission-detail"]').exists()).toBe(false)

    resolvePromise!({ success: true, mission: mockMission })
    await flushPromises()
  })

  it('shows not-found state for 404', async () => {
    vi.mocked(publicMissionsApi.fetchPublicMissionDetail).mockResolvedValue({
      success: false,
      notFound: true,
      error: 'Mission non trouvée',
    })

    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.find('[data-testid="not-found-state"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Mission non trouvée')
    expect(wrapper.find('[data-testid="mission-detail"]').exists()).toBe(false)
  })

  it('shows error state with retry button', async () => {
    vi.mocked(publicMissionsApi.fetchPublicMissionDetail).mockResolvedValue({
      success: false,
      error: 'Une erreur est survenue. Veuillez réessayer.',
    })

    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.find('[data-testid="error-state"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="retry-button"]').exists()).toBe(true)
  })

  it('retry button triggers a new fetch', async () => {
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

    expect(publicMissionsApi.fetchPublicMissionDetail).toHaveBeenCalledTimes(1)

    await wrapper.find('[data-testid="retry-button"]').trigger('click')
    await flushPromises()

    expect(publicMissionsApi.fetchPublicMissionDetail).toHaveBeenCalledTimes(2)
    expect(wrapper.find('[data-testid="mission-detail"]').exists()).toBe(true)
  })

  it('back link navigates to missions list', async () => {
    vi.mocked(publicMissionsApi.fetchPublicMissionDetail).mockResolvedValue({
      success: true,
      mission: mockMission,
    })

    const wrapper = mountView()
    await flushPromises()

    const backLink = wrapper.find('[data-testid="back-to-list"]')
    expect(backLink.exists()).toBe(true)
    expect(backLink.attributes('href')).toBe('/missions')
    expect(backLink.text()).toContain('Retour aux missions')
  })

  it('CTA buttons link to login and register', async () => {
    vi.mocked(publicMissionsApi.fetchPublicMissionDetail).mockResolvedValue({
      success: true,
      mission: mockMission,
    })

    const wrapper = mountView()
    await flushPromises()

    const loginCta = wrapper.find('[data-testid="login-cta"]')
    expect(loginCta.exists()).toBe(true)
    expect(loginCta.attributes('href')).toBe('/login')
    expect(loginCta.text()).toContain('Se connecter pour postuler')

    const registerCta = wrapper.find('[data-testid="register-cta"]')
    expect(registerCta.exists()).toBe(true)
    expect(registerCta.attributes('href')).toBe('/register/face')
  })

  it('displays formatted dates and budget', async () => {
    vi.mocked(publicMissionsApi.fetchPublicMissionDetail).mockResolvedValue({
      success: true,
      mission: mockMission,
    })

    const wrapper = mountView()
    await flushPromises()

    // Budget should be formatted in XOF
    const budget = wrapper.find('[data-testid="mission-budget"]')
    expect(budget.exists()).toBe(true)
    expect(budget.text()).toContain('XOF')

    // Shooting date should be formatted in French
    const shootingDate = wrapper.find('[data-testid="mission-shooting-date"]')
    expect(shootingDate.exists()).toBe(true)
    expect(shootingDate.text()).toContain('2026')
  })

  it('displays producer section with name, photo, and rating', async () => {
    vi.mocked(publicMissionsApi.fetchPublicMissionDetail).mockResolvedValue({
      success: true,
      mission: mockMission,
    })

    const wrapper = mountView()
    await flushPromises()

    const producerSection = wrapper.find('[data-testid="producer-section"]')
    expect(producerSection.exists()).toBe(true)

    const producerName = wrapper.find('[data-testid="producer-name"]')
    expect(producerName.text()).toBe('Studio Cotonou')

    const producerPhoto = wrapper.find('[data-testid="producer-photo"]')
    expect(producerPhoto.exists()).toBe(true)
    expect(producerPhoto.attributes('src')).toBe('https://example.com/thumb.jpg')

    const producerRating = wrapper.find('[data-testid="producer-rating"]')
    expect(producerRating.exists()).toBe(true)
    expect(producerRating.text()).toContain('4.5')
    expect(producerRating.text()).toContain('12 avis')
  })

  it('displays mission description and profil recherché', async () => {
    vi.mocked(publicMissionsApi.fetchPublicMissionDetail).mockResolvedValue({
      success: true,
      mission: mockMission,
    })

    const wrapper = mountView()
    await flushPromises()

    const description = wrapper.find('[data-testid="mission-description"]')
    expect(description.text()).toBe('Recherche comédien(ne) pour spot TV')

    const profilRecherche = wrapper.find('[data-testid="mission-profil-recherche"]')
    expect(profilRecherche.text()).toBe('Jeune femme 20-30 ans')
  })

  it('displays type and genre badges', async () => {
    vi.mocked(publicMissionsApi.fetchPublicMissionDetail).mockResolvedValue({
      success: true,
      mission: mockMission,
    })

    const wrapper = mountView()
    await flushPromises()

    const typeBadge = wrapper.find('[data-testid="mission-type-badge"]')
    expect(typeBadge.text()).toBe('Publicité')

    const genreBadge = wrapper.find('[data-testid="mission-genre-badge"]')
    expect(genreBadge.text()).toBe('Femme')
  })

  it('shows "Candidatures clôturées" when deadline has passed', async () => {
    const expiredMission = {
      ...mockMission,
      date_limite_candidature: '2020-01-01',
    }

    vi.mocked(publicMissionsApi.fetchPublicMissionDetail).mockResolvedValue({
      success: true,
      mission: expiredMission,
    })

    const wrapper = mountView()
    await flushPromises()

    const deadlineBadge = wrapper.find('[data-testid="deadline-passed-badge"]')
    expect(deadlineBadge.exists()).toBe(true)
    expect(deadlineBadge.text()).toContain('Candidatures clôturées')

    // Login CTA should NOT be visible
    expect(wrapper.find('[data-testid="login-cta"]').exists()).toBe(false)
  })

  it('not-found state has link back to missions list', async () => {
    vi.mocked(publicMissionsApi.fetchPublicMissionDetail).mockResolvedValue({
      success: false,
      notFound: true,
      error: 'Mission non trouvée',
    })

    const wrapper = mountView()
    await flushPromises()

    const backCta = wrapper.find('[data-testid="back-to-missions-cta"]')
    expect(backCta.exists()).toBe(true)
    expect(backCta.attributes('href')).toBe('/missions')
  })

  it('does not call API when route param is non-numeric', async () => {
    mockParams.id = 'abc'

    vi.mocked(publicMissionsApi.fetchPublicMissionDetail).mockResolvedValue({
      success: true,
      mission: mockMission,
    })

    mountView()
    await flushPromises()

    expect(publicMissionsApi.fetchPublicMissionDetail).not.toHaveBeenCalled()
  })

  it('has breadcrumb navigation with proper ARIA', async () => {
    vi.mocked(publicMissionsApi.fetchPublicMissionDetail).mockResolvedValue({
      success: true,
      mission: mockMission,
    })

    const wrapper = mountView()
    await flushPromises()

    const nav = wrapper.find('nav[aria-label="Breadcrumb"]')
    expect(nav.exists()).toBe(true)
  })
})
