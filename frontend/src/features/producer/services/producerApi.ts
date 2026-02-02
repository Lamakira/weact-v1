import apiClient, { getCsrfCookie } from '@/services/apiClient'
import type {
  ProducerProfileResponse,
  ProducerBioResponse,
  AgencyLogoResponse,
  ProducerBasicInfoResponse,
  ProducerBasicInfoFormData,
} from '../types'

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

  /**
   * Get the agency logo (Agency only)
   */
  async getLogo(): Promise<AgencyLogoResponse> {
    const response = await apiClient.get<AgencyLogoResponse>('/producer/profile/logo')
    return response.data
  },

  /**
   * Upload an agency logo (Agency only)
   * @param logo The logo file to upload (max 2MB)
   */
  async uploadLogo(logo: File): Promise<ProducerProfileResponse> {
    await getCsrfCookie()

    const formData = new FormData()
    formData.append('logo', logo)

    const response = await apiClient.post<ProducerProfileResponse>('/producer/profile/logo', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })
    return response.data
  },

  /**
   * Delete the agency logo (Agency only)
   */
  async deleteLogo(): Promise<ProducerProfileResponse> {
    await getCsrfCookie()
    const response = await apiClient.delete<ProducerProfileResponse>('/producer/profile/logo')
    return response.data
  },

  /**
   * Get the current basic info (agency_name or first_name/last_name based on type)
   */
  async getBasicInfo(): Promise<ProducerBasicInfoResponse> {
    const response = await apiClient.get<ProducerBasicInfoResponse>('/producer/basic-info')
    return response.data
  },

  /**
   * Update basic info
   * For agency: { agency_name: string }
   * For particulier: { first_name?: string, last_name?: string }
   */
  async updateBasicInfo(data: ProducerBasicInfoFormData): Promise<ProducerBasicInfoResponse> {
    await getCsrfCookie()
    const response = await apiClient.put<ProducerBasicInfoResponse>('/producer/basic-info', data)
    return response.data
  },
}
