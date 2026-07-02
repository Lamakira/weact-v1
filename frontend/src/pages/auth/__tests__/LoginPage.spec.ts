import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createRouter, createMemoryHistory } from 'vue-router'
import LoginPage from '../LoginPage.vue'

// Mock LoginForm component
vi.mock('@/features/auth/components/LoginForm.vue', () => ({
  default: {
    template: '<form data-testid="login-form"><slot /></form>',
    emits: ['success'],
  },
}))

// Mock useToast composable
const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  warning: vi.fn(),
  info: vi.fn(),
}

vi.mock('@/composables/useToast', () => ({
  useToast: () => mockToast,
}))

describe('LoginPage', () => {
  let router: ReturnType<typeof createRouter>

  beforeEach(() => {
    vi.clearAllMocks()

    router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/login', name: 'login', component: LoginPage },
        { path: '/face/dashboard', name: 'face-dashboard', component: { template: '<div>Face Dashboard</div>' } },
        { path: '/producer/dashboard', name: 'producer-dashboard', component: { template: '<div>Producer Dashboard</div>' } },
        { path: '/forgot-password', name: 'forgot-password', component: { template: '<div>Forgot Password</div>' } },
        { path: '/register/face', name: 'register-face', component: { template: '<div>Register Face</div>' } },
        { path: '/register/producer', name: 'register-producer', component: { template: '<div>Register Producer</div>' } },
        { path: '/some-protected-route', name: 'protected', component: { template: '<div>Protected</div>' } },
      ],
    })
  })

  async function mountComponent(queryParams: Record<string, string> = {}) {
    const query = new URLSearchParams(queryParams).toString()
    const path = query ? `/login?${query}` : '/login'
    await router.push(path)
    await router.isReady()

    return mount(LoginPage, {
      global: {
        plugins: [createTestingPinia(), router],
      },
    })
  }

  it('renders the login page', async () => {
    const wrapper = await mountComponent()

    expect(wrapper.find('h2').text()).toBe('Connexion à WEACT')
    expect(wrapper.find('[data-testid="login-form"]').exists()).toBe(true)
  })

  it('displays success message when redirected from password reset', async () => {
    const wrapper = await mountComponent({ message: 'password-reset-success' })
    await flushPromises()

    expect(wrapper.find('[data-testid="success-message"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="success-message"]').text()).toContain('Mot de passe réinitialisé avec succès')
  })

  it('does not display success message without query param', async () => {
    const wrapper = await mountComponent()
    await flushPromises()

    expect(wrapper.find('[data-testid="success-message"]').exists()).toBe(false)
  })

  it('has a forgot password link', async () => {
    const wrapper = await mountComponent()

    const link = wrapper.find('a[href="/forgot-password"]')
    expect(link.exists()).toBe(true)
    expect(link.text()).toContain('Mot de passe oublié ?')
  })

  describe('Session Expired Message', () => {
    it('displays warning message when redirected with session-expired param', async () => {
      const wrapper = await mountComponent({ message: 'session-expired' })
      await flushPromises()

      expect(wrapper.find('[data-testid="warning-message"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="warning-message"]').text()).toContain('Session expirée')
    })

    it('shows toast notification for session expired', async () => {
      await mountComponent({ message: 'session-expired' })
      await flushPromises()

      expect(mockToast.warning).toHaveBeenCalledWith('Session expirée, veuillez vous reconnecter')
    })

    it('does not display warning message without query param', async () => {
      const wrapper = await mountComponent()
      await flushPromises()

      expect(wrapper.find('[data-testid="warning-message"]').exists()).toBe(false)
    })

    it('preserves redirect param when clearing session-expired message', async () => {
      await mountComponent({ message: 'session-expired', redirect: '/some-protected-route' })
      await flushPromises()

      // Should have replaced URL with redirect param preserved
      expect(router.currentRoute.value.query.redirect).toBe('/some-protected-route')
      expect(router.currentRoute.value.query.message).toBeUndefined()
    })
  })

  describe('Post-Login Redirect', () => {
    it('preserves redirect query param in URL after session expired cleanup', async () => {
      await mountComponent({ message: 'session-expired', redirect: '/some-protected-route' })
      await flushPromises()

      // The redirect param should be preserved for use after login
      expect(router.currentRoute.value.query.redirect).toBe('/some-protected-route')
    })
  })

  describe('Register links preserve redirect query (FP-2.15)', () => {
    it('passes redirect query through to /register/face link when arriving from /pricing', async () => {
      const wrapper = await mountComponent({ redirect: '/pricing?plan=starter' })
      await flushPromises()

      const registerFaceLink = wrapper.find('a[href*="/register/face"]')
      expect(registerFaceLink.exists()).toBe(true)
      const href = registerFaceLink.attributes('href') ?? ''
      // URL contains the encoded redirect param
      expect(href).toMatch(/^\/register\/face\?redirect=/)
      expect(decodeURIComponent(href)).toContain('/pricing?plan=starter')
    })

    it('passes redirect query through to /register/producer link when arriving with a redirect', async () => {
      const wrapper = await mountComponent({ redirect: '/pricing?plan=pro' })
      await flushPromises()

      const registerProducerLink = wrapper.find('a[href*="/register/producer"]')
      expect(registerProducerLink.exists()).toBe(true)
      const href = registerProducerLink.attributes('href') ?? ''
      expect(href).toMatch(/^\/register\/producer\?redirect=/)
      expect(decodeURIComponent(href)).toContain('/pricing?plan=pro')
    })

    it('renders register links without redirect query when no redirect is present', async () => {
      const wrapper = await mountComponent()
      await flushPromises()

      const registerFaceLink = wrapper.find('a[href*="/register/face"]')
      expect(registerFaceLink.exists()).toBe(true)
      expect(registerFaceLink.attributes('href')).toBe('/register/face')

      const registerProducerLink = wrapper.find('a[href*="/register/producer"]')
      expect(registerProducerLink.exists()).toBe(true)
      expect(registerProducerLink.attributes('href')).toBe('/register/producer')
    })
  })
})
