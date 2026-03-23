import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createRouter, createMemoryHistory } from 'vue-router'
import ForgotPasswordPage from '../ForgotPasswordPage.vue'

// Mock the authApi
vi.mock('@/features/auth/services/authApi', () => ({
  authApi: {
    forgotPassword: vi.fn(),
  },
  getApiErrorMessage: vi.fn(() => 'Une erreur est survenue'),
}))

import { authApi } from '@/features/auth/services/authApi'

describe('ForgotPasswordPage', () => {
  let router: ReturnType<typeof createRouter>

  beforeEach(() => {
    vi.clearAllMocks()

    router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/forgot-password', name: 'forgot-password', component: ForgotPasswordPage },
        { path: '/login', name: 'login', component: { template: '<div>Login</div>' } },
      ],
    })
  })

  function mountComponent() {
    return mount(ForgotPasswordPage, {
      global: {
        plugins: [createTestingPinia(), router],
      },
    })
  }

  it('renders the forgot password form', () => {
    const wrapper = mountComponent()

    expect(wrapper.find('h2').text()).toBe('Mot de passe oublié')
    expect(wrapper.find('[data-testid="email-input"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="submit-button"]').exists()).toBe(true)
  })

  it('calls API when form is submitted', async () => {
    vi.mocked(authApi.forgotPassword).mockResolvedValueOnce(undefined)

    const wrapper = mountComponent()

    await wrapper.find('[data-testid="email-input"]').setValue('test@example.com')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(authApi.forgotPassword).toHaveBeenCalledWith('test@example.com')
  })

  it('displays success message after successful submission', async () => {
    vi.mocked(authApi.forgotPassword).mockResolvedValueOnce(undefined)

    const wrapper = mountComponent()

    await wrapper.find('[data-testid="email-input"]').setValue('test@example.com')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.find('[data-testid="success-message"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="success-message"]').text()).toContain('Email envoyé')
  })

  it('displays error message on API failure', async () => {
    vi.mocked(authApi.forgotPassword).mockRejectedValueOnce(new Error('API Error'))

    const wrapper = mountComponent()

    await wrapper.find('[data-testid="email-input"]').setValue('test@example.com')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.find('[data-testid="error-message"]').exists()).toBe(true)
  })

  it('shows loading state during submission', async () => {
    // Create a promise that we can control
    let resolvePromise: () => void
    const promise = new Promise<void>((resolve) => {
      resolvePromise = resolve
    })
    vi.mocked(authApi.forgotPassword).mockReturnValueOnce(promise)

    const wrapper = mountComponent()

    await wrapper.find('[data-testid="email-input"]').setValue('test@example.com')
    await wrapper.find('form').trigger('submit')

    // Check loading state
    expect(wrapper.find('[data-testid="submit-button"]').attributes('disabled')).toBeDefined()
    expect(wrapper.find('[data-testid="submit-button"]').text()).toContain('Envoi en cours')

    // Resolve the promise
    resolvePromise!()
    await flushPromises()
  })

  it('has a link back to login page', () => {
    const wrapper = mountComponent()

    const loginLink = wrapper.find('a[href="/login"]')
    expect(loginLink.exists()).toBe(true)
    expect(loginLink.text()).toContain('Retour à la connexion')
  })
})
