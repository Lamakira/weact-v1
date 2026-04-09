import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { ref } from 'vue'
import FaceMissionDetailPage from '../FaceMissionDetailPage.vue'
import RatingDisplay from '@/components/RatingDisplay.vue'
import type { Mission, MissionProducer } from '@/features/mission/types'
import { useAuthStore } from '@/stores/auth'

// Mock useToast
vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    success: vi.fn(),
    error: vi.fn(),
    warning: vi.fn(),
    info: vi.fn(),
  }),
}))

// Mock authApi
vi.mock('@/features/auth/services/authApi', () => ({
  authApi: {
    resendVerificationEmail: vi.fn(),
  },
}))

// Mock vue-router
const mockRoute = {
  params: { id: '1' },
}
const mockRouter = {
  push: vi.fn(),
  back: vi.fn(),
}
vi.mock('vue-router', () => ({
  useRoute: () => mockRoute,
  useRouter: () => mockRouter,
}))

// Mock the composable
const mockMission = ref<Mission | null>(null)
const mockCandidature = ref(null)
const mockIsLoading = ref(false)
const mockError = ref<string | null>(null)
const mockNotFound = ref(false)
const mockFetchMission = vi.fn()
const mockSetCandidature = vi.fn()

vi.mock('@/features/mission/composables', () => ({
  useMissionDetail: () => ({
    mission: mockMission,
    candidature: mockCandidature,
    isLoading: mockIsLoading,
    error: mockError,
    notFound: mockNotFound,
    fetchMission: mockFetchMission,
    setCandidature: mockSetCandidature,
  }),
}))

// Factory for creating test mission data
function createMission(overrides: Partial<Mission> = {}): Mission {
  return {
    id: 1,
    titre: 'Test Mission',
    description: 'Test mission description',
    date_tournage: '2026-02-15',
    profil_recherche: 'Femme 25-35 ans',
    budget: 150000,
    date_limite_candidature: '2026-02-01',
    nombre_faces_voulu: 3,
    type_mission: 'publicite',
    type_mission_label: 'Publicité',
    genre_voulu: 'femme',
    genre_voulu_label: 'Femme',
    lieu: 'Cotonou',
    duree: '2 jours',
    status: 'published',
    status_label: 'Publiée',
    is_accepting_candidatures: true,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    producer: createProducer(),
    ...overrides,
  }
}

function createProducer(overrides: Partial<MissionProducer> = {}): MissionProducer {
  return {
    id: 1,
    type: 'agency',
    agency_name: 'Test Agency',
    first_name: null,
    last_name: null,
    display_name: 'Test Agency',
    bio: null,
    profile_photo_url: null,
    thumbnail_url: null,
    agency_logo_url: null,
    agency_logo_thumbnail_url: null,
    average_rating: null,
    ratings_count: 0,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

describe('FaceMissionDetailPage', () => {
  beforeEach(() => {
    mockMission.value = null
    mockCandidature.value = null
    mockIsLoading.value = false
    mockError.value = null
    mockNotFound.value = false
    mockFetchMission.mockClear()
    mockSetCandidature.mockClear()
    mockRouter.push.mockClear()
    mockRouter.back.mockClear()
  })

  describe('producer rating display', () => {
    it('renders RatingDisplay component in producer card', async () => {
      mockMission.value = createMission({
        producer: createProducer({
          average_rating: 4.5,
          ratings_count: 10,
        }),
      })

      const wrapper = mount(FaceMissionDetailPage, {
        global: {
          plugins: [createTestingPinia()],
          stubs: {
            ApplyToMissionModal: true,
            RatingDisplay: true,
            RouterLink: {
              template: '<a><slot /></a>',
              props: ['to'],
            },
          },
        },
      })

      await flushPromises()

      expect(wrapper.findComponent(RatingDisplay).exists()).toBe(true)
    })

    it('passes correct average_rating to RatingDisplay', async () => {
      mockMission.value = createMission({
        producer: createProducer({
          average_rating: 4.2,
          ratings_count: 15,
        }),
      })

      const wrapper = mount(FaceMissionDetailPage, {
        global: {
          plugins: [createTestingPinia()],
          stubs: {
            ApplyToMissionModal: true,
            RatingDisplay: true,
            RouterLink: {
              template: '<a><slot /></a>',
              props: ['to'],
            },
          },
        },
      })

      await flushPromises()

      const ratingDisplay = wrapper.findComponent(RatingDisplay)
      expect(ratingDisplay.props('averageRating')).toBe(4.2)
    })

    it('passes correct ratings_count to RatingDisplay', async () => {
      mockMission.value = createMission({
        producer: createProducer({
          average_rating: 4.2,
          ratings_count: 15,
        }),
      })

      const wrapper = mount(FaceMissionDetailPage, {
        global: {
          plugins: [createTestingPinia()],
          stubs: {
            ApplyToMissionModal: true,
            RatingDisplay: true,
            RouterLink: {
              template: '<a><slot /></a>',
              props: ['to'],
            },
          },
        },
      })

      await flushPromises()

      const ratingDisplay = wrapper.findComponent(RatingDisplay)
      expect(ratingDisplay.props('reviewCount')).toBe(15)
    })

    it('passes null average_rating when producer has no ratings', async () => {
      mockMission.value = createMission({
        producer: createProducer({
          average_rating: null,
          ratings_count: 0,
        }),
      })

      const wrapper = mount(FaceMissionDetailPage, {
        global: {
          plugins: [createTestingPinia()],
          stubs: {
            ApplyToMissionModal: true,
            RatingDisplay: true,
            RouterLink: {
              template: '<a><slot /></a>',
              props: ['to'],
            },
          },
        },
      })

      await flushPromises()

      const ratingDisplay = wrapper.findComponent(RatingDisplay)
      expect(ratingDisplay.props('averageRating')).toBe(null)
      expect(ratingDisplay.props('reviewCount')).toBe(0)
    })
  })

  describe('producer link navigation', () => {
    it('renders producer section as router-link', async () => {
      mockMission.value = createMission({
        producer: createProducer({ id: 42 }),
      })

      const wrapper = mount(FaceMissionDetailPage, {
        global: {
          plugins: [createTestingPinia()],
          stubs: {
            ApplyToMissionModal: true,
            RatingDisplay: true,
            RouterLink: {
              template: '<a :href="to"><slot /></a>',
              props: ['to'],
            },
          },
        },
      })

      await flushPromises()

      // Find the producer link
      const producerLink = wrapper.find('a[href="/producers/42"]')
      expect(producerLink.exists()).toBe(true)
    })

    it('has correct aria-label for accessibility', async () => {
      mockMission.value = createMission({
        producer: createProducer({
          id: 42,
          display_name: 'Studio XYZ',
          agency_name: 'Studio XYZ',
        }),
      })

      const wrapper = mount(FaceMissionDetailPage, {
        global: {
          plugins: [createTestingPinia()],
          stubs: {
            ApplyToMissionModal: true,
            RatingDisplay: true,
            RouterLink: {
              template: '<a :aria-label="ariaLabel" :href="to"><slot /></a>',
              props: ['to', 'ariaLabel'],
            },
          },
        },
      })

      await flushPromises()

      const link = wrapper.find('a[href="/producers/42"]')
      expect(link.attributes('aria-label')).toBe('Voir le profil de Studio XYZ')
    })
  })

  describe('producer display', () => {
    it('displays producer name', async () => {
      mockMission.value = createMission({
        producer: createProducer({
          agency_name: 'Amazing Studio',
          display_name: 'Amazing Studio',
        }),
      })

      const wrapper = mount(FaceMissionDetailPage, {
        global: {
          plugins: [createTestingPinia()],
          stubs: {
            ApplyToMissionModal: true,
            RatingDisplay: true,
            RouterLink: {
              template: '<a><slot /></a>',
              props: ['to'],
            },
          },
        },
      })

      await flushPromises()

      expect(wrapper.text()).toContain('Amazing Studio')
    })

    it('displays producer type label', async () => {
      mockMission.value = createMission({
        producer: createProducer({
          type: 'agency',
        }),
      })

      const wrapper = mount(FaceMissionDetailPage, {
        global: {
          plugins: [createTestingPinia()],
          stubs: {
            ApplyToMissionModal: true,
            RatingDisplay: true,
            RouterLink: {
              template: '<a><slot /></a>',
              props: ['to'],
            },
          },
        },
      })

      await flushPromises()

      expect(wrapper.text()).toContain('Agence')
    })

    it('displays particulier type correctly', async () => {
      mockMission.value = createMission({
        producer: createProducer({
          type: 'particulier',
          agency_name: null,
          first_name: 'Jean',
          last_name: 'Dupont',
          display_name: 'Jean Dupont',
        }),
      })

      const wrapper = mount(FaceMissionDetailPage, {
        global: {
          plugins: [createTestingPinia()],
          stubs: {
            ApplyToMissionModal: true,
            RatingDisplay: true,
            RouterLink: {
              template: '<a><slot /></a>',
              props: ['to'],
            },
          },
        },
      })

      await flushPromises()

      expect(wrapper.text()).toContain('Particulier')
      expect(wrapper.text()).toContain('Jean Dupont')
    })
  })

  describe('loading states', () => {
    it('shows loading spinner when isLoading is true', () => {
      mockIsLoading.value = true

      const wrapper = mount(FaceMissionDetailPage, {
        global: {
          plugins: [createTestingPinia()],
          stubs: {
            ApplyToMissionModal: true,
            RatingDisplay: true,
          },
        },
      })

      expect(wrapper.text()).toContain('Chargement de la mission')
    })

    it('shows not found message when notFound is true', () => {
      mockNotFound.value = true

      const wrapper = mount(FaceMissionDetailPage, {
        global: {
          plugins: [createTestingPinia()],
          stubs: {
            ApplyToMissionModal: true,
            RatingDisplay: true,
          },
        },
      })

      expect(wrapper.text()).toContain('Mission introuvable')
    })

    it('shows error message when error is set', () => {
      mockError.value = 'Une erreur réseau est survenue'

      const wrapper = mount(FaceMissionDetailPage, {
        global: {
          plugins: [createTestingPinia()],
          stubs: {
            ApplyToMissionModal: true,
            RatingDisplay: true,
          },
        },
      })

      expect(wrapper.text()).toContain('Une erreur réseau est survenue')
    })
  })

  describe('gender mismatch', () => {
    function mountWithAuthStore(mission: Mission, userOverrides: Record<string, unknown> = {}) {
      return mount(FaceMissionDetailPage, {
        global: {
          plugins: [
            createTestingPinia({
              initialState: {
                auth: {
                  user: {
                    id: 1,
                    email: 'test@test.com',
                    email_verified: true,
                    email_verified_at: '2026-01-01',
                    userable_type: 'Face',
                    userable: { sexe: 'homme', ...userOverrides },
                  },
                  token: 'fake-token',
                },
              },
              stubActions: false,
            }),
          ],
          stubs: {
            ApplyToMissionModal: true,
            RatingDisplay: true,
            ConfirmModal: true,
            RouterLink: {
              template: '<a><slot /></a>',
              props: ['to'],
            },
          },
        },
      })
    }

    it('disables apply button when Face gender does not match mission genre_voulu', async () => {
      mockMission.value = createMission({
        genre_voulu: 'femme',
        genre_voulu_label: 'Femme',
        is_accepting_candidatures: true,
      })

      const wrapper = mountWithAuthStore(mockMission.value, { sexe: 'homme' })
      await flushPromises()

      const block = wrapper.find('[data-testid="gender-mismatch-block"]')
      expect(block.exists()).toBe(true)
      expect(block.text()).toContain('Cette mission recherche un profil Femme')
      const disabledButton = wrapper.find('[data-testid="apply-button-disabled"]')
      expect(disabledButton.exists()).toBe(true)
      expect(disabledButton.attributes('disabled')).toBeDefined()
    })

    it('shows profile completion message when Face sexe is null', async () => {
      mockMission.value = createMission({
        genre_voulu: 'homme',
        genre_voulu_label: 'Homme',
        is_accepting_candidatures: true,
      })

      const wrapper = mountWithAuthStore(mockMission.value, { sexe: null })
      await flushPromises()

      const block = wrapper.find('[data-testid="gender-mismatch-block"]')
      expect(block.exists()).toBe(true)
      expect(block.text()).toContain('Complétez votre profil')
    })

    it('shows apply button when genre_voulu is tous', async () => {
      mockMission.value = createMission({
        genre_voulu: 'tous',
        genre_voulu_label: 'Homme et Femme',
        is_accepting_candidatures: true,
      })

      const wrapper = mountWithAuthStore(mockMission.value, { sexe: 'homme' })
      await flushPromises()

      const block = wrapper.find('[data-testid="gender-mismatch-block"]')
      expect(block.exists()).toBe(false)
      expect(wrapper.text()).toContain('Postuler à cette mission')
    })

    it('shows apply button when genders match', async () => {
      mockMission.value = createMission({
        genre_voulu: 'homme',
        genre_voulu_label: 'Homme',
        is_accepting_candidatures: true,
      })

      const wrapper = mountWithAuthStore(mockMission.value, { sexe: 'homme' })
      await flushPromises()

      const block = wrapper.find('[data-testid="gender-mismatch-block"]')
      expect(block.exists()).toBe(false)
      expect(wrapper.text()).toContain('Postuler à cette mission')
    })

    it('refreshes auth state and keeps apply CTA disabled while gender context is unknown', async () => {
      mockMission.value = createMission({
        genre_voulu: 'femme',
        genre_voulu_label: 'Femme',
        is_accepting_candidatures: true,
      })

      const pinia = createTestingPinia({
        initialState: {
          auth: {
            user: {
              id: 1,
              email: 'test@test.com',
              email_verified: true,
              email_verified_at: '2026-01-01',
              userable_type: 'Face',
              userable: {},
            },
            token: 'fake-token',
          },
        },
        stubActions: true,
      })
      const authStore = useAuthStore(pinia)
      vi.mocked(authStore.refreshUser).mockResolvedValue(true)

      const wrapper = mount(FaceMissionDetailPage, {
        global: {
          plugins: [pinia],
          stubs: {
            ApplyToMissionModal: true,
            RatingDisplay: true,
            ConfirmModal: true,
            RouterLink: {
              template: '<a><slot /></a>',
              props: ['to'],
            },
          },
        },
      })

      await flushPromises()

      expect(authStore.refreshUser).toHaveBeenCalledOnce()
      expect(wrapper.find('[data-testid="gender-context-block"]').exists()).toBe(true)
      expect(wrapper.text()).toContain('Validation du profil requise')
      expect(wrapper.find('[data-testid="apply-button-disabled"]').exists()).toBe(true)
    })

    it('does not access Face-only gender fields when auth userable is a producer', async () => {
      mockMission.value = createMission({
        genre_voulu: 'femme',
        genre_voulu_label: 'Femme',
        is_accepting_candidatures: true,
      })

      const wrapper = mount(FaceMissionDetailPage, {
        global: {
          plugins: [
            createTestingPinia({
              initialState: {
                auth: {
                  user: {
                    id: 1,
                    email: 'producer@test.com',
                    email_verified: true,
                    email_verified_at: '2026-01-01',
                    userable_type: 'Producer',
                    userable: {
                      id: 12,
                      type: 'agency',
                      agency_name: 'Prod Agency',
                      first_name: null,
                      last_name: null,
                      display_name: 'Prod Agency',
                      created_at: '2026-01-01T00:00:00Z',
                      updated_at: '2026-01-01T00:00:00Z',
                    },
                  },
                  token: 'fake-token',
                },
              },
            }),
          ],
          stubs: {
            ApplyToMissionModal: true,
            RatingDisplay: true,
            ConfirmModal: true,
            RouterLink: {
              template: '<a><slot /></a>',
              props: ['to'],
            },
          },
        },
      })

      await flushPromises()

      expect(wrapper.find('[data-testid="gender-context-block"]').exists()).toBe(false)
      expect(wrapper.find('[data-testid="gender-mismatch-block"]').exists()).toBe(false)
      expect(wrapper.text()).toContain('Postuler à cette mission')
    })
  })

  describe('duration display', () => {
    it('normalizes legacy duration labels with the max prefix', async () => {
      mockMission.value = createMission({ duree: '2 journées (16h)' })

      const wrapper = mount(FaceMissionDetailPage, {
        global: {
          plugins: [createTestingPinia()],
          stubs: {
            ApplyToMissionModal: true,
            RatingDisplay: true,
            RouterLink: {
              template: '<a><slot /></a>',
              props: ['to'],
            },
          },
        },
      })

      await flushPromises()

      expect(wrapper.text()).toContain('2 journées (max 16h)')
    })
  })
})
