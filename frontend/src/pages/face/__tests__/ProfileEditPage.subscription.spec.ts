import { beforeEach, describe, expect, it, vi } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import ProfileEditPage from '../ProfileEditPage.vue'

const mocks = vi.hoisted(() => ({
  ref: <T>(value: T) => ({ __v_isRef: true, value }),
  albumPhotos: { __v_isRef: true, value: [] as Array<{ id: number; position: number; photo_url: string; thumbnail_url: string }> },
  canAddMore: { __v_isRef: true, value: true },
  capabilities: {
    __v_isRef: true,
    value: {
      max_album_photos: 2,
      max_presentation_videos: 0,
      max_acting_videos: 0,
      max_ugc_videos: 0,
      ugc_access: false,
      commission_rate: 0.1,
      sort_priority: 4,
      has_elite_badge: false,
    },
  },
  maxAlbumPhotos: { __v_isRef: true, value: 2 },
  addAlbumPhoto: vi.fn(),
  uploadActingVideo: vi.fn(),
  toastWarning: vi.fn(),
  resolved: vi.fn(() => Promise.resolve()),
}))

vi.mock('vue-router', () => ({
  useRoute: () => ({ query: {}, path: '/face/profile' }),
  useRouter: () => ({ replace: vi.fn() }),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({ isEmailVerified: true }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    success: vi.fn(),
    error: vi.fn(),
    warning: mocks.toastWarning,
  }),
}))

vi.mock('@/features/face/composables/useProfilePhoto', () => ({
  useProfilePhoto: () => ({
    profile: mocks.ref(null),
    isLoading: mocks.ref(false),
    isUploading: mocks.ref(false),
    isDeleting: mocks.ref(false),
    error: mocks.ref(null),
    fetchProfile: mocks.resolved,
    uploadPhoto: vi.fn(),
    deletePhoto: vi.fn(),
  }),
}))

vi.mock('@/features/face/composables/usePhotoAlbum', () => ({
  usePhotoAlbum: () => ({
    photos: mocks.albumPhotos,
    isLoading: { value: false },
    isUploading: { value: false },
    isDeleting: { value: false },
    error: { value: null },
    canAddMore: mocks.canAddMore,
    fetchPhotos: mocks.resolved,
    addPhoto: mocks.addAlbumPhoto,
    deletePhoto: vi.fn(),
  }),
}))

vi.mock('@/features/face/composables/usePresentationVideo', () => ({
  usePresentationVideo: () => ({
    videoInfo: { value: null },
    isUploading: { value: false },
    isDeleting: { value: false },
    error: { value: null },
    uploadProgress: { value: null },
    fetchVideoInfo: mocks.resolved,
    uploadVideo: vi.fn(),
    deleteVideo: vi.fn(),
  }),
}))

vi.mock('@/features/face/composables/useActingVideo', () => ({
  useActingVideo: () => ({
    videoInfo: { value: null },
    isUploading: { value: false },
    isDeleting: { value: false },
    error: { value: null },
    uploadProgress: { value: null },
    fetchVideoInfo: mocks.resolved,
    uploadVideo: mocks.uploadActingVideo,
    deleteVideo: vi.fn(),
  }),
}))

vi.mock('@/features/face/composables/useBioLocation', () => ({
  useBioLocation: () => ({
    bioLocationInfo: { value: null },
    isSaving: { value: false },
    error: { value: null },
    fetchBioLocation: mocks.resolved,
    updateBioLocation: vi.fn(),
  }),
}))

vi.mock('@/features/face/composables/useLangues', () => ({
  useLangues: () => ({
    languesInfo: { value: null },
    isLoading: { value: false },
    isSaving: { value: false },
    error: { value: null },
    fetchLangues: mocks.resolved,
    updateLangues: vi.fn(),
    MAX_LANGUES: 5,
    MAX_LANGUE_LENGTH: 50,
  }),
}))

vi.mock('@/features/face/composables/usePersonalInfo', () => ({
  usePersonalInfo: () => ({
    personalInfo: { value: null },
    isLoading: { value: false },
    isSaving: { value: false },
    error: { value: null },
    fetchPersonalInfo: mocks.resolved,
    updatePersonalInfo: vi.fn(),
  }),
}))

vi.mock('@/features/face/composables/usePhysicalCharacteristics', () => ({
  usePhysicalCharacteristics: () => ({
    physicalCharacteristics: { value: null },
    isLoading: { value: false },
    isSaving: { value: false },
    error: { value: null },
    fetchPhysicalCharacteristics: mocks.resolved,
    updatePhysicalCharacteristics: vi.fn(),
  }),
}))

vi.mock('@/features/face/composables/useCategoryNiche', () => ({
  useCategoryNiche: () => ({
    categoryNiche: { value: null },
    isLoading: { value: false },
    isSaving: { value: false },
    error: { value: null },
    categoryOptions: { value: [] },
    nicheOptions: { value: [] },
    fetchCategoryNiche: mocks.resolved,
    updateCategoryNiche: vi.fn(),
    fetchCategoryOptions: mocks.resolved,
    fetchNicheOptions: mocks.resolved,
  }),
}))

vi.mock('@/features/face/composables/useExperiences', () => ({
  useExperiences: () => ({
    experiences: { value: [] },
    isLoading: { value: false },
    isCreating: { value: false },
    isUpdating: { value: false },
    isDeleting: { value: false },
    error: { value: null },
    fetchExperiences: mocks.resolved,
    addExperience: vi.fn(),
    editExperience: vi.fn(),
    removeExperience: vi.fn(),
    clearError: vi.fn(),
  }),
}))

vi.mock('@/features/face/composables/useTarifs', () => ({
  useTarifs: () => ({
    tarifsInfo: { value: null },
    isLoading: { value: false },
    isSaving: { value: false },
    error: { value: null },
    fetchTarifs: mocks.resolved,
    updateTarifs: vi.fn(),
  }),
}))

vi.mock('@/features/face/composables/useAvailability', () => ({
  useAvailability: () => ({
    availabilityInfo: { value: null },
    isLoading: { value: false },
    isToggling: { value: false },
    error: { value: null },
    fetchAvailability: mocks.resolved,
    toggleAvailability: vi.fn(),
  }),
}))

vi.mock('@/features/face/composables/useProfileCompletion', () => ({
  useProfileCompletion: () => ({
    isLoading: { value: false },
    error: { value: null },
    percentage: { value: 0 },
    missingItems: { value: [] },
    isComplete: { value: false },
    fetchCompletion: mocks.resolved,
  }),
}))

vi.mock('@/features/face/composables/useSubscriptionStatus', () => ({
  useSubscriptionStatus: () => ({
    capabilities: mocks.capabilities,
    maxAlbumPhotos: mocks.maxAlbumPhotos,
    fetchStatus: mocks.resolved,
  }),
}))

function mountPage() {
  return shallowMount(ProfileEditPage, {
    global: {
      stubs: {
        SubscriptionPanel: true,
        PhotoAlbumGrid: {
          template: '<button data-testid="grid-add" @click="$emit(\'add-click\')">add</button>',
        },
        ActingVideoUpload: {
          template: '<button data-testid="acting-upload" @click="$emit(\'upload\', {})">upload</button>',
        },
      },
    },
  })
}

describe('ProfileEditPage subscription guards', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mocks.albumPhotos.value = [
      { id: 1, position: 1, photo_url: '/1.jpg', thumbnail_url: '/1.jpg' },
      { id: 2, position: 2, photo_url: '/2.jpg', thumbnail_url: '/2.jpg' },
    ]
    mocks.canAddMore.value = true
    mocks.maxAlbumPhotos.value = 2
    mocks.capabilities.value.max_acting_videos = 0
  })

  it('does not open the hidden album file input from grid shortcut when entitlement quota is reached', async () => {
    const wrapper = mountPage()
    const input = wrapper.find('input[accept="image/jpeg,image/png"]').element as HTMLInputElement
    const clickSpy = vi.spyOn(input, 'click')

    await wrapper.find('[data-testid="grid-add"]').trigger('click')

    expect(clickSpy).not.toHaveBeenCalled()
    expect(mocks.toastWarning).toHaveBeenCalledWith(
      'Votre quota de photos est atteint pour votre abonnement actuel.',
    )
  })

  it('does not upload from the hidden album file input when entitlement quota is reached', async () => {
    const wrapper = mountPage()
    const input = wrapper.find('input[accept="image/jpeg,image/png"]')
    const file = new File(['x'], 'photo.jpg', { type: 'image/jpeg' })

    Object.defineProperty(input.element, 'files', {
      value: [file],
      writable: false,
    })

    await input.trigger('change')

    expect(mocks.addAlbumPhoto).not.toHaveBeenCalled()
  })

  it('does not forward acting-video upload when entitlement disallows it', async () => {
    const wrapper = mountPage()

    await wrapper.find('[data-testid="acting-upload"]').trigger('click')

    expect(mocks.uploadActingVideo).not.toHaveBeenCalled()
    expect(mocks.toastWarning).toHaveBeenCalledWith(
      "La vidéo d'acting est réservée aux abonnés Premium.",
    )
  })
})
