import apiClient, { getCsrfCookie } from '@/services/apiClient'
import type { FaceProfileResponse, FacePhotosResponse, FacePhotoResponse } from '../types'

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
}
