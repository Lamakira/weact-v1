import adminApiClient from './adminApiClient'

export type AdminSubscriptionPlan = 'starter' | 'pro' | 'elite'

export type AdminSubscriptionStatus =
  | 'pending_payment'
  | 'active'
  | 'expired'
  | 'cancelled'
  | 'failed'

export type AdminSubscriptionAuditAction =
  | 'manual_activate'
  | 'extend'
  | 'cancel'
  | 'correct_dates'
  | 'change_tier'

export interface AdminFaceSubscriptionAudit {
  id: string
  action: AdminSubscriptionAuditAction
  action_label: string
  notes: string
  previous_state: Record<string, unknown> | null
  new_state: Record<string, unknown>
  admin: {
    id: string | null
    name: string
    role: 'superadmin' | 'admin' | 'editor' | null
  }
  created_at: string | null
}

export interface AdminFaceSubscription {
  id: string
  plan: AdminSubscriptionPlan | null
  plan_label: string | null
  status: AdminSubscriptionStatus | null
  status_label: string | null
  starts_at: string | null
  expires_at: string | null
  cancelled_at: string | null
  paid_amount: number | null
  currency: string
  created_at: string | null
  updated_at: string | null
  audits: AdminFaceSubscriptionAudit[]
}

export interface AdminFaceSubscriptionIndexResponse {
  data: {
    face: { id: string; display_name: string }
    subscriptions: AdminFaceSubscription[]
  }
}

export interface AdminFaceSubscriptionMutationResponse {
  data: AdminFaceSubscription
  message: string
}

export interface ActivatePayload {
  plan: AdminSubscriptionPlan
  notes: string
  starts_at?: string
  duration_days?: number
}

export interface ExtendPayload {
  notes: string
  additional_days: number
}

export interface CancelPayload {
  notes: string
}

export interface CorrectPayload {
  notes: string
  starts_at?: string
  expires_at?: string
}

export interface ChangeTierPayload {
  notes: string
  new_plan: AdminSubscriptionPlan
}

interface RequestOptions {
  signal?: AbortSignal
}

export const adminFaceSubscriptionsApi = {
  async index(
    faceId: string,
    options: RequestOptions = {},
  ): Promise<AdminFaceSubscriptionIndexResponse> {
    const response = await adminApiClient.get<AdminFaceSubscriptionIndexResponse>(
      `/admin/faces/${encodeURIComponent(faceId)}/subscriptions`,
      { signal: options.signal },
    )
    return response.data
  },

  async activate(
    faceId: string,
    payload: ActivatePayload,
    options: RequestOptions = {},
  ): Promise<AdminFaceSubscriptionMutationResponse> {
    const response = await adminApiClient.post<AdminFaceSubscriptionMutationResponse>(
      `/admin/faces/${encodeURIComponent(faceId)}/subscriptions/activate`,
      payload,
      { signal: options.signal },
    )
    return response.data
  },

  async extend(
    subscriptionId: string,
    payload: ExtendPayload,
    options: RequestOptions = {},
  ): Promise<AdminFaceSubscriptionMutationResponse> {
    const response = await adminApiClient.post<AdminFaceSubscriptionMutationResponse>(
      `/admin/face-subscriptions/${encodeURIComponent(subscriptionId)}/extend`,
      payload,
      { signal: options.signal },
    )
    return response.data
  },

  async cancel(
    subscriptionId: string,
    payload: CancelPayload,
    options: RequestOptions = {},
  ): Promise<AdminFaceSubscriptionMutationResponse> {
    const response = await adminApiClient.post<AdminFaceSubscriptionMutationResponse>(
      `/admin/face-subscriptions/${encodeURIComponent(subscriptionId)}/cancel`,
      payload,
      { signal: options.signal },
    )
    return response.data
  },

  async correct(
    subscriptionId: string,
    payload: CorrectPayload,
    options: RequestOptions = {},
  ): Promise<AdminFaceSubscriptionMutationResponse> {
    const response = await adminApiClient.post<AdminFaceSubscriptionMutationResponse>(
      `/admin/face-subscriptions/${encodeURIComponent(subscriptionId)}/correct`,
      payload,
      { signal: options.signal },
    )
    return response.data
  },

  async changeTier(
    subscriptionId: string,
    payload: ChangeTierPayload,
    options: RequestOptions = {},
  ): Promise<AdminFaceSubscriptionMutationResponse> {
    const response = await adminApiClient.post<AdminFaceSubscriptionMutationResponse>(
      `/admin/face-subscriptions/${encodeURIComponent(subscriptionId)}/change-tier`,
      payload,
      { signal: options.signal },
    )
    return response.data
  },
}
