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

describe('LoginPage', () => {
  let router: ReturnType<typeof createRouter>

  beforeEach(() => {
    vi.clearAllMocks()

    router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/login', name: 'login', component: LoginPage },
        { path: '/dashboard/face', name: 'face-dashboard', component: { template: '<div>Face Dashboard</div>' } },
        { path: '/dashboard/producer', name: 'producer-dashboard', component: { template: '<div>Producer Dashboard</div>' } },
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
})
