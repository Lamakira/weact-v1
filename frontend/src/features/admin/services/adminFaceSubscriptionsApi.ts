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

export interface AdminSubscriptionFaceSummary {
  id: string
  nom: string | null
  prenom: string | null
  username: string | null
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
  paid_at?: string | null
  currency: string
  created_at: string | null
  updated_at: string | null
  /** Présent uniquement sur la liste transversale (relation `face` eager-loaded). */
  face?: AdminSubscriptionFaceSummary
  audits: AdminFaceSubscriptionAudit[]
}

/** Ligne de la liste transversale : bloc `face` présent, audits non chargés. */
export type AdminSubscriptionListItem = Omit<AdminFaceSubscription, 'audits' | 'face'> & {
  face: AdminSubscriptionFaceSummary
}

export interface AdminSubscriptionListParams {
  page?: number
  per_page?: number
  plan?: string
  status?: string
  search?: string
  sort?: 'expires_at_asc' | 'expires_at_desc'
}

export interface AdminSubscriptionListResponse {
  data: AdminSubscriptionListItem[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export interface AdminSubscriptionStats {
  active_by_plan: {
    starter: number
    pro: number
    elite: number
    total: number
  }
  revenue: {
    current_month: number
    total: number
    currency: string
  }
  expiring_within_30_days: number
  pending_payment_count: number
  failed_count: number
}

export interface AdminSubscriptionStatsResponse {
  data: AdminSubscriptionStats
  message: string
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
  async list(
    params: AdminSubscriptionListParams = {},
    options: RequestOptions = {},
  ): Promise<AdminSubscriptionListResponse> {
    const response = await adminApiClient.get<AdminSubscriptionListResponse>(
      '/admin/face-subscriptions',
      { params, signal: options.signal },
    )
    return response.data
  },

  async stats(options: RequestOptions = {}): Promise<AdminSubscriptionStatsResponse> {
    const response = await adminApiClient.get<AdminSubscriptionStatsResponse>(
      '/admin/face-subscriptions/stats',
      { signal: options.signal },
    )
    return response.data
  },

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
