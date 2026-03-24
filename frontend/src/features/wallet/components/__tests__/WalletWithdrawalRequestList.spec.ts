import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import WalletWithdrawalRequestList from '../WalletWithdrawalRequestList.vue'

describe('WalletWithdrawalRequestList', () => {
  it('shows an empty state when there are no withdrawal requests', () => {
    const wrapper = mount(WalletWithdrawalRequestList, {
      props: {
        requests: [],
      },
    })

    expect(wrapper.text()).toContain('Aucune demande de retrait pour le moment.')
  })

  it('renders request statuses and rejection notes', () => {
    const wrapper = mount(WalletWithdrawalRequestList, {
      props: {
        requests: [
          {
            id: 12,
            amount: 25000,
            payment_mode: 'mtn',
            phone_number: '64000001',
            phone_country: 'bj',
            status: 'pending',
            notes: null,
            processed_at: null,
            created_at: '2026-03-24T10:00:00Z',
          },
          {
            id: 13,
            amount: 18000,
            payment_mode: 'moov',
            phone_number: '97000002',
            phone_country: 'bj',
            status: 'rejected',
            notes: 'Coordonnées invalides',
            processed_at: '2026-03-24T12:00:00Z',
            created_at: '2026-03-24T09:00:00Z',
          },
        ],
      },
    })

    expect(wrapper.text()).toContain('En attente')
    expect(wrapper.text()).toContain('Rejeté')
    expect(wrapper.text()).toContain('Coordonnées invalides')
    expect(wrapper.text()).toContain('MTN Mobile Money')
    expect(wrapper.text()).toContain('Moov Money')
  })
})
