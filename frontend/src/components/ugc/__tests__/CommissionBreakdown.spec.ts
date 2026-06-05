import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import CommissionBreakdown from '../CommissionBreakdown.vue'

// `toLocaleString('fr-FR')` uses narrow no-break spaces (U+202F / U+00A0) as the
// thousands separator — normalize them to a plain space before asserting.
const normalize = (text: string): string => text.replace(/[\u202f\u00a0]/g, ' ')

const mountBreak = (props: { productValue: number; payAmount?: number }) =>
  mount(CommissionBreakdown, { props })

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
})
