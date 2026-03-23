import adminApiClient from './adminApiClient'

export interface FinanceOverview {
  retirable: {
    amount: number
    label: string
  }
  faces: {
    amount: number
    wallet_balance: number
    escrow_booking: number
    escrow_mission: number
    label: string
  }
  platform: {
    amount: number
    from_bookings: number
    from_missions: number
    label: string
  }
  volume: {
    bookings: number
    missions: number
    total: number
  }
  withdrawals: {
    total_amount: number
    count: number
  }
}

export interface WithdrawalEntry {
  id: number
  amount: number
  description: string
  reference: string
  created_at: string
  user_email: string | null
}

export const adminFinanceApi = {
  async getOverview(): Promise<{ data: FinanceOverview; message: string }> {
    const response = await adminApiClient.get('/admin/finance/overview')
    return response.data
  },

  async getRecentWithdrawals(): Promise<{ data: WithdrawalEntry[]; message: string }> {
    const response = await adminApiClient.get('/admin/finance/recent-withdrawals')
    return response.data
  },
}
