import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
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

describe('BookingFormSheet — pricing preview', () => {
  it('shows pricing preview when duration >= 4h and tariff is provided', () => {
    const wrapper = mountForm()

    // Default duree_heures = 4, tarifHoraire = 5000 → preview renders
    expect(wrapper.find('[data-testid="pricing-preview"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Total à payer')
  })

  it('does not show pricing preview when duration < 4h', async () => {
    const wrapper = mountForm()

    // Change duration to 3 (below the 4h minimum)
    const input = wrapper.find('input#duree_heures')
    await input.setValue(3)

    expect(wrapper.find('[data-testid="pricing-preview"]').exists()).toBe(false)
  })

  it('pricing amounts match calculatePricingPreview for given tariff and duration', () => {
    const tarifHoraire = 5000
    const dureeHeures = 4
    const expectedTarifBase = dureeHeures * tarifHoraire // 20000
    const pricing = calculatePricingPreview(expectedTarifBase)

    const wrapper = mountForm({ tarifHoraire, tarifJournalier: null })

    const formattedTotal = new Intl.NumberFormat('fr-FR', {
      style: 'currency',
      currency: 'XOF',
      maximumFractionDigits: 0,
    })
      .format(pricing.totalProducerPays)
      .replace(/\s/g, '')

    expect(wrapper.text().replace(/\s/g, '')).toContain(formattedTotal)
  })
})
