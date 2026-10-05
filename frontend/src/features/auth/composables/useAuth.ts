import { storeToRefs } from 'pinia'
import type { ComputedRef, Ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notification'
import { useUgcValidationCountStore } from '@/stores/ugcValidationCount'
import { useRouter } from 'vue-router'
import { authApi, getApiErrorDetails, getApiErrorMessage, getApiErrorCode } from '../services/authApi'
import type {
  FaceRegistrationForm,
  ProducerRegistrationForm,
  LoginForm,
  User,
  GoogleExchangeResult,
  CompleteGoogleRegistrationData,
} from '../types'

interface AuthResult {
  success: boolean
  errors?: Record<string, string[]>
  message?: string
  errorCode?: string | null
}

interface GoogleExchangeAuthResult extends AuthResult {
  result?: GoogleExchangeResult
}

interface UseAuthReturn {
  login: (data: LoginForm) => Promise<AuthResult>
  registerFace: (data: FaceRegistrationForm) => Promise<AuthResult>
  registerProducer: (data: ProducerRegistrationForm) => Promise<AuthResult>
  exchangeGoogleCode: (code: string, nonce: string) => Promise<GoogleExchangeAuthResult>
  completeGoogleRegistration: (data: CompleteGoogleRegistrationData) => Promise<AuthResult>
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
  const notificationStore = useNotificationStore()
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

      // Initialize notification store (subscribe to WebSocket + fetch unread count)
      notificationStore.subscribe()
      notificationStore.fetchUnreadCount()

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

      // Initialize notification store
      notificationStore.subscribe()
      notificationStore.fetchUnreadCount()

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

      // Initialize notification store
      notificationStore.subscribe()
      notificationStore.fetchUnreadCount()

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
   * Adopt a freshly issued session. Same order as login(): token, user, then the
   * notification store — subscribing before the token is stored would 401.
   */
  function adoptSession(token: string, newUser: User): void {
    // In-place account switch: the store's subscribe() is a no-op while already
    // subscribed, so the tab would keep receiving the previous account's events.
    // Leave the old channel first — unsubscribe() reads the current user id, so it
    // must run before setUser().
    const previousUserId = authStore.user?.id
    if (previousUserId != null && previousUserId !== newUser.id) {
      notificationStore.unsubscribe()
    }

    authStore.setToken(token)
    authStore.setUser(newUser)

    notificationStore.subscribe()
    notificationStore.fetchUnreadCount()
  }

  /**
   * Trade the one-shot code from the Google callback URL.
   *
   * Two outcomes: an existing account comes back with a token (session adopted
   * here), a brand-new one comes back needing the finalisation screen — nothing
   * has been created server-side at that point.
   */
  async function exchangeGoogleCode(code: string, nonce: string): Promise<GoogleExchangeAuthResult> {
    authStore.setLoading(true)

    try {
      const result = await authApi.exchangeGoogleCode(code, nonce)

      if (!result.needs_completion && result.token && result.user) {
        adoptSession(result.token, result.user)
      }

      return { success: true, result }
    } catch (error) {
      return {
        success: false,
        errors: getApiErrorDetails(error),
        message: getApiErrorMessage(error),
        errorCode: getApiErrorCode(error),
      }
    } finally {
      authStore.setLoading(false)
    }
  }

  /**
   * Create the account described by the finalisation screen.
   */
  async function completeGoogleRegistration(
    data: CompleteGoogleRegistrationData
  ): Promise<AuthResult> {
    authStore.setLoading(true)

    try {
      const response = await authApi.completeGoogleRegistration(data)

      adoptSession(response.data.token, response.data.user)

      return { success: true }
    } catch (error) {
      return {
        success: false,
        errors: getApiErrorDetails(error),
        message: getApiErrorMessage(error),
        errorCode: getApiErrorCode(error),
      }
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
      notificationStore.unsubscribe()
      // Reset le compteur de validation UGC pour éviter qu'il fuite d'un compte
      // Producteur à l'autre sur un re-login SPA sans reload (calque du reset
      // notification ci-dessus ; le store est un singleton non remis à 0 sinon).
      useUgcValidationCountStore().$reset()
      authStore.clearAuth()
      authStore.setLoading(false)
      await router.push('/login')
    }
  }

  return {
    login,
    registerFace,
    registerProducer,
    exchangeGoogleCode,
    completeGoogleRegistration,
    logout,
    isAuthenticated,
    isLoading,
    user,
    isFace,
    isProducer,
  }
}
