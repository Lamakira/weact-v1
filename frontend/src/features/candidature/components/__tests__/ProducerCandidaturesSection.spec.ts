import { computed, ref } from 'vue'
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, shallowMount } from '@vue/test-utils'
import ProducerCandidaturesSection from '../ProducerCandidaturesSection.vue'
import ProducerCandidatureCard from '../ProducerCandidatureCard.vue'
import UgcCandidaturePaymentOverlay from '../UgcCandidaturePaymentOverlay.vue'
import { UgcShipmentForm } from '@/components/ugc'

const mockToastSuccess = vi.fn()
const mockToastError = vi.fn()
vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    success: mockToastSuccess,
    error: mockToastError,
    info: vi.fn(),
    warning: vi.fn(),
    clear: vi.fn(),
  }),
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
const mockReleaseCandidature = vi.fn()
const mockReleaseError = ref<string | null>(null)
const mockReleaseSuccessMessage = ref<string | null>(null)

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
  useReleaseCandidature: () => ({
    error: mockReleaseError,
    successMessage: mockReleaseSuccessMessage,
    releaseCandidature: mockReleaseCandidature,
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
    mockReleaseCandidature.mockReset()
    mockReleaseError.value = null
    mockReleaseSuccessMessage.value = null
    mockToastSuccess.mockReset()
    mockToastError.mockReset()
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
      emits: ['reject', 'accept', 'release', 'toggle-selection', 'confirm-shipment'],
      methods: {
        resetRejecting() {},
        resetAccepting() {},
        resetReleasing() {},
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

    it('releases a candidature and refreshes the list when a card emits release (9-2)', async () => {
      mockReleaseCandidature.mockResolvedValue({
        data: { candidature_status: 'cancelled', message: 'Place libérée' },
      })
      const wrapper = mountUgcProductSection()

      wrapper.findComponent(ProducerCandidatureCardStub).vm.$emit('release', 'cand-1')
      await flushPromises()

      expect(mockReleaseCandidature).toHaveBeenCalledWith('cand-1')
      expect(mockRefresh).toHaveBeenCalledTimes(1)
    })

    function mountUgcReleaseSection(ugcCompensationType: string) {
      return shallowMount(ProducerCandidaturesSection, {
        props: {
          missionId: 'mission-uuid-under-test',
          missionBudget: 0,
          missionStatus: 'published',
          isUgcMission: true,
          ugcCompensationType,
        },
        global: {
          stubs: {
            Teleport: true,
            ProducerCandidatureCard: ProducerCandidatureCardStub,
          },
        },
      })
    }

    it('product-only release toast drops the refund claim (honest copy, D-9.2.c)', async () => {
      // Le backend renvoie un message INCONDITIONNEL mentionnant le remboursement ;
      // en produit-seul aucun escrow n'est remboursé → le front doit l'élider.
      mockReleaseCandidature.mockImplementation(async () => {
        mockReleaseSuccessMessage.value = 'La place a été libérée et le règlement remboursé.'
        return { data: { candidature_status: 'cancelled', message: mockReleaseSuccessMessage.value } }
      })
      const wrapper = mountUgcReleaseSection('product')

      wrapper.findComponent(ProducerCandidatureCardStub).vm.$emit('release', 'cand-1')
      await flushPromises()

      expect(mockToastSuccess).toHaveBeenCalledTimes(1)
      expect(mockToastSuccess.mock.calls[0][0]).toBe('La place a été libérée.')
      expect(mockToastSuccess.mock.calls[0][0]).not.toContain('remboursé')
      expect(mockRefresh).toHaveBeenCalledTimes(1)
    })

    it('hybrid release toast keeps the backend refund message', async () => {
      mockReleaseCandidature.mockImplementation(async () => {
        mockReleaseSuccessMessage.value = 'La place a été libérée et le règlement remboursé.'
        return { data: { candidature_status: 'cancelled', message: mockReleaseSuccessMessage.value } }
      })
      const wrapper = mountUgcReleaseSection('hybrid')

      wrapper.findComponent(ProducerCandidatureCardStub).vm.$emit('release', 'cand-1')
      await flushPromises()

      expect(mockToastSuccess).toHaveBeenCalledTimes(1)
      expect(mockToastSuccess.mock.calls[0][0]).toContain('remboursé')
      expect(mockRefresh).toHaveBeenCalledTimes(1)
    })

    it('does not refresh and surfaces an error toast when a release fails (AC3)', async () => {
      mockReleaseCandidature.mockImplementation(async () => {
        mockReleaseError.value = 'Seule une candidature acceptée peut être libérée.'
        return null
      })
      const wrapper = mountUgcReleaseSection('product')

      wrapper.findComponent(ProducerCandidatureCardStub).vm.$emit('release', 'cand-1')
      await flushPromises()

      expect(mockReleaseCandidature).toHaveBeenCalledWith('cand-1')
      expect(mockToastError).toHaveBeenCalledWith('Seule une candidature acceptée peut être libérée.')
      // AC3 : la liste n'est pas faussement vidée sur échec.
      expect(mockRefresh).not.toHaveBeenCalled()
    })
  })

  describe('UGC hybrid accept overlay (8.5)', () => {
    const ProducerCandidatureCardStub = {
      name: 'ProducerCandidatureCard',
      template: '<div class="producer-candidature-card-stub"></div>',
      props: ['candidature', 'selectionMode', 'isSelected', 'isUgcMission', 'ugcCompensationType'],
      emits: ['reject', 'accept', 'release', 'toggle-selection', 'confirm-shipment'],
      methods: {
        resetRejecting() {},
        resetAccepting() {},
        resetReleasing() {},
      },
    }

    function mountUgcHybridSection() {
      return shallowMount(ProducerCandidaturesSection, {
        props: {
          missionId: 'mission-uuid-under-test',
          missionBudget: 0,
          missionStatus: 'published',
          isUgcMission: true,
          ugcCompensationType: 'hybrid',
          missionMontantRemuneration: 15000,
        },
        global: {
          stubs: {
            Teleport: true,
            ProducerCandidatureCard: ProducerCandidatureCardStub,
          },
        },
      })
    }

    it('opens the FedaPay payment overlay (and does NOT free-accept) on a hybrid accept', async () => {
      const wrapper = mountUgcHybridSection()

      // Pas d'overlay tant qu'aucune candidature n'est ciblée.
      expect(wrapper.findComponent(UgcCandidaturePaymentOverlay).exists()).toBe(false)

      wrapper.findComponent(ProducerCandidatureCardStub).vm.$emit('accept', 'cand-1')
      await flushPromises()

      const overlay = wrapper.findComponent(UgcCandidaturePaymentOverlay)
      expect(overlay.exists()).toBe(true)
      expect(overlay.props('candidatureId')).toBe('cand-1')
      expect(overlay.props('faceName')).toBe('Alice')
      expect(overlay.props('montantRemuneration')).toBe(15000)
      // Hybride : l'accept gratuit produit-seul n'est JAMAIS appelé.
      expect(mockAcceptCandidature).not.toHaveBeenCalled()
    })

    it('refreshes the candidatures list when the overlay emits payment-success', async () => {
      const wrapper = mountUgcHybridSection()
      wrapper.findComponent(ProducerCandidatureCardStub).vm.$emit('accept', 'cand-1')
      await flushPromises()

      wrapper.findComponent(UgcCandidaturePaymentOverlay).vm.$emit('payment-success')
      await flushPromises()

      expect(mockRefresh).toHaveBeenCalledTimes(1)
    })

    it('closes the overlay when it emits update:modelValue=false', async () => {
      const wrapper = mountUgcHybridSection()
      wrapper.findComponent(ProducerCandidatureCardStub).vm.$emit('accept', 'cand-1')
      await flushPromises()
      expect(wrapper.findComponent(UgcCandidaturePaymentOverlay).exists()).toBe(true)

      wrapper.findComponent(UgcCandidaturePaymentOverlay).vm.$emit('update:modelValue', false)
      await flushPromises()

      expect(wrapper.findComponent(UgcCandidaturePaymentOverlay).exists()).toBe(false)
    })
  })
})
