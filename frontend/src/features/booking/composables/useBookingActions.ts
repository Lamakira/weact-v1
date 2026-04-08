import { ref, type Ref } from 'vue'
import { bookingApi } from '../services/bookingApi'
import type { Booking, CancellationReasonValue } from '../types'
import { getApiErrorMessage } from '@/features/auth/services/authApi'

export interface UseBookingActionsReturn {
  isAccepting: Ref<boolean>
  isRefusing: Ref<boolean>
  isConfirming: Ref<boolean>
  isCancelling: Ref<boolean>
  isReportingNoShow: Ref<boolean>
  error: Ref<string | null>
  accept: (bookingId: number) => Promise<Booking | null>
  refuse: (bookingId: number, reason?: string) => Promise<Booking | null>
  confirm: (bookingId: number) => Promise<Booking | null>
  cancel: (bookingId: number, reason: CancellationReasonValue, customReason?: string) => Promise<Booking | null>
  reportNoShow: (bookingId: number) => Promise<Booking | null>
  clearError: () => void
}

export function useBookingActions(): UseBookingActionsReturn {
  const isAccepting = ref(false)
  const isRefusing = ref(false)
  const isConfirming = ref(false)
  const isCancelling = ref(false)
  const isReportingNoShow = ref(false)
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

  async function cancel(
    bookingId: number,
    reason: CancellationReasonValue,
    customReason?: string,
  ): Promise<Booking | null> {
    isCancelling.value = true
    error.value = null

    try {
      const response = await bookingApi.cancelBooking(bookingId, reason, customReason)
      return response.data
    } catch (err) {
      error.value = getApiErrorMessage(err)
      return null
    } finally {
      isCancelling.value = false
    }
  }

  async function reportNoShow(bookingId: number): Promise<Booking | null> {
    isReportingNoShow.value = true
    error.value = null

    try {
      const response = await bookingApi.reportNoShow(bookingId)
      return response.data
    } catch (err) {
      error.value = getApiErrorMessage(err)
      return null
    } finally {
      isReportingNoShow.value = false
    }
  }

  return {
    isAccepting,
    isRefusing,
    isConfirming,
    isCancelling,
    isReportingNoShow,
    error,
    accept,
    refuse,
    confirm,
    cancel,
    reportNoShow,
    clearError,
  }
}
