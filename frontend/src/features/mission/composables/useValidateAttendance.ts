import { ref } from 'vue'
import { isAxiosError } from 'axios'
import { missionApi } from '../services/missionApi'
import { getApiErrorDetails, getApiErrorMessage } from '@/features/auth/services/authApi'
import type { Ref } from 'vue'
import type {
  AttendanceFormResponse,
  ValidateAttendancePayload,
  ValidateAttendanceResponse,
} from '../types/attendance'

export interface ValidateAttendanceResult<TData> {
  success: boolean
  message: string
  status: number | null
  data?: TData
}

export interface UseValidateAttendanceReturn {
  isLoading: Ref<boolean>
  isSubmitting: Ref<boolean>
  error: Ref<string | null>
  fieldErrors: Ref<Record<string, string[]>>
  httpStatus: Ref<number | null>
  data: Ref<AttendanceFormResponse['data'] | null>
  fetchForm: (
    missionUuid: string
  ) => Promise<ValidateAttendanceResult<AttendanceFormResponse['data']>>
  submitAttendance: (
    missionUuid: string,
    payload: ValidateAttendancePayload
  ) => Promise<ValidateAttendanceResult<ValidateAttendanceResponse['data']>>
  resetError: () => void
}

/**
 * Composable for the Producer attendance validation flow (FIX-26.7).
 *
 * Manages fetching the attendance form, submitting decisions, and surfacing
 * field-level + global error state — including the HTTP status code so the
 * consumer can branch on 403/404 vs 422 deterministically (not by message
 * substring matching).
 */
export function useValidateAttendance(): UseValidateAttendanceReturn {
  const isLoading = ref(false)
  const isSubmitting = ref(false)
  const error = ref<string | null>(null)
  const fieldErrors = ref<Record<string, string[]>>({})
  const httpStatus = ref<number | null>(null)
  const data = ref<AttendanceFormResponse['data'] | null>(null)
  let fetchRequestId = 0

  function captureError(err: unknown): void {
    error.value = getApiErrorMessage(err)
    fieldErrors.value = getApiErrorDetails(err)
    httpStatus.value = isAxiosError(err) ? (err.response?.status ?? null) : null
  }

  async function fetchForm(
    missionUuid: string
  ): Promise<ValidateAttendanceResult<AttendanceFormResponse['data']>> {
    const requestId = ++fetchRequestId
    isLoading.value = true
    error.value = null
    fieldErrors.value = {}
    httpStatus.value = null
    data.value = null

    try {
      const response = await missionApi.getAttendanceForm(missionUuid)
      if (requestId === fetchRequestId) {
        data.value = response.data
      }
      return { success: true, message: '', status: null, data: response.data }
    } catch (err: unknown) {
      if (requestId !== fetchRequestId) {
        return { success: false, message: '', status: null }
      }
      captureError(err)
      return { success: false, message: error.value ?? '', status: httpStatus.value }
    } finally {
      if (requestId === fetchRequestId) {
        isLoading.value = false
      }
    }
  }

  async function submitAttendance(
    missionUuid: string,
    payload: ValidateAttendancePayload
  ): Promise<ValidateAttendanceResult<ValidateAttendanceResponse['data']>> {
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
