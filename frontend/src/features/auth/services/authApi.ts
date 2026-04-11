import apiClient, { getCsrfCookie } from '@/services/apiClient'
import type { AxiosError } from 'axios'
import type {
  FaceRegistrationForm,
  ProducerRegistrationForm,
  LoginForm,
  AuthResponse,
  ApiError,
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

/**
 * Type guard to check if error is an API error response
 */
function isApiError(error: unknown): error is AxiosError<ApiError> {
  return (
    typeof error === 'object' &&
    error !== null &&
    'response' in error &&
    typeof (error as AxiosError).response?.data === 'object' &&
    (error as AxiosError<ApiError>).response?.data?.error !== undefined
  )
}

/**
 * Type guard to check if error is an Axios error
 */
function isAxiosError(error: unknown): error is AxiosError {
  return (
    typeof error === 'object' &&
    error !== null &&
    'isAxiosError' in error &&
    (error as AxiosError).isAxiosError
  )
}

/**
 * Get HTTP status code from error
 */
function getErrorStatus(error: unknown): number | undefined {
  if (isAxiosError(error)) {
    return error.response?.status
  }
  return undefined
}

/**
 * Extract error details from API error response
 */
export function getApiErrorDetails(error: unknown): Record<string, string[]> {
  if (isApiError(error) && error.response?.data?.error?.details) {
    return error.response.data.error.details
  }
  return {}
}

/**
 * Extract error code from API error response
 */
export function getApiErrorCode(error: unknown): string | null {
  if (isApiError(error) && error.response?.data?.error?.code) {
    return error.response.data.error.code
  }
  return null
}

/**
 * Extract user-friendly error message from API error response
 * Handles different HTTP status codes with appropriate French messages
 */
export function getApiErrorMessage(error: unknown): string {
  const status = getErrorStatus(error)

  // First, try to get the actual message from API error response
  if (isApiError(error)) {
    const data = error.response?.data
    if (data?.error?.message) {
      return data.error.message
    }
  }

  // Laravel validation errors (422) have { message: "...", errors: {...} }
  // Extract the specific message instead of falling through to generic 422 text
  const axiosErr = error as AxiosError<{ message?: string }>
  if (axiosErr?.response?.data?.message && typeof axiosErr.response.data.message === 'string') {
    return axiosErr.response.data.message
  }

  // Handle specific status codes with fallback user-friendly messages
  switch (status) {
    case 419:
      return 'Votre session a expiré, veuillez rafraîchir la page.'
    case 422:
      return 'Les données fournies ne sont pas valides.'
    case 429:
      return 'Trop de tentatives. Veuillez réessayer dans quelques instants.'
    case 500:
    case 502:
    case 503:
      return 'Le serveur est temporairement indisponible. Veuillez réessayer.'
    case 401:
      return 'Vous n\'êtes pas autorisé à effectuer cette action.'
    case 403:
      return 'Accès refusé.'
    case 404:
      return 'La ressource demandée n\'existe pas.'
    default:
      // Generic error message
      return 'Une erreur est survenue. Veuillez réessayer.'
  }
}
