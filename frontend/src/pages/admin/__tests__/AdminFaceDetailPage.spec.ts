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
    is_featured: false,
    profile_completion_percentage: 80,
    profile_completion_missing: [],
    profile_completion_is_complete: false,
    average_rating: 4.5,
    ratings_count: 12,
    profile_photo_url: null,
    thumbnail_url: null,
    presentation_video_url: null,
    presentation_video_thumbnail_url: null,
    acting_video_url: null,
    acting_video_thumbnail_url: null,
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

  it('shows a featured badge in read-only mode when the face is featured', async () => {
    const { wrapper } = await mountPage(makeFace({ is_featured: true }))

    const badge = wrapper.get('[data-testid="featured-badge"]')

    expect(badge.text()).toContain('En vedette')
  })

  it('includes is_featured in the update payload when the checkbox changes', async () => {
    mockUpdateFace.mockResolvedValue({
      success: true,
      message: 'Profil Face mis à jour avec succès',
    })

    const { wrapper } = await mountPage(makeFace({ is_featured: false }))

    await wrapper.get('[data-testid="edit-button"]').trigger('click')
    await wrapper.get('[data-testid="edit-featured"]').setValue(true)
    await wrapper.get('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(mockUpdateFace).toHaveBeenCalledWith('1', { is_featured: true })
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
        is_featured: false,
      }),
    )

    await wrapper.get('[data-testid="edit-button"]').trigger('click')
    await wrapper.get('[data-testid="edit-featured"]').setValue(true)
    await wrapper.get('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    expect(mockUpdateFace).toHaveBeenCalledWith('1', { is_featured: true })
  })
})
