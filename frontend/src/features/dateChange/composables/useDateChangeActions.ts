import { ref } from 'vue'
import { formatApiError } from '@/services/errorFormatter'
import type { Mission } from '@/features/mission/types'
import type { Booking } from '@/features/booking/types'
import { dateChangeApi } from '../services/dateChangeApi'
import type {
  BookingDateDecision,
  DateChangePayload,
  FaceMissionDateChange,
  MissionDateDecision,
} from '../types'

export interface DateChangeResult<T> {
  data: T
  message?: string
}

/**
 * Actions du changement de date. Chaque action renvoie le résultat ou null en cas d'échec ;
 * `error` porte alors le message du serveur (les erreurs métier 422 sont sous la clé `date`).
 */
export function useDateChangeActions() {
  const isSubmitting = ref(false)
  const error = ref<string | null>(null)

  function clearError(): void {
    error.value = null
  }

  async function run<T>(call: () => Promise<{ data: T; message?: string }>): Promise<DateChangeResult<T> | null> {
    if (isSubmitting.value) return null
    isSubmitting.value = true
    error.value = null
    try {
      const response = await call()
      return { data: response.data, message: response.message }
    } catch (err) {
      error.value = formatApiError(err)
      return null
    } finally {
      isSubmitting.value = false
    }
  }

  return {
    isSubmitting,
    error,
    clearError,
    proposeMission: (missionId: string, payload: DateChangePayload): Promise<DateChangeResult<Mission> | null> =>
      run(() => dateChangeApi.proposeMission(missionId, payload)),
    respondMission: (
      candidatureId: string,
      decision: MissionDateDecision,
    ): Promise<DateChangeResult<FaceMissionDateChange> | null> =>
      run(() => dateChangeApi.respondMission(candidatureId, decision)),
    proposeBooking: (bookingId: string, payload: DateChangePayload): Promise<DateChangeResult<Booking> | null> =>
      run(() => dateChangeApi.proposeBooking(bookingId, payload)),
    respondBooking: (bookingId: string, decision: BookingDateDecision): Promise<DateChangeResult<Booking> | null> =>
      run(() => dateChangeApi.respondBooking(bookingId, decision)),
    withdrawBooking: (bookingId: string): Promise<DateChangeResult<Booking> | null> =>
      run(() => dateChangeApi.withdrawBooking(bookingId)),
  }
}
