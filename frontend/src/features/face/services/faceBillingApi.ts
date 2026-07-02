import apiClient from '@/services/apiClient'

/**
 * Face billing tab data layer.
 *
 * Modeled on `adminFaceSubscriptionsApi`: a small, typed wrapper over the real
 * Face subscription endpoints. The current-subscription status + the pending
 * payment flow are read through the shared `useSubscriptionStatus` /
 * `useSubscriptionPayment` composables (same source as the dashboard nudge and
 * the pricing page), so this service only owns the read-only history list that
 * has no composable equivalent.
 *
 *  - getHistory → GET /face/subscriptions/history (the Face's own subscription
 *    rows, newest first).
 */

export type FaceBillingSubscriptionStatus =
  | 'pending_payment'
  | 'active'
  | 'expired'
  | 'cancelled'
  | 'failed'

export type FaceBillingPlan = 'starter' | 'pro' | 'elite'

export interface FaceBillingHistoryItem {
  id: string
  plan: FaceBillingPlan | null
  plan_label: string | null
  status: FaceBillingSubscriptionStatus | null
  status_label: string | null
  starts_at: string | null
  expires_at: string | null
  cancelled_at: string | null
  paid_amount: number | null
  currency: string
  provider: string | null
  provider_reference: string | null
  created_at: string | null
}

export interface FaceBillingHistoryResponse {
  data: FaceBillingHistoryItem[]
}

interface RequestOptions {
  signal?: AbortSignal
}

export const faceBillingApi = {
  async getHistory(options: RequestOptions = {}): Promise<FaceBillingHistoryResponse> {
    const response = await apiClient.get<FaceBillingHistoryResponse>('/face/subscriptions/history', {
      signal: options.signal,
    })
    return response.data
  },
}
