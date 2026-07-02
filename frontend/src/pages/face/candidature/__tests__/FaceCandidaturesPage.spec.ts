import { describe, it, expect, vi, beforeEach } from 'vitest'
import { shallowMount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import FaceCandidaturesPage from '../FaceCandidaturesPage.vue'

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn(), clear: vi.fn() }),
}))

const mockRoute = { query: {} as Record<string, string> }
const mockRouter = { replace: vi.fn(), push: vi.fn() }
vi.mock('vue-router', () => ({
  useRoute: () => mockRoute,
  useRouter: () => mockRouter,
}))

// Hoisted spies — captured across the module mock factory.
const mockConfirmCandidature = vi.fn()
const mockReconfirmCandidature = vi.fn()
const mockCancelCandidature = vi.fn()
const mockRefresh = vi.fn()
const candidaturesRef = ref<unknown[]>([])

vi.mock('@/features/candidature/composables', () => ({
  useFaceCandidatures: () => ({
    candidatures: candidaturesRef,
    isLoading: ref(false),
    error: ref(null),
    currentPage: ref(1),
    lastPage: ref(1),
    total: ref(1),
    hasNextPage: ref(false),
    hasPrevPage: ref(false),
    isEmpty: ref(false),
    statusFilter: ref(''),
    fetchCandidatures: vi.fn(),
    nextPage: vi.fn(),
    prevPage: vi.fn(),
    goToPage: vi.fn(),
    setStatusFilter: vi.fn(),
    refresh: mockRefresh,
  }),
  useConfirmCandidature: () => ({
    error: ref(null),
    successMessage: ref('Participation confirmée'),
    confirmCandidature: mockConfirmCandidature,
    reset: vi.fn(),
  }),
  useReconfirmCandidature: () => ({
    error: ref(null),
    successMessage: ref('Participation reconfirmée'),
    reconfirmCandidature: mockReconfirmCandidature,
    reset: vi.fn(),
  }),
  useCancelCandidature: () => ({
    error: ref(null),
    successMessage: ref(null),
    cancelCandidature: mockCancelCandidature,
    reset: vi.fn(),
  }),
}))

// Custom stub exposing the reset methods the page calls via template refs.
const CandidatureCardStub = {
  name: 'CandidatureCard',
  template: '<div class="candidature-card-stub"></div>',
  props: ['candidature'],
  emits: ['confirm', 'cancel'],
  methods: {
    resetConfirming() {},
    resetCancelling() {},
  },
}

function makeCandidature(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    id: 'cand-x',
    status: 'accepted',
    status_label: 'Acceptée',
    message_motivation: null,
    created_at: '2026-06-20T10:00:00Z',
    mission: {
      id: 'm1',
      titre: 'Mission',
      date_tournage: null,
      lieu: null,
      budget: 0,
      type_compensation: null,
    },
    producer: { id: 'p1', display_name: 'Acme', type: 'agency', profile_photo_url: null },
    conversation_id: null,
    ...overrides,
  }
}

function mountPage() {
  return shallowMount(FaceCandidaturesPage, {
    global: {
      stubs: {
        CandidatureCard: CandidatureCardStub,
        StatusFilter: true,
        ConfirmModal: true,
      },
    },
  })
}

describe('FaceCandidaturesPage — confirm routing (8.3)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    candidaturesRef.value = []
  })

  it('routes confirm to reconfirmCandidature for a UGC candidature (type_compensation set)', async () => {
    mockReconfirmCandidature.mockResolvedValue({
      data: { id: 'ugc-cand' },
      message: 'Participation reconfirmée',
    })
    candidaturesRef.value = [
      makeCandidature({
        id: 'ugc-cand',
        mission: {
          id: 'm1',
          titre: 'UGC',
          date_tournage: null,
          lieu: null,
          budget: 0,
          type_compensation: 'product',
        },
      }),
    ]

    const wrapper = mountPage()
    await flushPromises()

    wrapper.findComponent(CandidatureCardStub).vm.$emit('confirm', 'ugc-cand')
    await flushPromises()

    expect(mockReconfirmCandidature).toHaveBeenCalledWith('ugc-cand')
    expect(mockConfirmCandidature).not.toHaveBeenCalled()
    expect(mockRefresh).toHaveBeenCalledTimes(1)
  })

  it('routes confirm to confirmCandidature for a cash candidature (type_compensation null) — non-regression', async () => {
    mockConfirmCandidature.mockResolvedValue({
      data: { id: 'cash-cand' },
      message: 'Participation confirmée',
    })
    candidaturesRef.value = [
      makeCandidature({
        id: 'cash-cand',
        mission: {
          id: 'm2',
          titre: 'Cash',
          date_tournage: '2026-07-01',
          lieu: 'Cotonou',
          budget: 150000,
          type_compensation: null,
        },
      }),
    ]

    const wrapper = mountPage()
    await flushPromises()

    wrapper.findComponent(CandidatureCardStub).vm.$emit('confirm', 'cash-cand')
    await flushPromises()

    expect(mockConfirmCandidature).toHaveBeenCalledWith('cash-cand')
    expect(mockReconfirmCandidature).not.toHaveBeenCalled()
    expect(mockRefresh).toHaveBeenCalledTimes(1)
  })
})
