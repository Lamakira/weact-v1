import axios, { type AxiosInstance, type AxiosError, type InternalAxiosRequestConfig } from 'axios'
import type { ApiError } from '@/features/auth/types'
import type { Router } from 'vue-router'
import type { Pinia } from 'pinia'

/**
 * Lazy-loaded router instance to avoid circular dependency issues
 * Must be set after app initialization via setRouter()
 */
let routerInstance: Router | null = null

/**
 * Lazy-loaded pinia instance to avoid initialization issues
 * Must be set after app initialization via setPinia()
 */
let piniaInstance: Pinia | null = null

/**
 * Set the router instance for use in interceptors
 * Must be called after app creation in main.ts
 */
export function setRouter(router: Router): void {
  routerInstance = router
}

/**
 * Set the pinia instance for use in interceptors
 * Must be called after app creation in main.ts
 */
export function setPinia(pinia: Pinia): void {
  piniaInstance = pinia
}

/**
 * API Base URL from environment
 */
const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1'

/**
 * Backend Base URL (without /api/v1) for Sanctum CSRF cookie
 */
const BACKEND_URL = import.meta.env.VITE_BACKEND_URL || 'http://localhost:8000'

/**
 * Storage key for auth token
 */
const TOKEN_KEY = 'auth_token'

/**
 * Get XSRF token from cookies
 * Required for cross-origin requests where Axios automatic XSRF handling doesn't work
 */
function getXsrfTokenFromCookie(): string | null {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  if (match && match[1]) {
    // The cookie value is URL encoded, decode it
    return decodeURIComponent(match[1])
  }
  return null
}

/**
 * Configure Axios defaults for Sanctum SPA authentication
 */
axios.defaults.withCredentials = true

/**
 * Create configured Axios instance for API requests
 */
const apiClient: AxiosInstance = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  timeout: 30000,
  withCredentials: true,
})

/**
 * Request interceptor to add auth token and XSRF token
 */
apiClient.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    // Add Bearer token if available (for token-based auth after login)
    const token = localStorage.getItem(TOKEN_KEY)
    if (token && config.headers) {
      config.headers.Authorization = `Bearer ${token}`
    }

    // CRITICAL: Manually add XSRF token for cross-origin requests
    // Axios automatic XSRF handling does NOT work for cross-origin requests
    if (config.method && !['get', 'head', 'options'].includes(config.method.toLowerCase())) {
      const xsrfToken = getXsrfTokenFromCookie()
      if (xsrfToken && config.headers) {
        config.headers['X-XSRF-TOKEN'] = xsrfToken
      }
    }

    return config
  },
  (error: AxiosError) => {
    return Promise.reject(error)
  },
)

/**
 * Auth paths that should NOT trigger redirect on 401
 * These are pages where 401 is expected (e.g., invalid credentials)
 */
const AUTH_PATHS = ['/login', '/register', '/forgot-password', '/reset-password']

/**
 * Check if current path is an auth page
 */
function isOnAuthPage(): boolean {
  if (!routerInstance) return false
  const currentPath = routerInstance.currentRoute.value.path
  return AUTH_PATHS.some((path) => currentPath.startsWith(path))
}

/**
 * Response interceptor for error handling
 */
apiClient.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiError>) => {
    // Handle 401 Unauthorized - clear ALL auth data and redirect to login
    if (error.response?.status === 401) {
      // Clear localStorage
      localStorage.removeItem(TOKEN_KEY)
      localStorage.removeItem('auth_user')

      // Clear Pinia auth store if available
      if (piniaInstance) {
        // Dynamic import to avoid circular dependency
        import('@/stores/auth').then(({ useAuthStore }) => {
          const authStore = useAuthStore(piniaInstance!)
          authStore.clearAuth()
        })
      }

      // Redirect to login with session-expired message if not already on auth page
      if (routerInstance && !isOnAuthPage()) {
        routerInstance.push({
          name: 'login',
          query: { message: 'session-expired' },
        })
      }
    }
    return Promise.reject(error)
  },
)

/**
 * Fetch CSRF cookie from Sanctum before making stateful requests
 * MUST be called before any POST/PUT/PATCH/DELETE requests (login, register, etc.)
 * This is required for Laravel Sanctum SPA cookie-based authentication
 *
 * @throws {Error} If the CSRF cookie request fails
 */
export async function getCsrfCookie(): Promise<void> {
  await axios.get(`${BACKEND_URL}/sanctum/csrf-cookie`, {
    withCredentials: true,
  })
}

/**
 * Helper to store auth token
 */
export function setAuthToken(token: string): void {
  localStorage.setItem(TOKEN_KEY, token)
}

/**
 * Helper to remove auth token
 */
export function removeAuthToken(): void {
  localStorage.removeItem(TOKEN_KEY)
}

/**
 * Helper to get stored auth token
 */
export function getAuthToken(): string | null {
  return localStorage.getItem(TOKEN_KEY)
}

export default apiClient
