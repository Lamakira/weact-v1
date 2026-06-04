import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import type { AdminEngagementData } from '@/features/admin/services/adminEngagementsApi'
import AdminFaceContactsPage from '../AdminFaceContactsPage.vue'

const mockFetchEngagements = vi.fn()

let engagementsRef = ref<AdminEngagementData[]>([])
let paginationRef = ref<{
  current_page: number
  last_page: number
  per_page: number
  total: number
} | null>(null)
let isLoadingRef = ref(false)
let errorRef = ref<string | null>(null)

vi.mock('@/features/admin/composables/useAdminEngagements', () => ({
  useAdminEngagements: () => ({
    engagements: engagementsRef,
    pagination: paginationRef,
    isLoading: isLoadingRef,
    error: errorRef,
    fetchEngagements: mockFetchEngagements,
  }),
}))

function makeEngagement(overrides: Partial<AdminEngagementData> = {}): AdminEngagementData {
  return {
    id: 'booking:uuid-base',
    type: 'booking',
    status: 'paid',
    status_label: 'Payée',
    engaged_since: '2026-06-01T12:00:00Z',
    montant_face_recoit: 90000,
    face: {
      id: 'face-base',
      display_name: 'Awa Traore',
      whatsapp_number: '+229 97 12 34 56',
      has_whatsapp: true,
    },
    producer: { display_name: 'Studio Lumiere' },
    objet: { label: 'Shooting', date: '2026-06-10T08:00:00Z', detail_id: null },
    ...overrides,
  }
}

async function mountPage() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/admin/engagements', name: 'admin-engagements', component: AdminFaceContactsPage },
      {
        path: '/admin/faces/:id',
        name: 'admin-face-detail',
        component: { template: '<div>Face</div>' },
      },
      {
        path: '/admin/missions/:id',
        name: 'admin-mission-detail',
        component: { template: '<div>Mission</div>' },
      },
    ],
  })

  await router.push('/admin/engagements')
  await router.isReady()

  const wrapper = mount(AdminFaceContactsPage, {
    global: { plugins: [router] },
  })

  await flushPromises()
  return wrapper
}

describe('AdminFaceContactsPage (ADMIN-ENGAGEMENTS.1)', () => {
  beforeEach(() => {
    engagementsRef = ref<AdminEngagementData[]>([])
    paginationRef = ref(null)
    isLoadingRef = ref(false)
    errorRef = ref<string | null>(null)
    vi.clearAllMocks()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('renders mixed booking and mission engagement rows', async () => {
    engagementsRef.value = [
      makeEngagement({ id: 'booking:1', type: 'booking' }),
      makeEngagement({
        id: 'mission:1',
        type: 'mission',
        face: { id: 'face-2', display_name: 'Koffi Mensah', whatsapp_number: null, has_whatsapp: false },
        objet: { label: 'Pub TV', date: null, detail_id: 'mission-uuid-1' },
      }),
    ]
    paginationRef.value = { current_page: 1, last_page: 1, per_page: 20, total: 2 }

    const wrapper = await mountPage()

    expect(wrapper.findAll('[data-testid="engagement-row"]')).toHaveLength(2)
  })

  it('builds a wa.me link with digits-only number when WhatsApp is present', async () => {
    engagementsRef.value = [makeEngagement({ id: 'booking:1' })]
    paginationRef.value = { current_page: 1, last_page: 1, per_page: 20, total: 1 }

    const wrapper = await mountPage()

    const link = wrapper.find('[data-testid="whatsapp-link"]')
    expect(link.exists()).toBe(true)
    expect(link.attributes('href')).toMatch(/^https:\/\/wa\.me\/22997123456\?text=/)
    expect(link.attributes('target')).toBe('_blank')
    expect(link.attributes('rel')).toBe('noopener noreferrer')
  })

  it('shows the "Numéro manquant" badge when WhatsApp is absent', async () => {
    engagementsRef.value = [
      makeEngagement({
        id: 'mission:1',
        type: 'mission',
        face: { id: 'face-2', display_name: 'Koffi Mensah', whatsapp_number: null, has_whatsapp: false },
      }),
    ]
    paginationRef.value = { current_page: 1, last_page: 1, per_page: 20, total: 1 }

    const wrapper = await mountPage()

    expect(wrapper.find('[data-testid="whatsapp-missing"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="whatsapp-link"]').exists()).toBe(false)
  })

  it('does not render a WhatsApp link when the stored value has no digits', async () => {
    engagementsRef.value = [
      makeEngagement({
        id: 'booking:invalid-whatsapp',
        face: { id: 'face-1', display_name: 'Awa Traore', whatsapp_number: 'N/A', has_whatsapp: true },
      }),
    ]
    paginationRef.value = { current_page: 1, last_page: 1, per_page: 20, total: 1 }

    const wrapper = await mountPage()

    expect(wrapper.find('[data-testid="whatsapp-missing"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="whatsapp-link"]').exists()).toBe(false)
  })

  it('refetches when the type filter changes', async () => {
    engagementsRef.value = [makeEngagement({ id: 'booking:1' })]
    paginationRef.value = { current_page: 1, last_page: 1, per_page: 20, total: 1 }

    const wrapper = await mountPage()
    const initialCalls = mockFetchEngagements.mock.calls.length

    await wrapper.find('[data-testid="engagement-type-filter"]').setValue('mission')
    await flushPromises()

    expect(mockFetchEngagements.mock.calls.length).toBeGreaterThan(initialCalls)
    expect(mockFetchEngagements).toHaveBeenLastCalledWith({ page: 1, type: 'mission' })
  })

  it('refetches when the status filter changes', async () => {
    engagementsRef.value = [makeEngagement({ id: 'booking:1' })]
    paginationRef.value = { current_page: 1, last_page: 1, per_page: 20, total: 1 }

    const wrapper = await mountPage()
    const initialCalls = mockFetchEngagements.mock.calls.length

    await wrapper.find('[data-testid="engagement-status-filter"]').setValue('paid')
    await flushPromises()

    expect(mockFetchEngagements.mock.calls.length).toBeGreaterThan(initialCalls)
    expect(mockFetchEngagements).toHaveBeenLastCalledWith({ page: 1, status: 'paid' })
  })

  it('refetches with a debounced search query', async () => {
    engagementsRef.value = [makeEngagement({ id: 'booking:1' })]
    paginationRef.value = { current_page: 1, last_page: 1, per_page: 20, total: 1 }

    const wrapper = await mountPage()
    vi.useFakeTimers()

    const initialCalls = mockFetchEngagements.mock.calls.length
    await wrapper.find('[data-testid="engagement-search"]').setValue('Awa')
    await vi.advanceTimersByTimeAsync(300)
    await flushPromises()

    expect(mockFetchEngagements.mock.calls.length).toBeGreaterThan(initialCalls)
    expect(mockFetchEngagements).toHaveBeenLastCalledWith({ page: 1, search: 'Awa' })
  })
})
