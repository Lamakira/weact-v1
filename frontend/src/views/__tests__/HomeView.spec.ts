import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, VueWrapper, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import { createRouter, createMemoryHistory } from 'vue-router'
import HomeView from '../HomeView.vue'
import type { LandingMission, LandingFace } from '@/features/landing/types'

// --- Mock composables ---
const mockMissions = ref<LandingMission[]>([])
const mockIsLoadingMissions = ref(false)
const mockMissionsError = ref<string | null>(null)
const mockFetchMissions = vi.fn()
const mockRetryMissions = vi.fn()

const mockFaces = ref<LandingFace[]>([])
const mockIsLoadingFaces = ref(false)
const mockFacesCount = ref(0)
const mockFetchFaces = vi.fn()

vi.mock('@/features/landing/composables/useLandingMissions', () => ({
  useLandingMissions: () => ({
    missions: mockMissions,
    isLoading: mockIsLoadingMissions,
    error: mockMissionsError,
    totalCount: ref(0),
    fetchMissions: mockFetchMissions,
    retry: mockRetryMissions,
    clearError: vi.fn(),
  }),
}))

vi.mock('@/features/landing/composables/useLandingFaces', () => ({
  useLandingFaces: () => ({
    faces: mockFaces,
    isLoading: mockIsLoadingFaces,
    error: ref<string | null>(null),
    totalCount: mockFacesCount,
    fetchFaces: mockFetchFaces,
    retry: vi.fn(),
    clearError: vi.fn(),
  }),
}))

// --- Mock data factories ---
function makeMission(overrides: Partial<LandingMission> = {}): LandingMission {
  return {
    id: 1,
    slug: 'mission-test',
    titre: 'Mission Test',
    description: 'Description test',
    date_tournage: '2025-06-15',
    profil_recherche: 'Acteur',
    budget: 50000,
    date_limite_candidature: '2025-06-01',
    nombre_faces_voulu: 3,
    type_mission: 'film',
    type_mission_label: 'Film',
    genre_voulu: 'homme',
    genre_voulu_label: 'Homme',
    lieu: 'Cotonou',
    duree: '2 jours',
    status: 'published',
    status_label: 'Disponible',
    created_at: '2025-05-01T00:00:00.000000Z',
    producer: null,
    ...overrides,
  }
}

function makeFace(overrides: Partial<LandingFace> = {}): LandingFace {
  return {
    id: 1,
    username: 'face1',
    prenom: 'Alice',
    nom: 'Dupont',
    ville: 'Cotonou',
    categories: [{ value: 'acteur', label: 'Acteur' }],
    is_available: true,
    profile_photo_url: 'https://example.com/photo.jpg',
    profile_photo_thumbnail_url: 'https://example.com/thumb.jpg',
    average_rating: 4.0,
    ...overrides,
  }
}

const sampleMissions: LandingMission[] = [
  makeMission({ id: 1, slug: 'film-casting', titre: 'Film Casting', type_mission_label: 'Film', budget: 50000, lieu: 'Cotonou' }),
  makeMission({ id: 2, slug: 'pub-video', titre: 'Pub Vidéo', type_mission_label: 'Publicité', budget: 75000, lieu: 'Porto-Novo' }),
  makeMission({ id: 3, slug: 'clip-musical', titre: 'Clip Musical', type_mission_label: 'Clip', budget: 30000, lieu: 'Abomey' }),
]

const sampleFaces: LandingFace[] = [
  makeFace({ id: 1, prenom: 'Alice' }),
  makeFace({ id: 2, prenom: 'Bob' }),
  makeFace({ id: 3, prenom: 'Charlie' }),
]

// --- Router ---
const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/', name: 'home', component: HomeView },
    { path: '/register/face', name: 'register-face', component: { template: '<div>Register</div>' } },
    { path: '/register/producer', name: 'register-producer', component: { template: '<div>Register Producer</div>' } },
    { path: '/missions', name: 'missions', component: { template: '<div>Missions</div>' } },
    { path: '/missions/:slug', name: 'public-mission-detail', component: { template: '<div>Mission Detail</div>' } },
    { path: '/faces', name: 'faces', component: { template: '<div>Faces</div>' } },
  ],
})

describe('HomeView', () => {
  let wrapper: VueWrapper

  beforeEach(async () => {
    vi.useFakeTimers()

    // Default: data loaded
    mockMissions.value = sampleMissions
    mockIsLoadingMissions.value = false
    mockMissionsError.value = null
    mockFaces.value = sampleFaces
    mockIsLoadingFaces.value = false
    mockFacesCount.value = 50

    router.push('/')
    await router.isReady()
    wrapper = mount(HomeView, {
      global: {
        plugins: [router],
      },
    })
    await flushPromises()
  })

  afterEach(() => {
    vi.useRealTimers()
    wrapper.unmount()
  })

  describe('Hero Section', () => {
    it('renders the main headline', () => {
      const headline = wrapper.find('h1')
      expect(headline.exists()).toBe(true)
      expect(headline.text()).toContain('Monétisez votre')
      expect(headline.text()).toContain('image dans')
    })

    it('renders the animated word element', () => {
      const animatedWord = wrapper.find('[data-testid="hero-animated-word"]')
      expect(animatedWord.exists()).toBe(true)
    })

    it('cycles through words every 2.5 seconds', async () => {
      expect(wrapper.find('[data-testid="hero-animated-word"]').text()).toBe('film')

      vi.advanceTimersByTime(2500)
      await wrapper.vm.$nextTick()
      expect(wrapper.find('[data-testid="hero-animated-word"]').text()).toBe('série télévisée')

      vi.advanceTimersByTime(2500)
      await wrapper.vm.$nextTick()
      expect(wrapper.find('[data-testid="hero-animated-word"]').text()).toBe('vidéo publicitaire')

      vi.advanceTimersByTime(2500)
      await wrapper.vm.$nextTick()
      expect(wrapper.find('[data-testid="hero-animated-word"]').text()).toBe('clip musical')

      vi.advanceTimersByTime(2500)
      await wrapper.vm.$nextTick()
      expect(wrapper.find('[data-testid="hero-animated-word"]').text()).toBe('film')
    })

    it('renders the hero CTA button linking to face registration', () => {
      const cta = wrapper.find('[data-testid="hero-cta"]')
      expect(cta.exists()).toBe(true)
      expect(cta.text()).toContain('Créer mon profil')
      expect(cta.attributes('href')).toBe('/register/face')
    })

    it('renders hero images on desktop', () => {
      const heroImages = wrapper.find('[data-testid="hero-images"]')
      expect(heroImages.exists()).toBe(true)
    })
  })

  describe('Comment ça marche Section', () => {
    it('renders the section title', () => {
      const title = wrapper.find('[data-testid="how-it-works-title"]')
      expect(title.exists()).toBe(true)
      expect(title.text()).toContain('Comment ça marche')
    })

    it('renders all 4 step cards', () => {
      expect(wrapper.find('[data-testid="step-1-card"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="step-2-card"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="step-3-card"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="step-4-card"]').exists()).toBe(true)
    })

    it('displays correct step titles', () => {
      expect(wrapper.find('[data-testid="step-1-card"]').text()).toContain('CRÉER VOTRE PROFIL')
      expect(wrapper.find('[data-testid="step-2-card"]').text()).toContain('RECEVOIR OU CANDIDATER')
      expect(wrapper.find('[data-testid="step-3-card"]').text()).toContain('TRAVAILLER')
      expect(wrapper.find('[data-testid="step-4-card"]').text()).toContain('RECEVOIR VOS GAINS')
    })
  })

  describe('Faces/Talents Showcase Section', () => {
    it('renders the faces carousel when faces are loaded', () => {
      const carousel = wrapper.find('[data-testid="faces-carousel"]')
      expect(carousel.exists()).toBe(true)
    })

    it('renders the deviens face CTA button', () => {
      const cta = wrapper.find('[data-testid="deviens-face-cta"]')
      expect(cta.exists()).toBe(true)
      expect(cta.text()).toContain('Explorer nos Faces')
    })

    it('shows loading skeleton when faces are loading', async () => {
      mockIsLoadingFaces.value = true
      mockFaces.value = []
      await wrapper.vm.$nextTick()

      expect(wrapper.find('[data-testid="faces-loading"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="faces-carousel"]').exists()).toBe(false)
    })

    it('hides section when no faces and not loading', async () => {
      mockIsLoadingFaces.value = false
      mockFaces.value = []
      await wrapper.vm.$nextTick()

      expect(wrapper.find('[data-testid="faces-loading"]').exists()).toBe(false)
      expect(wrapper.find('[data-testid="faces-carousel"]').exists()).toBe(false)
    })

    it('calls fetchFaces on mount', () => {
      expect(mockFetchFaces).toHaveBeenCalled()
    })
  })

  describe('Missions Section', () => {
    it('renders the missions section title', () => {
      const title = wrapper.find('[data-testid="missions-section-title"]')
      expect(title.exists()).toBe(true)
      expect(title.text()).toContain('Missions en cours')
    })

    it('renders the see all missions link', () => {
      const link = wrapper.find('[data-testid="see-all-missions"]')
      expect(link.exists()).toBe(true)
      expect(link.text()).toContain('Voir toutes les missions')
      expect(link.attributes('href')).toBe('/missions')
    })

    it('missions section has id for anchor link', () => {
      const section = wrapper.find('#missions')
      expect(section.exists()).toBe(true)
    })

    it('renders mission cards matching data count', () => {
      const cards = wrapper.findAll('[data-testid^="mission-card-"]:not([data-testid*="mobile"])')
      expect(cards.length).toBe(sampleMissions.length)
    })

    it('displays mission type badges from API data', () => {
      const card1 = wrapper.find('[data-testid="mission-card-1"]')
      expect(card1.exists()).toBe(true)
      expect(card1.text()).toContain('Film')
    })

    it('displays formatted budget', () => {
      const card1 = wrapper.find('[data-testid="mission-card-1"]')
      // 50000 formatted as "50 000 FCFA"
      expect(card1.text()).toContain('FCFA')
    })

    it('displays mission location', () => {
      const card1 = wrapper.find('[data-testid="mission-card-1"]')
      expect(card1.text()).toContain('Cotonou')
    })

    it('mission cards are links to mission detail', () => {
      const card1 = wrapper.find('[data-testid="mission-card-1"]')
      expect(card1.attributes('href')).toBe('/missions/film-casting')
    })

    it('shows loading skeleton when missions are loading', async () => {
      mockIsLoadingMissions.value = true
      mockMissions.value = []
      await wrapper.vm.$nextTick()

      expect(wrapper.find('[data-testid="missions-loading"]').exists()).toBe(true)
    })

    it('shows error state with retry button', async () => {
      mockIsLoadingMissions.value = false
      mockMissionsError.value = 'Impossible de charger les missions. Veuillez réessayer.'
      mockMissions.value = []
      await wrapper.vm.$nextTick()

      expect(wrapper.find('[data-testid="missions-error"]').exists()).toBe(true)
      expect(wrapper.text()).toContain('Impossible de charger les missions')

      const retryBtn = wrapper.find('[data-testid="missions-retry"]')
      expect(retryBtn.exists()).toBe(true)
      await retryBtn.trigger('click')
      expect(mockRetryMissions).toHaveBeenCalled()
    })

    it('hides missions section when no data and no loading/error', async () => {
      mockIsLoadingMissions.value = false
      mockMissionsError.value = null
      mockMissions.value = []
      await wrapper.vm.$nextTick()

      expect(wrapper.find('[data-testid="missions-section"]').exists()).toBe(false)
    })

    it('calls fetchMissions on mount', () => {
      expect(mockFetchMissions).toHaveBeenCalled()
    })
  })

  describe('Pourquoi WEACT Section', () => {
    it('renders the section title', () => {
      const title = wrapper.find('[data-testid="why-weact-title"]')
      expect(title.exists()).toBe(true)
      expect(title.text()).toContain('Pourquoi WEACT')
    })

    it('renders all 3 feature cards', () => {
      expect(wrapper.find('[data-testid="feature-card-1"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="feature-card-2"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="feature-card-3"]').exists()).toBe(true)
    })

    it('displays correct feature titles', () => {
      expect(wrapper.find('[data-testid="feature-card-1"]').text()).toContain('Plateforme sécurisée')
      expect(wrapper.find('[data-testid="feature-card-2"]').text()).toContain('Paiement sécurisé')
      expect(wrapper.find('[data-testid="feature-card-3"]').text()).toContain('Transactions protégées')
    })
  })

  describe('Final CTA Section', () => {
    it('renders the final CTA title', () => {
      const title = wrapper.find('[data-testid="final-cta-title"]')
      expect(title.exists()).toBe(true)
      expect(title.text()).toContain('Prêt à commencer')
    })

    it('renders the final CTA button linking to face registration', () => {
      const button = wrapper.find('[data-testid="final-cta-button"]')
      expect(button.exists()).toBe(true)
      expect(button.text()).toContain("S'inscrire comme Face")
      expect(button.attributes('href')).toBe('/register/face')
    })
  })

  describe('Accessibility', () => {
    it('has proper heading hierarchy starting with h1', () => {
      const h1 = wrapper.find('h1')
      expect(h1.exists()).toBe(true)
    })

    it('all CTAs have data-testid attributes', () => {
      expect(wrapper.find('[data-testid="hero-cta"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="deviens-face-cta"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="final-cta-button"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="see-all-missions"]').exists()).toBe(true)
    })

    it('images have alt attributes', () => {
      const images = wrapper.findAll('img')
      images.forEach((img) => {
        expect(img.attributes('alt')).toBeDefined()
        expect(img.attributes('alt')).not.toBe('')
      })
    })
  })

  describe('Responsive Design', () => {
    it('silhouette has hidden lg:block classes for mobile hiding', () => {
      const silhouetteContainer = wrapper.find('.hidden.lg\\:block')
      expect(silhouetteContainer.exists()).toBe(true)
    })
  })

  describe('Hero images section', () => {
    it('hero images container exists', () => {
      const heroImages = wrapper.find('[data-testid="hero-images"]')
      expect(heroImages.exists()).toBe(true)
    })

    it('hero images contain img elements', () => {
      const heroImages = wrapper.find('[data-testid="hero-images"]')
      const imgs = heroImages.findAll('img')
      expect(imgs.length).toBeGreaterThan(0)
    })
  })

  describe('Perspective Toggle (Story 11-2)', () => {
    it('renders the perspective toggle', () => {
      const toggle = wrapper.find('[data-testid="perspective-toggle"]')
      expect(toggle.exists()).toBe(true)
    })

    it('defaults to Face perspective', () => {
      const faceTab = wrapper.find('[data-testid="toggle-face"]')
      expect(faceTab.exists()).toBe(true)
      expect(faceTab.attributes('aria-selected')).toBe('true')
      expect(faceTab.classes()).toContain('bg-black')
      expect(wrapper.text()).toContain('Monétisez votre')
    })

    it('toggle has accessibility attributes', () => {
      const toggle = wrapper.find('[data-testid="perspective-toggle"]')
      const tablist = toggle.find('[role="tablist"]')
      expect(tablist.exists()).toBe(true)
      expect(tablist.attributes('aria-label')).toBe('Choisir votre profil')
    })

    it('switches to Producer perspective when clicked', async () => {
      const producerTab = wrapper.find('[data-testid="toggle-producer"]')
      await producerTab.trigger('click')
      await wrapper.vm.$nextTick()

      expect(producerTab.attributes('aria-selected')).toBe('true')
      expect(wrapper.text()).toContain('Trouvez votre prochain')
    })

    it('updates How It Works section when switching perspectives', async () => {
      expect(wrapper.find('[data-testid="step-1-card"]').text()).toContain('CRÉER VOTRE PROFIL')

      const producerTab = wrapper.find('[data-testid="toggle-producer"]')
      await producerTab.trigger('click')
      await wrapper.vm.$nextTick()

      expect(wrapper.find('[data-testid="step-1-card"]').text()).toContain('PUBLIEZ VOTRE MISSION')
    })

    it('updates Pourquoi WEACT section when switching perspectives', async () => {
      expect(wrapper.find('[data-testid="feature-card-1"]').text()).toContain('Plateforme sécurisée')

      const producerTab = wrapper.find('[data-testid="toggle-producer"]')
      await producerTab.trigger('click')
      await wrapper.vm.$nextTick()

      expect(wrapper.find('[data-testid="feature-card-1"]').text()).toContain('vivier de talents')
    })

    it('updates final CTA when switching perspectives', async () => {
      expect(wrapper.find('[data-testid="final-cta-button"]').text()).toContain("S'inscrire comme Face")

      const producerTab = wrapper.find('[data-testid="toggle-producer"]')
      await producerTab.trigger('click')
      await wrapper.vm.$nextTick()

      expect(wrapper.find('[data-testid="final-cta-button"]').text()).toContain("S'inscrire comme Producteur")
    })

    it('switches showcase component when perspective changes', async () => {
      expect(wrapper.find('[data-testid="faces-carousel"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="talents-showcase"]').exists()).toBe(false)

      const producerTab = wrapper.find('[data-testid="toggle-producer"]')
      await producerTab.trigger('click')
      await wrapper.vm.$nextTick()

      expect(wrapper.find('[data-testid="talents-showcase"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="faces-carousel"]').exists()).toBe(false)
    })

    it('can switch back to Face perspective', async () => {
      const producerTab = wrapper.find('[data-testid="toggle-producer"]')
      await producerTab.trigger('click')
      await wrapper.vm.$nextTick()
      expect(wrapper.text()).toContain('Trouvez votre prochain')

      const faceTab = wrapper.find('[data-testid="toggle-face"]')
      await faceTab.trigger('click')
      await wrapper.vm.$nextTick()
      expect(wrapper.text()).toContain('Monétisez votre')
    })
  })
})
