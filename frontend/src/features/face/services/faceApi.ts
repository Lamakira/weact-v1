import apiClient, { getCsrfCookie } from '@/services/apiClient'
import type {
  FaceProfileResponse,
  FacePhotosResponse,
  FacePhotoResponse,
  PresentationVideoResponse,
  ActingVideoResponse,
  VideoUploadProgress,
  BioLocationResponse,
  PhysicalCharacteristicsResponse,
  CategoryNicheResponse,
  CategoryOption,
  NicheOption,
  ExperienceResponse,
  ExperiencesListResponse,
  ExperienceFormData,
  TarifsResponse,
  TarifsFormData,
  AvailabilityResponse,
  AvailabilityFormData,
  LanguesResponse,
  ProfileCompletionResponse,
  BasicInfoResponse,
  BasicInfoFormData,
  PersonalInfoResponse,
  PersonalInfoFormData,
} from '../types'

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

  /**
   * Get all album photos
   */
  async getAlbumPhotos(): Promise<FacePhotosResponse> {
    const response = await apiClient.get<FacePhotosResponse>('/face/album')
    return response.data
  },

  /**
   * Add a photo to the album
   * @param photo The photo file to upload
   */
  async addAlbumPhoto(photo: File): Promise<FacePhotoResponse> {
    await getCsrfCookie()

    const formData = new FormData()
    formData.append('photo', photo)

    const response = await apiClient.post<FacePhotoResponse>('/face/album', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })
    return response.data
  },

  /**
   * Delete an album photo
   * @param photoId The ID of the photo to delete
   */
  async deleteAlbumPhoto(photoId: string): Promise<void> {
    await getCsrfCookie()
    await apiClient.delete(`/face/album/${photoId}`)
  },

  /**
   * Reorder album photos
   * @param order Array of photo IDs in the new order
   */
  async reorderAlbumPhotos(order: string[]): Promise<FacePhotosResponse> {
    await getCsrfCookie()
    const response = await apiClient.put<FacePhotosResponse>('/face/album/reorder', { order })
    return response.data
  },

  /**
   * Get the current presentation video info
   */
  async getPresentationVideo(): Promise<PresentationVideoResponse> {
    const response = await apiClient.get<PresentationVideoResponse>('/face/presentation-video')
    return response.data
  },

  /**
   * Upload a presentation video
   * @param video The video file to upload
   * @param onProgress Optional callback for upload progress
   */
  async uploadPresentationVideo(
    video: File,
    onProgress?: (progress: VideoUploadProgress) => void,
  ): Promise<PresentationVideoResponse> {
    await getCsrfCookie()

    const formData = new FormData()
    formData.append('video', video)

    const response = await apiClient.post<PresentationVideoResponse>(
      '/face/presentation-video',
      formData,
      {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
        onUploadProgress: (progressEvent) => {
          if (onProgress && progressEvent.total) {
            onProgress({
              loaded: progressEvent.loaded,
              total: progressEvent.total,
              percentage: Math.round((progressEvent.loaded * 100) / progressEvent.total),
            })
          }
        },
      },
    )
    return response.data
  },

  /**
   * Delete the presentation video
   */
  async deletePresentationVideo(): Promise<{ message: string }> {
    await getCsrfCookie()
    const response = await apiClient.delete<{ message: string }>('/face/presentation-video')
    return response.data
  },

  /**
   * Get the current acting video info
   */
  async getActingVideo(): Promise<ActingVideoResponse> {
    const response = await apiClient.get<ActingVideoResponse>('/face/acting-video')
    return response.data
  },

  /**
   * Upload an acting video
   * @param video The video file to upload
   * @param onProgress Optional callback for upload progress
   */
  async uploadActingVideo(
    video: File,
    onProgress?: (progress: VideoUploadProgress) => void,
  ): Promise<ActingVideoResponse> {
    await getCsrfCookie()

    const formData = new FormData()
    formData.append('video', video)

    const response = await apiClient.post<ActingVideoResponse>('/face/acting-video', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
      onUploadProgress: (progressEvent) => {
        if (onProgress && progressEvent.total) {
          onProgress({
            loaded: progressEvent.loaded,
            total: progressEvent.total,
            percentage: Math.round((progressEvent.loaded * 100) / progressEvent.total),
          })
        }
      },
    })
    return response.data
  },

  /**
   * Delete the acting video
   */
  async deleteActingVideo(): Promise<{ message: string }> {
    await getCsrfCookie()
    const response = await apiClient.delete<{ message: string }>('/face/acting-video')
    return response.data
  },

  /**
   * Get the current bio and location info
   */
  async getBioLocation(): Promise<BioLocationResponse> {
    const response = await apiClient.get<BioLocationResponse>('/face/bio-location')
    return response.data
  },

  /**
   * Update bio and/or location
   * @param data The bio and location data to update
   */
  async updateBioLocation(data: {
    bio?: string | null
    ville?: string | null
    pays?: string | null
  }): Promise<BioLocationResponse> {
    await getCsrfCookie()
    const response = await apiClient.put<BioLocationResponse>('/face/bio-location', data)
    return response.data
  },

  /**
   * Get the current physical characteristics
   */
  async getPhysicalCharacteristics(): Promise<PhysicalCharacteristicsResponse> {
    const response = await apiClient.get<PhysicalCharacteristicsResponse>(
      '/face/physical-characteristics',
    )
    return response.data
  },

  /**
   * Update physical characteristics
   * @param data The physical characteristics data to update
   */
  async updatePhysicalCharacteristics(data: {
    taille?: number | null
    poids?: number | null
  }): Promise<PhysicalCharacteristicsResponse> {
    await getCsrfCookie()
    const response = await apiClient.put<PhysicalCharacteristicsResponse>(
      '/face/physical-characteristics',
      data,
    )
    return response.data
  },

  /**
   * Get the current category and niche
   */
  async getCategoryNiche(): Promise<CategoryNicheResponse> {
    const response = await apiClient.get<CategoryNicheResponse>('/face/category-niche')
    return response.data
  },

  /**
   * Update categories and/or niches (multi-select arrays)
   * @param data The categories and niches arrays to update
   */
  async updateCategoryNiche(data: {
    categories?: string[] | null
    niches?: string[] | null
  }): Promise<CategoryNicheResponse> {
    await getCsrfCookie()
    const response = await apiClient.put<CategoryNicheResponse>('/face/category-niche', data)
    return response.data
  },

  /**
   * Get category options for dropdown
   */
  async getCategoryOptions(): Promise<{ data: CategoryOption[] }> {
    const response = await apiClient.get<{ data: CategoryOption[] }>('/face/options/categories')
    return response.data
  },

  /**
   * Get niche options for dropdown
   */
  async getNicheOptions(): Promise<{ data: NicheOption[] }> {
    const response = await apiClient.get<{ data: NicheOption[] }>('/face/options/niches')
    return response.data
  },

  /**
   * Get all experiences
   */
  async getExperiences(): Promise<ExperiencesListResponse> {
    const response = await apiClient.get<ExperiencesListResponse>('/face/experiences')
    return response.data
  },

  /**
   * Create a new experience
   * @param data The experience data to create
   */
  async createExperience(data: ExperienceFormData): Promise<ExperienceResponse> {
    await getCsrfCookie()
    const response = await apiClient.post<ExperienceResponse>('/face/experiences', data)
    return response.data
  },

  /**
   * Get a single experience
   * @param id The experience ID
   */
  async getExperience(id: string): Promise<ExperienceResponse> {
    const response = await apiClient.get<ExperienceResponse>(`/face/experiences/${id}`)
    return response.data
  },

  /**
   * Update an experience
   * @param id The experience ID
   * @param data The experience data to update
   */
  async updateExperience(id: string, data: ExperienceFormData): Promise<ExperienceResponse> {
    await getCsrfCookie()
    const response = await apiClient.put<ExperienceResponse>(`/face/experiences/${id}`, data)
    return response.data
  },

  /**
   * Delete an experience
   * @param id The experience ID
   */
  async deleteExperience(id: string): Promise<{ message: string }> {
    await getCsrfCookie()
    const response = await apiClient.delete<{ message: string }>(`/face/experiences/${id}`)
    return response.data
  },

  /**
   * Get the current tarifs
   */
  async getTarifs(): Promise<TarifsResponse> {
    const response = await apiClient.get<TarifsResponse>('/face/tarifs')
    return response.data
  },

  /**
   * Update tarifs
   * @param data The tarifs data to update
   */
  async updateTarifs(data: TarifsFormData): Promise<TarifsResponse> {
    await getCsrfCookie()
    const response = await apiClient.put<TarifsResponse>('/face/tarifs', data)
    return response.data
  },

  /**
   * Get the current availability status
   */
  async getAvailability(): Promise<AvailabilityResponse> {
    const response = await apiClient.get<AvailabilityResponse>('/face/availability')
    return response.data
  },

  /**
   * Update availability status
   * @param data The availability data to update
   */
  async updateAvailability(data: AvailabilityFormData): Promise<AvailabilityResponse> {
    await getCsrfCookie()
    const response = await apiClient.put<AvailabilityResponse>('/face/availability', data)
    return response.data
  },

  /**
   * Get the current profile completion status
   */
  async getProfileCompletion(): Promise<ProfileCompletionResponse> {
    const response = await apiClient.get<ProfileCompletionResponse>('/face/profile-completion')
    return response.data
  },

  /**
   * Get the current basic info (nom, prenom, username)
   */
  async getBasicInfo(): Promise<BasicInfoResponse> {
    const response = await apiClient.get<BasicInfoResponse>('/face/basic-info')
    return response.data
  },

  /**
   * Update basic info (nom, prenom, username)
   * @param data The basic info data to update
   */
  async updateBasicInfo(data: BasicInfoFormData): Promise<BasicInfoResponse> {
    await getCsrfCookie()
    const response = await apiClient.put<BasicInfoResponse>('/face/basic-info', data)
    return response.data
  },

  /**
   * Get the current langues
   */
  async getLangues(): Promise<LanguesResponse> {
    const response = await apiClient.get<LanguesResponse>('/face/langues')
    return response.data
  },

  /**
   * Update langues
   * @param langues The languages array to save
   */
  async updateLangues(langues: string[] | null): Promise<LanguesResponse> {
    await getCsrfCookie()
    const response = await apiClient.put<LanguesResponse>('/face/langues', { langues })
    return response.data
  },

  /**
   * Get the current personal info (sexe, date_naissance, nationalite, pays)
   */
  async getPersonalInfo(): Promise<PersonalInfoResponse> {
    const response = await apiClient.get<PersonalInfoResponse>('/face/personal-info')
    return response.data
  },

  /**
   * Update personal info (sexe, date_naissance, nationalite, pays)
   * @param data The personal info data to update
   */
  async updatePersonalInfo(data: PersonalInfoFormData): Promise<PersonalInfoResponse> {
    await getCsrfCookie()
    const response = await apiClient.put<PersonalInfoResponse>('/face/personal-info', data)
    return response.data
  },
}
