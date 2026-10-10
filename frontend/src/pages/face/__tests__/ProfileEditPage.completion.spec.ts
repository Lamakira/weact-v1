import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, shallowMount } from '@vue/test-utils'
import { ref } from 'vue'
import ProfileEditPage from '../ProfileEditPage.vue'
import PersonalInfoForm from '@/features/face/components/PersonalInfoForm.vue'
import BioLocationForm from '@/features/face/components/BioLocationForm.vue'
import LanguesTagInput from '@/features/face/components/LanguesTagInput.vue'
import CategoryNicheForm from '@/features/face/components/CategoryNicheForm.vue'
import TarifsForm from '@/features/face/components/TarifsForm.vue'
import ProfilePhotoUpload from '@/features/face/components/ProfilePhotoUpload.vue'

const h = vi.hoisted(() => ({
  fetchCompletion: vi.fn(),
  updatePersonalInfo: vi.fn(),
  updateBioLocation: vi.fn(),
  updateLangues: vi.fn(),
  updateCategoryNiche: vi.fn(),
  updateTarifs: vi.fn(),
  uploadPhoto: vi.fn(),
}))

vi.mock('vue-router', () => ({
  useRoute: () => ({ query: {}, path: '/face/profile' }),
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
}))

vi.mock('@/stores/auth', () => ({ useAuthStore: () => ({ isAuthenticated: true }) }))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn(), warning: vi.fn() }),
}))

const noop = (): Promise<void> => Promise.resolve()

vi.mock('@/features/face/composables/useProfilePhoto', () => ({
  useProfilePhoto: () => ({
    profile: ref(null),
    isLoading: ref(false),
    isUploading: ref(false),
    isDeleting: ref(false),
    error: ref(null),
    fetchProfile: noop,
    uploadPhoto: h.uploadPhoto,
    deletePhoto: noop,
  }),
}))
vi.mock('@/features/face/composables/usePhotoAlbum', () => ({
  usePhotoAlbum: () => ({
    photos: ref([]),
    isLoading: ref(false),
    isUploading: ref(false),
    isDeleting: ref(false),
    error: ref(null),
    canAddMore: ref(true),
    fetchPhotos: noop,
    addPhoto: noop,
    deletePhoto: noop,
  }),
}))
vi.mock('@/features/face/composables/usePresentationVideo', () => ({
  usePresentationVideo: () => ({
    videoInfo: ref(null),
    isUploading: ref(false),
    isDeleting: ref(false),
    error: ref(null),
    uploadProgress: ref(null),
    fetchVideoInfo: noop,
    uploadVideo: noop,
    deleteVideo: noop,
  }),
}))
vi.mock('@/features/face/composables/useFaceVideos', () => ({
  useFaceVideos: () => ({
    videos: ref([]),
    actingVideos: ref([]),
    ugcVideos: ref([]),
    isUploading: ref(false),
    uploadingType: ref(null),
    isDeleting: ref(false),
    deletingType: ref(null),
    error: ref(null),
    errorType: ref(null),
    uploadProgress: ref(0),
    fetchVideos: noop,
    uploadVideo: noop,
    deleteVideo: noop,
  }),
}))
vi.mock('@/features/face/composables/useBioLocation', () => ({
  useBioLocation: () => ({
    bioLocationInfo: ref(null),
    isSaving: ref(false),
    error: ref(null),
    fetchBioLocation: noop,
    updateBioLocation: h.updateBioLocation,
  }),
}))
vi.mock('@/features/face/composables/useLangues', () => ({
  useLangues: () => ({
    languesInfo: ref(null),
    isLoading: ref(false),
    isSaving: ref(false),
    error: ref(null),
    fetchLangues: noop,
    updateLangues: h.updateLangues,
    MAX_LANGUES: 5,
    MAX_LANGUE_LENGTH: 30,
  }),
}))
vi.mock('@/features/face/composables/usePersonalInfo', () => ({
  usePersonalInfo: () => ({
    personalInfo: ref(null),
    isLoading: ref(false),
    isSaving: ref(false),
    error: ref(null),
    fetchPersonalInfo: noop,
    updatePersonalInfo: h.updatePersonalInfo,
  }),
}))
vi.mock('@/features/face/composables/usePhysicalCharacteristics', () => ({
  usePhysicalCharacteristics: () => ({
    physicalCharacteristicsInfo: ref(null),
    isSaving: ref(false),
    error: ref(null),
    fetchPhysicalCharacteristics: noop,
    updatePhysicalCharacteristics: noop,
  }),
}))
vi.mock('@/features/face/composables/useCategoryNiche', () => ({
  useCategoryNiche: () => ({
    categoryNicheInfo: ref(null),
    categoryOptions: ref([]),
    nicheOptions: ref([]),
    isLoading: ref(false),
    isSaving: ref(false),
    error: ref(null),
    fetchCategoryNiche: noop,
    fetchCategoryOptions: noop,
    fetchNicheOptions: noop,
    updateCategoryNiche: h.updateCategoryNiche,
  }),
}))
vi.mock('@/features/face/composables/useExperiences', () => ({
  useExperiences: () => ({
    experiences: ref([]),
    isLoading: ref(false),
    isSaving: ref(false),
    isDeleting: ref(false),
    error: ref(null),
    validationErrors: ref({}),
    fetchExperiences: noop,
    addExperience: noop,
    editExperience: noop,
    removeExperience: noop,
    clearError: vi.fn(),
  }),
}))
vi.mock('@/features/face/composables/useTarifs', () => ({
  useTarifs: () => ({
    tarifsInfo: ref(null),
    isSaving: ref(false),
    error: ref(null),
    fetchTarifs: noop,
    updateTarifs: h.updateTarifs,
  }),
}))
vi.mock('@/features/face/composables/useAvailability', () => ({
  useAvailability: () => ({
    availabilityInfo: ref(null),
    isLoading: ref(false),
    isSaving: ref(false),
    error: ref(null),
    fetchAvailability: noop,
    toggleAvailability: noop,
  }),
}))
vi.mock('@/features/face/composables/useProfileCompletion', () => ({
  useProfileCompletion: () => ({
    isLoading: ref(false),
    error: ref(null),
    percentage: ref(0),
    missingItems: ref([]),
    isComplete: ref(false),
    fetchCompletion: h.fetchCompletion,
  }),
}))
vi.mock('@/features/face/composables/useSubscriptionStatus', () => ({
  useSubscriptionStatus: () => ({
    tier: ref('free'),
    capabilities: ref({
      max_album_photos: 4,
      max_presentation_videos: 1,
      max_acting_videos: 1,
      max_ugc_videos: 1,
      ugc_access: true,
      commission_rate: 0.1,
      sort_priority: 0,
      has_elite_badge: false,
    }),
    offers: ref([]),
    maxAlbumPhotos: ref(4),
    fetchStatus: noop,
  }),
}))

describe('ProfileEditPage - completion refresh after a criterion save', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    h.fetchCompletion.mockResolvedValue(undefined)
    for (const fn of [
      h.updatePersonalInfo,
      h.updateBioLocation,
      h.updateLangues,
      h.updateCategoryNiche,
      h.updateTarifs,
    ]) {
      fn.mockResolvedValue({ success: true })
    }
    h.uploadPhoto.mockResolvedValue({ success: true, message: 'Photo envoyée' })
  })

  async function mountPage() {
    const wrapper = shallowMount(ProfileEditPage)
    await flushPromises()
    // The mount-time load is not a save: only what happens after counts.
    h.fetchCompletion.mockClear()

    return wrapper
  }

  it.each([
    ['personal info (sexe, nationalité, whatsapp)', PersonalInfoForm, { sexe: 'homme' }],
    ['bio / location', BioLocationForm, { bio: 'x', ville: 'Cotonou', pays: 'Bénin' }],
    ['langues', LanguesTagInput, ['Français']],
    ['category / niche', CategoryNicheForm, { categories: ['a'], niches: null }],
    ['tarifs', TarifsForm, { tarif_journalier: 1000 }],
  ])('re-fetches the completion with force after saving %s', async (_label, component, payload) => {
    const wrapper = await mountPage()

    wrapper.findComponent(component).vm.$emit('save', payload)
    await flushPromises()

    expect(h.fetchCompletion).toHaveBeenCalledTimes(1)
    expect(h.fetchCompletion).toHaveBeenCalledWith({ force: true })
  })

  it('re-fetches the completion with force after a profile photo upload', async () => {
    const wrapper = await mountPage()

    wrapper.findComponent(ProfilePhotoUpload).vm.$emit('upload', new File(['x'], 'a.jpg'))
    await flushPromises()

    expect(h.fetchCompletion).toHaveBeenCalledWith({ force: true })
  })

  it('does not re-fetch the completion when the personal info save fails', async () => {
    h.updatePersonalInfo.mockResolvedValue({ success: false })
    const wrapper = await mountPage()

    wrapper.findComponent(PersonalInfoForm).vm.$emit('save', { sexe: 'homme' })
    await flushPromises()

    expect(h.fetchCompletion).not.toHaveBeenCalled()
  })
})
