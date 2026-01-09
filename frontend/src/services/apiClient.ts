import axios, { type AxiosInstance, type AxiosError, type InternalAxiosRequestConfig } from 'axios'
import type { ApiError } from '@/features/auth/types'

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
 * Response interceptor for error handling
 */
apiClient.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiError>) => {
    // Handle 401 Unauthorized - clear token and redirect to login
    if (error.response?.status === 401) {
      localStorage.removeItem(TOKEN_KEY)
      // Could emit event or redirect to login
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
