import adminApiClient, { getCsrfCookie } from './adminApiClient'

// Re-export error utilities from existing auth service
export {
  isApiError,
  isAxiosError,
  getErrorStatus,
  getApiErrorDetails,
  getApiErrorCode,
  getApiErrorMessage,
} from '@/features/auth/services/authApi'

/**
 * Admin auth response from API
 */
export interface AdminAuthResponse {
  data: {
    admin: {
      id: number
      name: string
      email: string
      role: 'superadmin' | 'admin' | 'editor'
    }
    token: string
  }
  message: string
  meta: Record<string, unknown>
}

/**
 * Admin me response from API
 */
export interface AdminMeResponse {
  data: {
    id: number
    name: string
    email: string
    role: 'superadmin' | 'admin' | 'editor'
  }
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
  async login(data: AdminLoginForm): Promise<AdminAuthResponse> {
    await getCsrfCookie()
    const response = await adminApiClient.post<AdminAuthResponse>('/admin/login', data)
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
