import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import type { AdminProducerData } from '@/features/admin/services/adminProducersApi'
import AdminProducerDetailPage from '../AdminProducerDetailPage.vue'

const mockFetchProducer = vi.fn()
const mockUpdateProducer = vi.fn()
const mockToggleActive = vi.fn()
const mockDeleteProducer = vi.fn()
const mockToastSuccess = vi.fn()
const mockToastError = vi.fn()
const mockToastWarning = vi.fn()
const mockToastInfo = vi.fn()

let producerRef = ref<AdminProducerData | null>(null)
let isLoadingRef = ref(false)
let errorRef = ref<string | null>(null)

vi.mock('@/features/admin/composables/useAdminProducers', () => ({
  useAdminProducers: () => ({
    producer: producerRef,
    isLoading: isLoadingRef,
    error: errorRef,
    fetchProducer: mockFetchProducer,
    updateProducer: mockUpdateProducer,
    toggleActive: mockToggleActive,
    deleteProducer: mockDeleteProducer,
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

function makeProducer(overrides: Partial<AdminProducerData> = {}): AdminProducerData {
  return {
    id: 1,
    type: 'particulier',
    agency_name: null,
    first_name: 'Jean',
    last_name: 'Dupont',
    display_name: 'Jean Dupont',
    bio: 'Bio test',
    profile_photo_url: null,
    thumbnail_url: null,
    agency_logo_url: null,
    agency_logo_thumbnail_url: null,
    average_rating: 4.2,
    ratings_count: 8,
    missions_count: 3,
    missions: [],
    created_at: '2026-04-05T12:00:00Z',
    updated_at: '2026-04-05T12:00:00Z',
    email: 'jean@example.com',
    is_active: true,
    ...overrides,
  }
}

async function mountPage(producer: AdminProducerData) {
  producerRef.value = producer
  isLoadingRef.value = false
  errorRef.value = null

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/admin/producers', name: 'admin-producers-list', component: { template: '<div>List</div>' } },
      { path: '/admin/producers/:id', name: 'admin-producer-detail', component: AdminProducerDetailPage },
    ],
  })

  await router.push('/admin/producers/1')
  await router.isReady()

  const wrapper = mount(AdminProducerDetailPage, {
    global: {
      plugins: [router],
      stubs: {
        ConfirmModal: {
          template: '<div><slot /></div>',
        },
      },
    },
  })

  await flushPromises()

  return { wrapper, router }
}

describe('AdminProducerDetailPage', () => {
  beforeEach(() => {
    producerRef = ref<AdminProducerData | null>(null)
    isLoadingRef = ref(false)
    errorRef = ref<string | null>(null)
    mockFetchProducer.mockReset()
    mockUpdateProducer.mockReset()
    mockToggleActive.mockReset()
    mockDeleteProducer.mockReset()
    mockToastSuccess.mockReset()
    mockToastError.mockReset()
    mockToastWarning.mockReset()
    mockToastInfo.mockReset()
  })

  it('displays email with mailto link when producer has email', async () => {
    const { wrapper } = await mountPage(makeProducer({ email: 'jean@example.com' }))

    const section = wrapper.get('[data-testid="identity-section"]')
    const emailLink = section.find('a[href="mailto:jean@example.com"]')

    expect(emailLink.exists()).toBe(true)
    expect(emailLink.text()).toBe('jean@example.com')
  })

  it('displays dash when producer has no email', async () => {
    const { wrapper } = await mountPage(makeProducer({ email: undefined }))

    const section = wrapper.get('[data-testid="identity-section"]')
    const emailLink = section.find('a[href^="mailto:"]')

    expect(emailLink.exists()).toBe(false)

    const emailDts = section.findAll('dt').filter((dt) => dt.text().includes('Email'))
    expect(emailDts.length).toBe(1)
    const dd = emailDts[0].element.parentElement!.querySelector('dd')!
    expect(dd.textContent?.trim()).toBe('—')
  })

  it('keeps email visible after a successful save', async () => {
    mockUpdateProducer.mockImplementation(async () => {
      producerRef.value = makeProducer({
        first_name: 'Jeanne',
        display_name: 'Jeanne Dupont',
        email: 'jean@example.com',
      })

      return {
        success: true,
        message: 'Profil Producteur mis à jour avec succès',
      }
    })

    const { wrapper } = await mountPage(makeProducer({ email: 'jean@example.com' }))

    await wrapper.get('[data-testid="edit-button"]').trigger('click')
    await wrapper.get('[data-testid="edit-first-name"]').setValue('Jeanne')
    await wrapper.get('[data-testid="save-button"]').trigger('click')
    await flushPromises()

    const section = wrapper.get('[data-testid="identity-section"]')
    const emailLink = section.find('a[href="mailto:jean@example.com"]')

    expect(emailLink.exists()).toBe(true)
    expect(section.text()).toContain('Jeanne')
  })
})
