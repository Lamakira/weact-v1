import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import PublicFaceProfileView from '../PublicFaceProfileView.vue'
import * as publicFacesApi from '@/features/public/services/publicFacesApi'
import type { PublicFaceProfile } from '@/features/public/services/publicFacesApi'

// Mock route params - mutable for different test scenarios
const mockParams: Record<string, string> = { username: 'adjoua' }

vi.mock('vue-router', () => ({
  useRoute: () => ({
    params: mockParams,
  }),
}))

vi.mock('@vueuse/core', () => ({
  useTitle: vi.fn(),
}))

vi.mock('@/features/public/services/publicFacesApi', async () => {
  const actual = await vi.importActual<typeof publicFacesApi>(
    '@/features/public/services/publicFacesApi'
  )
  return {
    ...actual,
    fetchPublicFaceProfile: vi.fn(),
  }
})

vi.mock('@/components/ui/skeleton', () => ({
  Skeleton: { template: '<div class="skeleton"></div>' },
}))

const mockProfile: PublicFaceProfile = {
  id: 1,
  username: 'adjoua',
  prenom: 'Adjoua',
  nom: 'Dossou',
  ville: 'Cotonou',
  categorie: 'acteur',
  categorie_label: 'Acteur',
  is_available: true,
  profile_photo_thumbnail_url: null,
  average_rating: 4.5,
  niche: 'publicite',
  niche_label: 'Publicité',
  profile_photo_url: 'https://example.com/photo.jpg',
  ratings_count: 12,
  has_album_photos: true,
  album_photos_count: 5,
  has_presentation_video: true,
  has_acting_video: false,
}

function mountView() {
  return mount(PublicFaceProfileView, {
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

describe('PublicFaceProfileView (Integration)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockParams.username = 'adjoua'
  })

  afterEach(() => {
    // Clean up dynamically added meta tags
    document
      .querySelectorAll('meta[property^="og:"], meta[name="description"]')
      .forEach((el) => el.remove())
  })

  it('loads and renders face profile on success', async () => {
    vi.mocked(publicFacesApi.fetchPublicFaceProfile).mockResolvedValue({
      success: true,
      profile: mockProfile,
    })

    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.find('[data-testid="face-profile"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Adjoua')
    expect(publicFacesApi.fetchPublicFaceProfile).toHaveBeenCalledWith('adjoua')
  })

  it('shows not found state on 404 response', async () => {
    vi.mocked(publicFacesApi.fetchPublicFaceProfile).mockResolvedValue({
      success: false,
      notFound: true,
      error: 'Face non trouvée',
    })

    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.find('[data-testid="not-found-state"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Face non trouvée')
    expect(wrapper.find('[data-testid="face-profile"]').exists()).toBe(false)
  })

  it('shows error state with retry button on API error', async () => {
    vi.mocked(publicFacesApi.fetchPublicFaceProfile).mockResolvedValue({
      success: false,
      error: 'Une erreur est survenue. Veuillez réessayer.',
    })

    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.find('[data-testid="error-state"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="retry-button"]').exists()).toBe(true)
  })

  it('CTA links to producer registration page', async () => {
    vi.mocked(publicFacesApi.fetchPublicFaceProfile).mockResolvedValue({
      success: true,
      profile: mockProfile,
    })

    const wrapper = mountView()
    await flushPromises()

    const cta = wrapper.find('[data-testid="producer-cta"]')
    expect(cta.exists()).toBe(true)
    expect(cta.attributes('href')).toBe('/register/producer')
  })

  it('has breadcrumb navigation with proper ARIA', async () => {
    vi.mocked(publicFacesApi.fetchPublicFaceProfile).mockResolvedValue({
      success: true,
      profile: mockProfile,
    })

    const wrapper = mountView()
    await flushPromises()

    const nav = wrapper.find('nav[aria-label="Breadcrumb"]')
    expect(nav.exists()).toBe(true)

    const backLink = wrapper.find('[data-testid="back-to-list"]')
    expect(backLink.exists()).toBe(true)
    expect(backLink.text()).toContain('Retour aux talents')
    expect(backLink.attributes('href')).toBe('/faces')
  })

  it('has accessible rating section with aria-label', async () => {
    vi.mocked(publicFacesApi.fetchPublicFaceProfile).mockResolvedValue({
      success: true,
      profile: mockProfile,
    })

    const wrapper = mountView()
    await flushPromises()

    const ratingSection = wrapper.find('[data-testid="rating-section"]')
    expect(ratingSection.exists()).toBe(true)
    expect(ratingSection.attributes('aria-label')).toContain('4.5')
    expect(ratingSection.attributes('aria-label')).toContain('étoiles')
  })

  it('has accessible profile photo with alt text', async () => {
    vi.mocked(publicFacesApi.fetchPublicFaceProfile).mockResolvedValue({
      success: true,
      profile: mockProfile,
    })

    const wrapper = mountView()
    await flushPromises()

    const photo = wrapper.find('[data-testid="profile-photo"]')
    expect(photo.exists()).toBe(true)
    expect(photo.attributes('alt')).toContain('Adjoua')
  })

  it('sets SEO meta tags when face is loaded', async () => {
    vi.mocked(publicFacesApi.fetchPublicFaceProfile).mockResolvedValue({
      success: true,
      profile: mockProfile,
    })

    mountView()
    await flushPromises()

    const descMeta = document.querySelector('meta[name="description"]')
    expect(descMeta).not.toBeNull()
    expect(descMeta?.getAttribute('content')).toContain('Adjoua')
    expect(descMeta?.getAttribute('content')).toContain('Acteur')
    expect(descMeta?.getAttribute('content')).toContain('Cotonou')

    const ogTitle = document.querySelector('meta[property="og:title"]')
    expect(ogTitle).not.toBeNull()
    expect(ogTitle?.getAttribute('content')).toContain('Adjoua')
    expect(ogTitle?.getAttribute('content')).toContain('Acteur')

    const ogDesc = document.querySelector('meta[property="og:description"]')
    expect(ogDesc).not.toBeNull()

    const ogImage = document.querySelector('meta[property="og:image"]')
    expect(ogImage).not.toBeNull()
    expect(ogImage?.getAttribute('content')).toBe('https://example.com/photo.jpg')
  })

  it('renders locked content teasers in success state', async () => {
    vi.mocked(publicFacesApi.fetchPublicFaceProfile).mockResolvedValue({
      success: true,
      profile: mockProfile,
    })

    const wrapper = mountView()
    await flushPromises()

    const teasers = wrapper.findAll('[data-testid="locked-content-teaser"]')
    expect(teasers.length).toBe(2)
    expect(wrapper.text()).toContain('Bio & Expériences professionnelles')
    expect(wrapper.text()).toContain('Tarifs & Caractéristiques')
  })
})
