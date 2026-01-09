import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'
import { authApi, getApiErrorDetails, getApiErrorMessage } from '../services/authApi'
import type { FaceRegistrationForm, ProducerRegistrationForm, LoginForm } from '../types'

export interface AuthResult {
  success: boolean
  errors?: Record<string, string[]>
  message?: string
}

export interface UseAuthReturn {
  login: (data: LoginForm) => Promise<AuthResult>
  registerFace: (data: FaceRegistrationForm) => Promise<AuthResult>
  registerProducer: (data: ProducerRegistrationForm) => Promise<AuthResult>
  logout: () => Promise<void>
  isAuthenticated: ReturnType<typeof useAuthStore>['isAuthenticated']
  isLoading: ReturnType<typeof useAuthStore>['isLoading']
  user: ReturnType<typeof useAuthStore>['user']
  isFace: ReturnType<typeof useAuthStore>['isFace']
  isProducer: ReturnType<typeof useAuthStore>['isProducer']
}

/**
 * Composable for authentication operations
 */
export function useAuth(): UseAuthReturn {
  const authStore = useAuthStore()
  const router = useRouter()

  /**
   * Login a user with email and password
   */
  async function login(data: LoginForm): Promise<AuthResult> {
    authStore.setLoading(true)

    try {
      const response = await authApi.login(data)

      // Store token and user data
      authStore.setToken(response.data.token)
      authStore.setUser(response.data.user)

      return { success: true }
    } catch (error) {
      const errors = getApiErrorDetails(error)
      const message = getApiErrorMessage(error)

      return { success: false, errors, message }
    } finally {
      authStore.setLoading(false)
    }
  }

  /**
   * Register a new Face user
   */
  async function registerFace(data: FaceRegistrationForm): Promise<AuthResult> {
    authStore.setLoading(true)

    try {
      const response = await authApi.registerFace(data)

      // Store token and user data
      authStore.setToken(response.data.token)
      authStore.setUser(response.data.user)

      return { success: true }
    } catch (error) {
      const errors = getApiErrorDetails(error)
      const message = getApiErrorMessage(error)

      return { success: false, errors, message }
    } finally {
      authStore.setLoading(false)
    }
  }

  /**
   * Register a new Producer user (Agency or Particulier)
   */
  async function registerProducer(data: ProducerRegistrationForm): Promise<AuthResult> {
    authStore.setLoading(true)

    try {
      const response = await authApi.registerProducer(data)

      // Store token and user data
      authStore.setToken(response.data.token)
      authStore.setUser(response.data.user)

      return { success: true }
    } catch (error) {
      const errors = getApiErrorDetails(error)
      const message = getApiErrorMessage(error)

      return { success: false, errors, message }
    } finally {
      authStore.setLoading(false)
    }
  }

  /**
   * Logout the current user
   * Calls API to revoke token, then clears local state regardless of API result
   */
  async function logout(): Promise<void> {
    authStore.setLoading(true)

    try {
      await authApi.logout()
    } catch (error) {
      console.warn('[Auth] Logout API call failed, clearing local state anyway', error)
    } finally {
      authStore.clearAuth()
      authStore.setLoading(false)
      // Redirect to login page; guard against undefined push in tests
      const push = router.push?.bind(router) ?? router.push
      if (push) {
        await push('/login')
      }
    }
  }

  return {
    login,
    registerFace,
    registerProducer,
    logout,
    isAuthenticated: authStore.isAuthenticated,
    isLoading: authStore.isLoading,
    user: authStore.user,
    isFace: authStore.isFace,
    isProducer: authStore.isProducer,
  }
}
