export interface WalletTransaction {
  id: number
  type: 'credit' | 'debit'
  amount: number
  reference: string
  description: string
  booking_id: string | null
  status: 'pending' | 'completed' | 'failed' | null
  created_at: string
}

export interface WalletWithdrawalRequest {
  id: string
  amount: number
  payment_mode: 'mtn' | 'moov' | 'momo_test'
  phone_number: string
  phone_country: 'bj' | 'tg' | 'ci' | 'sn' | 'bf'
  status: 'pending' | 'approved' | 'rejected'
  notes: string | null
  processed_at: string | null
  created_at: string
}

export interface WalletTransactionsMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface WalletData {
  balance: number
  pending_escrow: number
  withdrawal_mode: 'manual' | 'fedapay'
  withdrawal_requests: WalletWithdrawalRequest[]
  /** Flat array of transactions (no nested `data` key) */
  transactions: WalletTransaction[]
  transactions_meta: WalletTransactionsMeta
}

export interface WalletResponse {
  data: WalletData
}
