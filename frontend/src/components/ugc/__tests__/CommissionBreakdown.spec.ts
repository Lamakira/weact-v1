import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import CommissionBreakdown from '../CommissionBreakdown.vue'

// `toLocaleString('fr-FR')` uses narrow no-break spaces (U+202F / U+00A0) as the
// thousands separator — normalize them to a plain space before asserting.
const normalize = (text: string): string => text.replace(/[\u202f\u00a0]/g, ' ')

const mountBreak = (props: {
  productValue: number
  payAmount?: number
  onPlatform?: boolean
  mode?: 'booking' | 'mission'
  nombreFaces?: number
}) => mount(CommissionBreakdown, { props })

describe('CommissionBreakdown', () => {
  it('shows product value + commission and hides the Face remuneration line when payAmount is 0', () => {
    const wrapper = mountBreak({ productValue: 50000, payAmount: 0 })
    const text = normalize(wrapper.text())

    expect(text).toContain('50 000') // product value
    expect(text).toContain('5 000') // commission (10% of 50000)
    expect(text).not.toContain('Rémunération de la Face')
  })

  it('applies the 2 500 floor when 10% of the product value is below it', () => {
    const wrapper = mountBreak({ productValue: 20000 })
    const text = normalize(wrapper.text())

    expect(text).toContain('2 500')
  })

  it('shows the Face remuneration line in hybrid mode (payAmount > 0)', () => {
    const wrapper = mountBreak({ productValue: 50000, payAmount: 15000 })
    const text = normalize(wrapper.text())

    expect(text).toContain('Rémunération de la Face')
    expect(text).toContain('15 000')
    // Commission still sits on the product value only, never on the cash
    expect(text).toContain('5 000')
  })

  it('renders the commission badge and testid anchor', () => {
    const wrapper = mountBreak({ productValue: 50000 })
    expect(normalize(wrapper.text())).toContain('10% min. 2 500')
    expect(wrapper.find('[data-testid="commission-breakdown"]').exists()).toBe(true)
  })

  it('hides the rate badge in mission publish mode (product-only recap shows the FCFA amount only)', () => {
    const wrapper = mountBreak({ productValue: 50000, mode: 'mission' })
    const text = normalize(wrapper.text())

    expect(text).toContain('Commission WeAct')
    expect(text).toContain('5 000') // le montant reste affiché
    expect(text).not.toContain('10% min. 2 500') // le taux disparaît du récap de création
  })

  it('shows the service fee + escrow footer and drops the commission-only copy when onPlatform (booking hybride)', () => {
    const wrapper = mountBreak({ productValue: 50000, payAmount: 15000, onPlatform: true })
    const text = normalize(wrapper.text())

    expect(text).toContain('Frais de service')
    expect(text).toContain('16 500') // 15000 + 10% frais service
    expect(text).toContain('séquestrée par WeAct') // footer escrow honnête
    expect(text).not.toContain('WeAct ne facture que sa commission')
  })

  // Mission publish, HYBRID (produit + cash) — the WeAct commission sits on the CASH
  // (charged per-Face at acceptance), never on the product value. Publication is free.
  describe('mission publish hybrid mode', () => {
    it('drops the product commission and charges nothing at publish', () => {
      const wrapper = mountBreak({ productValue: 80000, payAmount: 25000, mode: 'mission', nombreFaces: 1 })
      const text = normalize(wrapper.text())

      // No product-based commission, no "à payer maintenant" fee at publish
      expect(text).not.toContain('8 000') // 10% of 80000 must never surface
      expect(text).not.toContain('WeAct ne facture que sa commission')
      expect(text.toLowerCase()).toContain('gratuit')
      // Face cash + service fee (per-Face cost at acceptance)
      expect(text).toContain('25 000') // cash
      expect(text).toContain('Commission WeAct')
      // Le taux n'est jamais affiché dans le récap de publication (montant FCFA uniquement)
      expect(text).not.toContain('(10 %)')
      expect(text).toContain('2 500') // 10% WeAct commission on 25000
      expect(text).toContain('27 500') // per-Face cost = cash + service fee
      expect(text).toContain('séquestrée') // escrow note
    })

    it('multiplies the per-Face cost by the number of Faces', () => {
      const wrapper = mountBreak({ productValue: 80000, payAmount: 25000, mode: 'mission', nombreFaces: 2 })
      const text = normalize(wrapper.text())

      expect(text).toContain('Nombre de Faces')
      expect(text).toContain('27 500') // per Face
      expect(text).toContain('55 000') // 2 × 27 500 total
    })

    it('hides the Faces total line for a single Face', () => {
      const wrapper = mountBreak({ productValue: 80000, payAmount: 25000, mode: 'mission', nombreFaces: 1 })
      const text = normalize(wrapper.text())

      expect(text).not.toContain('Nombre de Faces')
      expect(text).not.toContain('55 000')
    })
  })
})
