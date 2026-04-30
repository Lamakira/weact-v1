import { ref } from 'vue'
import { isAxiosError } from 'axios'
import { missionApi } from '../services/missionApi'
import { getApiErrorDetails, getApiErrorMessage } from '@/features/auth/services/authApi'
import type {
  AttendanceFormResponse,
  ValidateAttendancePayload,
  ValidateAttendanceResponse,
} from '../types/attendance'

/**
 * Composable for the Producer attendance validation flow (FIX-26.7).
 *
 * Manages fetching the attendance form, submitting decisions, and surfacing
 * field-level + global error state — including the HTTP status code so the
 * consumer can branch on 403/404 vs 422 deterministically (not by message
 * substring matching).
 */
export function useValidateAttendance() {
  const isLoading = ref(false)
  const isSubmitting = ref(false)
  const error = ref<string | null>(null)
  const fieldErrors = ref<Record<string, string[]>>({})
  const httpStatus = ref<number | null>(null)
  const data = ref<AttendanceFormResponse['data'] | null>(null)

  function captureError(err: unknown): void {
    error.value = getApiErrorMessage(err)
    fieldErrors.value = getApiErrorDetails(err)
    httpStatus.value = isAxiosError(err) ? (err.response?.status ?? null) : null
  }

  async function fetchForm(
    missionUuid: string
  ): Promise<{
    success: boolean
    message: string
    status: number | null
    data?: AttendanceFormResponse['data']
  }> {
    isLoading.value = true
    error.value = null
    fieldErrors.value = {}
    httpStatus.value = null
    data.value = null

    try {
      const response = await missionApi.getAttendanceForm(missionUuid)
      data.value = response.data
      return { success: true, message: '', status: null, data: response.data }
    } catch (err: unknown) {
      captureError(err)
      return { success: false, message: error.value ?? '', status: httpStatus.value }
    } finally {
      isLoading.value = false
    }
  }

  async function submitAttendance(
    missionUuid: string,
    payload: ValidateAttendancePayload
  ): Promise<{
    success: boolean
    message: string
    status: number | null
    data?: ValidateAttendanceResponse['data']
  }> {
    isSubmitting.value = true
    error.value = null
    fieldErrors.value = {}
    httpStatus.value = null

    try {
      const response = await missionApi.validateAttendance(missionUuid, payload)
      return {
        success: true,
        message: response.message,
        status: null,
        data: response.data,
      }
    } catch (err: unknown) {
      captureError(err)
      return { success: false, message: error.value ?? '', status: httpStatus.value }
    } finally {
      isSubmitting.value = false
    }
  }

  function resetError(): void {
    error.value = null
    fieldErrors.value = {}
    httpStatus.value = null
  }

  return {
    isLoading,
    isSubmitting,
    error,
    fieldErrors,
    httpStatus,
    data,
    fetchForm,
    submitAttendance,
    resetError,
  }
}
