import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import ProducerMissionCandidaturesPage from '../ProducerMissionCandidaturesPage.vue'
import { missionApi } from '@/features/mission/services/missionApi'
import type { Mission } from '@/features/mission/types'

const toastErrorSpy = vi.fn()

// Mock router composables so the page can read `route.params.id`.
vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { id: 'mission-uuid-under-test' } }),
  useRouter: () => ({ push: vi.fn() }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    error: toastErrorSpy,
    success: vi.fn(),
    warning: vi.fn(),
    info: vi.fn(),
    clear: vi.fn(),
    toast: {},
  }),
}))

// Stub the candidatures section — we are only testing the page-level banner
// and polling logic here, not the nested candidatures UI.
vi.mock('@/features/candidature/components', () => ({
  ProducerCandidaturesSection: defineComponent({
    name: 'ProducerCandidaturesSectionStub',
    props: {
      missionId: { type: String, required: true },
      missionBudget: { type: Number, required: false, default: 0 },
      missionStatus: { type: String, required: false, default: '' },
      nombreFacesVoulu: { type: Number, required: false, default: 0 },
      allowRetrySelection: { type: Boolean, required: false, default: false },
    },
    emits: ['selection-confirmed', 'selection-failed'],
    setup(props, { slots }) {
      return () => h('div', {
        'data-testid': 'candidatures-section-stub',
        'data-retry-selection-enabled': String(props.allowRetrySelection),
      }, slots.default?.())
    },
  }),
}))

// Spies we can assert on — the composable itself stays real so we only mock
// the API boundary, not Vue reactivity.
const startPollingSpy = vi.fn()
const stopPollingSpy = vi.fn()
vi.mock('@/features/mission/composables', () => ({
  useMissionPayment: () => ({
    startPolling: startPollingSpy,
    stopPolling: stopPollingSpy,
  }),
}))

vi.mock('@/features/mission/services/missionApi', () => ({
  missionApi: {
    getMission: vi.fn(),
    getPaymentStatus: vi.fn(),
  },
}))

function makePendingPaymentMission(overrides: Partial<Mission> = {}): Mission {
  return {
    id: 1,
    uuid: 'mission-uuid-under-test',
    titre: 'Tournage spot TV',
    description: 'Une mission de test',
    date_tournage: '2026-05-01',
    profil_recherche: 'Face polyvalent',
    budget: 90000,
    date_limite_candidature: '2026-04-25',
    nombre_faces_voulu: 2,
    type_mission: 'publicite',
    type_mission_label: 'Publicité',
    genre_voulu: 'tous',
    genre_voulu_label: 'Homme et Femme',
    lieu: 'Cotonou',
    duree: '1 jour',
    status: 'pending_payment',
    status_label: 'En attente de paiement',
    is_accepting_candidatures: false,
    created_at: '2026-04-10T00:00:00Z',
    updated_at: '2026-04-12T00:00:00Z',
    ...overrides,
  } as Mission
}

describe('ProducerMissionCandidaturesPage — FIX-19.3 false-pending guard', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.spyOn(console, 'error').mockImplementation(() => {})
    toastErrorSpy.mockReset()
  })

  afterEach(() => {
    vi.clearAllMocks()
    vi.restoreAllMocks()
  })

  it('does NOT start polling and shows the init-failed banner when pending_payment is not trackable (AC #1, #2, #4)', async () => {
    vi.mocked(missionApi.getMission).mockResolvedValueOnce({
      data: makePendingPaymentMission(),
      message: 'ok',
    })
    vi.mocked(missionApi.getPaymentStatus).mockResolvedValueOnce({
      data: {
        has_payment: false,
        is_trackable: false,
        status: undefined,
        mission_status: 'pending_payment',
      },
    })

    const wrapper = mount(ProducerMissionCandidaturesPage)

    // Let fetchMission + evaluatePendingPaymentState resolve.
    await flushPromises()
    await flushPromises()

    expect(missionApi.getPaymentStatus).toHaveBeenCalledWith('mission-uuid-under-test')
    expect(startPollingSpy).not.toHaveBeenCalled()
    // Do NOT render the pending banner.
    expect(wrapper.find('[data-testid="mission-payment-pending-banner"]').exists()).toBe(false)
    // DO render the dedicated init-failed banner with a clear retry invitation.
    const failedBanner = wrapper.find('[data-testid="mission-payment-init-failed-banner"]')
    expect(failedBanner.exists()).toBe(true)
    expect(failedBanner.text()).toMatch(/initialisation du paiement a échoué/i)
    expect(failedBanner.text()).toMatch(/réessayer/i)
    // A11y: the init-failed banner must be announced by screen readers.
    expect(failedBanner.attributes('role')).toBe('alert')
    expect(failedBanner.attributes('aria-live')).toBe('assertive')
    expect(wrapper.get('[data-testid="candidatures-section-stub"]').attributes('data-retry-selection-enabled')).toBe('true')
  })

  it('renders the init-failed banner when a non-trackable payment is in a failed state (review pass-2)', async () => {
    vi.mocked(missionApi.getMission).mockResolvedValueOnce({
      data: makePendingPaymentMission(),
      message: 'ok',
    })
    vi.mocked(missionApi.getPaymentStatus).mockResolvedValueOnce({
      data: {
        has_payment: true,
        is_trackable: false,
        payment_id: 42,
        status: 'failed',
        mission_status: 'pending_payment',
      },
    })

    const wrapper = mount(ProducerMissionCandidaturesPage)

    await flushPromises()
    await flushPromises()

    // Regression lock: before the pass-2 fix, a non-trackable pending_payment
    // mission with a failed payment fell through to no banner at all.
    expect(startPollingSpy).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="mission-payment-pending-banner"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="mission-payment-init-failed-banner"]').exists()).toBe(true)
  })

  it('starts polling and shows the pending banner when pending_payment is trackable', async () => {
    vi.mocked(missionApi.getMission).mockResolvedValueOnce({
      data: makePendingPaymentMission(),
      message: 'ok',
    })
    vi.mocked(missionApi.getPaymentStatus).mockResolvedValueOnce({
      data: {
        has_payment: true,
        is_trackable: true,
        payment_id: 42,
        status: 'pending',
        paid_at: null,
        montant_total: 198000,
        mission_status: 'pending_payment',
      },
    })

    const wrapper = mount(ProducerMissionCandidaturesPage)

    await flushPromises()
    await flushPromises()

    expect(startPollingSpy).toHaveBeenCalledTimes(1)
    expect(startPollingSpy).toHaveBeenCalledWith(
      'mission-uuid-under-test',
      expect.any(Function),
    )
    expect(wrapper.find('[data-testid="mission-payment-pending-banner"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="mission-payment-init-failed-banner"]').exists()).toBe(false)
    expect(wrapper.get('[data-testid="candidatures-section-stub"]').attributes('data-retry-selection-enabled')).toBe('false')
  })

  it('refreshes the mission and shows the success banner when payment-status reports the payment as paid', async () => {
    vi.mocked(missionApi.getMission)
      .mockResolvedValueOnce({
        data: makePendingPaymentMission(),
        message: 'ok',
      })
      .mockResolvedValueOnce({
        data: makePendingPaymentMission({ status: 'closed', status_label: 'Clôturée' }),
        message: 'ok',
      })
    vi.mocked(missionApi.getPaymentStatus).mockResolvedValueOnce({
      data: {
        has_payment: true,
        is_trackable: false,
        payment_id: 42,
        status: 'paid',
        paid_at: '2026-04-13T12:00:00Z',
        montant_total: 198000,
        mission_status: 'closed',
      },
    })

    const wrapper = mount(ProducerMissionCandidaturesPage)

    await flushPromises()
    await flushPromises()
    await flushPromises()

    expect(missionApi.getMission).toHaveBeenCalledTimes(2)
    expect(startPollingSpy).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="mission-payment-success-banner"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="mission-payment-init-failed-banner"]').exists()).toBe(false)
  })

  it('shows a generic status-unavailable banner when payment-status cannot be fetched', async () => {
    vi.mocked(missionApi.getMission).mockResolvedValueOnce({
      data: makePendingPaymentMission(),
      message: 'ok',
    })
    vi.mocked(missionApi.getPaymentStatus).mockRejectedValueOnce(new Error('timeout'))

    const wrapper = mount(ProducerMissionCandidaturesPage)

    await flushPromises()
    await flushPromises()

    expect(startPollingSpy).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="mission-payment-init-failed-banner"]').exists()).toBe(false)
    const unavailableBanner = wrapper.find('[data-testid="mission-payment-status-unavailable-banner"]')
    expect(unavailableBanner.exists()).toBe(true)
    expect(unavailableBanner.text()).toMatch(/Impossible de vérifier le statut du paiement/i)
  })

  it('ignores late payment-status responses after the page unmounts', async () => {
    let resolvePaymentStatus: ((value: {
      data: {
        has_payment: boolean
        is_trackable: boolean
        status: string
        mission_status: string
      }
    }) => void) | null = null

    vi.mocked(missionApi.getMission).mockResolvedValueOnce({
      data: makePendingPaymentMission(),
      message: 'ok',
    })
    vi.mocked(missionApi.getPaymentStatus).mockReturnValueOnce(
      new Promise((resolve) => {
        resolvePaymentStatus = resolve
      }),
    )

    const wrapper = mount(ProducerMissionCandidaturesPage)

    await flushPromises()
    wrapper.unmount()

    resolvePaymentStatus?.({
      data: {
        has_payment: true,
        is_trackable: true,
        status: 'pending',
        mission_status: 'pending_payment',
      },
    })

    await flushPromises()

    expect(startPollingSpy).not.toHaveBeenCalled()
  })

  it('uses the global toast system while the page refreshes mission state after payment failure', async () => {
    vi.mocked(missionApi.getMission)
      .mockResolvedValueOnce({
        data: makePendingPaymentMission({ status: 'published', status_label: 'Publiée' }),
        message: 'ok',
      })
      .mockResolvedValueOnce({
        data: makePendingPaymentMission(),
        message: 'ok',
      })
    vi.mocked(missionApi.getPaymentStatus).mockResolvedValueOnce({
      data: {
        has_payment: false,
        is_trackable: false,
        status: undefined,
        mission_status: 'pending_payment',
      },
    })

    const wrapper = mount(ProducerMissionCandidaturesPage)

    await flushPromises()

    wrapper.getComponent({ name: 'ProducerCandidaturesSectionStub' })
      .vm
      .$emit('selection-failed', 'Le paiement de la mission n\'a pas pu être initialisé. Veuillez réessayer.')

    await flushPromises()
    await flushPromises()

    expect(toastErrorSpy).toHaveBeenCalledWith(
      'Le paiement de la mission n\'a pas pu être initialisé. Veuillez réessayer.',
    )
  })

  it('does not fetch the payment-status endpoint when the mission is not pending_payment', async () => {
    vi.mocked(missionApi.getMission).mockResolvedValueOnce({
      data: makePendingPaymentMission({ status: 'published', status_label: 'Publiée' }),
      message: 'ok',
    })

    mount(ProducerMissionCandidaturesPage)

    await flushPromises()

    expect(missionApi.getPaymentStatus).not.toHaveBeenCalled()
    expect(startPollingSpy).not.toHaveBeenCalled()
  })
})
