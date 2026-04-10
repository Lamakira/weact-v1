import { ref, type Ref } from 'vue'
import { faceApi } from '../services/faceApi'
import type { Experience, ExperienceFormData, ExperienceResult } from '../types'
import { getApiErrorDetails, getApiErrorMessage } from '@/features/auth/services/authApi'
import { createSharedCachedResource } from '@/lib/createSharedCachedResource'

const EXPERIENCES_CACHE_TTL_MS = 5 * 60 * 1000

const experiencesResource = createSharedCachedResource<Experience[]>({
  key: 'face-experiences',
  initialValue: [],
  ttlMs: EXPERIENCES_CACHE_TTL_MS,
  load: async () => {
    const response = await faceApi.getExperiences()
    return response.data
  },
  getErrorMessage: getApiErrorMessage,
})

interface UseExperiencesReturn {
  experiences: Ref<Experience[]>
  isLoading: Ref<boolean>
  isSaving: Ref<boolean>
  isDeleting: Ref<boolean>
  error: Ref<string | null>
  validationErrors: Ref<Record<string, string[]>>
  fetchExperiences: () => Promise<void>
  addExperience: (data: ExperienceFormData) => Promise<ExperienceResult>
  editExperience: (id: string, data: ExperienceFormData) => Promise<ExperienceResult>
  removeExperience: (id: string) => Promise<boolean>
  clearError: () => void
}

/**
 * Composable for Face professional experiences operations
 */
export function useExperiences(): UseExperiencesReturn {
  const experiences = experiencesResource.data
  const isLoading = experiencesResource.isLoading
  const isSaving = ref(false)
  const isDeleting = ref(false)
  const error = experiencesResource.error
  const validationErrors = ref<Record<string, string[]>>({})

  /**
   * Clear the current error
   */
  function clearError(): void {
    experiencesResource.clearError()
    validationErrors.value = {}
  }

  /**
   * Sort experiences by date (newest first).
   * Used after add/edit operations to maintain sort order locally.
   */
  function sortByDateDesc(experiencesList: Experience[]): Experience[] {
    return [...experiencesList].sort(
      (a, b) => new Date(b.date_debut).getTime() - new Date(a.date_debut).getTime(),
    )
  }

  /**
   * Fetch all experiences for the authenticated Face.
   * Backend returns data already sorted by date descending.
   */
  async function fetchExperiences(): Promise<void> {
    await experiencesResource.fetch()
  }

  /**
   * Add a new experience
   */
  async function addExperience(data: ExperienceFormData): Promise<ExperienceResult> {
    isSaving.value = true
    error.value = null
    validationErrors.value = {}

    try {
      const response = await faceApi.createExperience(data)
      // Add the new experience and re-sort
      experiencesResource.setData(sortByDateDesc([...experiences.value, response.data]))

      return {
        success: true,
        data: response.data,
        message: response.message,
      }
    } catch (err) {
      const errors = getApiErrorDetails(err)
      const message = getApiErrorMessage(err)
      error.value = message
      validationErrors.value = errors

      return {
        success: false,
        errors,
        message,
      }
    } finally {
      isSaving.value = false
    }
  }

  /**
   * Edit an existing experience
   */
  async function editExperience(
    id: string,
    data: ExperienceFormData,
  ): Promise<ExperienceResult> {
    isSaving.value = true
    error.value = null
    validationErrors.value = {}

    try {
      const response = await faceApi.updateExperience(id, data)
      // Update the experience in the list and re-sort
      const index = experiences.value.findIndex((e) => e.id === id)
      if (index !== -1) {
        const nextExperiences = [...experiences.value]
        nextExperiences[index] = response.data
        experiencesResource.setData(sortByDateDesc(nextExperiences))
      }

      return {
        success: true,
        data: response.data,
        message: response.message,
      }
    } catch (err) {
      const errors = getApiErrorDetails(err)
      const message = getApiErrorMessage(err)
      error.value = message
      validationErrors.value = errors

      return {
        success: false,
        errors,
        message,
      }
    } finally {
      isSaving.value = false
    }
  }

  /**
   * Remove an experience
   */
  async function removeExperience(id: string): Promise<boolean> {
    isDeleting.value = true
    error.value = null

    try {
      await faceApi.deleteExperience(id)
      // Remove the experience from the list
      experiencesResource.setData(experiences.value.filter((e) => e.id !== id))
      return true
    } catch (err) {
      error.value = getApiErrorMessage(err)
      return false
    } finally {
      isDeleting.value = false
    }
  }

  return {
    experiences,
    isLoading,
    isSaving,
    isDeleting,
    error,
    validationErrors,
    fetchExperiences,
    addExperience,
    editExperience,
    removeExperience,
    clearError,
  }
}
