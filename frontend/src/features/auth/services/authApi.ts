import apiClient, { getCsrfCookie } from '@/services/apiClient'
import type { AxiosError } from 'axios'
import type {
  FaceRegistrationForm,
  ProducerRegistrationForm,
  AuthResponse,
  ApiError,
} from '../types'

/**
 * Auth API service
 * Uses Laravel Sanctum SPA cookie-based authentication
 * CSRF cookie must be fetched before any state-changing requests
 */
export const authApi = {
  /**
   * Register a new Face user
   */
  async registerFace(data: FaceRegistrationForm): Promise<AuthResponse> {
    // MANDATORY: Get CSRF cookie before registration (Sanctum SPA auth)
    await getCsrfCookie()
    const response = await apiClient.post<AuthResponse>('/auth/register/face', data)
    return response.data
  },

  /**
   * Register a new Producer user (Agency or Particulier)
   */
  async registerProducer(data: ProducerRegistrationForm): Promise<AuthResponse> {
    // MANDATORY: Get CSRF cookie before registration (Sanctum SPA auth)
    await getCsrfCookie()
    const response = await apiClient.post<AuthResponse>('/auth/register/producer', data)
    return response.data
  },
}

/**
 * Type guard to check if error is an API error response
 */
export function isApiError(error: unknown): error is AxiosError<ApiError> {
  return (
    typeof error === 'object' &&
    error !== null &&
    'response' in error &&
    typeof (error as AxiosError).response?.data === 'object' &&
    (error as AxiosError<ApiError>).response?.data?.error !== undefined
  )
}

/**
 * Type guard to check if error is an Axios error
 */
export function isAxiosError(error: unknown): error is AxiosError {
  return (
    typeof error === 'object' &&
    error !== null &&
    'isAxiosError' in error &&
    (error as AxiosError).isAxiosError === true
  )
}

/**
 * Get HTTP status code from error
 */
export function getErrorStatus(error: unknown): number | undefined {
  if (isAxiosError(error)) {
    return error.response?.status
  }
  return undefined
}

/**
 * Extract error details from API error response
 */
export function getApiErrorDetails(error: unknown): Record<string, string[]> {
  if (isApiError(error) && error.response?.data?.error?.details) {
    return error.response.data.error.details
  }
  return {}
}

/**
 * Extract user-friendly error message from API error response
 * Handles different HTTP status codes with appropriate French messages
 */
export function getApiErrorMessage(error: unknown): string {
  const status = getErrorStatus(error)

  // Handle specific status codes with user-friendly messages
  switch (status) {
    case 419:
      return 'Votre session a expiré, veuillez rafraîchir la page.'
    case 422:
      // Return the actual validation message from backend
      if (isApiError(error) && error.response?.data?.error?.message) {
        return error.response.data.error.message
      }
      return 'Les données fournies ne sont pas valides.'
    case 429:
      return 'Trop de tentatives. Veuillez réessayer dans quelques instants.'
    case 500:
    case 502:
    case 503:
      return 'Le serveur est temporairement indisponible. Veuillez réessayer.'
    case 401:
      return 'Vous n\'êtes pas autorisé à effectuer cette action.'
    case 403:
      return 'Accès refusé.'
    case 404:
      return 'La ressource demandée n\'existe pas.'
    default:
      // Try to get message from API error response
      if (isApiError(error) && error.response?.data?.error?.message) {
        return error.response.data.error.message
      }
      // Generic error message
      return 'Une erreur est survenue. Veuillez réessayer.'
  }
}

export default authApi
