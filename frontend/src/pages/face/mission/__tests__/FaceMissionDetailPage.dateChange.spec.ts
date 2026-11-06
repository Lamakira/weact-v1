import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { ref } from 'vue'
import FaceMissionDetailPage from '../FaceMissionDetailPage.vue'
import { dateChangeApi } from '@/features/dateChange/services/dateChangeApi'
import type { FaceMissionDateChange } from '@/features/dateChange/types'
import type { Mission, MissionCandidature } from '@/features/mission/types'

/**
 * Changement de date côté Face : le bloc se place au-dessus de la barre d'étapes, la confirmation part
 * directement, le retrait passe par la fenêtre de confirmation.
 */

const mockToast = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() }))
vi.mock('@/composables/useToast', () => ({ useToast: () => mockToast }))
vi.mock('@/features/auth/services/authApi', () => ({ authApi: { resendVerificationEmail: vi.fn() } }))

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { id: 'mission-uuid-1' }, fullPath: '/face/missions/mission-uuid-1' }),
  useRouter: () => ({ push: vi.fn(), back: vi.fn(), replace: vi.fn() }),
}))

vi.mock('@/features/face/composables/useSubscriptionStatus', () => ({
  useSubscriptionStatus: () => ({ data: ref(null), tier: ref('free'), fetchStatus: vi.fn() }),
}))

const mockMission = ref<Mission | null>(null)
const mockCandidature = ref<MissionCandidature | null>(null)
const mockFetchMission = vi.fn()

vi.mock('@/features/mission/composables', () => ({
  useMissionDetail: () => ({
    mission: mockMission,
    candidature: mockCandidature,
    isLoading: ref(false),
    error: ref(null),
    notFound: ref(false),
    ugcPaywall: ref(false),
    ugcPaywallMessage: ref(null),
    fetchMission: mockFetchMission,
    setCandidature: vi.fn(),
  }),
}))

vi.mock('@/features/candidature/composables', () => ({
  useConfirmCandidature: () => ({ isConfirming: ref(false), error: ref(null), errorCode: ref(null), confirmCandidature: vi.fn() }),
  useReconfirmCandidature: () => ({ isReconfirming: ref(false), error: ref(null), errorCode: ref(null), reconfirmCandidature: vi.fn() }),
  useCancelCandidature: () => ({
    isCancelling: ref(false),
    error: ref(null),
    successMessage: ref(null),
    cancelCandidature: vi.fn(),
    reset: vi.fn(),
  }),
}))

vi.mock('@/composables/useUgcShipment', () => ({
  useUgcShipment: () => ({ isSubmitting: ref(false), error: ref(null), errorCode: ref(null), confirmShipment: vi.fn(), confirmReceipt: vi.fn(), clearError: vi.fn() }),
}))
vi.mock('@/composables/useUgcDeliverable', () => ({
  useUgcDeliverable: () => ({
    isUploading: ref(false),
    uploadProgress: ref(null),
    error: ref(null),
    errorCode: ref(null),
    uploadDeliverable: vi.fn(),
    validateFile: vi.fn(() => ({ valid: true })),
    clearError: vi.fn(),
  }),
}))

vi.mock('@/features/dateChange/services/dateChangeApi', () => ({
  dateChangeApi: {
    proposeMission: vi.fn(),
    respondMission: vi.fn(),
    proposeBooking: vi.fn(),
    respondBooking: vi.fn(),
    withdrawBooking: vi.fn(),
  },
}))

vi.mock('@/features/notification/components/PushMomentCard.vue', () => ({
  default: { template: '<div />' },
}))

function makeDateChange(overrides: Partial<FaceMissionDateChange> = {}): FaceMissionDateChange {
  return {
    old_date: '2026-11-15',
    new_date: '2026-11-20',
    reason_label: 'Lieu indisponible',
    respond_by: new Date(Date.now() + 40 * 3600 * 1000).toISOString(),
    status: 'pending',
    conflict: false,
    can_respond: true,
    ...overrides,
  }
}

function setup(dateChange: FaceMissionDateChange | null) {
  mockMission.value = {
    id: 'mission-uuid-1',
    titre: 'Spot boutique Haie Vive',
    description: 'Brief',
    date_tournage: '2026-11-20',
    profil_recherche: 'Face',
    budget: 25000,
    genre_voulu_label: 'Tous',
    lieu: 'Cotonou',
    duree: '1 jour',
    type_mission: 'publicite',
    nom_produit: null,
    montant_remuneration: null,
    date_change: dateChange,
    producer: { id: 'p1', slug: 'agence', display_name: 'Agence', average_rating: null, ratings_count: 0 },
  } as unknown as Mission
  mockCandidature.value = {
    id: 'cand-uuid-1',
    mission_id: 'mission-uuid-1',
    face_id: 'f1',
    status: 'confirmed',
    status_label: 'Confirmée',
    montant_face_recoit: 22500,
  } as unknown as MissionCandidature

  return mount(FaceMissionDetailPage, {
    global: {
      plugins: [createTestingPinia()],
      stubs: {
        teleport: true,
        ApplyToMissionModal: true,
        RatingDisplay: true,
        UgcFaceTrackingCard: true,
        MissionAbsenceContestBlock: true,
        MissionRatingDialog: true,
        RouterLink: { template: '<a><slot /></a>', props: ['to'] },
      },
    },
  })
}

beforeEach(() => {
  mockMission.value = null
  mockCandidature.value = null
  mockFetchMission.mockReset()
  vi.mocked(dateChangeApi.respondMission).mockReset()
  mockToast.success.mockClear()
  mockToast.error.mockClear()
})

describe('FaceMissionDetailPage — changement de date', () => {
  it('place le bloc juste au-dessus de la barre d’étapes', async () => {
    const wrapper = setup(makeDateChange())
    await flushPromises()

    const block = wrapper.get('[data-testid="date-change-face-block"]')
    const steps = wrapper.get('[data-testid="hub-steps"]')
    // 4 = DOCUMENT_POSITION_FOLLOWING : la barre d'étapes suit le bloc
    expect(block.element.compareDocumentPosition(steps.element) & 4).toBeTruthy()
    expect(block.element.nextElementSibling).toBe(steps.element)
  })

  it('aucun bloc sans changement de date', async () => {
    const wrapper = setup(null)
    await flushPromises()
    expect(wrapper.find('[data-testid="date-change-face-block"]').exists()).toBe(false)
  })

  it('« Je confirme » appelle l’API puis relit la mission', async () => {
    vi.mocked(dateChangeApi.respondMission).mockResolvedValue({ data: makeDateChange({ status: 'confirmed' }), message: 'Nouvelle date confirmée.' })
    const wrapper = setup(makeDateChange())
    await flushPromises()

    await wrapper.get('[data-testid="date-change-confirm"]').trigger('click')
    await flushPromises()

    expect(dateChangeApi.respondMission).toHaveBeenCalledWith('cand-uuid-1', 'confirm')
    expect(mockToast.success).toHaveBeenCalledWith('Nouvelle date confirmée.')
    expect(mockFetchMission).toHaveBeenCalledWith('mission-uuid-1', { silent: true })
  })

  it('« Je ne peux pas » n’appelle l’API qu’après la fenêtre de confirmation', async () => {
    vi.mocked(dateChangeApi.respondMission).mockResolvedValue({ data: makeDateChange({ status: 'withdrawn', can_respond: false }) })
    const wrapper = setup(makeDateChange())
    await flushPromises()

    await wrapper.get('[data-testid="date-change-withdraw"]').trigger('click')
    expect(dateChangeApi.respondMission).not.toHaveBeenCalled()
    // Montant déjà connu de la page : le net de la candidature
    expect(wrapper.get('[data-testid="date-change-withdraw-dialog"]').text()).toMatch(/22\D?500/)

    await wrapper.get('[data-testid="date-change-withdraw-confirm"]').trigger('click')
    await flushPromises()

    expect(dateChangeApi.respondMission).toHaveBeenCalledWith('cand-uuid-1', 'withdraw')
    expect(mockFetchMission).toHaveBeenCalledWith('mission-uuid-1', { silent: true })
  })

  it('date déjà prise : la confirmation est désactivée et n’appelle rien', async () => {
    const wrapper = setup(makeDateChange({ conflict: true }))
    await flushPromises()

    const confirm = wrapper.get('[data-testid="date-change-confirm"]')
    expect(confirm.attributes('disabled')).toBeDefined()
    await confirm.trigger('click')
    expect(dateChangeApi.respondMission).not.toHaveBeenCalled()
  })

  it('erreur serveur : message affiché et mission relue', async () => {
    vi.mocked(dateChangeApi.respondMission).mockRejectedValue({
      isAxiosError: true,
      response: { status: 422, data: { error: { message: 'Les données fournies ne sont pas valides', details: { date: ['Le 20 novembre est déjà pris dans votre agenda.'] } } } },
    })
    const wrapper = setup(makeDateChange())
    await flushPromises()

    await wrapper.get('[data-testid="date-change-confirm"]').trigger('click')
    await flushPromises()

    expect(mockToast.error).toHaveBeenCalledWith('Le 20 novembre est déjà pris dans votre agenda.')
    expect(mockFetchMission).toHaveBeenCalled()
  })
})
