import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'
import { authApi, getApiErrorDetails, getApiErrorMessage } from '../services/authApi'
import type { FaceRegistrationForm, ProducerRegistrationForm } from '../types'

export interface AuthResult {
  success: boolean
  errors?: Record<string, string[]>
  message?: string
}

export interface UseAuthReturn {
  registerFace: (data: FaceRegistrationForm) => Promise<AuthResult>
  registerProducer: (data: ProducerRegistrationForm) => Promise<AuthResult>
  logout: () => void
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
   */
  function logout(): void {
    authStore.clearAuth()
    router.push('/login')
  }

  return {
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

export default useAuth
