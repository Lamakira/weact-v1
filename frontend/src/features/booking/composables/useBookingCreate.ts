import { ref, type Ref } from 'vue'
import { bookingApi } from '../services/bookingApi'
import type { Booking, BookingCreateResult, CreateBookingData } from '../types'
import { getApiErrorDetails, getApiErrorMessage, getApiErrorCode } from '@/features/auth/services/authApi'

export interface UseBookingCreateReturn {
  isSubmitting: Ref<boolean>
  error: Ref<string | null>
  errorCode: Ref<string | null>
  validationErrors: Ref<Record<string, string[]>>
  createdBooking: Ref<Booking | null>
  createBooking: (data: CreateBookingData) => Promise<BookingCreateResult>
  clearError: () => void
  reset: () => void
}

/**
 * Composable for booking creation operations
 */
export function useBookingCreate(): UseBookingCreateReturn {
  const isSubmitting = ref(false)
  const error = ref<string | null>(null)
  const errorCode = ref<string | null>(null)
  const validationErrors = ref<Record<string, string[]>>({})
  const createdBooking = ref<Booking | null>(null)

  /**
   * Clear the current error state
   */
  function clearError(): void {
    error.value = null
    errorCode.value = null
    validationErrors.value = {}
  }

  /**
   * Reset all state to initial values
   */
  function reset(): void {
    isSubmitting.value = false
    error.value = null
    errorCode.value = null
    validationErrors.value = {}
    createdBooking.value = null
  }

  /**
   * Create a new booking
   */
  async function createBooking(data: CreateBookingData): Promise<BookingCreateResult> {
    isSubmitting.value = true
    error.value = null
    errorCode.value = null
    validationErrors.value = {}

    try {
      const response = await bookingApi.createBooking(data)
      createdBooking.value = response.data

      return {
        success: true,
        data: response.data,
        message: response.message || 'Demande de booking envoyée',
      }
    } catch (err) {
      const errors = getApiErrorDetails(err)
      const message = getApiErrorMessage(err)
      const code = getApiErrorCode(err)
      error.value = message
      errorCode.value = code
      validationErrors.value = errors

      return {
        success: false,
        errors,
        message,
        errorCode: code,
      }
    } finally {
      isSubmitting.value = false
    }
  }

  return {
    isSubmitting,
    error,
    errorCode,
    validationErrors,
    createdBooking,
    createBooking,
    clearError,
    reset,
  }
}
