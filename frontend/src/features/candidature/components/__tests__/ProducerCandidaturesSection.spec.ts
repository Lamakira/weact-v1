import { computed, ref } from 'vue'
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import ProducerCandidaturesSection from '../ProducerCandidaturesSection.vue'

vi.mock('../../composables', () => ({
  useProducerCandidatures: () => ({
    candidatures: ref([
      {
        id: 'cand-1',
        face: {
          display_name: 'Alice',
        },
      },
    ]),
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
    refresh: vi.fn(),
  }),
  useAcceptCandidature: () => ({
    error: ref(null),
    successMessage: ref(null),
    acceptCandidature: vi.fn(),
    reset: vi.fn(),
  }),
  useRejectCandidature: () => ({
    error: ref(null),
    successMessage: ref(null),
    rejectCandidature: vi.fn(),
    reset: vi.fn(),
  }),
}))

vi.mock('@/features/mission/composables', () => ({
  useMissionPayment: () => ({
    isConfirming: ref(false),
    error: ref(null),
    pricing: computed(() => ({
      nombreFaces: 1,
      budgetParFace: 90000,
      sousTotal: 90000,
      commissionProducteur: 9000,
      montantTotal: 99000,
    })),
    selectedCount: ref(1),
    selectedFaces: ref([{ candidatureId: 'cand-1', label: 'Alice' }]),
    selectionLimitReached: ref(false),
    toggleSelection: vi.fn(),
    removeSelection: vi.fn(),
    isSelected: vi.fn(() => true),
    confirmAndPay: vi.fn(),
  }),
}))

describe('ProducerCandidaturesSection', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('keeps selection controls visible for the FIX-19.3 retry flow on pending missions', () => {
    const wrapper = shallowMount(ProducerCandidaturesSection, {
      props: {
        missionId: 'mission-uuid-under-test',
        missionBudget: 90000,
        missionStatus: 'pending_payment',
        nombreFacesVoulu: 2,
        allowRetrySelection: true,
      },
      global: {
        stubs: {
          Teleport: true,
        },
      },
    })

    expect(wrapper.text()).toContain('Sélectionnez les faces à retenir')
    expect(wrapper.find('mission-selection-summary-stub').exists()).toBe(true)
  })

  it('keeps retry controls hidden for pending missions when no retry path is enabled', () => {
    const wrapper = shallowMount(ProducerCandidaturesSection, {
      props: {
        missionId: 'mission-uuid-under-test',
        missionBudget: 90000,
        missionStatus: 'pending_payment',
        nombreFacesVoulu: 2,
        allowRetrySelection: false,
      },
      global: {
        stubs: {
          Teleport: true,
        },
      },
    })

    expect(wrapper.text()).not.toContain('Sélectionnez les faces à retenir')
    expect(wrapper.find('mission-selection-summary-stub').exists()).toBe(false)
  })
})
