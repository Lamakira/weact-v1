import { publicApiClient } from '@/features/public/services/apiClient'
import type { MissionsApiResponse, FacesApiResponse } from '../types'

export const landingApi = {
  async getMissions(params: { per_page: number; page: number }): Promise<MissionsApiResponse> {
    const response = await publicApiClient.get<MissionsApiResponse>('/public/missions', { params })
    return response.data
  },

  async getFaces(params: { per_page: number; page: number }): Promise<FacesApiResponse> {
    const response = await publicApiClient.get<FacesApiResponse>('/public/faces', { params })
    return response.data
  },
}
