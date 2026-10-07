import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WalletBalance from '../WalletBalance.vue'

describe('WalletBalance — fonds en litige', () => {
  it('hides the "En litige" line when nothing is held', () => {
    const wrapper = mount(WalletBalance, { props: { balance: 1000, pendingEscrow: 500 } })

    expect(wrapper.find('[data-testid="held-in-dispute"]').exists()).toBe(false)
  })

  it('shows a separate "En litige" line with an explanation when funds are held', () => {
    const wrapper = mount(WalletBalance, { props: { balance: 1000, pendingEscrow: 500, heldInDispute: 90000 } })
    const line = wrapper.get('[data-testid="held-in-dispute"]')

    expect(line.text()).toContain('En litige')
    expect(line.text().replace(/\s/g, '')).toContain('90000')
    expect(line.text()).toContain('72 h')
    // Les fonds en attente restent affichés séparément.
    expect(wrapper.text()).toContain('Fonds en attente')
  })

  it('does not show the empty-wallet state while funds are held in dispute', () => {
    const wrapper = mount(WalletBalance, { props: { balance: 0, pendingEscrow: 0, heldInDispute: 90000 } })

    expect(wrapper.text()).not.toContain('Votre portefeuille est vide')
  })
})
