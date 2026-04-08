import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import BookingFormSheet from '../BookingFormSheet.vue'
import { calculatePricingPreview } from '@/features/booking/types'

vi.mock('../../composables/useBookingCreate', () => ({
  useBookingCreate: () => ({
    createBooking: vi.fn(),
    isSubmitting: ref(false),
    error: ref(null),
    validationErrors: ref(null),
  }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    success: vi.fn(),
    error: vi.fn(),
  }),
}))

const mountForm = (propsOverride: Record<string, unknown> = {}) =>
  mount(BookingFormSheet, {
    props: {
      isOpen: true,
      faceId: 1,
      faceName: 'Jane Doe',
      tarifHoraire: 5000,
      tarifJournalier: 30000,
      ...propsOverride,
    },
    global: {
      stubs: {
        Teleport: { template: '<slot />' },
      },
    },
  })

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'XOF',
    maximumFractionDigits: 0,
  }).format(amount)
}

describe('BookingFormSheet — pricing preview', () => {
  it('shows pricing preview by default when a daily tariff is available', () => {
    const wrapper = mountForm()

    expect(wrapper.find('[data-testid="pricing-preview"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Total à payer')
  })

  it('does not show pricing preview when no daily tariff is provided', () => {
    const wrapper = mountForm({ tarifJournalier: null })

    expect(wrapper.find('[data-testid="pricing-preview"]').exists()).toBe(false)
  })

  it('updates the preview total when the duration preset changes', async () => {
    const wrapper = mountForm({ tarifJournalier: 40000 })

    await wrapper.find('select#duree_preset').setValue('8')
    await flushPromises()

    const pricing = calculatePricingPreview(40000)

    expect(wrapper.find('[data-testid="pricing-preview"]').exists()).toBe(true)
    expect(wrapper.text()).toContain(formatCurrency(pricing.totalProducerPays))
    expect(wrapper.text()).toContain(formatCurrency(pricing.tarifBase))
  })
})
