import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, shallowMount } from '@vue/test-utils'
import { ref } from 'vue'
import ProfileEditPage from '../ProfileEditPage.vue'

const h = await vi.hoisted(async () => {
  const { reactive, ref: vueRef } = await import('vue')
  const route = reactive<{ query: Record<string, string>; path: string }>({
    query: {},
    path: '/face/profile',
  })
  const push = vi.fn((to: { query: Record<string, string> }) => {
    route.query = to.query
    return Promise.resolve()
  })
  return {
    route,
    push,
    missing: vueRef<Array<{ key: string; label: string }>>([]),
  }
})

vi.mock('vue-router', () => ({
  useRoute: () => h.route,
  useRouter: () => ({ push: h.push, replace: vi.fn() }),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({ isAuthenticated: true, user: { email: 'alice@exemple.com' } }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn(), warning: vi.fn() }),
}))

const noop = (): Promise<void> => Promise.resolve()

vi.mock('@/features/face/composables/useProfilePhoto', () => ({
  useProfilePhoto: () => ({
    profile: ref({
      prenom: 'Alice',
      nom: 'Dupont',
      username: 'alice',
      profile_photo_url: null,
      average_rating: 4.5,
      ratings_count: 3,
    }),
    isLoading: ref(false),
    isUploading: ref(false),
    isDeleting: ref(false),
    error: ref(null),
    fetchProfile: noop,
    uploadPhoto: noop,
    deletePhoto: noop,
  }),
}))
vi.mock('@/features/face/composables/usePhotoAlbum', () => ({
  usePhotoAlbum: () => ({
    photos: ref([{ id: 1 }]),
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
    bioLocationInfo: ref({ ville: 'Cotonou' }),
    isSaving: ref(false),
    error: ref(null),
    fetchBioLocation: noop,
    updateBioLocation: noop,
  }),
}))
vi.mock('@/features/face/composables/useLangues', () => ({
  useLangues: () => ({
    languesInfo: ref({ langues: ['Français', 'Fon'] }),
    isLoading: ref(false),
    isSaving: ref(false),
    error: ref(null),
    fetchLangues: noop,
    updateLangues: noop,
    MAX_LANGUES: 5,
    MAX_LANGUE_LENGTH: 30,
  }),
}))
vi.mock('@/features/face/composables/usePersonalInfo', () => ({
  usePersonalInfo: () => ({
    personalInfo: ref({ nationalite: 'Béninoise', date_naissance: null }),
    isLoading: ref(false),
    isSaving: ref(false),
    error: ref(null),
    fetchPersonalInfo: noop,
    updatePersonalInfo: noop,
  }),
}))
vi.mock('@/features/face/composables/usePhysicalCharacteristics', () => ({
  usePhysicalCharacteristics: () => ({
    physicalCharacteristicsInfo: ref({ taille: 178, poids: null }),
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
    updateCategoryNiche: noop,
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
    updateTarifs: noop,
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
    percentage: ref(60),
    missingItems: h.missing,
    isComplete: ref(false),
    fetchCompletion: noop,
  }),
}))
vi.mock('@/features/face/composables/useSubscriptionStatus', () => ({
  useSubscriptionStatus: () => ({
    tier: ref('free'),
    capabilities: ref(null),
    offers: ref([]),
    maxAlbumPhotos: ref(4),
    fetchStatus: noop,
  }),
}))

const ROW_IDS = [
  'photo',
  'infos',
  'identite',
  'physique',
  'langues',
  'bio',
  'album',
  'video-pres',
  'video-acting',
  'video-ugc',
  'categorie',
  'experiences',
  'tarif',
  'securite',
  'donnees',
]

async function mountPage() {
  const wrapper = shallowMount(ProfileEditPage)
  await flushPromises()
  return wrapper
}

describe('ProfileEditPage - liste mobile', () => {
  beforeEach(() => {
    h.route.query = {}
    h.missing.value = []
    h.push.mockClear()
    window.scrollTo = vi.fn() as unknown as typeof window.scrollTo
  })

  it('sans section dans la query, rend la liste avec les 15 lignes', async () => {
    const wrapper = await mountPage()

    expect(wrapper.find('[data-testid="profile-mobile-list"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="mobile-back"]').exists()).toBe(false)
    for (const id of ROW_IDS) {
      expect(wrapper.find(`[data-testid="mobile-row-${id}"]`).exists(), id).toBe(true)
    }
    expect(wrapper.findAll('[data-testid^="mobile-row-"]')).toHaveLength(15)
  })

  it('affiche les aperçus calculés depuis les données déjà chargées', async () => {
    const wrapper = await mountPage()

    expect(wrapper.get('[data-testid="mobile-row-infos"]').text()).toContain('Alice Dupont')
    expect(wrapper.get('[data-testid="mobile-row-physique"]').text()).toContain('178 cm')
    expect(wrapper.get('[data-testid="mobile-row-langues"]').text()).toContain('Français, Fon')
    expect(wrapper.get('[data-testid="mobile-row-album"]').text()).toContain('1 photo')
    expect(wrapper.get('[data-testid="mobile-row-securite"]').text()).toContain('a•••@exemple.com')
  })

  it('avec ?tab=profil&section=identite, masque la liste et affiche la barre retour', async () => {
    h.route.query = { tab: 'profil', section: 'identite' }
    const wrapper = await mountPage()

    expect(wrapper.find('[data-testid="profile-mobile-list"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="mobile-back"]').exists()).toBe(true)
  })

  it('un appui sur une ligne pousse { tab, section } et ouvre le détail', async () => {
    const wrapper = await mountPage()

    await wrapper.get('[data-testid="mobile-row-bio"]').trigger('click')
    await flushPromises()

    expect(h.push).toHaveBeenCalledWith({ query: { tab: 'profil', section: 'bio' } })
    expect(wrapper.find('[data-testid="profile-mobile-list"]').exists()).toBe(false)
    expect(window.scrollTo).toHaveBeenCalledWith({ top: 0 })
  })

  it('« Mon profil » pousse une query sans tab ni section, en gardant les autres clés', async () => {
    h.route.query = { tab: 'portfolio', section: 'album', foo: 'bar' }
    const wrapper = await mountPage()

    await wrapper.get('[data-testid="mobile-back"]').trigger('click')
    await flushPromises()

    expect(h.push).toHaveBeenCalledWith({ query: { foo: 'bar' } })
    expect(wrapper.find('[data-testid="profile-mobile-list"]').exists()).toBe(true)
  })

  it('une section incomplète affiche « À compléter » sur sa ligne', async () => {
    h.missing.value = [
      { key: 'langues', label: 'Langues' },
      { key: 'profile_photo', label: 'Photo' },
    ]
    const wrapper = await mountPage()

    expect(wrapper.get('[data-testid="mobile-row-langues"]').text()).toContain('À compléter')
    expect(wrapper.get('[data-testid="mobile-row-photo"]').text()).toContain('À compléter')
    expect(wrapper.get('[data-testid="mobile-row-bio"]').text()).not.toContain('À compléter')
  })

  it('« Section suivante » depuis identite mène à physique', async () => {
    h.route.query = { tab: 'profil', section: 'identite' }
    const wrapper = await mountPage()

    const next = wrapper.get('[data-testid="mobile-next"]')
    expect(next.text()).toContain('Caractéristiques physiques')
    await next.trigger('click')

    expect(h.push).toHaveBeenCalledWith({ query: { tab: 'profil', section: 'physique' } })
  })

  it('section=photo est accepté, ouvre le détail, et « Section suivante » mène à infos', async () => {
    h.route.query = { tab: 'profil', section: 'photo' }
    const wrapper = await mountPage()

    expect(wrapper.find('[data-testid="profile-mobile-list"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="mobile-back"]').exists()).toBe(true)
    // Sur md+, le panneau « infos » reste le panneau actif.
    expect(wrapper.get('[data-testid="panel-infos"]').attributes('style') ?? '').not.toContain(
      'display: none',
    )

    const next = wrapper.get('[data-testid="mobile-next"]')
    expect(next.text()).toContain('Infos perso')
    await next.trigger('click')

    expect(h.push).toHaveBeenCalledWith({ query: { tab: 'profil', section: 'infos' } })
  })

  it('la ligne photo pousse section=photo dans la famille profil', async () => {
    const wrapper = await mountPage()

    await wrapper.get('[data-testid="mobile-row-photo"]').trigger('click')

    expect(h.push).toHaveBeenCalledWith({ query: { tab: 'profil', section: 'photo' } })
  })

  it('aucune section suivante après « Mes données »', async () => {
    h.route.query = { tab: 'compte', section: 'donnees' }
    const wrapper = await mountPage()

    expect(wrapper.find('[data-testid="mobile-next"]').exists()).toBe(false)
  })
})
