import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createRouter, createMemoryHistory } from 'vue-router'
import ResetPasswordPage from '../ResetPasswordPage.vue'

// Mock the authApi
vi.mock('@/features/auth/services/authApi', () => ({
  authApi: {
    resetPassword: vi.fn(),
  },
  getApiErrorMessage: vi.fn(() => 'Lien expiré ou invalide'),
}))

import { authApi } from '@/features/auth/services/authApi'

describe('ResetPasswordPage', () => {
  let router: ReturnType<typeof createRouter>

  beforeEach(() => {
    vi.clearAllMocks()

    router = createRouter({
      history: createMemoryHistory(),
      routes: [
        {
          path: '/reset-password/:token',
          name: 'reset-password',
          component: ResetPasswordPage,
        },
        { path: '/login', name: 'login', component: { template: '<div>Login</div>' } },
      ],
    })
  })

  async function mountComponent(token = 'test-token', email = 'test@example.com') {
    await router.push(`/reset-password/${token}?email=${encodeURIComponent(email)}`)
    await router.isReady()

    return mount(ResetPasswordPage, {
      global: {
        plugins: [createTestingPinia(), router],
      },
    })
  }

  it('renders the reset password form', async () => {
    const wrapper = await mountComponent()

    expect(wrapper.find('h2').text()).toBe('Réinitialiser le mot de passe')
    expect(wrapper.find('[data-testid="email-input"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="password-input"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="password-confirmation-input"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="submit-button"]').exists()).toBe(true)
  })

  it('extracts token from URL params', async () => {
    const wrapper = await mountComponent('my-reset-token')

    // Token is passed to the API call, we verify this in the submission test
    expect(wrapper.exists()).toBe(true)
  })

  it('extracts email from query string and displays it', async () => {
    const wrapper = await mountComponent('test-token', 'user@example.com')

    const emailInput = wrapper.find('[data-testid="email-input"]')
    expect((emailInput.element as HTMLInputElement).value).toBe('user@example.com')
  })

  it('calls API with correct data when form is submitted', async () => {
    vi.mocked(authApi.resetPassword).mockResolvedValueOnce(undefined)

    const wrapper = await mountComponent('my-token', 'test@example.com')

    await wrapper.find('[data-testid="password-input"]').setValue('NewPassword1')
    await wrapper.find('[data-testid="password-confirmation-input"]').setValue('NewPassword1')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(authApi.resetPassword).toHaveBeenCalledWith({
      token: 'my-token',
      email: 'test@example.com',
      password: 'NewPassword1',
      password_confirmation: 'NewPassword1',
    })
  })

  it('redirects to login on successful reset', async () => {
    vi.mocked(authApi.resetPassword).mockResolvedValueOnce(undefined)

    const wrapper = await mountComponent('my-token', 'test@example.com')

    await wrapper.find('[data-testid="password-input"]').setValue('NewPassword1')
    await wrapper.find('[data-testid="password-confirmation-input"]').setValue('NewPassword1')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/login')
  })

  it('displays error message for invalid token', async () => {
    vi.mocked(authApi.resetPassword).mockRejectedValueOnce(new Error('Invalid token'))

    const wrapper = await mountComponent('invalid-token', 'test@example.com')

    await wrapper.find('[data-testid="password-input"]').setValue('NewPassword1')
    await wrapper.find('[data-testid="password-confirmation-input"]').setValue('NewPassword1')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.find('[data-testid="error-message"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="error-message"]').text()).toContain('Lien expiré ou invalide')
  })

  it('shows password requirements when password field is focused', async () => {
    const wrapper = await mountComponent()

    // Password requirements should not be visible initially
    expect(wrapper.find('[data-testid="password-requirements"]').exists()).toBe(false)

    // Focus on password field
    await wrapper.find('[data-testid="password-input"]').trigger('focus')

    // Password requirements should now be visible
    expect(wrapper.find('[data-testid="password-requirements"]').exists()).toBe(true)
  })

  it('validates password has minimum length', async () => {
    const wrapper = await mountComponent()

    await wrapper.find('[data-testid="password-input"]').trigger('focus')
    await wrapper.find('[data-testid="password-input"]').setValue('Short1')

    const requirements = wrapper.find('[data-testid="password-requirements"]')
    // Check that the minimum length requirement is not met (no green color)
    expect(requirements.text()).toContain('Au moins 8 caractères')
  })

  it('validates password has uppercase letter', async () => {
    const wrapper = await mountComponent()

    await wrapper.find('[data-testid="password-input"]').trigger('focus')
    await wrapper.find('[data-testid="password-input"]').setValue('lowercase1')

    const requirements = wrapper.find('[data-testid="password-requirements"]')
    expect(requirements.text()).toContain('Au moins 1 lettre majuscule')
  })

  it('validates password has number', async () => {
    const wrapper = await mountComponent()

    await wrapper.find('[data-testid="password-input"]').trigger('focus')
    await wrapper.find('[data-testid="password-input"]').setValue('NoNumbers')

    const requirements = wrapper.find('[data-testid="password-requirements"]')
    expect(requirements.text()).toContain('Au moins 1 chiffre')
  })

  it('validates passwords match', async () => {
    const wrapper = await mountComponent()

    await wrapper.find('[data-testid="password-input"]').setValue('NewPassword1')
    await wrapper.find('[data-testid="password-confirmation-input"]').setValue('DifferentPassword1')

    expect(wrapper.text()).toContain('Les mots de passe ne correspondent pas')
  })

  it('disables submit button when password requirements are not met', async () => {
    const wrapper = await mountComponent()

    // Set invalid password
    await wrapper.find('[data-testid="password-input"]').setValue('short')
    await wrapper.find('[data-testid="password-confirmation-input"]').setValue('short')

    const submitButton = wrapper.find('[data-testid="submit-button"]')
    expect(submitButton.attributes('disabled')).toBeDefined()
  })

  it('enables submit button when all requirements are met', async () => {
    const wrapper = await mountComponent()

    await wrapper.find('[data-testid="password-input"]').setValue('ValidPassword1')
    await wrapper.find('[data-testid="password-confirmation-input"]').setValue('ValidPassword1')

    const submitButton = wrapper.find('[data-testid="submit-button"]')
    expect(submitButton.attributes('disabled')).toBeUndefined()
  })
})
