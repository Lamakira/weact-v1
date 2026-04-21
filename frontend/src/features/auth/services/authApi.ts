import apiClient, { getCsrfCookie } from '@/services/apiClient'
import type {
  FaceRegistrationForm,
  ProducerRegistrationForm,
  LoginForm,
  AuthResponse,
  ResetPasswordData,
} from '../types'

/**
 * Auth API service
 * Uses Laravel Sanctum SPA cookie-based authentication
 * CSRF cookie must be fetched before any state-changing requests
 */
export const authApi = {
  /**
   * Check if registration is currently enabled
   */
  async getRegistrationStatus(): Promise<{ data: { enabled: boolean } }> {
    const response = await apiClient.get<{ data: { enabled: boolean } }>('/auth/registration-status')
    return response.data
  },

  /**
   * Register a new Face user
   */
  async registerFace(data: FaceRegistrationForm): Promise<AuthResponse> {
    // MANDATORY: Get CSRF cookie before registration (Sanctum SPA auth)
    await getCsrfCookie()
    const response = await apiClient.post<AuthResponse>('/auth/register/face', data)
    return response.data
  },

  /**
   * Register a new Producer user (Agency or Particulier)
   */
  async registerProducer(data: ProducerRegistrationForm): Promise<AuthResponse> {
    // MANDATORY: Get CSRF cookie before registration (Sanctum SPA auth)
    await getCsrfCookie()
    const response = await apiClient.post<AuthResponse>('/auth/register/producer', data)
    return response.data
  },

  /**
   * Login a user with email and password
   */
  async login(data: LoginForm): Promise<AuthResponse> {
    // MANDATORY: Get CSRF cookie before login (Sanctum SPA auth)
    await getCsrfCookie()
    const response = await apiClient.post<AuthResponse>('/auth/login', data)
    return response.data
  },

  /**
   * Logout the current user - revokes the server-side token
   * Note: Caller (useAuth) handles errors and clears local state regardless
   */
  async logout(): Promise<void> {
    await getCsrfCookie()
    await apiClient.post('/auth/logout')
  },

  /**
   * Request password reset email
   */
  async forgotPassword(email: string): Promise<void> {
    await getCsrfCookie()
    await apiClient.post('/auth/forgot-password', { email })
  },

  /**
   * Reset password with token
   */
  async resetPassword(data: ResetPasswordData): Promise<void> {
    await getCsrfCookie()
    await apiClient.post('/auth/reset-password', data)
  },

  /**
   * Resend email verification notification
   * Returns { sent: true } if email was sent, { verified: true } if already verified
   */
  async resendVerificationEmail(): Promise<{ sent?: boolean; verified?: boolean }> {
    await getCsrfCookie()
    const response = await apiClient.post<{
      data: { sent?: boolean; verified?: boolean }
      message: string
    }>('/email/verification-notification')
    return response.data.data
  },

  /**
   * Verify email with signed URL parameters
   */
  async verifyEmail(
    id: string,
    hash: string,
    expires: string,
    signature: string
  ): Promise<{ verified: boolean; already_verified?: boolean }> {
    const response = await apiClient.get<{
      data: { verified: boolean; already_verified?: boolean }
      message: string
    }>(`/auth/email/verify/${id}/${hash}`, {
      params: { expires, signature },
    })
    return response.data.data
  },

  /**
   * Get email verification status
   */
  async getVerificationStatus(): Promise<{ verified: boolean; email_verified_at: string | null }> {
    const response = await apiClient.get<{
      data: { verified: boolean; email_verified_at: string | null }
      message: string
    }>('/email/verification-status')
    return response.data.data
  },
}

export {
  formatApiError as getApiErrorMessage,
  getApiErrorCode,
  getApiErrorDetails,
} from '@/services/errorFormatter'
