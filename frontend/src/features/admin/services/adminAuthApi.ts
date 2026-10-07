import adminApiClient, { getCsrfCookie } from './adminApiClient'

// Re-export error utilities from existing auth service
export {
  getApiErrorDetails,
  getApiErrorMessage,
} from '@/features/auth/services/authApi'

/**
 * Admin profile as returned by the API
 */
interface AdminProfile {
  id: string
  name: string
  email: string
  role: 'superadmin' | 'admin' | 'editor'
  two_factor_enabled?: boolean
}

/**
 * Admin token response (login without 2FA, or second login step)
 */
export interface AdminTokenResponse {
  data: {
    admin: AdminProfile
    token: string
  }
  message: string
  meta: Record<string, unknown>
}

/**
 * Admin login step 1 response when 2FA is enabled: no token, a short-lived challenge
 */
export interface AdminTwoFactorChallengeResponse {
  data: {
    two_factor_required: true
    challenge: string
  }
  message: string
  meta: Record<string, unknown>
}

export type AdminLoginResponse = AdminTokenResponse | AdminTwoFactorChallengeResponse

export function isTwoFactorChallenge(
  response: AdminLoginResponse,
): response is AdminTwoFactorChallengeResponse {
  return 'two_factor_required' in response.data && response.data.two_factor_required === true
}

/**
 * Admin login step 2 payload: challenge + TOTP code OR recovery code
 */
export interface AdminTwoFactorLoginForm {
  challenge: string
  code?: string
  recovery_code?: string
}

/**
 * Admin me response from API
 */
interface AdminMeResponse {
  data: AdminProfile
  message: string
}

/**
 * Admin login form data
 */
export interface AdminLoginForm {
  email: string
  password: string
}

/**
 * Admin reset password form data
 */
export interface AdminResetPasswordForm {
  token: string
  email: string
  password: string
  password_confirmation: string
}

/**
 * Admin auth API service
 */
export const adminAuthApi = {
  /**
   * Login an admin with email and password
   */
  async login(data: AdminLoginForm): Promise<AdminLoginResponse> {
    await getCsrfCookie()
    const response = await adminApiClient.post<AdminLoginResponse>('/admin/login', data)
    return response.data
  },

  /**
   * Login step 2: exchange the challenge + TOTP/recovery code for a token
   */
  async verifyTwoFactor(data: AdminTwoFactorLoginForm): Promise<AdminTokenResponse> {
    await getCsrfCookie()
    const response = await adminApiClient.post<AdminTokenResponse>('/admin/login/two-factor', data)
    return response.data
  },

  /**
   * Logout the current admin - revokes the server-side token
   */
  async logout(): Promise<void> {
    await getCsrfCookie()
    await adminApiClient.post('/admin/logout')
  },

  /**
   * Get the authenticated admin's profile
   */
  async getMe(): Promise<AdminMeResponse> {
    const response = await adminApiClient.get<AdminMeResponse>('/admin/me')
    return response.data
  },

  /**
   * Request a password reset link for the given admin email
   */
  async forgotPassword(email: string): Promise<{ message: string }> {
    await getCsrfCookie()
    const response = await adminApiClient.post<{ message: string }>('/admin/forgot-password', {
      email,
    })
    return response.data
  },

  /**
   * Reset admin password with a valid token
   */
  async resetPassword(data: AdminResetPasswordForm): Promise<{ message: string }> {
    await getCsrfCookie()
    const response = await adminApiClient.post<{ message: string }>('/admin/reset-password', data)
    return response.data
  },
}
