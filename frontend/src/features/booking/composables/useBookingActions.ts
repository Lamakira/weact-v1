import { ref, type Ref } from 'vue'
import { bookingApi } from '../services/bookingApi'
import type { Booking } from '../types'
import { getApiErrorMessage } from '@/features/auth/services/authApi'

export interface UseBookingActionsReturn {
  isAccepting: Ref<boolean>
  isRefusing: Ref<boolean>
  isConfirming: Ref<boolean>
  error: Ref<string | null>
  accept: (bookingId: number) => Promise<Booking | null>
  refuse: (bookingId: number, reason?: string) => Promise<Booking | null>
  confirm: (bookingId: number) => Promise<Booking | null>
  clearError: () => void
}

export function useBookingActions(): UseBookingActionsReturn {
  const isAccepting = ref(false)
  const isRefusing = ref(false)
  const isConfirming = ref(false)
  const error = ref<string | null>(null)

  function clearError(): void {
    error.value = null
  }

  async function accept(bookingId: number): Promise<Booking | null> {
    isAccepting.value = true
    error.value = null

    try {
      const response = await bookingApi.acceptBooking(bookingId)
      return response.data
    } catch (err) {
      error.value = getApiErrorMessage(err)
      return null
    } finally {
      isAccepting.value = false
    }
  }

  async function refuse(bookingId: number, reason?: string): Promise<Booking | null> {
    isRefusing.value = true
    error.value = null

    try {
      const response = await bookingApi.refuseBooking(bookingId, reason)
      return response.data
    } catch (err) {
      error.value = getApiErrorMessage(err)
      return null
    } finally {
      isRefusing.value = false
    }
  }

  async function confirm(bookingId: number): Promise<Booking | null> {
    isConfirming.value = true
    error.value = null

    try {
      const response = await bookingApi.confirmBooking(bookingId)
      return response.data
    } catch (err) {
      error.value = getApiErrorMessage(err)
      return null
    } finally {
      isConfirming.value = false
    }
  }

  return {
    isAccepting,
    isRefusing,
    isConfirming,
    error,
    accept,
    refuse,
    confirm,
    clearError,
  }
}
