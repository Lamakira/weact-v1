import { storeToRefs } from 'pinia'
import { useAdminAuthStore } from '@/stores/adminAuth'
import { useRouter } from 'vue-router'
import {
  adminAuthApi,
  getApiErrorDetails,
  getApiErrorMessage,
  type AdminLoginForm,
  type AdminTwoFactorLoginForm,
} from '../services/adminAuthApi'
import { getApiErrorCode } from '@/services/errorFormatter'

interface AuthResult {
  success: boolean
  errors?: Record<string, string[]>
  message?: string
  /** Set when the password was accepted but a second factor is required */
  twoFactorChallenge?: string
  errorCode?: string | null
}

/**
 * Composable for admin authentication operations
 */
export function useAdminAuth() {
  const adminAuthStore = useAdminAuthStore()
  const router = useRouter()
  const { isLoading, isAuthenticated, admin, adminName, adminEmail } = storeToRefs(adminAuthStore)

  /**
   * Login an admin with email and password
   */
  async function login(data: AdminLoginForm): Promise<AuthResult> {
    adminAuthStore.setLoading(true)

    try {
      const response = await adminAuthApi.login(data)

      // 2FA enabled: no token yet, only a short-lived challenge for step 2
      if ('two_factor_required' in response.data) {
        return { success: false, twoFactorChallenge: response.data.challenge }
      }

      // Store token and admin data
      adminAuthStore.setToken(response.data.token)
      adminAuthStore.setAdmin(response.data.admin)

      return { success: true }
    } catch (error) {
      const errors = getApiErrorDetails(error)
      const message = getApiErrorMessage(error)

      return { success: false, errors, message }
    } finally {
      adminAuthStore.setLoading(false)
    }
  }

  /**
   * Login step 2: challenge + TOTP code (or recovery code) -> token
   */
  async function verifyTwoFactor(data: AdminTwoFactorLoginForm): Promise<AuthResult> {
    adminAuthStore.setLoading(true)

    try {
      const response = await adminAuthApi.verifyTwoFactor(data)

      adminAuthStore.setToken(response.data.token)
      adminAuthStore.setAdmin(response.data.admin)

      return { success: true }
    } catch (error) {
      return {
        success: false,
        errors: getApiErrorDetails(error),
        message: getApiErrorMessage(error),
        errorCode: getApiErrorCode(error),
      }
    } finally {
      adminAuthStore.setLoading(false)
    }
  }

  /**
   * Logout the current admin
   * Calls API to revoke token, then clears local state regardless of API result
   */
  async function logout(): Promise<void> {
    adminAuthStore.setLoading(true)

    try {
      await adminAuthApi.logout()
    } catch (error) {
      console.warn('[AdminAuth] Logout API call failed, clearing local state anyway', error)
    } finally {
      adminAuthStore.clearAuth()
      adminAuthStore.setLoading(false)
      await router.push({ name: 'admin-login' })
    }
  }

  return {
    login,
    verifyTwoFactor,
    logout,
    isAuthenticated,
    isLoading,
    admin,
    adminName,
    adminEmail,
  }
}
