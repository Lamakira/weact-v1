import { storeToRefs } from 'pinia'
import type { ComputedRef, Ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'
import { authApi, getApiErrorDetails, getApiErrorMessage, getApiErrorCode } from '../services/authApi'
import type { FaceRegistrationForm, ProducerRegistrationForm, LoginForm, User } from '../types'

export interface AuthResult {
  success: boolean
  errors?: Record<string, string[]>
  message?: string
  errorCode?: string | null
}

export interface UseAuthReturn {
  login: (data: LoginForm) => Promise<AuthResult>
  registerFace: (data: FaceRegistrationForm) => Promise<AuthResult>
  registerProducer: (data: ProducerRegistrationForm) => Promise<AuthResult>
  logout: () => Promise<void>
  isAuthenticated: ComputedRef<boolean>
  isLoading: Ref<boolean>
  user: Ref<User | null>
  isFace: ComputedRef<boolean>
  isProducer: ComputedRef<boolean>
}

/**
 * Composable for authentication operations
 */
export function useAuth(): UseAuthReturn {
  const authStore = useAuthStore()
  const router = useRouter()
  const { isLoading, isAuthenticated, user, isFace, isProducer } = storeToRefs(authStore)

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
      const errorCode = getApiErrorCode(error)

      return { success: false, errors, message, errorCode }
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
      // API call failed but we still clear local state (graceful degradation)
      console.warn('[Auth] Logout API call failed, clearing local state anyway', error)
    } finally {
      authStore.clearAuth()
      authStore.setLoading(false)
      await router.push('/login')
    }
  }

  return {
    login,
    registerFace,
    registerProducer,
    logout,
    isAuthenticated,
    isLoading,
    user,
    isFace,
    isProducer,
  }
}
