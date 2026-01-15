import apiClient, { getCsrfCookie } from '@/services/apiClient'
import type { ProducerProfileResponse } from '../types'

/**
 * Producer API service
 */
export const producerApi = {
  /**
   * Get the current producer profile
   */
  async getProfile(): Promise<ProducerProfileResponse> {
    const response = await apiClient.get<ProducerProfileResponse>('/producer/profile')
    return response.data
  },

  /**
   * Upload a profile photo
   * @param photo The photo file to upload
   */
  async uploadProfilePhoto(photo: File): Promise<ProducerProfileResponse> {
    await getCsrfCookie()

    const formData = new FormData()
    formData.append('photo', photo)

    const response = await apiClient.post<ProducerProfileResponse>('/producer/profile/photo', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })
    return response.data
  },

  /**
   * Delete the profile photo
   */
  async deleteProfilePhoto(): Promise<ProducerProfileResponse> {
    await getCsrfCookie()
    const response = await apiClient.delete<ProducerProfileResponse>('/producer/profile/photo')
    return response.data
  },
}
