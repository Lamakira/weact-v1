import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { ref } from 'vue'
import FaceMissionDetailPage from '../FaceMissionDetailPage.vue'
import RatingDisplay from '@/components/RatingDisplay.vue'
import { ChronoRing } from '@/components/ugc'
import type { Mission, MissionCandidature, MissionProducer } from '@/features/mission/types'
import { useAuthStore } from '@/stores/auth'

// Mock useToast — instance hoistée unique pour pouvoir asserter les appels
const mockToast = vi.hoisted(() => ({
  success: vi.fn(),
  error: vi.fn(),
  warning: vi.fn(),
  info: vi.fn(),
}))
vi.mock('@/composables/useToast', () => ({
  useToast: () => mockToast,
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
  replace: vi.fn(),
}
vi.mock('vue-router', () => ({
  useRoute: () => mockRoute,
  useRouter: () => mockRouter,
}))

// Mock the composable
const mockMission = ref<Mission | null>(null)
const mockCandidature = ref<MissionCandidature | null>(null)
const mockIsLoading = ref(false)
const mockError = ref<string | null>(null)
const mockNotFound = ref(false)
const mockUgcPaywall = ref(false)
const mockUgcPaywallMessage = ref<string | null>(null)
const mockFetchMission = vi.fn()
const mockSetCandidature = vi.fn()

vi.mock('@/features/mission/composables', () => ({
  useMissionDetail: () => ({
    mission: mockMission,
    candidature: mockCandidature,
    isLoading: mockIsLoading,
    error: mockError,
    notFound: mockNotFound,
    ugcPaywall: mockUgcPaywall,
    ugcPaywallMessage: mockUgcPaywallMessage,
    fetchMission: mockFetchMission,
    setCandidature: mockSetCandidature,
  }),
}))

// Mock du barrel candidature (8-3) — le mock remplace TOUT le module, donc il
// doit fournir useCancelCandidature (importé par la page) EN PLUS de useReconfirmCandidature.
const mockIsReconfirming = ref(false)
const mockReconfirmError = ref<string | null>(null)
const mockReconfirmCandidature = vi.fn()
const mockIsCancelling = ref(false)
const mockCancelError = ref<string | null>(null)
const mockCancelSuccessMessage = ref<string | null>(null)
const mockCancelCandidature = vi.fn()
const mockResetCancel = vi.fn()

vi.mock('@/features/candidature/composables', () => ({
  useReconfirmCandidature: () => ({
    isReconfirming: mockIsReconfirming,
    error: mockReconfirmError,
    reconfirmCandidature: mockReconfirmCandidature,
  }),
  useCancelCandidature: () => ({
    isCancelling: mockIsCancelling,
    error: mockCancelError,
    successMessage: mockCancelSuccessMessage,
    cancelCandidature: mockCancelCandidature,
    reset: mockResetCancel,
  }),
}))

// Mock useUgcShipment (3.4) — spies hissés, reset dans beforeEach (hygiène mocks 3.2)
const mockIsSubmittingReceipt = ref(false)
const mockReceiptError = ref<string | null>(null)
const mockReceiptErrorCode = ref<string | null>(null)
const mockConfirmShipment = vi.fn()
const mockConfirmReceipt = vi.fn()

vi.mock('@/composables/useUgcShipment', () => ({
  useUgcShipment: () => ({
    isSubmitting: mockIsSubmittingReceipt,
    error: mockReceiptError,
    errorCode: mockReceiptErrorCode,
    confirmShipment: mockConfirmShipment,
    confirmReceipt: mockConfirmReceipt,
    clearError: vi.fn(),
  }),
}))

// Mock useUgcDeliverable (4.2) — factory COMPLÈTE (piège n°6), spies hissés.
const mockUploadDeliverable = vi.fn()
const mockDeliverableError = ref<string | null>(null)
const mockDeliverableErrorCode = ref<string | null>(null)

vi.mock('@/composables/useUgcDeliverable', () => ({
  useUgcDeliverable: () => ({
    isUploading: ref(false),
    uploadProgress: ref(null),
    error: mockDeliverableError,
    errorCode: mockDeliverableErrorCode,
    uploadDeliverable: mockUploadDeliverable,
    validateFile: vi.fn(() => ({ valid: true })),
    clearError: vi.fn(),
  }),
}))

// Factory for creating test mission data
function createMission(overrides: Partial<Mission> = {}): Mission {
  return {
    id: 'mission-uuid-1',
    titre: 'Test Mission',
    description: 'Test mission description',
    date_tournage: '2026-02-15',
    profil_recherche: 'Femme 25-35 ans',
    budget: 150000,
    date_limite_candidature: '2026-02-01',
    nombre_faces_voulu: 3,
    type_mission: 'publicite',
    type_mission_label: 'Publicité',
    type_mission_autre: null,
    type_compensation: null,
    type_compensation_label: null,
    nom_produit: null,
    valeur_produit: null,
    nombre_videos: null,
    montant_remuneration: null,
    commission_ugc: null,
    commission_paid_at: null,
    genre_voulu: 'femme',
    genre_voulu_label: 'Femme',
    lieu: 'Cotonou',
    duree: '2 jours',
    status: 'published',
    status_label: 'Publiée',
    is_accepting_candidatures: true,
    has_paid_payment: false,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    producer: createProducer(),
    ...overrides,
  }
}

// Mission UGC hybride (la réponse Face réelle ne contient pas commission_ugc/
// commission_paid_at — la factory les laisse à null, aucun test ne doit les lire)
function createUgcMission(overrides: Partial<Mission> = {}): Mission {
  return createMission({
    titre: 'Sneakers running · Unbox + review',
    type_mission: 'autre' as Mission['type_mission'], // jamais lu par le code UGC (D-2.3.b)
    type_mission_label: 'UGC',
    budget: 20000, // dérivé serveur (= montant_remuneration) — ne doit PAS être rendu
    type_compensation: 'hybrid',
    type_compensation_label: 'Produit + Argent',
    nom_produit: 'Sneakers Shade Fit',
    valeur_produit: 35000,
    nombre_videos: 3,
    montant_remuneration: 20000,
    ...overrides,
  })
}

function createProducer(overrides: Partial<MissionProducer> = {}): MissionProducer {
  return {
    id: 'producer-uuid-1',
    slug: 'test-agency',
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
    mockUgcPaywall.value = false
    mockUgcPaywallMessage.value = null
    mockFetchMission.mockClear()
    mockSetCandidature.mockClear()
    mockIsReconfirming.value = false
    mockReconfirmError.value = null
    mockReconfirmCandidature.mockReset()
    mockIsCancelling.value = false
    mockCancelError.value = null
    mockCancelSuccessMessage.value = null
    mockCancelCandidature.mockClear()
    mockResetCancel.mockClear()
    mockRouter.push.mockClear()
    mockRouter.back.mockClear()
    mockRouter.replace.mockClear()
    mockToast.success.mockClear()
    mockToast.error.mockClear()
    mockToast.warning.mockClear()
    mockToast.info.mockClear()
    mockIsSubmittingReceipt.value = false
    mockReceiptError.value = null
    mockReceiptErrorCode.value = null
    mockConfirmShipment.mockReset()
    mockConfirmReceipt.mockReset()
    mockUploadDeliverable.mockReset()
    mockDeliverableError.value = null
    mockDeliverableErrorCode.value = null
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
        producer: createProducer({ id: 'producer-uuid-42', slug: 'producer-42' }),
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
      const producerLink = wrapper.find('a[href="/producers/producer-42"]')
      expect(producerLink.exists()).toBe(true)
    })

    it('has correct aria-label for accessibility', async () => {
      mockMission.value = createMission({
        producer: createProducer({
          id: 'producer-uuid-42',
          slug: 'studio-xyz',
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

      const link = wrapper.find('a[href="/producers/studio-xyz"]')
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

  describe('UGC mission detail', () => {
    function mountPage() {
      return mount(FaceMissionDetailPage, {
        global: {
          plugins: [createTestingPinia()],
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

    it('renders the 3-cell stats grid for a hybrid UGC mission', async () => {
      mockMission.value = createUgcMission()

      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.find('[data-testid="ugc-mission-stats"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="ugc-stat-cash"]').exists()).toBe(true)
      expect(wrapper.text().replace(/\s/g, ' ')).toContain('35 000 FCFA')
    })

    it('omits the Cash cell for a product-only UGC mission', async () => {
      mockMission.value = createUgcMission({
        type_compensation: 'product',
        type_compensation_label: 'Produit seul',
        montant_remuneration: null,
        nombre_videos: 2,
        budget: 0,
      })

      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.find('[data-testid="ugc-mission-stats"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="ugc-stat-cash"]').exists()).toBe(false)
    })

    it('hides the budget cell for UGC and keeps it for standard missions', async () => {
      mockMission.value = createUgcMission()

      const ugcWrapper = mountPage()
      await flushPromises()
      expect(ugcWrapper.text()).not.toContain('Rémunération proposée')

      mockMission.value = createMission()
      const standardWrapper = mountPage()
      await flushPromises()
      expect(standardWrapper.text()).toContain('Rémunération proposée')
    })

    it('hides the shooting fields (date de tournage / lieu / durée) for UGC and keeps them for standard', async () => {
      // Pour l'UGC ces champs sont null (masqués au cadrage 8-1) → ne JAMAIS les rendre vides.
      mockMission.value = createUgcMission()
      const ugcWrapper = mountPage()
      await flushPromises()
      expect(ugcWrapper.text()).not.toContain('Date de tournage')
      expect(ugcWrapper.text()).not.toContain('Lieu')
      expect(ugcWrapper.text()).not.toContain('Durée')

      mockMission.value = createMission()
      const standardWrapper = mountPage()
      await flushPromises()
      expect(standardWrapper.text()).toContain('Date de tournage')
      expect(standardWrapper.text()).toContain('Lieu')
      expect(standardWrapper.text()).toContain('Durée')
    })

    it('renders the deliverables block with two ChronoRing at progress 0', async () => {
      mockMission.value = createUgcMission()

      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.find('[data-testid="ugc-deliverables-preview"]').exists()).toBe(true)
      const rings = wrapper.findAllComponents(ChronoRing)
      expect(rings).toHaveLength(2)
      expect(rings[0]!.props('progress')).toBe(0)
      expect(rings[1]!.props('progress')).toBe(0)
    })

    it('shows the verified producer line for UGC and hides it for standard', async () => {
      mockMission.value = createUgcMission()

      const ugcWrapper = mountPage()
      await flushPromises()
      const verified = ugcWrapper.find('[data-testid="ugc-verified-producer"]')
      expect(verified.exists()).toBe(true)
      expect(verified.text()).toContain('Producteur vérifié')

      mockMission.value = createMission()
      const standardWrapper = mountPage()
      await flushPromises()
      expect(standardWrapper.find('[data-testid="ugc-verified-producer"]').exists()).toBe(false)
    })

    it('titles the description section "Brief" for UGC missions', async () => {
      mockMission.value = createUgcMission()

      const wrapper = mountPage()
      await flushPromises()

      // Assertion ciblée sur les headings de section — pas sur le texte global de la page
      const headings = wrapper.findAll('h2').map((h) => h.text())
      expect(headings).toContain('Brief')
      expect(headings).not.toContain('Description')
    })

    it('renders the compensation tag next to the type badge', async () => {
      mockMission.value = createUgcMission()

      const wrapper = mountPage()
      await flushPromises()

      const tag = wrapper.find('[data-testid="ugc-compensation-tag"]')
      expect(tag.exists()).toBe(true)
      expect(tag.text()).toBe('Produit + Argent')
    })
  })

  describe('UGC candidature cycle (8.3)', () => {
    function makeCandidature(overrides: Partial<MissionCandidature> = {}): MissionCandidature {
      return {
        id: 'candidature-1',
        mission_id: 'mission-uuid-1',
        face_id: 'face-1',
        status: 'pending',
        status_label: 'En attente',
        message_motivation: null,
        created_at: '2026-06-11T10:00:00Z',
        updated_at: '2026-06-11T10:00:00Z',
        ...overrides,
      }
    }

    function mountEligiblePage() {
      return mount(FaceMissionDetailPage, {
        global: {
          plugins: [
            createTestingPinia({
              initialState: {
                auth: {
                  user: {
                    id: 1,
                    email: 'face@test.com',
                    email_verified: true,
                    email_verified_at: '2026-01-01',
                    userable_type: 'Face',
                    userable: { sexe: 'femme' },
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

    it('shows the apply button (no sticky CTA) for an eligible UGC mission without candidature', async () => {
      mockMission.value = createUgcMission({ genre_voulu: 'tous', genre_voulu_label: 'Homme et Femme' })

      const wrapper = mountEligiblePage()
      await flushPromises()

      expect(wrapper.text()).toContain('Postuler à cette mission')
      expect(wrapper.find('[data-testid="ugc-accept-sticky"]').exists()).toBe(false)
    })

    it('keeps the apply flow for standard missions without sticky CTA', async () => {
      mockMission.value = createMission({ genre_voulu: 'tous', genre_voulu_label: 'Homme et Femme' })

      const wrapper = mountEligiblePage()
      await flushPromises()

      expect(wrapper.text()).toContain('Postuler à cette mission')
      expect(wrapper.find('[data-testid="ugc-accept-sticky"]').exists()).toBe(false)
    })

    it('shows the reconfirm CTA for an accepted UGC candidature and reconfirms + refetches on click', async () => {
      mockMission.value = createUgcMission({ genre_voulu: 'tous', genre_voulu_label: 'Homme et Femme' })
      mockCandidature.value = makeCandidature({ status: 'accepted', status_label: 'Acceptée' })
      mockReconfirmCandidature.mockResolvedValueOnce({
        data: { id: 'candidature-1' },
        message: 'Participation reconfirmée',
      })

      const wrapper = mountEligiblePage()
      await flushPromises()
      mockFetchMission.mockClear()

      const btn = wrapper.find('[data-testid="ugc-reconfirm-btn"]')
      expect(btn.exists()).toBe(true)
      // Pas de bouton Annuler (pending-only) ni de sticky auto-accept.
      expect(wrapper.text()).not.toContain('Annuler ma candidature')
      expect(wrapper.find('[data-testid="ugc-accept-sticky"]').exists()).toBe(false)

      await btn.trigger('click')
      await flushPromises()

      expect(mockReconfirmCandidature).toHaveBeenCalledWith('candidature-1')
      expect(mockToast.success).toHaveBeenCalledWith(
        'Participation reconfirmée — le producteur va expédier votre produit',
      )
      expect(mockFetchMission).toHaveBeenCalledWith('1')
    })

    it('renders the engaged banner without CTA nor cancel for a confirmed candidature', async () => {
      mockMission.value = createUgcMission({ genre_voulu: 'tous', genre_voulu_label: 'Homme et Femme' })
      mockCandidature.value = makeCandidature({ status: 'confirmed', status_label: 'Confirmée' })

      const wrapper = mountEligiblePage()
      await flushPromises()

      expect(wrapper.find('[data-testid="ugc-engaged-banner"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="ugc-accept-sticky"]').exists()).toBe(false)
      expect(wrapper.text()).not.toContain('Annuler ma candidature')
    })

    it('shows "Candidature envoyée" + cancel (no sticky, no reconfirm) for a pending UGC candidature', async () => {
      mockMission.value = createUgcMission({ genre_voulu: 'tous', genre_voulu_label: 'Homme et Femme' })
      mockCandidature.value = makeCandidature({ status: 'pending', status_label: 'En attente' })

      const wrapper = mountEligiblePage()
      await flushPromises()

      expect(wrapper.text()).toContain('Candidature envoyée')
      expect(wrapper.text()).toContain('Annuler ma candidature')
      expect(wrapper.find('[data-testid="ugc-accept-sticky"]').exists()).toBe(false)
      expect(wrapper.find('[data-testid="ugc-reconfirm-btn"]').exists()).toBe(false)
    })

  })

  describe('carte de suivi Face & réception (story 3.4)', () => {
    function makeShipment(overrides: Record<string, unknown> = {}): Record<string, unknown> {
      return {
        id: 'shipment-uuid-1',
        transporteur: 'Gozem',
        numero_suivi: 'GZM-COT-882194',
        note_envoi: null,
        tunnel_status: 'shipped',
        tunnel_status_label: 'Produit expédié',
        shipped_at: '2026-06-12T10:00:00+00:00',
        recu_le: null,
        unboxing_deadline_at: null,
        destinataire: { nom: 'Aïcha Bello', ville: 'Cotonou', pays: 'Bénin' },
        created_at: '2026-06-12T10:00:00+00:00',
        ...overrides,
      }
    }

    function makeEngagedCandidature(overrides: Partial<MissionCandidature> = {}): MissionCandidature {
      return {
        id: 'candidature-1',
        mission_id: 'mission-uuid-1',
        face_id: 'face-1',
        status: 'confirmed',
        status_label: 'Confirmée',
        message_motivation: null,
        created_at: '2026-06-11T10:00:00Z',
        updated_at: '2026-06-11T10:00:00Z',
        ...overrides,
      }
    }

    function mountTrackingPage() {
      return mount(FaceMissionDetailPage, {
        global: {
          plugins: [
            createTestingPinia({
              initialState: {
                auth: {
                  user: {
                    id: 1,
                    email: 'face@test.com',
                    email_verified: true,
                    email_verified_at: '2026-01-01',
                    userable_type: 'Face',
                    userable: { sexe: 'femme' },
                  },
                  token: 'fake-token',
                },
              },
              stubActions: false,
            }),
          ],
          stubs: {
            ApplyToMissionModal: true,
            UgcEngagementModal: true,
            RatingDisplay: true,
            UgcFaceTrackingCard: {
              props: ['shipment', 'current', 'isSubmitting', 'isUploading', 'uploadProgress', 'deliverables'],
              emits: ['confirm-receipt', 'upload'],
              // `new File` vit dans methods (scope JS) — indisponible dans le scope template Vue.
              methods: {
                emitUpload() {
                  this.$emit('upload', new File(['x'], 'unboxing.mp4', { type: 'video/mp4' }))
                },
              },
              template: `<div>
                <button data-testid="face-tracking-card-stub" :data-current="current" :data-deliverables-count="deliverables.length" @click="$emit('confirm-receipt')"></button>
                <button data-testid="face-tracking-upload-stub" @click="emitUpload"></button>
              </div>`,
            },
            ConfirmModal: {
              props: ['isOpen'],
              emits: ['confirm', 'cancel'],
              template: '<button v-if="isOpen" data-testid="receipt-modal-confirm" @click="$emit(\'confirm\')"/>',
            },
            RouterLink: {
              template: '<a><slot /></a>',
              props: ['to'],
            },
          },
        },
      })
    }

    it('shows the Face tracking card instead of the engaged banner once shipped', async () => {
      mockMission.value = createUgcMission({ genre_voulu: 'tous', genre_voulu_label: 'Homme et Femme' })
      mockCandidature.value = makeEngagedCandidature({
        shipment: makeShipment() as unknown as MissionCandidature['shipment'],
      })

      const wrapper = mountTrackingPage()
      await flushPromises()

      const card = wrapper.find('[data-testid="face-tracking-card-stub"]')
      expect(card.exists()).toBe(true)
      // Step dérivé par ugcCandidatureTunnelStep : shipped → 4.
      expect(card.attributes('data-current')).toBe('4')
      expect(wrapper.find('[data-testid="ugc-engaged-banner"]').exists()).toBe(false)
    })

    it('keeps the engaged banner while the candidature has no shipment', async () => {
      mockMission.value = createUgcMission({ genre_voulu: 'tous', genre_voulu_label: 'Homme et Femme' })
      mockCandidature.value = makeEngagedCandidature()

      const wrapper = mountTrackingPage()
      await flushPromises()

      expect(wrapper.find('[data-testid="ugc-engaged-banner"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="face-tracking-card-stub"]').exists()).toBe(false)
    })

    it('confirms the receipt and applies the shipment to the candidature locally', async () => {
      mockMission.value = createUgcMission({ genre_voulu: 'tous', genre_voulu_label: 'Homme et Femme' })
      mockCandidature.value = makeEngagedCandidature({
        shipment: makeShipment() as unknown as MissionCandidature['shipment'],
      })
      const receivedShipment = makeShipment({
        tunnel_status: 'received',
        tunnel_status_label: 'Produit reçu',
        recu_le: '2026-06-12T12:00:00+00:00',
        unboxing_deadline_at: '2026-06-19T12:00:00+00:00',
      })
      mockConfirmReceipt.mockResolvedValue(receivedShipment)

      const wrapper = mountTrackingPage()
      await flushPromises()
      mockFetchMission.mockClear()

      // Le CTA de la carte ouvre la modal — pas de POST direct (D-3.4.c).
      await wrapper.find('[data-testid="face-tracking-card-stub"]').trigger('click')
      expect(mockConfirmReceipt).not.toHaveBeenCalled()

      await wrapper.find('[data-testid="receipt-modal-confirm"]').trigger('click')
      await flushPromises()

      expect(mockConfirmReceipt).toHaveBeenCalledWith('shipment-uuid-1')
      // Assignation locale via le setter existant — pas de refetch (D-3.4.d).
      expect(mockSetCandidature).toHaveBeenCalledWith(
        expect.objectContaining({ id: 'candidature-1', shipment: receivedShipment }),
      )
      expect(mockFetchMission).not.toHaveBeenCalled()
      expect(mockToast.success).toHaveBeenCalledWith('Réception confirmée — le chrono Unboxing démarre')
    })

    it('refetches the mission when receipt fails with ALREADY_RECEIVED', async () => {
      mockMission.value = createUgcMission({ genre_voulu: 'tous', genre_voulu_label: 'Homme et Femme' })
      mockCandidature.value = makeEngagedCandidature({
        shipment: makeShipment() as unknown as MissionCandidature['shipment'],
      })
      mockConfirmReceipt.mockImplementation(async () => {
        mockReceiptError.value = 'La réception de ce produit a déjà été confirmée.'
        mockReceiptErrorCode.value = 'ALREADY_RECEIVED'
        return null
      })

      const wrapper = mountTrackingPage()
      await flushPromises()
      mockFetchMission.mockClear()

      await wrapper.find('[data-testid="face-tracking-card-stub"]').trigger('click')
      await wrapper.find('[data-testid="receipt-modal-confirm"]').trigger('click')
      await flushPromises()

      expect(mockToast.info).toHaveBeenCalledWith('La réception de ce produit a déjà été confirmée.')
      expect(mockFetchMission).toHaveBeenCalledWith('1')
      expect(mockSetCandidature).not.toHaveBeenCalled()
    })

    it('shows an error toast when receipt fails with another error', async () => {
      mockMission.value = createUgcMission({ genre_voulu: 'tous', genre_voulu_label: 'Homme et Femme' })
      mockCandidature.value = makeEngagedCandidature({
        shipment: makeShipment() as unknown as MissionCandidature['shipment'],
      })
      mockConfirmReceipt.mockImplementation(async () => {
        mockReceiptError.value = 'La commission de ce deal est en cours de remboursement — action impossible.'
        mockReceiptErrorCode.value = 'INVALID_STATUS'
        return null
      })

      const wrapper = mountTrackingPage()
      await flushPromises()
      mockFetchMission.mockClear()

      await wrapper.find('[data-testid="face-tracking-card-stub"]').trigger('click')
      await wrapper.find('[data-testid="receipt-modal-confirm"]').trigger('click')
      await flushPromises()

      expect(mockToast.error).toHaveBeenCalledWith(
        'La commission de ce deal est en cours de remboursement — action impossible.',
      )
      // Pas de refetch ni d'assignation locale hors ALREADY_RECEIVED.
      expect(mockFetchMission).not.toHaveBeenCalled()
      expect(mockSetCandidature).not.toHaveBeenCalled()
    })

    it('uploads the unboxing video and refetches the mission on success', async () => {
      mockMission.value = createUgcMission({ genre_voulu: 'tous', genre_voulu_label: 'Homme et Femme' })
      mockCandidature.value = makeEngagedCandidature({
        shipment: makeShipment() as unknown as MissionCandidature['shipment'],
      })
      mockUploadDeliverable.mockResolvedValue({ id: 'deliverable-uuid-1', kind: 'unboxing' })

      const wrapper = mountTrackingPage()
      await flushPromises()
      mockFetchMission.mockClear()

      await wrapper.find('[data-testid="face-tracking-upload-stub"]').trigger('click')
      await flushPromises()

      expect(mockUploadDeliverable).toHaveBeenCalledWith('shipment-uuid-1', expect.any(File))
      expect(mockToast.success).toHaveBeenCalledWith('Vidéo Unboxing déposée — en attente de validation')
      // D-4.2.d : le 201 porte la DeliverableResource, PAS le shipment → refetch via fetchMission.
      expect(mockFetchMission).toHaveBeenCalledWith('1')
    })

    it('refetches the mission when the upload reports ALREADY_UPLOADED', async () => {
      mockMission.value = createUgcMission({ genre_voulu: 'tous', genre_voulu_label: 'Homme et Femme' })
      mockCandidature.value = makeEngagedCandidature({
        shipment: makeShipment() as unknown as MissionCandidature['shipment'],
      })
      mockUploadDeliverable.mockImplementation(async () => {
        mockDeliverableError.value = 'Votre vidéo Unboxing a déjà été déposée pour ce deal.'
        mockDeliverableErrorCode.value = 'ALREADY_UPLOADED'
        return null
      })

      const wrapper = mountTrackingPage()
      await flushPromises()
      mockFetchMission.mockClear()

      await wrapper.find('[data-testid="face-tracking-upload-stub"]').trigger('click')
      await flushPromises()

      expect(mockToast.info).toHaveBeenCalledWith('Votre vidéo Unboxing a déjà été déposée pour ce deal.')
      expect(mockFetchMission).toHaveBeenCalledWith('1')
    })

    it('refetches the mission when the upload reports INVALID_STATUS', async () => {
      mockMission.value = createUgcMission({ genre_voulu: 'tous', genre_voulu_label: 'Homme et Femme' })
      mockCandidature.value = makeEngagedCandidature({
        shipment: makeShipment() as unknown as MissionCandidature['shipment'],
      })
      mockUploadDeliverable.mockImplementation(async () => {
        mockDeliverableError.value = "La vidéo ne peut pas être déposée dans l'état actuel de ce deal."
        mockDeliverableErrorCode.value = 'INVALID_STATUS'
        return null
      })

      const wrapper = mountTrackingPage()
      await flushPromises()
      mockFetchMission.mockClear()

      await wrapper.find('[data-testid="face-tracking-upload-stub"]').trigger('click')
      await flushPromises()

      expect(mockToast.error).toHaveBeenCalledWith(
        "La vidéo ne peut pas être déposée dans l'état actuel de ce deal.",
      )
      expect(mockFetchMission).toHaveBeenCalledWith('1')
    })

    it('shows an error toast without refetching when the upload fails', async () => {
      mockMission.value = createUgcMission({ genre_voulu: 'tous', genre_voulu_label: 'Homme et Femme' })
      mockCandidature.value = makeEngagedCandidature({
        shipment: makeShipment() as unknown as MissionCandidature['shipment'],
      })
      mockUploadDeliverable.mockImplementation(async () => {
        mockDeliverableError.value = 'Format non supporté.'
        mockDeliverableErrorCode.value = null // validation client/errors.video → branche « autre »
        return null
      })

      const wrapper = mountTrackingPage()
      await flushPromises()
      mockFetchMission.mockClear()

      await wrapper.find('[data-testid="face-tracking-upload-stub"]').trigger('click')
      await flushPromises()

      expect(mockToast.error).toHaveBeenCalledWith('Format non supporté.')
      expect(mockFetchMission).not.toHaveBeenCalled() // pas de refetch : l'état du deal n'a pas changé
    })

    it('passes the candidature deliverables to the Face tracking card (4.6)', async () => {
      mockMission.value = createUgcMission({ genre_voulu: 'tous', genre_voulu_label: 'Homme et Femme' })
      mockCandidature.value = makeEngagedCandidature({
        shipment: makeShipment({
          tunnel_status: 'avis_pending',
          avis_deadline_at: '2026-06-26T12:00:00+00:00',
        }) as unknown as MissionCandidature['shipment'],
        deliverables: [
          { id: 'd1', kind: 'unboxing', validation_status: 'validated' },
          { id: 'd2', kind: 'avis', validation_status: 'rejected' },
        ] as unknown as MissionCandidature['deliverables'],
      })

      const wrapper = mountTrackingPage()
      await flushPromises()

      const card = wrapper.find('[data-testid="face-tracking-card-stub"]')
      expect(card.exists()).toBe(true)
      expect(card.attributes('data-deliverables-count')).toBe('2')
    })

    it('uploads in the Avis phase and refetches the mission (handler réutilisé)', async () => {
      mockMission.value = createUgcMission({ genre_voulu: 'tous', genre_voulu_label: 'Homme et Femme' })
      mockCandidature.value = makeEngagedCandidature({
        shipment: makeShipment({
          tunnel_status: 'avis_pending',
          avis_deadline_at: '2026-06-26T12:00:00+00:00',
        }) as unknown as MissionCandidature['shipment'],
        deliverables: [
          { id: 'd1', kind: 'unboxing', validation_status: 'validated' },
        ] as unknown as MissionCandidature['deliverables'],
      })
      mockUploadDeliverable.mockResolvedValue({ id: 'deliverable-uuid-2', kind: 'avis' })

      const wrapper = mountTrackingPage()
      await flushPromises()
      mockFetchMission.mockClear()

      await wrapper.find('[data-testid="face-tracking-upload-stub"]').trigger('click')
      await flushPromises()

      expect(mockUploadDeliverable).toHaveBeenCalledWith('shipment-uuid-1', expect.any(File))
      expect(mockFetchMission).toHaveBeenCalledWith('1') // D-4.2.d : refetch
    })
  })

  describe('UGC paywall redirect', () => {
    it('redirects to pricing with an info toast when the paywall flag is set', async () => {
      mockUgcPaywall.value = true
      mockUgcPaywallMessage.value = "L'accès aux missions UGC est réservé aux Faces abonnées (Starter et plus)."

      mount(FaceMissionDetailPage, {
        global: {
          plugins: [createTestingPinia()],
          stubs: {
            ApplyToMissionModal: true,
            RatingDisplay: true,
            ConfirmModal: true,
          },
        },
      })
      await flushPromises()

      expect(mockToast.info).toHaveBeenCalledWith(
        "L'accès aux missions UGC est réservé aux Faces abonnées (Starter et plus).",
      )
      expect(mockRouter.replace).toHaveBeenCalledWith({ name: 'pricing' })
    })
  })
})
