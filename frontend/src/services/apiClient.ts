import axios, { type AxiosInstance, type AxiosError, type InternalAxiosRequestConfig } from 'axios'
import type { ApiError } from '@/features/auth/types'

/**
 * API Base URL from environment
 */
const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1'

/**
 * Storage key for auth token
 */
const TOKEN_KEY = 'auth_token'

/**
 * Create configured Axios instance
 */
const apiClient: AxiosInstance = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  timeout: 30000,
})

/**
 * Request interceptor to add auth token
 */
apiClient.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    const token = localStorage.getItem(TOKEN_KEY)
    if (token && config.headers) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error: AxiosError) => {
    return Promise.reject(error)
  }
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
  }
)

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
