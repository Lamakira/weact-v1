import { useAdminAuthStore } from '@/stores/adminAuth'
import { useRouter } from 'vue-router'
import {
  adminAuthApi,
  getApiErrorDetails,
  getApiErrorMessage,
  type AdminLoginForm,
} from '../services/adminAuthApi'

export interface AuthResult {
  success: boolean
  errors?: Record<string, string[]>
  message?: string
}

/**
 * Composable for admin authentication operations
 */
export function useAdminAuth() {
  const adminAuthStore = useAdminAuthStore()
  const router = useRouter()

  /**
   * Login an admin with email and password
   */
  async function login(data: AdminLoginForm): Promise<AuthResult> {
    adminAuthStore.setLoading(true)

    try {
      const response = await adminAuthApi.login(data)

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
    logout,
    isAuthenticated: adminAuthStore.isAuthenticated,
    isLoading: adminAuthStore.isLoading,
    admin: adminAuthStore.admin,
    adminName: adminAuthStore.adminName,
    adminEmail: adminAuthStore.adminEmail,
  }
}
