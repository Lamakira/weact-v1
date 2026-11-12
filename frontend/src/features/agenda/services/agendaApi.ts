import apiClient, { getCsrfCookie } from '@/services/apiClient'
import type {
  AgendaResponse,
  AvailabilityResponse,
  UnavailableDaysPayload,
  UnavailableDaysResponse,
} from '../types'

/** Calendrier : agenda de la Face (lecture, jours bloqués) et disponibilité vue par un Producteur. */
export const agendaApi = {
  /** Face : jours bloqués et événements qui chevauchent [from, to] (AAAA-MM-JJ, 92 jours au plus). */
  async getAgenda(from: string, to: string): Promise<AgendaResponse> {
    const response = await apiClient.get<AgendaResponse>('/face/agenda', { params: { from, to } })
    return response.data
  },

  /** Face : bloque (`add`) et libère (`remove`) des jours, opération atomique. */
  async updateUnavailableDays(payload: UnavailableDaysPayload): Promise<UnavailableDaysResponse> {
    await getCsrfCookie()
    const response = await apiClient.put<UnavailableDaysResponse>('/face/agenda/unavailable-days', payload)
    return response.data
  },

  /** Producteur : jours indisponibles (bloqués ou engagés, sans distinction) d'une Face (uuid). */
  async getFaceAvailability(faceUuid: string, from: string, to: string): Promise<AvailabilityResponse> {
    const response = await apiClient.get<AvailabilityResponse>(`/producer/candidates/${faceUuid}/availability`, {
      params: { from, to },
    })
    return response.data
  },
}
