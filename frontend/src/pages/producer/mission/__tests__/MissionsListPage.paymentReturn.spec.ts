import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h, ref } from 'vue'
import MissionsListPage from '../MissionsListPage.vue'
import { missionApi } from '@/features/mission/services/missionApi'
import type { Mission } from '@/features/mission/types'

const mockRouter = { push: vi.fn(), replace: vi.fn().mockResolvedValue(undefined) }
const mockRoute: { name: string; query: Record<string, unknown> } = {
  name: 'producer-missions',
  query: {},
}
vi.mock('vue-router', () => ({
  useRouter: () => mockRouter,
  useRoute: () => mockRoute,
  RouterLink: { template: '<a><slot /></a>', props: ['to'] },
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({ user: {}, isEmailVerified: true }),
}))

const toastSuccess = vi.fn()
vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: toastSuccess, error: vi.fn(), warning: vi.fn(), info: vi.fn() }),
}))

vi.mock('@/features/mission/services/missionApi', () => ({
  missionApi: { getCommissionStatus: vi.fn(), getPaymentStatus: vi.fn(), getMission: vi.fn() },
}))

const ugcPendingMission = {
  id: 'ugc-mission-1',
  titre: 'Appel UGC',
  status: 'pending_payment',
  commission_ugc: 2500,
} as unknown as Mission

const mockMissions = ref<Mission[]>([ugcPendingMission])
const mockFetchMissions = vi.fn().mockResolvedValue(undefined)
const mockRefreshMissions = vi.fn().mockResolvedValue(undefined)

vi.mock('@/features/mission/composables', () => ({
  useMissionsList: () => ({
    missions: mockMissions,
    isLoading: ref(false),
    error: ref(null),
    currentPage: ref(1),
    lastPage: ref(1),
    total: ref(1),
    hasLoaded: ref(true),
    fetchMissions: mockFetchMissions,
    refreshMissions: mockRefreshMissions,
  }),
  useDeleteMission: () => ({ deleteMission: vi.fn(), isDeleting: ref(false) }),
  useCloseMission: () => ({ closeMission: vi.fn(), isClosing: ref(false) }),
  useReopenMission: () => ({ reopenMission: vi.fn(), isReopening: ref(false) }),
  useCompleteMission: () => ({ completeMission: vi.fn(), isCompleting: ref(false) }),
}))

vi.mock('@/features/mission/components', () => ({
  MissionsTable: defineComponent({ name: 'MissionsTableStub', setup: () => () => h('div') }),
  DeleteMissionDialog: defineComponent({ name: 'DeleteMissionDialogStub', setup: () => () => h('div') }),
  CloseMissionDialog: defineComponent({ name: 'CloseMissionDialogStub', setup: () => () => h('div') }),
  ReopenMissionDialog: defineComponent({ name: 'ReopenMissionDialogStub', setup: () => () => h('div') }),
  CompleteMissionDialog: defineComponent({ name: 'CompleteMissionDialogStub', setup: () => () => h('div') }),
  MissionStatusFilter: defineComponent({ name: 'MissionStatusFilterStub', setup: () => () => h('div') }),
}))

const overlayStub = defineComponent({
  name: 'UgcPaymentOverlay',
  props: { modelValue: { type: Boolean, required: true }, ownerId: { type: String, default: '' } },
  setup: (props) => () => h('div', { 'data-testid': 'ugc-overlay-stub', 'data-open': String(props.modelValue) }),
})

function mountPage() {
  return mount(MissionsListPage, { global: { stubs: { UgcPaymentOverlay: overlayStub } } })
}

describe('MissionsListPage — return from the same-tab FedaPay checkout', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockRoute.query = {}
    mockMissions.value = [ugcPendingMission]
  })

  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('without payment_return nothing is verified', async () => {
    const wrapper = mountPage()
    await flushPromises()

    expect(missionApi.getCommissionStatus).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="payment-return-verifying"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('?pay={id} still auto-opens the commission tunnel when there is no payment_return', async () => {
    mockRoute.query = { pay: 'ugc-mission-1' }
    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.find('[data-testid="ugc-overlay-stub"]').attributes('data-open')).toBe('true')
    wrapper.unmount()
  })

  it('payment_return=mission_commission polls the mission commission-status (not the cash one) and shows the verification state', async () => {
    mockRoute.query = { payment_return: 'mission_commission', mission: 'ugc-mission-1' }
    vi.mocked(missionApi.getCommissionStatus).mockResolvedValue({ data: { status: 'pending_payment' } } as never)

    const wrapper = mountPage()
    await flushPromises()

    expect(missionApi.getCommissionStatus).toHaveBeenCalledWith('ugc-mission-1')
    expect(missionApi.getPaymentStatus).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="payment-return-verifying"]').text()).toContain(
      'Vérification de votre paiement',
    )
    wrapper.unmount()
  })

  it('confirmed: success toast and the missions list is refreshed', async () => {
    mockRoute.query = { payment_return: 'mission_commission', mission: 'ugc-mission-1' }
    vi.mocked(missionApi.getCommissionStatus).mockResolvedValue({ data: { status: 'published' } } as never)

    const wrapper = mountPage()
    await flushPromises()

    expect(toastSuccess).toHaveBeenCalledTimes(1)
    expect(mockRefreshMissions).toHaveBeenCalled()
    wrapper.unmount()
  })

  it('?pay={id} is suppressed when a payment_return is present', async () => {
    mockRoute.query = { pay: 'ugc-mission-1', payment_return: 'mission_commission', mission: 'ugc-mission-1' }
    vi.mocked(missionApi.getCommissionStatus).mockResolvedValue({ data: { status: 'pending_payment' } } as never)

    const wrapper = mountPage()
    await flushPromises()

    expect(wrapper.findComponent(overlayStub).exists()).toBe(false)
    wrapper.unmount()
  })

  it('failed: « Réessayer le paiement » reopens the commission tunnel for that mission', async () => {
    mockRoute.query = { payment_return: 'mission_commission', mission: 'ugc-mission-1' }
    vi.mocked(missionApi.getCommissionStatus).mockResolvedValue({
      data: { status: 'pending_payment' },
      commission_payment_status: 'failed',
    } as never)

    const wrapper = mountPage()
    await flushPromises()
    await wrapper.find('[data-testid="payment-return-retry"]').trigger('click')

    expect(wrapper.find('[data-testid="ugc-overlay-stub"]').attributes('data-open')).toBe('true')
    wrapper.unmount()
  })
})
