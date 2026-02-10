import axios, { type AxiosInstance, type AxiosError, type InternalAxiosRequestConfig } from 'axios'
import type { ApiError } from '@/features/auth/types'
import type { Router } from 'vue-router'
import type { Pinia } from 'pinia'
import { getXsrfTokenFromCookie } from '@/utils/csrf'
import { getCsrfCookie } from '@/services/apiClient'

/**
 * Lazy-loaded router instance to avoid circular dependency issues
 * Must be set after app initialization via setAdminRouter()
 */
let routerInstance: Router | null = null

/**
 * Lazy-loaded pinia instance to avoid initialization issues
 * Must be set after app initialization via setAdminPinia()
 */
let piniaInstance: Pinia | null = null

/**
 * Set the router instance for use in admin interceptors
 * Must be called after app creation in main.ts
 */
export function setAdminRouter(router: Router): void {
  routerInstance = router
}

/**
 * Set the pinia instance for use in admin interceptors
 * Must be called after app creation in main.ts
 */
export function setAdminPinia(pinia: Pinia): void {
  piniaInstance = pinia
}

/**
 * API Base URL from environment
 */
const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1'

/**
 * Storage keys for admin auth (separate from user auth)
 */
const ADMIN_TOKEN_KEY = 'admin_auth_token'
const ADMIN_USER_KEY = 'admin_auth_user'

/**
 * Create configured Axios instance for admin API requests
 * Uses separate localStorage keys from the user API client
 */
const adminApiClient: AxiosInstance = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  timeout: 30000,
  withCredentials: true,
})

/**
 * Request interceptor to add admin auth token and XSRF token
 */
adminApiClient.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    const token = localStorage.getItem(ADMIN_TOKEN_KEY)
    if (token && config.headers) {
      config.headers.Authorization = `Bearer ${token}`
    }

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
 * Admin auth paths that should NOT trigger redirect on 401
 */
const ADMIN_AUTH_PATHS = ['/admin/login']

/**
 * Check if current path is an admin auth page
 */
function isOnAdminAuthPage(): boolean {
  if (!routerInstance) return false
  const currentPath = routerInstance.currentRoute.value.path
  return ADMIN_AUTH_PATHS.some((path) => currentPath.startsWith(path))
}

/**
 * Response interceptor for error handling
 * On 401: clears admin auth state and redirects to /admin/login
 */
adminApiClient.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiError>) => {
    if (error.response?.status === 401) {
      // Clear localStorage
      localStorage.removeItem(ADMIN_TOKEN_KEY)
      localStorage.removeItem(ADMIN_USER_KEY)

      // Clear Pinia admin auth store if available
      if (piniaInstance) {
        import('@/stores/adminAuth').then(({ useAdminAuthStore }) => {
          const adminAuthStore = useAdminAuthStore(piniaInstance!)
          adminAuthStore.clearAuth()
        })
      }

      // Redirect to admin login with session-expired message and preserve redirect context
      if (routerInstance && !isOnAdminAuthPage()) {
        const currentPath = routerInstance.currentRoute.value.fullPath
        routerInstance.push({
          name: 'admin-login',
          query: { message: 'session-expired', redirect: currentPath },
        })
      }
    }
    return Promise.reject(error)
  },
)

/**
 * Helper to store admin auth token
 */
export function setAdminAuthToken(token: string): void {
  localStorage.setItem(ADMIN_TOKEN_KEY, token)
}

/**
 * Helper to remove admin auth token
 */
export function removeAdminAuthToken(): void {
  localStorage.removeItem(ADMIN_TOKEN_KEY)
}

/**
 * Helper to get stored admin auth token
 */
export function getAdminAuthToken(): string | null {
  return localStorage.getItem(ADMIN_TOKEN_KEY)
}

/**
 * Helper to store admin user data
 */
export function setStoredAdmin(admin: {
  id: number
  name: string
  email: string
  role: 'superadmin' | 'admin' | 'editor'
}): void {
  localStorage.setItem(ADMIN_USER_KEY, JSON.stringify(admin))
}

/**
 * Helper to get stored admin user data
 */
export function getStoredAdmin(): {
  id: number
  name: string
  email: string
  role: 'superadmin' | 'admin' | 'editor'
} | null {
  const stored = localStorage.getItem(ADMIN_USER_KEY)
  if (stored) {
    try {
      return JSON.parse(stored)
    } catch {
      return null
    }
  }
  return null
}

/**
 * Helper to remove stored admin user data
 */
export function removeStoredAdmin(): void {
  localStorage.removeItem(ADMIN_USER_KEY)
}

export { getCsrfCookie }
export default adminApiClient
