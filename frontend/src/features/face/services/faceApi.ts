import apiClient, { getCsrfCookie } from '@/services/apiClient'
import type {
  FaceProfileResponse,
  FacePhotosResponse,
  FacePhotoResponse,
  PresentationVideoResponse,
  ActingVideoResponse,
  VideoUploadProgress,
  BioLocationResponse,
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
  async deleteAlbumPhoto(photoId: number): Promise<void> {
    await getCsrfCookie()
    await apiClient.delete(`/face/album/${photoId}`)
  },

  /**
   * Reorder album photos
   * @param order Array of photo IDs in the new order
   */
  async reorderAlbumPhotos(order: number[]): Promise<FacePhotosResponse> {
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
    quartier?: string | null
    pays?: string | null
  }): Promise<BioLocationResponse> {
    await getCsrfCookie()
    const response = await apiClient.put<BioLocationResponse>('/face/bio-location', data)
    return response.data
  },
}
