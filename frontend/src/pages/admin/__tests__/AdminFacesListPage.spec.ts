import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import type { AdminFaceData } from '@/features/admin/services/adminFacesApi'
import AdminFacesListPage from '../AdminFacesListPage.vue'

const mockFetchFaces = vi.fn()

let facesRef = ref<AdminFaceData[]>([])
let paginationRef = ref<{
  current_page: number
  last_page: number
  per_page: number
  total: number
} | null>(null)
let isLoadingRef = ref(false)
let errorRef = ref<string | null>(null)

vi.mock('@/features/admin/composables/useAdminFaces', () => ({
  useAdminFaces: () => ({
    faces: facesRef,
    pagination: paginationRef,
    isLoading: isLoadingRef,
    error: errorRef,
    fetchFaces: mockFetchFaces,
  }),
}))

function makeFace(overrides: Partial<AdminFaceData> = {}): AdminFaceData {
  return {
    id: 'face-uuid-base',
    nom: 'Doe',
    prenom: 'Jane',
    username: 'jane-doe',
    bio: null,
    ville: null,
    pays: null,
    whatsapp_number: null,
    categories: [],
    niches: [],
    is_available: true,
    is_featured: false,
    profile_completion_percentage: 80,
    profile_completion_missing: [],
    profile_completion_is_complete: false,
    average_rating: null,
    ratings_count: null,
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
    formatted_location: null,
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

async function mountPage() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/admin/faces', name: 'admin-faces-list', component: AdminFacesListPage },
      {
        path: '/admin/faces/:id',
        name: 'admin-face-detail',
        component: { template: '<div>Detail</div>' },
      },
    ],
  })

  await router.push('/admin/faces')
  await router.isReady()

  const wrapper = mount(AdminFacesListPage, {
    global: {
      plugins: [router],
    },
  })

  await flushPromises()
  return wrapper
}

describe('AdminFacesListPage - Premium badge (FEATURE-FP-1.11)', () => {
  beforeEach(() => {
    facesRef = ref<AdminFaceData[]>([])
    paginationRef = ref(null)
    isLoadingRef = ref(false)
    errorRef = ref<string | null>(null)
    vi.clearAllMocks()
  })

  it('renders a Premium badge for faces with is_featured_by_subscription: true', async () => {
    facesRef.value = [
      makeFace({ id: 'face-1', username: 'premium-user', is_featured_by_subscription: true }),
      makeFace({ id: 'face-2', username: 'free-user', is_featured_by_subscription: false }),
    ]
    paginationRef.value = { current_page: 1, last_page: 1, per_page: 15, total: 2 }

    const wrapper = await mountPage()

    expect(wrapper.findAll('[data-testid="face-row-premium-badge"]')).toHaveLength(1)
    const badge = wrapper.find('[data-testid="face-row-premium-badge"]')
    expect(badge.text()).toContain('Premium')
  })

  it('does not render a Premium badge when is_featured_by_subscription is undefined', async () => {
    const faceWithoutField = makeFace({ id: 'face-3', username: 'legacy-user' })
    delete faceWithoutField.is_featured_by_subscription
    facesRef.value = [faceWithoutField]
    paginationRef.value = { current_page: 1, last_page: 1, per_page: 15, total: 1 }

    const wrapper = await mountPage()

    expect(wrapper.findAll('[data-testid="face-row-premium-badge"]')).toHaveLength(0)
    expect(wrapper.find('[data-testid="status-badge"]').exists()).toBe(true)
  })
})
