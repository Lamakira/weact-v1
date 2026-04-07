import { defineComponent, ref } from 'vue'
import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import ProducerWalletPage from '../ProducerWalletPage.vue'

vi.mock('@/features/wallet', () => ({
  WalletBalance: defineComponent({
    props: {
      balance: { type: Number, required: true },
      pendingEscrow: { type: Number, required: true },
      showWithdraw: { type: Boolean, default: true },
      showPendingEscrow: { type: Boolean, default: true },
      emptyStateDescription: { type: String, default: '' },
    },
    template: `
      <div
        data-testid="wallet-balance"
        :data-show-withdraw="String(showWithdraw)"
        :data-show-pending-escrow="String(showPendingEscrow)"
        :data-empty-state-description="emptyStateDescription"
      />
    `,
  }),
  WalletTransactionList: { template: '<div data-testid="wallet-transaction-list" />' },
  useWallet: () => ({
    balance: ref(15000),
    pendingEscrow: ref(0),
    transactions: ref([]),
    isLoading: ref(false),
    error: ref(null),
    hasMore: ref(false),
    fetchWallet: vi.fn(),
    loadMore: vi.fn(),
  }),
}))

describe('ProducerWalletPage', () => {
  it('renders wallet balance and transaction list', () => {
    const wrapper = mount(ProducerWalletPage)

    expect(wrapper.find('[data-testid="producer-wallet-page"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="wallet-balance"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="wallet-transaction-list"]').exists()).toBe(true)
  })

  it('displays page title', () => {
    const wrapper = mount(ProducerWalletPage)

    expect(wrapper.text()).toContain('Portefeuille')
    expect(wrapper.text()).toContain('Consultez votre solde')
  })

  it('does not render withdraw form or withdrawal requests', () => {
    const wrapper = mount(ProducerWalletPage)
    const html = wrapper.html()

    expect(html).not.toContain('withdraw-form')
    expect(html).not.toContain('withdrawal-request')
  })

  it('configures the wallet balance component for producer-specific copy and actions', () => {
    const wrapper = mount(ProducerWalletPage)
    const walletBalance = wrapper.get('[data-testid="wallet-balance"]')

    expect(walletBalance.attributes('data-show-withdraw')).toBe('false')
    expect(walletBalance.attributes('data-show-pending-escrow')).toBe('false')
    expect(walletBalance.attributes('data-empty-state-description')).toBe('Les remboursements et crédits apparaîtront ici.')
  })
})
