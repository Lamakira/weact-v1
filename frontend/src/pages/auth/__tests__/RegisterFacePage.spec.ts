import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createMemoryHistory, createRouter } from 'vue-router'
import RegisterFacePage from '../RegisterFacePage.vue'

vi.mock('@/features/auth/services/authApi', () => ({
  authApi: {
    getRegistrationStatus: vi.fn(),
  },
  getApiErrorMessage: vi.fn(),
}))

vi.mock('@/features/auth/components/FaceRegistrationForm.vue', () => ({
  default: {
    template: '<form data-testid="face-registration-form"><slot /></form>',
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

describe('RegisterFacePage', () => {
  let router: ReturnType<typeof createRouter>

  beforeEach(() => {
    vi.clearAllMocks()

    router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/', name: 'home', component: { template: '<div>Home</div>' } },
        { path: '/register/face', name: 'register-face', component: RegisterFacePage },
        { path: '/register/producer', name: 'register-producer', component: { template: '<div>Register Producer</div>' } },
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
    await router.push('/register/face')
    await router.isReady()

    return mount(RegisterFacePage, {
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

    expect(wrapper.find('[data-testid="face-registration-form"]').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('Les inscriptions sont temporairement suspendues.')
  })
})
