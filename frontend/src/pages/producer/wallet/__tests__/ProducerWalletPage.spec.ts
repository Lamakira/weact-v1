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
    emits: ['withdraw'],
    template: `
      <div
        data-testid="wallet-balance"
        :data-show-withdraw="String(showWithdraw)"
        :data-show-pending-escrow="String(showPendingEscrow)"
        :data-empty-state-description="emptyStateDescription"
      >
        <button data-testid="withdraw-trigger" @click="$emit('withdraw')">Retirer</button>
      </div>
    `,
  }),
  WalletTransactionList: { template: '<div data-testid="wallet-transaction-list" />' },
  WalletWithdrawForm: defineComponent({
    props: {
      balance: { type: Number, required: true },
      withdrawalMode: { type: String, required: true },
      isWithdrawing: { type: Boolean, required: true },
      error: { type: String, default: null },
    },
    emits: ['submit', 'cancel'],
    template: '<div data-testid="wallet-withdraw-form" />',
  }),
  WalletWithdrawalRequestList: defineComponent({
    props: {
      requests: { type: Array, required: true },
    },
    template: '<div data-testid="wallet-withdrawal-request-list" />',
  }),
  useWallet: () => ({
    balance: ref(15000),
    pendingEscrow: ref(0),
    withdrawalMode: ref('manual'),
    withdrawalRequests: ref([]),
    transactions: ref([]),
    isLoading: ref(false),
    error: ref(null),
    hasMore: ref(false),
    fetchWallet: vi.fn(),
    loadMore: vi.fn(),
    isWithdrawing: ref(false),
    withdrawError: ref(null),
    withdraw: vi.fn(),
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

  it('renders withdrawal request list', () => {
    const wrapper = mount(ProducerWalletPage)

    expect(wrapper.find('[data-testid="wallet-withdrawal-request-list"]').exists()).toBe(true)
  })

  it('shows withdraw form when withdraw event is emitted from WalletBalance', async () => {
    const wrapper = mount(ProducerWalletPage)

    expect(wrapper.find('[data-testid="wallet-withdraw-form"]').exists()).toBe(false)

    await wrapper.find('[data-testid="withdraw-trigger"]').trigger('click')

    expect(wrapper.find('[data-testid="wallet-withdraw-form"]').exists()).toBe(true)
  })

  it('configures the wallet balance component for producer-specific copy', () => {
    const wrapper = mount(ProducerWalletPage)
    const walletBalance = wrapper.get('[data-testid="wallet-balance"]')

    expect(walletBalance.attributes('data-show-pending-escrow')).toBe('false')
    expect(walletBalance.attributes('data-empty-state-description')).toBe('Les remboursements et crédits apparaîtront ici.')
  })

  it('does not pass show-withdraw=false (withdraw is now enabled)', () => {
    const wrapper = mount(ProducerWalletPage)
    const walletBalance = wrapper.get('[data-testid="wallet-balance"]')

    // show-withdraw defaults to true when not explicitly set to false
    expect(walletBalance.attributes('data-show-withdraw')).toBe('true')
  })
})
