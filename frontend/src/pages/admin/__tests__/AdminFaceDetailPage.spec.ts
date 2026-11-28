import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import type { AdminFaceData } from '@/features/admin/services/adminFacesApi'
import AdminFaceDetailPage from '../AdminFaceDetailPage.vue'

const mockFetchFace = vi.fn()
const mockUpdateFace = vi.fn()
const mockToggleActive = vi.fn()
const mockDeleteFace = vi.fn()
const mockToastSuccess = vi.fn()
const mockToastError = vi.fn()
const mockToastWarning = vi.fn()
const mockToastInfo = vi.fn()

let faceRef = ref<AdminFaceData | null>(null)
let isLoadingRef = ref(false)
let errorRef = ref<string | null>(null)

vi.mock('@/features/admin/composables/useAdminFaces', () => ({
  useAdminFaces: () => ({
    face: faceRef,
    isLoading: isLoadingRef,
    error: errorRef,
    fetchFace: mockFetchFace,
    updateFace: mockUpdateFace,
    toggleActive: mockToggleActive,
    deleteFace: mockDeleteFace,
  }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    success: mockToastSuccess,
    error: mockToastError,
    warning: mockToastWarning,
    info: mockToastInfo,
  }),
}))

function makeFace(overrides: Partial<AdminFaceData> = {}): AdminFaceData {
  return {
    id: 1,
    nom: 'Doe',
    prenom: 'Jane',
    username: 'jane-doe',
    bio: 'Bio test',
    ville: 'Cotonou',
    pays: 'Bénin',
    whatsapp_number: '+22997000000',
    categories: [{ value: 'acteur', label: 'Acteur' }],
    niches: [{ value: 'beaute', label: 'Beauté' }],
    is_available: true,
    is_exclusive: false,
    subscription_tier: 'elite',
    exclusive_slots_taken: 1,
    exclusive_slots_max: 4,
    profile_completion_percentage: 80,
    profile_completion_missing: [],
    profile_completion_is_complete: false,
    average_rating: 4.5,
    ratings_count: 12,
    profile_photo_url: null,
    thumbnail_url: null,
    presentation_video_url: null,
    presentation_video_thumbnail_url: null,
    taille: null,
    poids: null,
    tarif_horaire: null,
    tarif_journalier: null,
    formatted_tarif_horaire: null,
    formatted_tarif_journalier: null,
    formatted_location: 'Cotonou, Bénin',
    photos: [],
    experiences: [],
    photos_count: 0,
    experiences_count: 0,
    created_at: '2026-04-05T12:00:00Z',
    updated_at: '2026-04-05T12:00:00Z',
    email: 'jane@example.com',
    is_active: true,
    ...overrides,
  }
}

async function mountPage(face: AdminFaceData) {
  faceRef.value = face
  isLoadingRef.value = false
  errorRef.value = null

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/admin/faces', name: 'admin-faces-list', component: { template: '<div>List</div>' } },
      { path: '/admin/faces/:id', name: 'admin-face-detail', component: AdminFaceDetailPage },
    ],
  })

  await router.push('/admin/faces/1')
  await router.isReady()

  const wrapper = mount(AdminFaceDetailPage, {
    global: {
      plugins: [router],
      stubs: {
        ConfirmModal: {
          template: '<div><slot /></div>',
        },
        AdminFaceSubscriptionSection: {
          template: '<div data-testid="admin-face-subscription-section-stub" />',
        },
      },
    },
  })

  await flushPromises()

  return { wrapper, router }
}

describe('AdminFaceDetailPage', () => {
  beforeEach(() => {
    faceRef = ref<AdminFaceData | null>(null)
    isLoadingRef = ref(false)
    errorRef = ref<string | null>(null)
    mockFetchFace.mockReset()
    mockUpdateFace.mockReset()
    mockToggleActive.mockReset()
    mockDeleteFace.mockReset()
    mockToastSuccess.mockReset()
    mockToastError.mockReset()
    mockToastWarning.mockReset()
    mockToastInfo.mockReset()
  })

  it('shows the exclusivity pill and the slot counter in read-only mode when the face is exclusive', async () => {
    const { wrapper } = await mountPage(makeFace({ is_exclusive: true, exclusive_slots_taken: 3 }))

    const badge = wrapper.get('[data-testid="exclusive-badge"]')

    expect(badge.text()).toContain('En tête de la page 1')
    expect(wrapper.get('[data-testid="exclusive-slots"]').text()).toBe('3 / 4 places prises')
    expect(wrapper.text()).toContain('Exclusivité Élite')
  })

  it('shows a grey "Non" pill and the slot counter when the face is not exclusive', async () => {
    const { wrapper } = await mountPage(makeFace({ is_exclusive: false, exclusive_slots_taken: 2 }))

    expect(wrapper.get('[data-testid="exclusive-badge"]').text()).toBe('Non')
    expect(wrapper.get('[data-testid="exclusive-slots"]').text()).toBe('2 / 4 places prises')
  })

  it('never shows the old "En vedette" or "Mise en avant" wording', async () => {
    const { wrapper } = await mountPage(makeFace({ is_exclusive: true }))
    await wrapper.get('[data-testid="edit-button"]').trigger('click')

    expect(wrapper.text()).not.toContain('En vedette')
    expect(wrapper.text()).not.toContain('Mise en avant')
  })

  it('enables the checkbox with the slot counter as help text for an Elite face', async () => {
    const { wrapper } = await mountPage(makeFace({ exclusive_slots_taken: 2 }))
    await wrapper.get('[data-testid="edit-button"]').trigger('click')

    const checkbox = wrapper.get('[data-testid="edit-exclusive"]')
    expect(checkbox.attributes('disabled')).toBeUndefined()
    expect(wrapper.get('[data-testid="exclusive-help"]').text()).toBe(
      'Place la Face en tête de la page 1. 2 / 4 places prises.',
    )
    const helpId = wrapper.get('[data-testid="exclusive-help"]').attributes('id')
    expect(checkbox.attributes('aria-describedby')).toContain(helpId)
    expect(wrapper.get('label[for="edit-exclusive"]').text()).toContain('Exclusivité Élite')
  })

  it.each([
    ['free', 'Découverte'],
    ['starter', 'Starter'],
    ['pro', 'Pro'],
  ] as const)('disables the checkbox for a %s face and names its tier', async (tier, label) => {
    const { wrapper } = await mountPage(makeFace({ subscription_tier: tier }))
    await wrapper.get('[data-testid="edit-button"]').trigger('click')

    expect(wrapper.get('[data-testid="edit-exclusive"]').attributes('disabled')).toBeDefined()
    expect(wrapper.get('[data-testid="exclusive-help"]').text()).toBe(
      `Réservée aux Faces Élite. Cette Face est ${label}.`,
    )
  })

  it('disables the checkbox when the 4 slots are taken and the face does not hold one', async () => {
    const { wrapper } = await mountPage(makeFace({ exclusive_slots_taken: 4 }))
    await wrapper.get('[data-testid="edit-button"]').trigger('click')

    expect(wrapper.get('[data-testid="edit-exclusive"]').attributes('disabled')).toBeDefined()
    expect(wrapper.get('[data-testid="exclusive-help"]').text()).toBe(
      'Les 4 places sont prises. Retirez d\u2019abord l\u2019exclusivité d\u2019une autre Face.',
    )
  })

  it('lets a face that holds a slot uncheck it even when the 4 slots are taken', async () => {
    mockUpdateFace.mockResolvedValue({ success: true, message: 'ok' })
    const { wrapper } = await mountPage(makeFace({ is_exclusive: true, exclusive_slots_taken: 4 }))
    await wrapper.get('[data-testid="edit-button"]').trigger('click')

    const checkbox = wrapper.get('[data-testid="edit-exclusive"]')
    expect(checkbox.attributes('disabled')).toBeUndefined()
    await checkbox.setValue(false)
    await wrapper.get('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(mockUpdateFace).toHaveBeenCalledWith('1', { is_exclusive: false })
  })

  it('shows the server is_exclusive error under the checkbox and keeps the other edits', async () => {
    mockUpdateFace.mockResolvedValue({
      success: false,
      message: 'Les données fournies sont invalides.',
      errors: { is_exclusive: ['Les 4 places d\u2019exclusivité Élite sont prises. Retirez d\u2019abord une exclusivité.'] },
    })
    const { wrapper } = await mountPage(makeFace())
    await wrapper.get('[data-testid="edit-button"]').trigger('click')
    await wrapper.get('[data-testid="edit-exclusive"]').setValue(true)
    await wrapper.get('[data-testid="edit-prenom"]').setValue('Nouveau prénom')
    await wrapper.get('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    const error = wrapper.get('[data-testid="exclusive-error"]')
    expect(error.text()).toContain('Les 4 places')
    expect(error.attributes('aria-live')).toBe('polite')
    expect((wrapper.get('[data-testid="edit-prenom"]').element as HTMLInputElement).value).toBe('Nouveau prénom')
    expect((wrapper.get('[data-testid="edit-exclusive"]').element as HTMLInputElement).checked).toBe(true)
  })

  it('includes is_exclusive in the update payload when the checkbox changes', async () => {
    mockUpdateFace.mockResolvedValue({
      success: true,
      message: 'Profil Face mis à jour avec succès',
    })

    const { wrapper } = await mountPage(makeFace({ is_exclusive: false }))

    await wrapper.get('[data-testid="edit-button"]').trigger('click')
    await wrapper.get('[data-testid="edit-exclusive"]').setValue(true)
    await wrapper.get('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(mockUpdateFace).toHaveBeenCalledWith('1', { is_exclusive: true })
    expect(mockToastSuccess).toHaveBeenCalled()
  })

  it('renders pays as a select dropdown in edit mode', async () => {
    const { wrapper } = await mountPage(makeFace())

    await wrapper.get('[data-testid="edit-button"]').trigger('click')

    const paysField = wrapper.get('[data-testid="edit-pays"]')

    expect(paysField.element.tagName).toBe('SELECT')
    expect((paysField.element as HTMLSelectElement).value).toBe('Bénin')
  })

  it('preserves a legacy free-text pays value in the admin dropdown', async () => {
    const legacyPays = 'Benin Republic'
    const { wrapper } = await mountPage(makeFace({ pays: legacyPays, formatted_location: legacyPays }))

    await wrapper.get('[data-testid="edit-button"]').trigger('click')

    const paysField = wrapper.get('[data-testid="edit-pays"]')
    const optionValues = Array.from((paysField.element as HTMLSelectElement).options).map((option) => option.value)

    expect((paysField.element as HTMLSelectElement).value).toBe(legacyPays)
    expect(optionValues).toContain(legacyPays)
  })

  it('displays email with mailto link when face has email', async () => {
    const { wrapper } = await mountPage(makeFace({ email: 'jane@example.com' }))

    const section = wrapper.get('[data-testid="personal-info-section"]')
    const emailLink = section.find('a[href="mailto:jane@example.com"]')

    expect(emailLink.exists()).toBe(true)
    expect(emailLink.text()).toBe('jane@example.com')
  })

  it('displays dash when face has no email', async () => {
    const { wrapper } = await mountPage(makeFace({ email: undefined }))

    const section = wrapper.get('[data-testid="personal-info-section"]')
    const emailLink = section.find('a[href^="mailto:"]')

    expect(emailLink.exists()).toBe(false)

    const emailDts = section.findAll('dt').filter((dt) => dt.text().includes('Email'))
    expect(emailDts.length).toBe(1)
    const dd = emailDts[0].element.parentElement!.querySelector('dd')!
    expect(dd.textContent?.trim()).toBe('—')
  })

  it('keeps email visible after a successful save', async () => {
    mockUpdateFace.mockImplementation(async () => {
      faceRef.value = makeFace({
        prenom: 'Janette',
        email: 'jane@example.com',
      })

      return {
        success: true,
        message: 'Profil Face mis à jour avec succès',
      }
    })

    const { wrapper } = await mountPage(makeFace({ email: 'jane@example.com' }))

    await wrapper.get('[data-testid="edit-button"]').trigger('click')
    await wrapper.get('[data-testid="edit-prenom"]').setValue('Janette')
    await wrapper.get('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    const section = wrapper.get('[data-testid="personal-info-section"]')
    const emailLink = section.find('a[href="mailto:jane@example.com"]')

    expect(emailLink.exists()).toBe(true)
    expect(section.text()).toContain('Janette')
  })

  it('does not clear ville when only toggling featured on a non-Benin profile', async () => {
    mockUpdateFace.mockResolvedValue({
      success: true,
      message: 'Profil Face mis à jour avec succès',
    })

    const { wrapper } = await mountPage(
      makeFace({
        pays: 'France',
        ville: 'Paris',
        is_exclusive: false,
      }),
    )

    await wrapper.get('[data-testid="edit-button"]').trigger('click')
    await wrapper.get('[data-testid="edit-exclusive"]').setValue(true)
    await wrapper.get('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(mockUpdateFace).toHaveBeenCalledWith('1', { is_exclusive: true })
  })

  it('liste les vidéos du portfolio et marque la vidéo verrouillée', async () => {
    const { wrapper } = await mountPage(
      makeFace({
        videos: [
          { id: 'u1', type: 'ugc', video_url: 'https://x/u1.mp4', thumbnail_url: null, position: 1 },
          { id: 'a1', type: 'acting', video_url: 'https://x/a1.mp4', thumbnail_url: 'https://x/a1.jpg', position: 1 },
          { id: 'a2', type: 'acting', video_url: 'https://x/a2.mp4', thumbnail_url: null, position: 2, is_locked: true, lock_reason: 'tier' },
        ],
      }),
    )

    const box = wrapper.get('[data-testid="portfolio-videos"]')
    expect(box.text()).toContain('Vidéos du portfolio')
    expect(box.get('[data-testid="portfolio-video-0"]').text()).toContain("Vidéo d'acting")
    expect(box.get('[data-testid="portfolio-video-1"]').text()).toContain("Vidéo d'acting 2")
    expect(box.get('[data-testid="portfolio-video-1"]').text()).toContain('Non visible : palier actuel')
    expect(box.get('[data-testid="portfolio-video-2"]').text()).toContain('Vidéo UGC')
    expect(box.get('[data-testid="portfolio-video-0"]').text()).not.toContain('Non visible')
    expect(wrapper.find('[data-testid="acting-video"]').exists()).toBe(false)
  })

  it("affiche l'état vide quand il n'y a aucune vidéo de portfolio", async () => {
    const { wrapper } = await mountPage(makeFace({ videos: [] }))

    expect(wrapper.get('[data-testid="portfolio-videos"]').text()).toContain('Pas de vidéo')
  })
})
