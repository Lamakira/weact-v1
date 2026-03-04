import apiClient from '@/services/apiClient'
import type { WalletResponse } from '../types/wallet'

export const walletApi = {
  async getWallet(page = 1): Promise<WalletResponse> {
    const response = await apiClient.get<WalletResponse>('/wallet', {
      params: { page },
    })
    return response.data
  },
}
