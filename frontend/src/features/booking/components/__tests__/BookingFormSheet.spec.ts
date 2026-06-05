import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref, nextTick } from 'vue'
import BookingFormSheet from '../BookingFormSheet.vue'
import { calculatePricingPreview } from '@/features/booking/types'

// Hoisted so the UGC submission tests can drive the resolved value.
// The 3 existing describe blocks never call createBooking, so this is inert for them.
const { createBookingMock } = vi.hoisted(() => ({ createBookingMock: vi.fn() }))

vi.mock('../../composables/useBookingCreate', () => ({
  useBookingCreate: () => ({
    createBooking: createBookingMock,
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
      faceId: '1',
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

describe('BookingFormSheet — duration presets', () => {
  it('shows duration presets with "max" prefix in labels', () => {
    const wrapper = mountForm()
    const options = wrapper.findAll('select#duree_preset option')
    const halfDay = options.find((o) => o.element.value === '4')
    expect(halfDay).toBeDefined()
    expect(halfDay!.text()).toContain('max 4h')
  })

  it('all standard presets contain "(max" in their label', () => {
    const wrapper = mountForm()
    const options = wrapper.findAll('select#duree_preset option')
    const standardPresets = options.filter((o) => o.element.value !== '' && o.element.value !== 'custom')
    for (const opt of standardPresets) {
      expect(opt.text()).toMatch(/\(max \d+h\)/)
    }
  })
})

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

describe('BookingFormSheet — shooting location', () => {
  it('renders a required shooting location select with Benin city options', () => {
    const wrapper = mountForm()

    const select = wrapper.get('[data-testid="lieu-select"]')
    const options = wrapper.findAll('[data-testid="lieu-select"] option')

    expect(select.attributes('required')).toBeDefined()
    expect(options.some((option) => option.text().includes('Cotonou'))).toBe(true)
    expect(options.some((option) => option.text().includes('Porto-Novo'))).toBe(true)
  })
})

describe('BookingFormSheet — UGC', () => {
  // bookingSchema requires face_id to be a UUID — the default mountForm '1' would
  // silently fail validation (no rendered error field) and block submission.
  const FACE_UUID = '550e8400-e29b-41d4-a716-446655440000'

  const iso = (daysAhead: number): string => {
    const d = new Date()
    d.setDate(d.getDate() + daysAhead)
    return d.toISOString().slice(0, 10)
  }

  // vee-validate submit validation needs a macrotask tick, not just microtask flushes.
  const waitForValidation = async () => {
    await flushPromises()
    await nextTick()
    await new Promise((resolve) => setTimeout(resolve, 10))
    await flushPromises()
  }

  /** Selects UGC + fills a valid form, ready to submit. */
  const fillValidUgcForm = async (
    wrapper: ReturnType<typeof mountForm>,
    { hybrid = false }: { hybrid?: boolean } = {},
  ) => {
    await wrapper.find('select#type_contenu').setValue('UGC')
    await flushPromises()

    await wrapper.find('#date_debut').setValue(iso(7))
    await wrapper.find('#date_fin').setValue(iso(8))
    await wrapper.find('select#lieu').setValue('Cotonou')
    await wrapper.find('#nom_produit').setValue('Tenue Shade Fit M')
    await wrapper.find('#valeur_produit').setValue('45000')

    if (hybrid) {
      await wrapper.find('[data-testid="compensation-hybrid"]').trigger('click')
      await flushPromises()
      await wrapper.find('#nombre_videos').setValue('3')
      await wrapper.find('#montant_remuneration').setValue('15000')
    }
    await flushPromises()
  }

  beforeEach(() => {
    createBookingMock.mockReset()
  })

  it('reveals the UGC block and hides the cash pricing preview when UGC is selected', async () => {
    const wrapper = mountForm()

    await wrapper.find('select#type_contenu').setValue('UGC')
    await flushPromises()

    expect(wrapper.find('[data-testid="ugc-booking-fields"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="compensation-product"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="commission-breakdown"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="pricing-preview"]').exists()).toBe(false)
  })

  it('shows the "Payer la commission" CTA in UGC mode', async () => {
    const wrapper = mountForm()

    await wrapper.find('select#type_contenu').setValue('UGC')
    await flushPromises()

    expect(wrapper.find('[data-testid="submit-booking"]').text()).toContain('Payer la commission')
  })

  it('toggles the video count + Face remuneration fields with the compensation type', async () => {
    const wrapper = mountForm()

    await wrapper.find('select#type_contenu').setValue('UGC')
    await flushPromises()

    // product (default): locked "2 vidéos", no remuneration field
    expect(wrapper.text()).toContain('2 vidéos')
    expect(wrapper.find('#nombre_videos').exists()).toBe(false)
    expect(wrapper.find('#montant_remuneration').exists()).toBe(false)

    // hybrid: editable video count + remuneration field
    await wrapper.find('[data-testid="compensation-hybrid"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('#nombre_videos').exists()).toBe(true)
    expect(wrapper.find('#montant_remuneration').exists()).toBe(true)
  })

  it('keeps the cash flow unchanged for a non-UGC booking', () => {
    const wrapper = mountForm()

    expect(wrapper.find('[data-testid="ugc-booking-fields"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="commission-breakdown"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="pricing-preview"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="submit-booking"]').text()).toContain('Envoyer la demande')
  })

  it('submits a product-only UGC payload without video/remuneration fields', async () => {
    createBookingMock.mockResolvedValue({ success: true, data: { id: 'abc' } })
    const wrapper = mountForm({ faceId: FACE_UUID })

    await fillValidUgcForm(wrapper)
    await wrapper.find('form').trigger('submit')
    await waitForValidation()

    expect(createBookingMock).toHaveBeenCalledTimes(1)
    const payload = createBookingMock.mock.calls[0][0]
    expect(payload).toMatchObject({
      type_contenu: 'UGC',
      type_compensation: 'product',
      nom_produit: 'Tenue Shade Fit M',
      valeur_produit: 45000,
    })
    expect(payload).not.toHaveProperty('nombre_videos')
    expect(payload).not.toHaveProperty('montant_remuneration')
    expect(wrapper.emitted('success')).toBeTruthy()
    expect(wrapper.emitted('close')).toBeTruthy()
  })

  it('submits hybrid UGC payload including video count + remuneration', async () => {
    createBookingMock.mockResolvedValue({ success: true, data: { id: 'abc' } })
    const wrapper = mountForm({ faceId: FACE_UUID })

    await fillValidUgcForm(wrapper, { hybrid: true })
    await wrapper.find('form').trigger('submit')
    await waitForValidation()

    expect(createBookingMock).toHaveBeenCalledTimes(1)
    expect(createBookingMock.mock.calls[0][0]).toMatchObject({
      type_contenu: 'UGC',
      type_compensation: 'hybrid',
      valeur_produit: 45000,
      nombre_videos: 3,
      montant_remuneration: 15000,
    })
  })

  it('does not submit and shows an inline error when a required UGC field is invalid', async () => {
    const wrapper = mountForm({ faceId: FACE_UUID })

    await wrapper.find('select#type_contenu').setValue('UGC')
    await flushPromises()

    // Fill everything valid EXCEPT valeur_produit (left empty → fails the Zod refine).
    await wrapper.find('#date_debut').setValue(iso(7))
    await wrapper.find('#date_fin').setValue(iso(8))
    await wrapper.find('select#lieu').setValue('Cotonou')
    await wrapper.find('#nom_produit').setValue('Tenue Shade Fit M')

    await wrapper.find('form').trigger('submit')
    await waitForValidation()

    expect(createBookingMock).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="valeur_produit-error"]').exists()).toBe(true)
  })

  it('maps a 422 server error onto the matching UGC field', async () => {
    createBookingMock.mockResolvedValue({
      success: false,
      errors: { valeur_produit: ['Valeur produit invalide côté serveur'] },
    })
    const wrapper = mountForm({ faceId: FACE_UUID })

    await fillValidUgcForm(wrapper)
    await wrapper.find('form').trigger('submit')
    await waitForValidation()

    expect(wrapper.find('[data-testid="valeur_produit-error"]').text()).toContain(
      'Valeur produit invalide côté serveur',
    )
  })
})
