import apiClient from '@/services/apiClient'
import type { AxiosError } from 'axios'
import type { FaceRegistrationForm, ProducerRegistrationForm, AuthResponse, ApiError } from '../types'

/**
 * Auth API service
 */
export const authApi = {
  /**
   * Register a new Face user
   */
  async registerFace(data: FaceRegistrationForm): Promise<AuthResponse> {
    const response = await apiClient.post<AuthResponse>('/auth/register/face', data)
    return response.data
  },

  /**
   * Register a new Producer user (Agency or Particulier)
   */
  async registerProducer(data: ProducerRegistrationForm): Promise<AuthResponse> {
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
 * Extract error details from API error response
 */
export function getApiErrorDetails(error: unknown): Record<string, string[]> {
  if (isApiError(error) && error.response?.data?.error?.details) {
    return error.response.data.error.details
  }
  return {}
}

/**
 * Extract error message from API error response
 */
export function getApiErrorMessage(error: unknown): string {
  if (isApiError(error) && error.response?.data?.error?.message) {
    return error.response.data.error.message
  }
  if (error instanceof Error) {
    return error.message
  }
  return 'Une erreur inattendue est survenue'
}

export default authApi
