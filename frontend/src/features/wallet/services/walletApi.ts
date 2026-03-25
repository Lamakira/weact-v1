import apiClient from '@/services/apiClient'
import type { WalletResponse } from '../types/wallet'

export interface WithdrawPayload {
  amount: number
  payment_mode: 'mtn' | 'moov' | 'celtiis' | 'momo_test'
  phone_number: string
  phone_country: 'bj' | 'tg' | 'ci' | 'sn' | 'bf'
}

export const walletApi = {
  async getWallet(page = 1): Promise<WalletResponse> {
    const response = await apiClient.get<WalletResponse>('/wallet', {
      params: { page },
    })
    return response.data
  },

  async withdraw(payload: WithdrawPayload): Promise<void> {
    await apiClient.post('/wallet/withdraw', payload)
  },
}
