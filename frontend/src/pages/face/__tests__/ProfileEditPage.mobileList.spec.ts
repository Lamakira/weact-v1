import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
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

// matchMedia simulé : `mobile` = vrai sous md (max-width: 767px).
function mockMatchMedia(mobile: boolean): void {
  Object.defineProperty(window, 'matchMedia', {
    configurable: true,
    writable: true,
    value: (query: string) => ({
      matches: query.includes('max-width') ? mobile : !mobile,
      media: query,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
    }),
  })
}

let wrappers: Array<{ unmount: () => void }> = []

// attachTo : les panneaux téléportés vivent hors du wrapper, il faut un vrai document.
async function mountPage() {
  const wrapper = shallowMount(ProfileEditPage, {
    attachTo: document.body,
    global: { stubs: { teleport: false } },
  })
  wrappers.push(wrapper)
  await flushPromises()
  return wrapper
}

const slot = (id: string): Element | null => document.querySelector(`[data-testid="mobile-slot-${id}"]`)
const row = (id: string): Element | null => document.querySelector(`[data-testid="mobile-row-${id}"]`)
const panel = (id: string): Element | null => document.querySelector(`[data-testid="panel-${id}"]`)

describe('ProfileEditPage - liste mobile (dépliant)', () => {
  let scrollSpy: ReturnType<typeof vi.fn>

  beforeEach(() => {
    h.route.query = {}
    h.missing.value = []
    h.push.mockClear()
    mockMatchMedia(true)
    scrollSpy = vi.fn()
    Element.prototype.scrollIntoView = scrollSpy as unknown as Element['scrollIntoView']
  })

  afterEach(() => {
    wrappers.forEach((w) => w.unmount())
    wrappers = []
    // @ts-expect-error nettoyage du mock
    delete window.matchMedia
  })

  it('sans section dans la query, rend la liste avec les 15 lignes', async () => {
    const wrapper = await mountPage()

    expect(wrapper.find('[data-testid="profile-mobile-list"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="mobile-back"]').exists()).toBe(false)
    for (const id of ROW_IDS) {
      expect(wrapper.find(`[data-testid="mobile-row-${id}"]`).exists(), id).toBe(true)
      expect(row(id)?.getAttribute('aria-expanded'), id).toBe('false')
      expect(row(id)?.getAttribute('aria-controls'), id).toBe(`mobile-slot-${id}`)
    }
    expect(wrapper.findAll('[data-testid^="mobile-row-"]')).toHaveLength(15)
    expect(scrollSpy).not.toHaveBeenCalled()
  })

  it('affiche les aperçus calculés depuis les données déjà chargées', async () => {
    const wrapper = await mountPage()

    expect(wrapper.get('[data-testid="mobile-row-infos"]').text()).toContain('Alice Dupont')
    expect(wrapper.get('[data-testid="mobile-row-physique"]').text()).toContain('178 cm')
    expect(wrapper.get('[data-testid="mobile-row-langues"]').text()).toContain('Français, Fon')
    expect(wrapper.get('[data-testid="mobile-row-album"]').text()).toContain('1 photo')
    expect(wrapper.get('[data-testid="mobile-row-securite"]').text()).toContain('a•••@exemple.com')
  })

  it('avec ?tab=profil&section=identite, la liste reste rendue et Identité est dépliée sous sa ligne', async () => {
    h.route.query = { tab: 'profil', section: 'identite' }
    const wrapper = await mountPage()

    expect(wrapper.find('[data-testid="profile-mobile-list"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="mobile-back"]').exists()).toBe(false)
    expect(row('identite')?.getAttribute('aria-expanded')).toBe('true')
    expect(slot('identite')?.querySelector('[data-testid="panel-identite"]')).not.toBeNull()
    expect(row('infos')?.getAttribute('aria-expanded')).toBe('false')
  })

  it('un appui sur une ligne fermée pousse { tab, section } et déplie la section', async () => {
    const wrapper = await mountPage()

    await wrapper.get('[data-testid="mobile-row-bio"]').trigger('click')
    await flushPromises()

    expect(h.push).toHaveBeenCalledWith({ query: { tab: 'profil', section: 'bio' } })
    expect(wrapper.find('[data-testid="profile-mobile-list"]').exists()).toBe(true)
    expect(row('bio')?.getAttribute('aria-expanded')).toBe('true')
    expect(slot('bio')?.querySelector('[data-testid="panel-bio"]')).not.toBeNull()
  })

  it('un appui sur la ligne ouverte la referme : push sans tab ni section, autres clés gardées, sans défilement', async () => {
    h.route.query = { tab: 'portfolio', section: 'album', foo: 'bar' }
    const wrapper = await mountPage()
    scrollSpy.mockClear()

    // Sur téléphone, « Mon profil » n'apparaît que dans la barre du haut.
    expect(wrapper.get('[data-testid="profile-page-heading"]').classes()).toEqual(
      expect.arrayContaining(['hidden', 'md:block']),
    )
    expect(row('album')?.getAttribute('aria-expanded')).toBe('true')

    await wrapper.get('[data-testid="mobile-row-album"]').trigger('click')
    await flushPromises()

    expect(h.push).toHaveBeenCalledWith({ query: { foo: 'bar' } })
    expect(row('album')?.getAttribute('aria-expanded')).toBe('false')
    expect(slot('album')?.children).toHaveLength(0)
    expect(scrollSpy).not.toHaveBeenCalled()
  })

  it('une seule section ouverte à la fois : ouvrir Langues ferme Identité', async () => {
    h.route.query = { tab: 'profil', section: 'identite' }
    const wrapper = await mountPage()

    await wrapper.get('[data-testid="mobile-row-langues"]').trigger('click')
    await flushPromises()

    expect(h.push).toHaveBeenCalledWith({ query: { tab: 'profil', section: 'langues' } })
    expect(row('langues')?.getAttribute('aria-expanded')).toBe('true')
    expect(row('identite')?.getAttribute('aria-expanded')).toBe('false')
    expect(slot('langues')?.querySelector('[data-testid="panel-langues"]')).not.toBeNull()
    expect(slot('identite')?.querySelector('[data-testid="panel-identite"]')).toBeNull()
    expect(document.querySelectorAll('[aria-expanded="true"]')).toHaveLength(1)
  })

  it('le panneau n\'est rendu qu\'une fois dans le DOM (pas de double instance)', async () => {
    h.route.query = { tab: 'profil', section: 'identite' }
    await mountPage()
    expect(document.querySelectorAll('[data-testid="panel-identite"]')).toHaveLength(1)

    h.route.query = { tab: 'profil', section: 'langues' }
    await flushPromises()
    expect(document.querySelectorAll('[data-testid="panel-identite"]')).toHaveLength(1)
    expect(document.querySelectorAll('[data-testid="panel-langues"]')).toHaveLength(1)
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

  it('« Section suivante » depuis identite ouvre physique', async () => {
    h.route.query = { tab: 'profil', section: 'identite' }
    const wrapper = await mountPage()

    const next = wrapper.get('[data-testid="mobile-next"]')
    expect(next.text()).toContain('Section suivante : Caractéristiques physiques')
    await next.trigger('click')
    await flushPromises()

    expect(h.push).toHaveBeenCalledWith({ query: { tab: 'profil', section: 'physique' } })
    expect(row('physique')?.getAttribute('aria-expanded')).toBe('true')
  })

  it('section=photo : la ligne photo est dépliée avec la carte photo, et « Section suivante » mène à infos', async () => {
    h.route.query = { tab: 'profil', section: 'photo' }
    const wrapper = await mountPage()

    expect(wrapper.find('[data-testid="profile-mobile-list"]').exists()).toBe(true)
    expect(row('photo')?.getAttribute('aria-expanded')).toBe('true')
    expect(slot('photo')?.querySelector('#section-profile-photo')).not.toBeNull()
    // Le panneau « infos » reste actif côté ordinateur mais n'est pas déplié sur téléphone.
    expect(row('infos')?.getAttribute('aria-expanded')).toBe('false')
    expect(slot('infos')?.querySelector('[data-testid="panel-infos"]')).toBeNull()

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

    expect(row('donnees')?.getAttribute('aria-expanded')).toBe('true')
    expect(wrapper.find('[data-testid="mobile-next"]').exists()).toBe(false)
  })

  it('à l\'ouverture, fait défiler l\'en-tête de la ligne ouverte en haut de la zone visible', async () => {
    const wrapper = await mountPage()
    expect(scrollSpy).not.toHaveBeenCalled()

    await wrapper.get('[data-testid="mobile-row-bio"]').trigger('click')
    await flushPromises()

    expect(scrollSpy).toHaveBeenCalledWith({ block: 'start' })
    expect(scrollSpy.mock.contexts[0]).toBe(row('bio'))
  })

  it('un lien profond arrive avec la section dépliée et calée en haut', async () => {
    h.route.query = { tab: 'profil', section: 'identite' }
    await mountPage()

    expect(scrollSpy).toHaveBeenCalledWith({ block: 'start' })
    expect(scrollSpy.mock.contexts[0]).toBe(row('identite'))
  })

  it('au-dessus de md, rien n\'est téléporté et la mise en page ordinateur est intacte', async () => {
    mockMatchMedia(false)
    h.route.query = { tab: 'profil', section: 'identite' }
    const wrapper = await mountPage()

    for (const id of ['identite', 'infos', 'langues']) {
      expect(slot(id)?.children, id).toHaveLength(0)
    }
    expect(slot('photo')?.children).toHaveLength(0)
    const desktop = wrapper.get('div.hidden.md\\:flex')
    expect(desktop.find('[data-testid="panel-identite"]').exists()).toBe(true)
    expect(desktop.find('#section-profile-photo').exists()).toBe(true)
    expect(desktop.get('[data-testid="panel-identite"]').attributes('style') ?? '').not.toContain(
      'display: none',
    )
    expect(desktop.get('[data-testid="panel-langues"]').attributes('style') ?? '').toContain(
      'display: none',
    )
    expect(scrollSpy).not.toHaveBeenCalled()
  })

  it('le panneau reste monté à sa place tant qu\'aucune section n\'est ouverte', async () => {
    await mountPage()

    expect(panel('infos')).not.toBeNull()
    expect(slot('infos')?.children).toHaveLength(0)
  })
})
