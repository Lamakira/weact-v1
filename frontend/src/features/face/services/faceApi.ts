import apiClient, { getCsrfCookie } from '@/services/apiClient'
import type { FaceProfileResponse } from '../types'

/**
 * Face API service
 */
export const faceApi = {
  /**
   * Get the current face profile
   */
  async getProfile(): Promise<FaceProfileResponse> {
    const response = await apiClient.get<FaceProfileResponse>('/face/profile')
    return response.data
  },

  /**
   * Upload a profile photo
   * @param photo The photo file to upload
   */
  async uploadProfilePhoto(photo: File): Promise<FaceProfileResponse> {
    await getCsrfCookie()

    const formData = new FormData()
    formData.append('photo', photo)

    const response = await apiClient.post<FaceProfileResponse>('/face/profile/photo', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })
    return response.data
  },

  /**
   * Delete the profile photo
   */
  async deleteProfilePhoto(): Promise<FaceProfileResponse> {
    await getCsrfCookie()
    const response = await apiClient.delete<FaceProfileResponse>('/face/profile/photo')
    return response.data
  },
}
