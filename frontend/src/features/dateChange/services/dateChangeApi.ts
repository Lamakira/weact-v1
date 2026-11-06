import apiClient, { getCsrfCookie } from '@/services/apiClient'
import type { MissionResponse } from '@/features/mission/types'
import type { BookingResponse } from '@/features/booking/types'
import type {
  BookingDateDecision,
  DateChangePayload,
  FaceDateChangeResponse,
  MissionDateDecision,
} from '../types'

/**
 * Changement de date : mission (Producteur propose, Face répond) et booking (les deux parties).
 * Préfixes réels : `/producer` et `/face` (routes `v1/producer`, `v1/face`), `/bookings` sans rôle.
 */
export const dateChangeApi = {
  /** Producteur : déplace la date d'une mission payée tout de suite. */
  async proposeMission(missionId: string, payload: DateChangePayload): Promise<MissionResponse> {
    await getCsrfCookie()
    const response = await apiClient.post<MissionResponse>(`/producer/missions/${missionId}/date-change`, payload)
    return response.data
  },

  /** Face : confirme la nouvelle date ou se retire de la mission. */
  async respondMission(candidatureId: string, decision: MissionDateDecision): Promise<FaceDateChangeResponse> {
    await getCsrfCookie()
    const response = await apiClient.post<FaceDateChangeResponse>(
      `/face/candidatures/${candidatureId}/date-change/respond`,
      { decision },
    )
    return response.data
  },

  /** Booking : une partie propose une autre date. */
  async proposeBooking(bookingId: string, payload: DateChangePayload): Promise<BookingResponse> {
    await getCsrfCookie()
    const response = await apiClient.post<BookingResponse>(`/bookings/${bookingId}/date-change`, payload)
    return response.data
  },

  /** Booking : l'autre partie accepte ou garde l'ancienne date. */
  async respondBooking(bookingId: string, decision: BookingDateDecision): Promise<BookingResponse> {
    await getCsrfCookie()
    const response = await apiClient.post<BookingResponse>(`/bookings/${bookingId}/date-change/respond`, { decision })
    return response.data
  },

  /** Booking : l'auteur retire sa proposition tant qu'elle est en attente. */
  async withdrawBooking(bookingId: string): Promise<BookingResponse> {
    await getCsrfCookie()
    const response = await apiClient.delete<BookingResponse>(`/bookings/${bookingId}/date-change`)
    return response.data
  },
}
