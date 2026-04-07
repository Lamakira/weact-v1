import adminApiClient, { getCsrfCookie } from './adminApiClient'

export interface FinanceOverview {
  retirable: {
    amount: number
    face_balance: number
    producer_balance: number
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
  withdrawal_requests: {
    pending_count: number
    pending_amount: number
  }
  fedapay_balance: {
    available: boolean
    total_amount: number | null
    refreshed_at: string
    error: string | null
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

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface WithdrawalRequestEntry {
  id: number
  amount: number
  payment_mode: string
  phone_number: string
  phone_country: string
  status: 'pending' | 'approved' | 'rejected'
  notes: string | null
  processed_at: string | null
  created_at: string
  user_email: string | null
  user_name: string | null
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

  async getWithdrawalRequests(params?: {
    status?: string
    page?: number
  }): Promise<{ data: WithdrawalRequestEntry[]; meta: PaginationMeta; message: string }> {
    const response = await adminApiClient.get('/admin/finance/withdrawal-requests', {
      params,
    })
    return response.data
  },

  async approveWithdrawalRequest(id: number, notes?: string): Promise<{ message: string }> {
    await getCsrfCookie()
    const response = await adminApiClient.post(`/admin/finance/withdrawal-requests/${id}/approve`, {
      notes,
    })
    return response.data
  },

  async rejectWithdrawalRequest(id: number, notes: string): Promise<{ message: string }> {
    await getCsrfCookie()
    const response = await adminApiClient.post(`/admin/finance/withdrawal-requests/${id}/reject`, {
      notes,
    })
    return response.data
  },
}
