import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createMemoryHistory, createRouter } from 'vue-router'
import RegisterFacePage from '../RegisterFacePage.vue'
import FaceRegistrationForm from '@/features/auth/components/FaceRegistrationForm.vue'

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
        { path: '/bienvenue', name: 'face-upsell', component: { template: '<div>Upsell</div>' } },
        { path: '/face/missions/:id', name: 'face-mission-detail', component: { template: '<div>Mission</div>' } },
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

  it('redirects to the upsell page after successful registration (no ?redirect=)', async () => {
    vi.mocked(authApi.getRegistrationStatus).mockResolvedValueOnce({ data: { enabled: true } } as never)

    const wrapper = await mountComponent()
    await flushPromises()

    // Guard against a false-green: prove we start away from the upsell route, so
    // the assertion below can only pass if handleSuccess actually navigated.
    expect(router.currentRoute.value.name).toBe('register-face')

    wrapper.findComponent(FaceRegistrationForm).vm.$emit('success')
    await flushPromises()

    expect(router.currentRoute.value.name).toBe('face-upsell')
    // AC#6: the email-verification reminder toast still fires before navigation.
    expect(mockToast.info).toHaveBeenCalled()
  })

  it('honors a valid ?redirect= bounce-back over the upsell page', async () => {
    vi.mocked(authApi.getRegistrationStatus).mockResolvedValueOnce({ data: { enabled: true } } as never)

    await router.push('/register/face?redirect=/face/missions/5')
    await router.isReady()
    const wrapper = mount(RegisterFacePage, {
      global: {
        plugins: [createTestingPinia(), router],
      },
    })
    await flushPromises()

    expect(router.currentRoute.value.name).toBe('register-face')

    wrapper.findComponent(FaceRegistrationForm).vm.$emit('success')
    await flushPromises()

    expect(router.currentRoute.value.fullPath).toBe('/face/missions/5')
    // AC#6: the reminder toast fires even on the bounce-back path.
    expect(mockToast.info).toHaveBeenCalled()
  })

  // ugc-disc-2 edge case: an already-registered Face reaching /register/face via the public
  // UGC banner must not lose the bounce-back when switching to login.
  it('preserves a ?redirect= on the "Se connecter" link', async () => {
    vi.mocked(authApi.getRegistrationStatus).mockResolvedValueOnce({ data: { enabled: true } } as never)

    await router.push('/register/face?redirect=/face/ugc-missions')
    await router.isReady()
    const wrapper = mount(RegisterFacePage, {
      global: {
        plugins: [createTestingPinia(), router],
      },
    })
    await flushPromises()

    const loginLink = wrapper.findAll('a').find((a) => a.text().includes('Se connecter'))
    expect(loginLink).toBeDefined()
    expect(decodeURIComponent(loginLink!.attributes('href') ?? '')).toBe('/login?redirect=/face/ugc-missions')
  })

  it('keeps the "Se connecter" link clean when there is no ?redirect=', async () => {
    vi.mocked(authApi.getRegistrationStatus).mockResolvedValueOnce({ data: { enabled: true } } as never)

    const wrapper = await mountComponent()
    await flushPromises()

    const loginLink = wrapper.findAll('a').find((a) => a.text().includes('Se connecter'))
    expect(loginLink).toBeDefined()
    expect(loginLink!.attributes('href')).toBe('/login')
  })
})
