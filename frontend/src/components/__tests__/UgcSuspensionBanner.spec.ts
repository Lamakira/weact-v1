import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import UgcSuspensionBanner from '../UgcSuspensionBanner.vue'

// Mock vue-router
const mockRouter = { push: vi.fn() }
vi.mock('vue-router', () => ({
  useRouter: () => mockRouter,
}))

// Mock the composable with reactive refs
const mockIsSuspended = ref(false)
const mockFetchStatus = vi.fn()

vi.mock('@/composables/useUgcSuspension', () => ({
  useUgcSuspension: () => ({
    isSuspended: mockIsSuspended,
    suspension: ref(null),
    isLoading: ref(false),
    error: ref(null),
    fetchStatus: mockFetchStatus,
  }),
}))

describe('UgcSuspensionBanner', () => {
  beforeEach(() => {
    mockIsSuspended.value = false
    vi.clearAllMocks()
  })

  it('fetches the suspension status on mount', async () => {
    mount(UgcSuspensionBanner)
    await flushPromises()

    expect(mockFetchStatus).toHaveBeenCalledOnce()
  })

  it('renders the banner when the Face is suspended', async () => {
    mockIsSuspended.value = true

    const wrapper = mount(UgcSuspensionBanner)
    await flushPromises()

    expect(wrapper.find('[data-testid="ugc-suspension-banner"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Compte suspendu')
  })

  it('renders nothing when the Face is not suspended', async () => {
    mockIsSuspended.value = false

    const wrapper = mount(UgcSuspensionBanner)
    await flushPromises()

    expect(wrapper.find('[data-testid="ugc-suspension-banner"]').exists()).toBe(false)
  })

  it('navigates to the suspension page on CTA click', async () => {
    mockIsSuspended.value = true

    const wrapper = mount(UgcSuspensionBanner)
    await flushPromises()

    await wrapper.get('[data-testid="ugc-suspension-banner-cta"]').trigger('click')

    expect(mockRouter.push).toHaveBeenCalledWith({ name: 'face-ugc-suspension' })
  })
})
