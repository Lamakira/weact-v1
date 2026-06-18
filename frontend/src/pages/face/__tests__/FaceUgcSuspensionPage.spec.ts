import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import FaceUgcSuspensionPage from '../FaceUgcSuspensionPage.vue'
import type { UgcSuspensionStatus } from '@/components/ugc'

// Mock vue-router
const mockRouter = { push: vi.fn() }
vi.mock('vue-router', () => ({
  useRouter: () => mockRouter,
  RouterLink: { template: '<a><slot /></a>', props: ['to'] },
}))

// Mock the composable with reactive refs
const mockIsSuspended = ref(false)
const mockSuspension = ref<UgcSuspensionStatus | null>(null)
const mockIsLoading = ref(false)
const mockError = ref<string | null>(null)
const mockFetchStatus = vi.fn()

vi.mock('@/composables/useUgcSuspension', () => ({
  useUgcSuspension: () => ({
    isSuspended: mockIsSuspended,
    suspension: mockSuspension,
    isLoading: mockIsLoading,
    error: mockError,
    fetchStatus: mockFetchStatus,
  }),
}))

function bookingSuspension(overrides: Partial<UgcSuspensionStatus> = {}): UgcSuspensionStatus {
  return {
    reason: 'avis_deadline_missed',
    reason_label: 'Avis non livré dans les délais',
    suspended_at: '2026-06-18T09:00:00+00:00',
    reactivation_deadline: '2026-07-18T09:00:00+00:00',
    appeal_status: 'none',
    deal: {
      owner_kind: 'booking',
      owner_uuid: 'booking-uuid-1',
      product_name: 'Sneakers Shade Fit',
      missed_deadline_at: '2026-06-18T03:00:00+00:00',
    },
    ...overrides,
  }
}

describe('FaceUgcSuspensionPage', () => {
  beforeEach(() => {
    mockIsSuspended.value = false
    mockSuspension.value = null
    mockIsLoading.value = false
    mockError.value = null
    vi.clearAllMocks()
  })

  it('fetches the suspension status on mount', async () => {
    mount(FaceUgcSuspensionPage)
    await flushPromises()

    expect(mockFetchStatus).toHaveBeenCalledOnce()
  })

  it('renders the 10A screen when suspended: banner + why + how', async () => {
    mockIsSuspended.value = true
    mockSuspension.value = bookingSuspension()

    const wrapper = mount(FaceUgcSuspensionPage)
    await flushPromises()

    expect(wrapper.find('[data-testid="ugc-suspension-page"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Compte suspendu')

    const why = wrapper.get('[data-testid="ugc-suspension-why"]')
    expect(why.text()).toContain('Avis non livré dans les délais')
    expect(why.text()).toContain('Sneakers Shade Fit')

    const how = wrapper.get('[data-testid="ugc-suspension-how"]')
    expect(how.text()).toContain('1')
    expect(how.text()).toContain('2')
    expect(how.text()).toContain('3')
  })

  it('routes a booking deal to the booking detail page', async () => {
    mockIsSuspended.value = true
    mockSuspension.value = bookingSuspension()

    const wrapper = mount(FaceUgcSuspensionPage)
    await flushPromises()

    await wrapper.get('[data-testid="ugc-suspension-terminer"]').trigger('click')

    expect(mockRouter.push).toHaveBeenCalledWith({
      name: 'face-booking-detail',
      params: { id: 'booking-uuid-1' },
    })
  })

  it('routes a candidature deal to the mission detail page', async () => {
    mockIsSuspended.value = true
    mockSuspension.value = bookingSuspension({
      deal: {
        owner_kind: 'candidature',
        owner_uuid: 'mission-uuid-1',
        product_name: 'Sneakers Shade Fit',
        missed_deadline_at: '2026-06-18T03:00:00+00:00',
      },
    })

    const wrapper = mount(FaceUgcSuspensionPage)
    await flushPromises()

    await wrapper.get('[data-testid="ugc-suspension-terminer"]').trigger('click')

    expect(mockRouter.push).toHaveBeenCalledWith({
      name: 'face-mission-detail',
      params: { id: 'mission-uuid-1' },
    })
  })

  it('hides the "Terminer" CTA and shows generic copy when the deal is null', async () => {
    mockIsSuspended.value = true
    mockSuspension.value = bookingSuspension({ deal: null })

    const wrapper = mount(FaceUgcSuspensionPage)
    await flushPromises()

    expect(wrapper.find('[data-testid="ugc-suspension-terminer"]').exists()).toBe(false)
    expect(wrapper.get('[data-testid="ugc-suspension-why"]').text()).toContain(
      "Un livrable UGC n'a pas été livré dans les délais.",
    )
  })

  it('renders the neutral state for a non-suspended Face with a dashboard link', async () => {
    mockIsSuspended.value = false
    mockSuspension.value = null

    const wrapper = mount(FaceUgcSuspensionPage)
    await flushPromises()

    const neutral = wrapper.get('[data-testid="ugc-suspension-not-suspended"]')
    expect(neutral.text()).toContain("Ton compte n'est pas suspendu.")
    expect(wrapper.find('[data-testid="ugc-suspension-page"]').exists()).toBe(false)

    await wrapper.get('[data-testid="ugc-suspension-dashboard"]').trigger('click')
    expect(mockRouter.push).toHaveBeenCalledWith({ name: 'face-dashboard' })
  })

  it('renders the error state and refetches on retry', async () => {
    mockError.value = 'Erreur réseau'

    const wrapper = mount(FaceUgcSuspensionPage)
    await flushPromises()

    expect(wrapper.get('[data-testid="ugc-suspension-error"]').text()).toContain('Erreur réseau')

    await wrapper.get('[data-testid="ugc-suspension-retry"]').trigger('click')
    // once on mount + once on retry
    expect(mockFetchStatus).toHaveBeenCalledTimes(2)
  })
})
