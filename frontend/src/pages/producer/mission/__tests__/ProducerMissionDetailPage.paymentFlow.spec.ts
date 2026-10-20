import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import ProducerMissionDetailPage from '../ProducerMissionDetailPage.vue'
import { missionApi } from '@/features/mission/services/missionApi'
import { candidatureApi } from '@/features/candidature/services/candidatureApi'
import { createPinia, setActivePinia } from 'pinia'
import type { Mission, MissionSummary } from '@/features/mission/types'

const toastErrorSpy = vi.fn()
const toastSuccessSpy = vi.hoisted(() => vi.fn())
const redirectSpy = vi.hoisted(() => vi.fn())
const openCandidaturePaymentSpy = vi.hoisted(() => vi.fn())

// Mock router composables so the page can read `route.params.id`.
const routeState = vi.hoisted(() => ({
  query: {} as Record<string, unknown>,
  replace: vi.fn().mockResolvedValue(undefined),
}))

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { id: 'mission-uuid-under-test' }, query: routeState.query }),
  useRouter: () => ({ push: vi.fn(), replace: routeState.replace }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    error: toastErrorSpy,
    success: toastSuccessSpy,
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
    setup(props, { slots, expose }) {
      expose({ openCandidaturePayment: openCandidaturePaymentSpy })
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
vi.mock('@/features/mission/composables/useMissionPayment', () => ({
  useMissionPayment: () => ({
    startPolling: startPollingSpy,
    stopPolling: stopPollingSpy,
  }),
}))

vi.mock('@/features/mission/services/missionApi', () => ({
  missionApi: {
    getMission: vi.fn(),
    getPaymentStatus: vi.fn(),
    getCommissionStatus: vi.fn(),
    confirmSelection: vi.fn(),
    cancelSelection: vi.fn(),
  },
}))

vi.mock('@/lib/redirectToCheckout', () => ({ redirectToCheckout: redirectSpy }))

vi.mock('@/features/candidature/services/candidatureApi', () => ({
  candidatureApi: { getCandidaturePaymentStatus: vi.fn() },
}))

/** Résumé C1 minimal cohérent avec le statut (la page en dérive étapes et action principale). */
function makeSummary(status: Mission['status']): MissionSummary {
  const step = (
    {
      draft: 'draft',
      published: 'recruiting',
      pending_payment: 'payment',
      closed: 'confirmations',
      pending_attendance_validation: 'attendance',
      completed: 'completed',
    } as const
  )[status]
  return {
    step,
    candidatures: { pending: 0, accepted: 0, confirmed: 0, in_progress: 0, completed: 0, rejected: 0, cancelled: 0, expired: 0 },
    selection: { faces_wanted: 2, faces_selected: 2, faces_confirmed: 0, freed_places: 0 },
    payment: { status: null, expires_at: null, paid_at: null, montant_total_producteur: null },
    attendance: null,
    ugc_deliverables: null,
    completion_blocker: null,
    can_edit: false,
  }
}

function makePendingPaymentMission(overrides: Partial<Mission> = {}): Mission {
  const mission = makeBaseMission(overrides)
  return { ...mission, summary: overrides.summary ?? makeSummary(mission.status) }
}

function makeBaseMission(overrides: Partial<Mission> = {}): Mission {
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

beforeEach(() => {
  setActivePinia(createPinia())
})

describe('ProducerMissionDetailPage — FIX-19.3 false-pending guard', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    routeState.query = {}
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

    const wrapper = mount(ProducerMissionDetailPage)

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

    const wrapper = mount(ProducerMissionDetailPage)

    await flushPromises()
    await flushPromises()

    // Regression lock: before the pass-2 fix, a non-trackable pending_payment
    // mission with a failed payment fell through to no banner at all.
    expect(startPollingSpy).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="mission-payment-pending-banner"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="mission-payment-init-failed-banner"]').exists()).toBe(true)
  })

  it('lot B1 : shows the payment deadline and « Modifier la sélection » on a pending_payment mission', async () => {
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
        expires_at: '2026-10-17T14:30:00Z',
        mission_status: 'pending_payment',
      },
    })

    const wrapper = mount(ProducerMissionDetailPage)
    await flushPromises()
    await flushPromises()

    const deadline = wrapper.get('[data-testid="mission-payment-deadline"]')
    expect(deadline.text()).toMatch(/^À payer avant le .+ à \d{2}h\d{2}\. Sans paiement, la sélection sera annulée\.$/)
    expect(wrapper.find('[data-testid="mission-payment-cancel-selection-btn"]').exists()).toBe(true)
  })

  it('lot B1 : hides the deadline and the cancel button when the mission is not pending_payment', async () => {
    vi.mocked(missionApi.getMission).mockResolvedValueOnce({
      data: makePendingPaymentMission({ status: 'published' }),
      message: 'ok',
    })

    const wrapper = mount(ProducerMissionDetailPage)
    await flushPromises()
    await flushPromises()

    expect(wrapper.find('[data-testid="mission-payment-deadline"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="mission-payment-cancel-selection-btn"]').exists()).toBe(false)
  })

  it('lot B1 : « Modifier la sélection » confirms, cancels server-side, toasts the message and refreshes', async () => {
    vi.mocked(missionApi.getMission)
      .mockResolvedValueOnce({ data: makePendingPaymentMission(), message: 'ok' })
      .mockResolvedValueOnce({ data: makePendingPaymentMission({ status: 'published' }), message: 'ok' })
    vi.mocked(missionApi.getPaymentStatus).mockResolvedValueOnce({
      data: { has_payment: true, is_trackable: true, payment_id: 42, status: 'pending', mission_status: 'pending_payment' },
    })
    vi.mocked(missionApi.cancelSelection).mockResolvedValueOnce({
      data: { mission_status: 'published' },
      message: 'Sélection annulée. Vous pouvez refaire votre sélection.',
    })

    const wrapper = mount(ProducerMissionDetailPage, { attachTo: document.body })
    await flushPromises()
    await flushPromises()

    await wrapper.get('[data-testid="mission-payment-cancel-selection-btn"]').trigger('click')
    expect(missionApi.cancelSelection).not.toHaveBeenCalled()

    const confirm = Array.from(document.body.querySelectorAll('button')).find((b) => b.textContent?.trim() === 'Annuler le paiement')
    expect(confirm).toBeDefined()
    confirm!.click()
    await flushPromises()
    await flushPromises()

    expect(missionApi.cancelSelection).toHaveBeenCalledWith('mission-uuid-under-test')
    expect(toastSuccessSpy).toHaveBeenCalledWith('Sélection annulée. Vous pouvez refaire votre sélection.')
    expect(missionApi.getMission).toHaveBeenCalledTimes(2)
    expect(wrapper.find('[data-testid="mission-payment-cancel-selection-btn"]').exists()).toBe(false)
    wrapper.unmount()
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

    const wrapper = mount(ProducerMissionDetailPage)

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
        data: makePendingPaymentMission({
          status: 'closed',
          status_label: 'Clôturée',
          display_status: 'paid_preparing',
          display_status_label: 'Payée, en préparation',
        }),
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

    const wrapper = mount(ProducerMissionDetailPage)

    await flushPromises()
    await flushPromises()
    await flushPromises()

    expect(missionApi.getMission).toHaveBeenCalledTimes(2)
    expect(startPollingSpy).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="mission-payment-success-banner"]').exists()).toBe(true)
    expect(wrapper.get('[data-testid="mission-payment-success-banner"]').text()).toBe(
      'Paiement confirmé : les Faces sélectionnées doivent maintenant confirmer leur participation.',
    )
    // Lot C3 : la pastille d'en-tête affiche le libellé véridique, pas « Clôturée »
    expect(wrapper.get('[data-testid="mission-status-pill"]').text()).toBe('Payée, en préparation')
    expect(wrapper.find('[data-testid="mission-payment-init-failed-banner"]').exists()).toBe(false)
  })

  it('falls back to status_label in the header pill when display_status_label is absent', async () => {
    vi.mocked(missionApi.getMission).mockResolvedValueOnce({
      data: makePendingPaymentMission({ status: 'pending_attendance_validation', status_label: 'En attente de validation des présences' }),
      message: 'ok',
    })

    const wrapper = mount(ProducerMissionDetailPage)
    await flushPromises()
    await flushPromises()

    const pill = wrapper.get('[data-testid="mission-status-pill"]')
    expect(pill.text()).toBe('En attente de validation des présences')
    expect(pill.classes()).toContain('bg-violet-100')
  })

  it('shows a generic status-unavailable banner when payment-status cannot be fetched', async () => {
    vi.mocked(missionApi.getMission).mockResolvedValueOnce({
      data: makePendingPaymentMission(),
      message: 'ok',
    })
    vi.mocked(missionApi.getPaymentStatus).mockRejectedValueOnce(new Error('timeout'))

    const wrapper = mount(ProducerMissionDetailPage)

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

    const wrapper = mount(ProducerMissionDetailPage)

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

    const wrapper = mount(ProducerMissionDetailPage)

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

  it('clears the init-failed banner after a selection-failed event refreshes the mission back to published (FIX-19.5 AC #4)', async () => {
    // FIX-19.5 regression guard: when a failed confirmAndPay attempt's
    // compensation path restores the mission to `published`, the page must
    // transition out of the init-failed banner on the next refresh and
    // re-enable the normal selection flow — no infinite "stuck" banner.
    vi.mocked(missionApi.getMission)
      .mockResolvedValueOnce({
        data: makePendingPaymentMission(),
        message: 'ok',
      })
      .mockResolvedValueOnce({
        data: makePendingPaymentMission({ status: 'published', status_label: 'Publiée' }),
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

    const wrapper = mount(ProducerMissionDetailPage)

    await flushPromises()
    await flushPromises()

    // Baseline: the init-failed banner is showing and retry is enabled.
    expect(wrapper.find('[data-testid="mission-payment-init-failed-banner"]').exists()).toBe(true)
    expect(wrapper.get('[data-testid="candidatures-section-stub"]').attributes('data-retry-selection-enabled')).toBe('true')

    // Simulate the retry attempt failing → compensation restored the mission
    // to `published`. The section emits `selection-failed`, which triggers a
    // mission re-fetch.
    wrapper.getComponent({ name: 'ProducerCandidaturesSectionStub' })
      .vm
      .$emit('selection-failed', 'Le paiement de la mission n\'a pas pu être initialisé. Veuillez réessayer.')

    await flushPromises()
    await flushPromises()

    // After refresh: mission is `published` again, so the init-failed banner
    // MUST be gone, polling MUST NOT be running, and the section should no
    // longer be in retry-selection mode.
    expect(missionApi.getMission).toHaveBeenCalledTimes(2)
    // The refreshed mission is `published`, so the page must NOT re-evaluate
    // the payment-status endpoint a second time — exactly one call total.
    expect(missionApi.getPaymentStatus).toHaveBeenCalledTimes(1)
    expect(wrapper.find('[data-testid="mission-payment-init-failed-banner"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="mission-payment-pending-banner"]').exists()).toBe(false)
    expect(startPollingSpy).not.toHaveBeenCalled()
    // The transition out of `pending_payment` must explicitly stop polling so
    // any prior poll loop is torn down on compensation success.
    expect(stopPollingSpy).toHaveBeenCalled()
    expect(wrapper.get('[data-testid="candidatures-section-stub"]').attributes('data-retry-selection-enabled')).toBe('false')
  })

  it('does not fetch the payment-status endpoint when the mission is not pending_payment', async () => {
    vi.mocked(missionApi.getMission).mockResolvedValueOnce({
      data: makePendingPaymentMission({ status: 'published', status_label: 'Publiée' }),
      message: 'ok',
    })

    mount(ProducerMissionDetailPage)

    await flushPromises()

    expect(missionApi.getPaymentStatus).not.toHaveBeenCalled()
    expect(startPollingSpy).not.toHaveBeenCalled()
  })

  describe('return from the same-tab FedaPay checkout', () => {
    it('candidature_escrow polls the candidature payment-status and NOT the cash mission one (no false « initialisation échouée » banner)', async () => {
      routeState.query = { payment_return: 'candidature_escrow', candidature: 'cand-uuid-1' }
      vi.mocked(missionApi.getMission).mockResolvedValue({
        data: makePendingPaymentMission({ status: 'published', type_mission: 'ugc' } as Partial<Mission>),
        message: 'ok',
      })
      vi.mocked(candidatureApi.getCandidaturePaymentStatus).mockResolvedValue({
        data: { candidature_status: 'pending', payment_status: 'pending', is_trackable: true },
      })

      const wrapper = mount(ProducerMissionDetailPage)
      await flushPromises()
      await flushPromises()

      expect(candidatureApi.getCandidaturePaymentStatus).toHaveBeenCalledWith('cand-uuid-1')
      expect(missionApi.getPaymentStatus).not.toHaveBeenCalled()
      expect(wrapper.find('[data-testid="payment-return-verifying"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="mission-payment-init-failed-banner"]').exists()).toBe(false)
      wrapper.unmount()
    })

    it('mission_selection polls the cash payment-status; confirmed shows the success banner and refetches the mission', async () => {
      routeState.query = { payment_return: 'mission_selection' }
      vi.mocked(missionApi.getMission).mockResolvedValue({
        data: makePendingPaymentMission(),
        message: 'ok',
      })
      vi.mocked(missionApi.getPaymentStatus).mockResolvedValue({
        data: { has_payment: true, is_trackable: true, status: 'paid', mission_status: 'closed' },
      })

      const wrapper = mount(ProducerMissionDetailPage)
      await flushPromises()
      await flushPromises()

      expect(missionApi.getPaymentStatus).toHaveBeenCalledWith('mission-uuid-under-test')
      expect(wrapper.find('[data-testid="mission-payment-success-banner"]').exists()).toBe(true)
      expect(missionApi.getMission).toHaveBeenCalledTimes(2)
      wrapper.unmount()
    })

    it('refetches the mission on a bfcache restore (Back from FedaPay after a cash selection) but not on a normal pageshow', async () => {
      vi.mocked(missionApi.getMission).mockResolvedValue({
        data: makePendingPaymentMission({ status: 'published', status_label: 'Publiée' }),
        message: 'ok',
      })
      const wrapper = mount(ProducerMissionDetailPage)
      await flushPromises()
      // (earlier tests of this file leave mounted pages listening: compare deltas)
      const afterMount = vi.mocked(missionApi.getMission).mock.calls.length

      const normal = new Event('pageshow')
      Object.defineProperty(normal, 'persisted', { value: false })
      window.dispatchEvent(normal)
      await flushPromises()
      expect(missionApi.getMission).toHaveBeenCalledTimes(afterMount)

      const restored = new Event('pageshow')
      Object.defineProperty(restored, 'persisted', { value: true })
      window.dispatchEvent(restored)
      await flushPromises()
      expect(vi.mocked(missionApi.getMission).mock.calls.length).toBeGreaterThan(afterMount)
      wrapper.unmount()
    })

    it('without payment_return the return flow stays idle', async () => {
      vi.mocked(missionApi.getMission).mockResolvedValue({
        data: makePendingPaymentMission({ status: 'published', status_label: 'Publiée' }),
        message: 'ok',
      })

      const wrapper = mount(ProducerMissionDetailPage)
      await flushPromises()

      expect(candidatureApi.getCandidaturePaymentStatus).not.toHaveBeenCalled()
      expect(wrapper.find('[data-testid="payment-return-verifying"]').exists()).toBe(false)
      wrapper.unmount()
    })

    it('candidature_escrow failed: « Réessayer le paiement » reopens the règlement overlay through the section exposure', async () => {
      routeState.query = { payment_return: 'candidature_escrow', candidature: 'cand-uuid-1' }
      vi.mocked(missionApi.getMission).mockResolvedValue({
        data: makePendingPaymentMission({ status: 'published', type_mission: 'ugc' } as Partial<Mission>),
        message: 'ok',
      })
      vi.mocked(candidatureApi.getCandidaturePaymentStatus).mockResolvedValue({
        data: { candidature_status: 'pending', payment_status: 'failed', is_trackable: false },
      })
      const wrapper = mount(ProducerMissionDetailPage)
      await flushPromises()
      await flushPromises()

      await wrapper.find('[data-testid="payment-return-retry"]').trigger('click')

      expect(openCandidaturePaymentSpy).toHaveBeenCalledWith('cand-uuid-1')
      wrapper.unmount()
    })

    it('mission_selection failed: one failure banner (no orange spinner), the normal evaluation is back so the producer can reconfirm', async () => {
      routeState.query = { payment_return: 'mission_selection', fedapay_status: 'canceled' }
      vi.mocked(missionApi.getMission).mockResolvedValue({
        data: makePendingPaymentMission(),
        message: 'ok',
      })
      // 1st call = the return check (canceled → released server-side → not trackable);
      // 2nd call = the page's own evaluation re-enabled after the flow.
      vi.mocked(missionApi.getPaymentStatus).mockResolvedValue({
        data: { has_payment: true, is_trackable: false, status: 'pending', mission_status: 'pending_payment' },
      })

      const wrapper = mount(ProducerMissionDetailPage)
      await flushPromises()
      await flushPromises()
      await flushPromises()

      expect(wrapper.find('[data-testid="payment-return-failed"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="mission-payment-pending-banner"]').exists()).toBe(false)
      expect(wrapper.find('[data-testid="mission-payment-init-failed-banner"]').exists()).toBe(false)
      expect(vi.mocked(missionApi.getPaymentStatus).mock.calls.length).toBeGreaterThanOrEqual(2)
      // Pending payment row without a live transaction → relaunch with the SAME selection
      // (re-picking would be rejected: confirm-selection prohibits ids while a Pending payment exists).
      expect(wrapper.find('[data-testid="mission-payment-resume-btn"]').exists()).toBe(true)
      expect(wrapper.get('[data-testid="candidatures-section-stub"]').attributes('data-retry-selection-enabled')).toBe('false')
      wrapper.unmount()
    })

    it('mission_selection timeout: the page resumes its normal pending polling so a late webhook still updates it', async () => {
      vi.useFakeTimers()
      try {
        routeState.query = { payment_return: 'mission_selection' }
        vi.mocked(missionApi.getMission).mockResolvedValue({
          data: makePendingPaymentMission(),
          message: 'ok',
        })
        vi.mocked(missionApi.getPaymentStatus).mockResolvedValue({
          data: { has_payment: true, is_trackable: true, status: 'pending', mission_status: 'pending_payment' },
        })

        const wrapper = mount(ProducerMissionDetailPage)
        await flushPromises()
        expect(startPollingSpy).not.toHaveBeenCalled()

        await vi.advanceTimersByTimeAsync(120000)
        await flushPromises()

        expect(wrapper.find('[data-testid="payment-return-timeout"]').exists()).toBe(true)
        expect(startPollingSpy).toHaveBeenCalled()
        wrapper.unmount()
      } finally {
        vi.useRealTimers()
      }
    })

    it('bfcache restore on a REAL pending_payment mission re-evaluates the payment state (init-failed banner appears, selection can be reconfirmed)', async () => {
      vi.mocked(missionApi.getMission).mockResolvedValue({
        data: makePendingPaymentMission(),
        message: 'ok',
      })
      vi.mocked(missionApi.getPaymentStatus).mockResolvedValue({
        data: { has_payment: true, is_trackable: true, status: 'pending', mission_status: 'pending_payment' },
      })
      const wrapper = mount(ProducerMissionDetailPage)
      await flushPromises()
      expect(wrapper.find('[data-testid="mission-payment-pending-banner"]').exists()).toBe(true)

      // While on FedaPay the payment was canceled: the server now reports it non-trackable.
      vi.mocked(missionApi.getPaymentStatus).mockResolvedValue({
        data: { has_payment: true, is_trackable: false, status: 'pending', mission_status: 'pending_payment' },
      })
      const restored = new Event('pageshow')
      Object.defineProperty(restored, 'persisted', { value: true })
      window.dispatchEvent(restored)
      await flushPromises()
      await flushPromises()

      expect(wrapper.find('[data-testid="mission-payment-init-failed-banner"]').exists()).toBe(true)
      // Pending payment row without a live transaction → relaunch with the SAME selection
      // (re-picking would be rejected: confirm-selection prohibits ids while a Pending payment exists).
      expect(wrapper.find('[data-testid="mission-payment-resume-btn"]').exists()).toBe(true)
      expect(wrapper.get('[data-testid="candidatures-section-stub"]').attributes('data-retry-selection-enabled')).toBe('false')
      wrapper.unmount()
    })

    it('released state: « Relancer le paiement » posts NO candidature ids and redirects to the fresh checkout', async () => {
      vi.mocked(missionApi.getMission).mockResolvedValue({ data: makePendingPaymentMission(), message: 'ok' })
      vi.mocked(missionApi.getPaymentStatus).mockResolvedValue({
        data: { has_payment: true, is_trackable: false, status: 'pending', mission_status: 'pending_payment' },
      })
      vi.mocked(missionApi.confirmSelection).mockResolvedValue({
        data: { checkout_url: 'https://checkout.fedapay.test/fresh' },
        message: 'ok',
      } as never)
      const wrapper = mount(ProducerMissionDetailPage)
      await flushPromises()

      expect(wrapper.text()).toContain('Relancer le paiement')
      expect(wrapper.text()).not.toContain('reconfirmant votre sélection')
      await wrapper.get('[data-testid="mission-payment-resume-btn"]').trigger('click')
      await flushPromises()

      expect(missionApi.confirmSelection).toHaveBeenCalledWith('mission-uuid-under-test', [])
      expect(redirectSpy).toHaveBeenCalledWith('https://checkout.fedapay.test/fresh')
      wrapper.unmount()
    })

    it('resume failure: error toast, no redirect, the page state is refreshed', async () => {
      vi.mocked(missionApi.getMission).mockResolvedValue({ data: makePendingPaymentMission(), message: 'ok' })
      vi.mocked(missionApi.getPaymentStatus).mockResolvedValue({
        data: { has_payment: true, is_trackable: false, status: 'pending', mission_status: 'pending_payment' },
      })
      vi.mocked(missionApi.confirmSelection).mockRejectedValue(new Error('422'))
      const wrapper = mount(ProducerMissionDetailPage)
      await flushPromises()
      const fetchesBefore = vi.mocked(missionApi.getMission).mock.calls.length

      await wrapper.get('[data-testid="mission-payment-resume-btn"]').trigger('click')
      await flushPromises()

      expect(toastErrorSpy).toHaveBeenCalledWith('Le paiement n\'a pas pu être relancé. Veuillez réessayer.')
      expect(redirectSpy).not.toHaveBeenCalled()
      expect(vi.mocked(missionApi.getMission).mock.calls.length).toBeGreaterThan(fetchesBefore)
      wrapper.unmount()
    })

    it('no payment row at all: no resume button, the re-pick selection mode stays available', async () => {
      vi.mocked(missionApi.getMission).mockResolvedValue({ data: makePendingPaymentMission(), message: 'ok' })
      vi.mocked(missionApi.getPaymentStatus).mockResolvedValue({
        data: { has_payment: false, is_trackable: false, status: undefined, mission_status: 'pending_payment' },
      })
      const wrapper = mount(ProducerMissionDetailPage)
      await flushPromises()

      expect(wrapper.find('[data-testid="mission-payment-resume-btn"]').exists()).toBe(false)
      expect(wrapper.get('[data-testid="candidatures-section-stub"]').attributes('data-retry-selection-enabled')).toBe('true')
      wrapper.unmount()
    })

    it('a live trackable payment offers « Reprendre le paiement » right away and keeps the confirmation wording', async () => {
      vi.mocked(missionApi.getMission).mockResolvedValue({ data: makePendingPaymentMission(), message: 'ok' })
      vi.mocked(missionApi.getPaymentStatus).mockResolvedValue({
        data: { has_payment: true, is_trackable: true, status: 'pending', mission_status: 'pending_payment' },
      })
      const wrapper = mount(ProducerMissionDetailPage)
      await flushPromises()

      expect(wrapper.get('[data-testid="mission-payment-resume-btn"]').text()).toBe('Reprendre le paiement')
      expect(wrapper.get('[data-testid="mission-payment-pending-banner"]').text()).toContain('Paiement en attente de confirmation...')
      expect(startPollingSpy).toHaveBeenCalled()
      wrapper.unmount()
    })

    it('trackable pending: after one minute the banner invites to resume, polling is kept', async () => {
      vi.useFakeTimers()
      try {
        vi.mocked(missionApi.getMission).mockResolvedValue({ data: makePendingPaymentMission(), message: 'ok' })
        vi.mocked(missionApi.getPaymentStatus).mockResolvedValue({
          data: { has_payment: true, is_trackable: true, status: 'pending', mission_status: 'pending_payment' },
        })
        const wrapper = mount(ProducerMissionDetailPage)
        await flushPromises()
        await vi.advanceTimersByTimeAsync(59000)
        expect(wrapper.get('[data-testid="mission-payment-pending-banner"]').text()).toContain('en attente de confirmation')

        await vi.advanceTimersByTimeAsync(2000)
        expect(wrapper.get('[data-testid="mission-payment-pending-banner"]').text()).toContain(
          'Paiement non finalisé ? Reprenez-le avec le bouton d\'action de la page.',
        )
        expect(stopPollingSpy).not.toHaveBeenCalled()
        wrapper.unmount()
      } finally {
        vi.useRealTimers()
      }
    })

    it('« Reprendre le paiement » posts NO candidature ids and redirects to the regenerated checkout', async () => {
      vi.mocked(missionApi.getMission).mockResolvedValue({ data: makePendingPaymentMission(), message: 'ok' })
      vi.mocked(missionApi.getPaymentStatus).mockResolvedValue({
        data: { has_payment: true, is_trackable: true, status: 'pending', mission_status: 'pending_payment' },
      })
      vi.mocked(missionApi.confirmSelection).mockResolvedValue({
        data: { checkout_url: 'https://checkout.fedapay.test/regenerated' },
        message: 'ok',
      } as never)
      const wrapper = mount(ProducerMissionDetailPage)
      await flushPromises()

      await wrapper.get('[data-testid="mission-payment-resume-btn"]').trigger('click')
      await flushPromises()

      expect(missionApi.confirmSelection).toHaveBeenCalledWith('mission-uuid-under-test', [])
      expect(redirectSpy).toHaveBeenCalledWith('https://checkout.fedapay.test/regenerated')
      wrapper.unmount()
    })
  })

})

describe('ProducerMissionDetailPage — place libérée (lot B4)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    routeState.query = {}
    vi.spyOn(console, 'error').mockImplementation(() => {})
  })

  afterEach(() => {
    vi.clearAllMocks()
    vi.restoreAllMocks()
  })

  it('annonce la place libérée et invite à choisir une remplaçante', async () => {
    vi.mocked(missionApi.getMission).mockResolvedValueOnce({
      data: makePendingPaymentMission({
        status: 'closed',
        status_label: 'Clôturée',
        freed_places_count: 1,
        can_replace_freed_place: true,
        freed_places_refund_at: '2026-10-29T23:00:00+00:00',
      }),
      message: 'ok',
    })

    const wrapper = mount(ProducerMissionDetailPage)
    await flushPromises()
    await flushPromises()

    const banner = wrapper.find('[data-testid="mission-freed-place-banner"]')
    expect(banner.exists()).toBe(true)
    expect(banner.text()).toContain("Une place s'est libérée")
    expect(banner.text()).toContain('proposez-la à une Face non retenue')
    expect(banner.text()).toContain('le budget de la place est crédité sur votre wallet')
  })

  it("n'affiche rien sans place libérée", async () => {
    vi.mocked(missionApi.getMission).mockResolvedValueOnce({
      data: makePendingPaymentMission({ status: 'closed', status_label: 'Clôturée', freed_places_count: 0 }),
      message: 'ok',
    })

    const wrapper = mount(ProducerMissionDetailPage)
    await flushPromises()

    expect(wrapper.find('[data-testid="mission-freed-place-banner"]').exists()).toBe(false)
  })
})
