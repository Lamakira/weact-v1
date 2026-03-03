import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import BookingPricingBreakdown from '../BookingPricingBreakdown.vue'
import { calculatePricingPreview } from '@/features/booking/types'

/**
 * Format amount to XOF string the same way the component does,
 * then strip all whitespace for robust comparison.
 */
function formatXOF(amount: number): string {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'XOF',
    maximumFractionDigits: 0,
  })
    .format(amount)
    .replace(/\s/g, '')
}

describe('BookingPricingBreakdown', () => {
  const tarifBase = 20000

  it('shows "Vous recevrez" with commission deducted for Face role', () => {
    const wrapper = mount(BookingPricingBreakdown, {
      props: { tarifBase, role: 'face' },
    })

    expect(wrapper.text()).toContain('Vous recevrez')
    expect(wrapper.text()).toContain('Commission plateforme (-15%)')
    expect(wrapper.text()).not.toContain('Total à payer')
  })

  it('shows "Total à payer" with commission added for Producer role', () => {
    const wrapper = mount(BookingPricingBreakdown, {
      props: { tarifBase, role: 'producer' },
    })

    expect(wrapper.text()).toContain('Total à payer')
    expect(wrapper.text()).toContain('Commission plateforme (+15%)')
    expect(wrapper.text()).not.toContain('Vous recevrez')
  })

  it('displays amounts matching calculatePricingPreview output', () => {
    const pricing = calculatePricingPreview(tarifBase)

    const faceWrapper = mount(BookingPricingBreakdown, {
      props: { tarifBase, role: 'face' },
    })

    const producerWrapper = mount(BookingPricingBreakdown, {
      props: { tarifBase, role: 'producer' },
    })

    const faceText = faceWrapper.text().replace(/\s/g, '')
    expect(faceText).toContain(formatXOF(pricing.faceReceives))

    const producerText = producerWrapper.text().replace(/\s/g, '')
    expect(producerText).toContain(formatXOF(pricing.totalProducerPays))
  })

  it('prefers server-stored amounts over client calculations', () => {
    const serverFaceRecoit = 16500
    const serverTotalProducteur = 24000

    const faceWrapper = mount(BookingPricingBreakdown, {
      props: { tarifBase, role: 'face', montantFaceRecoit: serverFaceRecoit },
    })

    const producerWrapper = mount(BookingPricingBreakdown, {
      props: { tarifBase, role: 'producer', montantTotalProducteur: serverTotalProducteur },
    })

    const faceText = faceWrapper.text().replace(/\s/g, '')
    expect(faceText).toContain(formatXOF(serverFaceRecoit))

    const producerText = producerWrapper.text().replace(/\s/g, '')
    expect(producerText).toContain(formatXOF(serverTotalProducteur))
  })

  it('shows "Revenu plateforme" row when showPlatformRevenue is true', () => {
    const pricing = calculatePricingPreview(tarifBase)

    const wrapper = mount(BookingPricingBreakdown, {
      props: { tarifBase, role: 'producer', showPlatformRevenue: true },
    })

    expect(wrapper.text()).toContain('Revenu plateforme')

    const platformRevenue = pricing.producerCommission + pricing.faceCommission
    expect(wrapper.text().replace(/\s/g, '')).toContain(formatXOF(platformRevenue))
  })

  it('does not show "Revenu plateforme" row when showPlatformRevenue is omitted', () => {
    const wrapper = mount(BookingPricingBreakdown, {
      props: { tarifBase, role: 'producer' },
    })

    expect(wrapper.text()).not.toContain('Revenu plateforme')
  })
})
