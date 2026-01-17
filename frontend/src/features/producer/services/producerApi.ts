import apiClient, { getCsrfCookie } from '@/services/apiClient'
import type { ProducerProfileResponse, ProducerBioResponse } from '../types'

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

  /**
   * Get the current producer bio
   */
  async getBio(): Promise<ProducerBioResponse> {
    const response = await apiClient.get<ProducerBioResponse>('/producer/profile/bio')
    return response.data
  },

  /**
   * Update the producer bio
   * @param bio The bio text (max 500 characters) or null to clear
   */
  async updateBio(bio: string | null): Promise<ProducerBioResponse> {
    await getCsrfCookie()
    const response = await apiClient.put<ProducerBioResponse>('/producer/profile/bio', { bio })
    return response.data
  },
}
