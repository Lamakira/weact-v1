import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createMemoryHistory, createRouter } from 'vue-router'
import RegisterProducerPage from '../RegisterProducerPage.vue'

// Real registration form: the point is whether the Google button ends up in the DOM.
vi.mock('@/features/auth/services/authApi', () => ({
  authApi: {
    getRegistrationStatus: vi.fn(),
  },
  getApiErrorMessage: vi.fn(),
  getApiErrorDetails: vi.fn(() => ({})),
  getApiErrorCode: vi.fn(() => null),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() }),
}))

import { authApi } from '@/features/auth/services/authApi'

const GoogleSignInButtonStub = {
  props: ['intent', 'disabled', 'label'],
  template: '<button data-testid="google-sign-in-button" :data-intent="intent" />',
}

describe('RegisterProducerPage - Google button visibility', () => {
  let router: ReturnType<typeof createRouter>

  beforeEach(() => {
    vi.clearAllMocks()
    router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/', name: 'home', component: { template: '<div />' } },
        { path: '/register/producer', name: 'register-producer', component: RegisterProducerPage },
        { path: '/register/face', name: 'register-face', component: { template: '<div />' } },
        { path: '/register/producer', name: 'register-producer', component: { template: '<div />' } },
        { path: '/cgu', name: 'cgu', component: { template: '<div />' } },
        { path: '/politique-confidentialite', name: 'privacy-policy', component: { template: '<div />' } },
        { path: '/login', name: 'login', component: { template: '<div />' } },
      ],
    })
  })

  async function mountPage() {
    await router.push('/register/producer')
    await router.isReady()

    return mount(RegisterProducerPage, {
      global: {
        plugins: [createTestingPinia(), router],
        stubs: { GoogleSignInButton: GoogleSignInButtonStub },
      },
    })
  }

  it('hides the Google button before the status probe answers', async () => {
    vi.mocked(authApi.getRegistrationStatus).mockReturnValue(new Promise(() => {}))

    const wrapper = await mountPage()
    await flushPromises()

    expect(wrapper.find('[data-testid="google-sign-in-button"]').exists()).toBe(false)
  })

  it('shows the Google button when the probe returns google_enabled: true', async () => {
    vi.mocked(authApi.getRegistrationStatus).mockResolvedValue({
      data: { enabled: true, google_enabled: true },
    } as never)

    const wrapper = await mountPage()
    await flushPromises()

    expect(wrapper.find('[data-testid="google-sign-in-button"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="google-sign-in-button"]').attributes('data-intent')).toBe(
      'producer'
    )
  })

  it('keeps the Google button hidden when the probe returns google_enabled: false', async () => {
    vi.mocked(authApi.getRegistrationStatus).mockResolvedValue({
      data: { enabled: true, google_enabled: false },
    } as never)

    const wrapper = await mountPage()
    await flushPromises()

    expect(wrapper.find('[data-testid="google-sign-in-button"]').exists()).toBe(false)
  })

  it('keeps the Google button hidden when the probe fails', async () => {
    vi.mocked(authApi.getRegistrationStatus).mockRejectedValue(new Error('Network error'))

    const wrapper = await mountPage()
    await flushPromises()

    // The form itself stays reachable (permissive fallback), only Google stays off.
    expect(wrapper.find('form').exists()).toBe(true)
    expect(wrapper.find('[data-testid="google-sign-in-button"]').exists()).toBe(false)
  })
})
