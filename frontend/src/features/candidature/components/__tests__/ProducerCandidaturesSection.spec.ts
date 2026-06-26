import { computed, ref } from 'vue'
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, shallowMount } from '@vue/test-utils'
import ProducerCandidaturesSection from '../ProducerCandidaturesSection.vue'
import ProducerCandidatureCard from '../ProducerCandidatureCard.vue'
import { UgcShipmentForm } from '@/components/ugc'

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn(), clear: vi.fn() }),
}))

// Spies hissés hors des factories pour être capturables dans les tests
// (pattern FaceBookingDetailPage.spec.ts).
const mockRefresh = vi.fn()
const mockConfirmShipment = vi.fn()
const mockShipmentError = ref<string | null>(null)
const mockShipmentErrorCode = ref<string | null>(null)
const mockAcceptCandidature = vi.fn()
const mockAcceptError = ref<string | null>(null)
const mockAcceptErrorCode = ref<string | null>(null)
const mockAcceptSuccessMessage = ref<string | null>(null)

vi.mock('@/composables/useUgcShipment', () => ({
  useUgcShipment: () => ({
    isSubmitting: ref(false),
    error: mockShipmentError,
    errorCode: mockShipmentErrorCode,
    confirmShipment: mockConfirmShipment,
    clearError: vi.fn(),
  }),
}))

vi.mock('../../composables', () => ({
  useProducerCandidatures: () => ({
    candidatures: ref([
      {
        id: 'cand-1',
        status: 'confirmed',
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
    refresh: mockRefresh,
  }),
  useRejectCandidature: () => ({
    error: ref(null),
    successMessage: ref(null),
    rejectCandidature: vi.fn(),
    reset: vi.fn(),
  }),
  useAcceptCandidature: () => ({
    error: mockAcceptError,
    errorCode: mockAcceptErrorCode,
    successMessage: mockAcceptSuccessMessage,
    acceptCandidature: mockAcceptCandidature,
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
    // clearAllMocks ne purge ni les implémentations ni les refs hissées :
    // sans ces resets, mockImplementation/errorCode du test ALREADY_SHIPPED
    // fuiraient vers tout test ajouté après lui.
    mockConfirmShipment.mockReset()
    mockShipmentError.value = null
    mockShipmentErrorCode.value = null
    mockAcceptCandidature.mockReset()
    mockAcceptError.value = null
    mockAcceptErrorCode.value = null
    mockAcceptSuccessMessage.value = null
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

  describe('UGC shipment modal (story 3.2)', () => {
    function mountUgcSection() {
      return shallowMount(ProducerCandidaturesSection, {
        props: {
          missionId: 'mission-uuid-under-test',
          missionBudget: 90000,
          missionStatus: 'closed',
          isUgcMission: true,
          ugcProductName: 'Sneakers Shade Fit',
        },
        global: {
          stubs: {
            Teleport: true,
          },
        },
      })
    }

    async function openModal(wrapper: ReturnType<typeof mountUgcSection>) {
      wrapper.findComponent(ProducerCandidatureCard).vm.$emit('confirm-shipment')
      await flushPromises()
    }

    it('opens the shipment modal when a card emits confirm-shipment', async () => {
      const wrapper = mountUgcSection()

      expect(wrapper.find('[data-testid="shipment-modal"]').exists()).toBe(false)

      await openModal(wrapper)

      const modal = wrapper.find('[data-testid="shipment-modal"]')
      expect(modal.exists()).toBe(true)
      expect(modal.text()).toContain('Vous envoyez à Alice')
      expect(modal.text()).toContain('Sneakers Shade Fit')
    })

    it('confirms the shipment and refreshes the list on success', async () => {
      mockConfirmShipment.mockResolvedValue({ id: 'shipment-uuid-1', tunnel_status: 'shipped' })
      const wrapper = mountUgcSection()
      await openModal(wrapper)

      const payload = { transporteur: 'Gozem', numero_suivi: 'GZM-1' }
      wrapper.findComponent(UgcShipmentForm).vm.$emit('submit', payload)
      await flushPromises()

      expect(mockConfirmShipment).toHaveBeenCalledWith('candidature', 'cand-1', payload)
      expect(mockRefresh).toHaveBeenCalledTimes(1)
      expect(wrapper.find('[data-testid="shipment-modal"]').exists()).toBe(false)
    })

    it('refreshes the list when confirmation fails with ALREADY_SHIPPED', async () => {
      mockConfirmShipment.mockImplementation(async () => {
        mockShipmentError.value = "L'expédition de ce deal a déjà été confirmée."
        mockShipmentErrorCode.value = 'ALREADY_SHIPPED'
        return null
      })
      const wrapper = mountUgcSection()
      await openModal(wrapper)

      wrapper.findComponent(UgcShipmentForm).vm.$emit('submit', { transporteur: 'DHL', numero_suivi: 'T-1' })
      await flushPromises()

      // D-3.2.e : toast info + fermeture + refresh (la liste refetchée porte le shipment).
      expect(mockRefresh).toHaveBeenCalledTimes(1)
      expect(wrapper.find('[data-testid="shipment-modal"]').exists()).toBe(false)
    })
  })

  describe('UGC accept (8.3)', () => {
    const ProducerCandidatureCardStub = {
      name: 'ProducerCandidatureCard',
      template: '<div class="producer-candidature-card-stub"></div>',
      props: ['candidature', 'selectionMode', 'isSelected', 'isUgcMission', 'ugcCompensationType'],
      emits: ['reject', 'accept', 'toggle-selection', 'confirm-shipment'],
      methods: {
        resetRejecting() {},
        resetAccepting() {},
      },
    }

    function mountUgcProductSection() {
      return shallowMount(ProducerCandidaturesSection, {
        props: {
          missionId: 'mission-uuid-under-test',
          missionBudget: 0,
          missionStatus: 'published',
          isUgcMission: true,
          ugcCompensationType: 'product',
        },
        global: {
          stubs: {
            Teleport: true,
            ProducerCandidatureCard: ProducerCandidatureCardStub,
          },
        },
      })
    }

    it('disables the cash selection panel on a UGC product-only mission (selection-mode off)', () => {
      const wrapper = mountUgcProductSection()

      expect(wrapper.text()).not.toContain('Sélectionnez les faces à retenir')
      expect(wrapper.find('mission-selection-summary-stub').exists()).toBe(false)
    })

    it('accepts a candidature and refreshes the list when a card emits accept', async () => {
      mockAcceptCandidature.mockResolvedValue({ data: { id: 'cand-1' }, message: 'Candidature acceptée' })
      const wrapper = mountUgcProductSection()

      wrapper.findComponent(ProducerCandidatureCardStub).vm.$emit('accept', 'cand-1')
      await flushPromises()

      expect(mockAcceptCandidature).toHaveBeenCalledWith('cand-1')
      expect(mockRefresh).toHaveBeenCalledTimes(1)
    })

    it('refreshes the list when an accept fails with MISSION_FULL (capacity resync, AC9)', async () => {
      mockAcceptCandidature.mockImplementation(async () => {
        mockAcceptError.value = 'Toutes les places de cette mission sont déjà pourvues.'
        mockAcceptErrorCode.value = 'MISSION_FULL'
        return null
      })
      const wrapper = mountUgcProductSection()

      wrapper.findComponent(ProducerCandidatureCardStub).vm.$emit('accept', 'cand-1')
      await flushPromises()

      expect(mockAcceptCandidature).toHaveBeenCalledWith('cand-1')
      // AC9 : sur MISSION_FULL la liste est rafraîchie (resync capacité / auto-clôture).
      expect(mockRefresh).toHaveBeenCalledTimes(1)
    })

    it('does not refresh on a non-capacity accept error (the AC9 gate is real)', async () => {
      mockAcceptCandidature.mockImplementation(async () => {
        mockAcceptError.value = "Vous n'êtes pas autorisé à effectuer cette action."
        mockAcceptErrorCode.value = null // 403 ownership / autre — hors MISSION_FULL/ALREADY_ACCEPTED
        return null
      })
      const wrapper = mountUgcProductSection()

      wrapper.findComponent(ProducerCandidatureCardStub).vm.$emit('accept', 'cand-1')
      await flushPromises()

      expect(mockAcceptCandidature).toHaveBeenCalledWith('cand-1')
      // Sans gate, ce test échouerait : le refresh ne doit PAS partir sur une erreur non-capacité.
      expect(mockRefresh).not.toHaveBeenCalled()
    })
  })
})
