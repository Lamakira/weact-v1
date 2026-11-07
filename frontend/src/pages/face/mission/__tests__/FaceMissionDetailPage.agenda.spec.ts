import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { ref } from 'vue'
import FaceMissionDetailPage from '../FaceMissionDetailPage.vue'
import { agendaApi } from '@/features/agenda/services/agendaApi'
import type { Mission, MissionCandidature } from '@/features/mission/types'

/**
 * Agenda : encart ambre non bloquant quand le jour de tournage est marqué indisponible par la Face.
 */

const mockToast = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() }))
vi.mock('@/composables/useToast', () => ({ useToast: () => mockToast }))
vi.mock('@/features/auth/services/authApi', () => ({ authApi: { resendVerificationEmail: vi.fn() } }))

const mockRouter = vi.hoisted(() => ({ push: vi.fn(), back: vi.fn(), replace: vi.fn() }))
vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { id: 'mission-uuid-1' }, fullPath: '/face/missions/mission-uuid-1' }),
  useRouter: () => mockRouter,
}))

vi.mock('@/features/agenda/services/agendaApi', () => ({
  agendaApi: { getAgenda: vi.fn(), updateUnavailableDays: vi.fn(), getFaceAvailability: vi.fn() },
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


vi.mock('@/features/notification/components/PushMomentCard.vue', () => ({
  default: { template: '<div />' },
}))

function setup(dateTournage: string | null = '2026-11-20') {
  mockMission.value = {
    id: 'mission-uuid-1',
    titre: 'Spot boutique Haie Vive',
    description: 'Brief',
    date_tournage: dateTournage,
    profil_recherche: 'Face',
    budget: 25000,
    genre_voulu_label: 'Tous',
    lieu: 'Cotonou',
    duree: '1 jour',
    type_mission: 'publicite',
    nom_produit: null,
    montant_remuneration: null,
    date_change: null,
    producer: { id: 'p1', slug: 'agence', display_name: 'Agence', average_rating: null, ratings_count: 0 },
  } as unknown as Mission
  mockCandidature.value = null

  return mount(FaceMissionDetailPage, {
    global: {
      plugins: [createTestingPinia()],
      stubs: {
        teleport: true,
        ApplyToMissionModal: true,
        RatingDisplay: true,
        RouterLink: { template: '<a><slot /></a>', props: ['to'] },
      },
    },
  })
}

function agendaResponse(unavailable: string[]) {
  return { data: { from: '2026-11-20', to: '2026-11-20', today: '2026-11-08', unavailable_days: unavailable, events: [] } }
}

describe('FaceMissionDetailPage — jour de tournage indisponible dans l\'agenda', () => {
  beforeEach(() => {
    mockRouter.push.mockReset()
    vi.mocked(agendaApi.getAgenda).mockReset().mockResolvedValue(agendaResponse([]))
  })

  it('affiche l\'encart ambre avec un lien vers l\'agenda quand le jour de tournage est bloqué', async () => {
    vi.mocked(agendaApi.getAgenda).mockResolvedValue(agendaResponse(['2026-11-20']))
    const wrapper = setup()
    await flushPromises()

    expect(agendaApi.getAgenda).toHaveBeenCalledWith('2026-11-20', '2026-11-20')
    const notice = wrapper.get('[data-testid="mission-day-blocked-notice"]')
    expect(notice.text()).toContain('Vous avez marqué ce jour comme indisponible dans votre agenda.')
    expect(notice.classes()).toContain('bg-amber-50')

    await wrapper.get('[data-testid="mission-day-blocked-link"]').trigger('click')
    expect(mockRouter.push).toHaveBeenCalledWith({ name: 'face-agenda' })
  })

  it('n\'affiche rien quand le jour est libre, et ne touche pas aux boutons de candidature', async () => {
    const wrapper = setup()
    await flushPromises()

    expect(wrapper.find('[data-testid="mission-day-blocked-notice"]').exists()).toBe(false)
  })

  it('n\'interroge pas l\'agenda sans date de tournage', async () => {
    const wrapper = setup(null)
    await flushPromises()

    expect(agendaApi.getAgenda).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="mission-day-blocked-notice"]').exists()).toBe(false)
  })

  it('reste silencieux si l\'agenda ne répond pas', async () => {
    vi.mocked(agendaApi.getAgenda).mockRejectedValue(new Error('boom'))
    const wrapper = setup()
    await flushPromises()

    expect(wrapper.find('[data-testid="mission-day-blocked-notice"]').exists()).toBe(false)
  })
})
