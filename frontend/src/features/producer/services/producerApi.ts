import apiClient, { getCsrfCookie } from '@/services/apiClient'
import type {
  ProducerProfileResponse,
  ProducerBioResponse,
  AgencyLogoResponse,
  ProducerBasicInfoResponse,
  ProducerBasicInfoFormData,
} from '../types'
import type { DeliverableResponse, DeliverableReviewListResponse } from '@/components/ugc'

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

  // ---------- UGC deliverable review (inbox de validation 5A, story 4.4) ----------

  /**
   * Liste les livrables in_review du Producteur (booking + candidature agrégés).
   */
  async listDeliverablesToReview(): Promise<DeliverableReviewListResponse> {
    const response = await apiClient.get<DeliverableReviewListResponse>('/producer/deliverables')
    return response.data
  },

  /**
   * Valide un livrable (Unboxing → chrono Avis ; Avis → clôture deal).
   */
  async validateDeliverable(id: string): Promise<DeliverableResponse> {
    await getCsrfCookie()
    const response = await apiClient.post<DeliverableResponse>(`/producer/deliverables/${id}/validate`)
    return response.data
  },

  /**
   * Rejette un livrable (motif requis, min 5 caractères côté serveur).
   */
  async rejectDeliverable(id: string, reviewNote: string): Promise<DeliverableResponse> {
    await getCsrfCookie()
    const response = await apiClient.post<DeliverableResponse>(
      `/producer/deliverables/${id}/reject`,
      { review_note: reviewNote },
    )
    return response.data
  },

  /**
   * Demande une retouche sur un livrable (motif requis, min 5 caractères).
   */
  async requestDeliverableRetouche(id: string, reviewNote: string): Promise<DeliverableResponse> {
    await getCsrfCookie()
    const response = await apiClient.post<DeliverableResponse>(
      `/producer/deliverables/${id}/request-retouche`,
      { review_note: reviewNote },
    )
    return response.data
  },
}
