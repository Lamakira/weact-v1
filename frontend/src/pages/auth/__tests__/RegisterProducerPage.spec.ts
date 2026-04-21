import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createMemoryHistory, createRouter } from 'vue-router'
import RegisterProducerPage from '../RegisterProducerPage.vue'

vi.mock('@/features/auth/services/authApi', () => ({
  authApi: {
    getRegistrationStatus: vi.fn(),
  },
  getApiErrorMessage: vi.fn(),
}))

vi.mock('@/features/auth/components/ProducerRegistrationForm.vue', () => ({
  default: {
    template: '<form data-testid="producer-registration-form"><slot /></form>',
    emits: ['success'],
  },
}))

const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  warning: vi.fn(),
  info: vi.fn(),
}

vi.mock('@/composables/useToast', () => ({
  useToast: () => mockToast,
}))

import { authApi } from '@/features/auth/services/authApi'

describe('RegisterProducerPage', () => {
  let router: ReturnType<typeof createRouter>

  beforeEach(() => {
    vi.clearAllMocks()

    router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/', name: 'home', component: { template: '<div>Home</div>' } },
        { path: '/register/producer', name: 'register-producer', component: RegisterProducerPage },
        { path: '/register/face', name: 'register-face', component: { template: '<div>Register Face</div>' } },
        { path: '/cgu', name: 'cgu', component: { template: '<div>CGU</div>' } },
        { path: '/politique-confidentialite', name: 'privacy-policy', component: { template: '<div>Privacy</div>' } },
        { path: '/login', name: 'login', component: { template: '<div>Login</div>' } },
        { path: '/face/dashboard', name: 'face-dashboard', component: { template: '<div>Face Dashboard</div>' } },
        { path: '/producer/dashboard', name: 'producer-dashboard', component: { template: '<div>Producer Dashboard</div>' } },
      ],
    })
  })

  afterEach(() => {
    vi.unstubAllEnvs()
  })

  async function mountComponent() {
    await router.push('/register/producer')
    await router.isReady()

    return mount(RegisterProducerPage, {
      global: {
        plugins: [createTestingPinia(), router],
      },
    })
  }

  it('red-phase: test_registration_form_visible_when_api_fails_and_vite_flag_true', async () => {
    vi.stubEnv('VITE_REGISTRATION_ENABLED', 'true')
    vi.mocked(authApi.getRegistrationStatus).mockRejectedValueOnce(new Error('Network error'))

    const wrapper = await mountComponent()
    await flushPromises()

    expect(wrapper.find('[data-testid="producer-registration-form"]').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('Les inscriptions sont temporairement suspendues.')
  })

  it('regression: test_registration_form_hidden_when_api_confirms_disabled', async () => {
    vi.mocked(authApi.getRegistrationStatus).mockResolvedValueOnce({
      data: { enabled: false },
    })

    const wrapper = await mountComponent()
    await flushPromises()

    expect(wrapper.find('[data-testid="producer-registration-form"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Les inscriptions sont temporairement suspendues.')
  })

  it('regression (vert avant ET après, verrouille le cas opt-out explicite côté build): test_registration_form_hidden_when_api_fails_and_vite_flag_false', async () => {
    vi.stubEnv('VITE_REGISTRATION_ENABLED', 'false')
    vi.mocked(authApi.getRegistrationStatus).mockRejectedValueOnce(new Error('Network error'))

    const wrapper = await mountComponent()
    await flushPromises()

    expect(wrapper.find('[data-testid="producer-registration-form"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Les inscriptions sont temporairement suspendues.')
  })
})
