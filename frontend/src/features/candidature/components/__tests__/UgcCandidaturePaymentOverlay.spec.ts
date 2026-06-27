import { ref } from 'vue'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import UgcCandidaturePaymentOverlay from '../UgcCandidaturePaymentOverlay.vue'
import type { PaymentStatus } from '@/features/booking/types'

// Controllable composable state — refs hoisted out of the factory (calque
// ProducerCandidaturesSection.spec.ts:14-21).
const mockIsInitiating = ref(false)
const mockPaymentStatus = ref<PaymentStatus>('idle')
const mockError = ref<string | null>(null)
const mockInitiate = vi.fn()
const mockStopPolling = vi.fn()
const mockReset = vi.fn()

vi.mock('../../composables/useUgcCandidaturePayment', () => ({
  useUgcCandidaturePayment: () => ({
    isInitiating: mockIsInitiating,
    paymentStatus: mockPaymentStatus,
    error: mockError,
    initiate: mockInitiate,
    stopPolling: mockStopPolling,
    reset: mockReset,
  }),
}))

const fmt = (n: number): string => new Intl.NumberFormat('fr-FR').format(n) + ' FCFA'

function mountOverlay(props: Partial<{ montantRemuneration: number | null }> = {}) {
  return mount(UgcCandidaturePaymentOverlay, {
    props: {
      candidatureId: 'cand-1',
      faceName: 'Alice',
      montantRemuneration: 15000,
      modelValue: true,
      ...props,
    },
    global: {
      stubs: { Teleport: true },
    },
  })
}

describe('UgcCandidaturePaymentOverlay (8-5)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockIsInitiating.value = false
    mockPaymentStatus.value = 'idle'
    mockError.value = null
  })

  it('renders the client-side pricing preview (cash + 10 % frais de service)', () => {
    const wrapper = mountOverlay({ montantRemuneration: 15000 })
    const text = wrapper.text()

    expect(text).toContain('Cash Face')
    expect(text).toContain('Frais de service (10 %)')
    expect(text).toContain('Total à payer')
    expect(text).toContain(fmt(15000)) // cash
    expect(text).toContain(fmt(1500)) // frais = round(15000 * 0.10)
    expect(text).toContain(fmt(16500)) // total
    expect(text).toContain('Alice')
  })

  it('treats a null montant_remuneration as 0 (defensive preview)', () => {
    const wrapper = mountOverlay({ montantRemuneration: null })
    expect(wrapper.text()).toContain(fmt(0))
  })

  it('calls initiate(candidatureId) when "Payer via FedaPay" is clicked', async () => {
    const wrapper = mountOverlay()

    await wrapper.find('[data-testid="ugc-hybrid-pay-btn"]').trigger('click')
    await flushPromises()

    expect(mockInitiate).toHaveBeenCalledWith('cand-1')
  })

  it('shows the success state and emits payment-success on confirm', async () => {
    mockPaymentStatus.value = 'confirmed'
    const wrapper = mountOverlay()

    expect(wrapper.text()).toContain('Paiement confirmé !')

    await wrapper.find('[data-testid="ugc-hybrid-success-btn"]').trigger('click')

    expect(wrapper.emitted('payment-success')).toBeTruthy()
    // La fermeture passe aussi modelValue=false au parent.
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([false])
  })

  it('shows the failed state with the error and retries via reset()', async () => {
    mockPaymentStatus.value = 'failed'
    mockError.value = 'Le paiement a échoué ou a été annulé.'
    const wrapper = mountOverlay()

    expect(wrapper.text()).toContain('Paiement échoué')
    expect(wrapper.text()).toContain('Le paiement a échoué ou a été annulé.')

    await wrapper.find('[data-testid="ugc-hybrid-retry-btn"]').trigger('click')
    expect(mockReset).toHaveBeenCalledTimes(1)
  })

  it('hides the overlay content when modelValue is false', async () => {
    const wrapper = mountOverlay()
    await wrapper.setProps({ modelValue: false })
    // v-if="modelValue" removes the dialog — no pay button rendered.
    expect(wrapper.find('[data-testid="ugc-hybrid-pay-btn"]').exists()).toBe(false)
  })
})
