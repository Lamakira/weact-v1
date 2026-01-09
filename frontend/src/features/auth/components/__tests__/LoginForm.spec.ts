import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { ref, nextTick } from 'vue'
import LoginForm from '../LoginForm.vue'

// Mock vue-router
vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: vi.fn(),
  }),
}))

// Mock the auth composable
const mockLogin = vi.fn()
const mockIsLoading = ref(false)

vi.mock('../../composables/useAuth', () => ({
  useAuth: () => ({
    login: mockLogin,
    isLoading: mockIsLoading,
  }),
}))

// Helper to wait for VeeValidate async validation
const waitForValidation = async () => {
  await flushPromises()
  await nextTick()
  await new Promise((resolve) => setTimeout(resolve, 10))
  await flushPromises()
}

describe('LoginForm', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    mockLogin.mockReset()
    mockIsLoading.value = false
  })

  it('renders email and password fields', () => {
    const wrapper = mount(LoginForm)

    expect(wrapper.find('[data-testid="email-input"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="password-input"]').exists()).toBe(true)
  })

  it('displays validation errors for empty fields on submit', async () => {
    const wrapper = mount(LoginForm)

    // Submit form with empty fields
    await wrapper.find('form').trigger('submit')
    await waitForValidation()

    // Check for validation error messages
    expect(wrapper.find('[data-testid="email-error"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="password-error"]').exists()).toBe(true)
  })

  it('password visibility toggle works', async () => {
    const wrapper = mount(LoginForm)

    const passwordInput = wrapper.find('[data-testid="password-input"]')
    const toggleButton = wrapper.find('[data-testid="toggle-password-visibility"]')

    // Initially password should be hidden
    expect(passwordInput.attributes('type')).toBe('password')

    // Click toggle button
    await toggleButton.trigger('click')

    // Password should now be visible
    expect(passwordInput.attributes('type')).toBe('text')

    // Click again
    await toggleButton.trigger('click')

    // Password should be hidden again
    expect(passwordInput.attributes('type')).toBe('password')
  })

  it('displays submit button with correct text', () => {
    const wrapper = mount(LoginForm)

    const submitButton = wrapper.find('[data-testid="submit-button"]')
    expect(submitButton.exists()).toBe(true)
    expect(submitButton.text()).toContain('Se connecter')
  })

  it('validates email format', async () => {
    const wrapper = mount(LoginForm)

    // Enter invalid email
    await wrapper.find('[data-testid="email-input"]').setValue('invalid-email')
    await wrapper.find('[data-testid="password-input"]').setValue('password123')

    // Submit form
    await wrapper.find('form').trigger('submit')
    await waitForValidation()

    // Should show email format error
    const emailError = wrapper.find('[data-testid="email-error"]')
    expect(emailError.exists()).toBe(true)
    expect(emailError.text()).toContain('email valide')
  })

  it('calls login on valid form submission', async () => {
    mockLogin.mockResolvedValue({ success: true })

    const wrapper = mount(LoginForm)

    // Fill valid form
    await wrapper.find('[data-testid="email-input"]').setValue('test@example.com')
    await wrapper.find('[data-testid="password-input"]').setValue('password123')

    // Submit form
    await wrapper.find('form').trigger('submit')
    await waitForValidation()

    // Should call login with correct data
    expect(mockLogin).toHaveBeenCalledWith({
      email: 'test@example.com',
      password: 'password123',
    })
  })

  it('emits success event on successful login', async () => {
    mockLogin.mockResolvedValue({ success: true })

    const wrapper = mount(LoginForm)

    // Fill valid form
    await wrapper.find('[data-testid="email-input"]').setValue('test@example.com')
    await wrapper.find('[data-testid="password-input"]').setValue('password123')

    // Submit form
    await wrapper.find('form').trigger('submit')
    await waitForValidation()

    // Should emit success event
    expect(wrapper.emitted('success')).toBeTruthy()
  })

  it('displays API error message on failed login', async () => {
    mockLogin.mockResolvedValue({
      success: false,
      message: 'Email ou mot de passe incorrect',
    })

    const wrapper = mount(LoginForm)

    // Fill valid form
    await wrapper.find('[data-testid="email-input"]').setValue('test@example.com')
    await wrapper.find('[data-testid="password-input"]').setValue('wrongpassword')

    // Submit form
    await wrapper.find('form').trigger('submit')
    await waitForValidation()

    // Should display API error
    const apiError = wrapper.find('[data-testid="api-error"]')
    expect(apiError.exists()).toBe(true)
    expect(apiError.text()).toContain('Email ou mot de passe incorrect')
  })

  it('has correct autocomplete attributes', () => {
    const wrapper = mount(LoginForm)

    expect(wrapper.find('[data-testid="email-input"]').attributes('autocomplete')).toBe('email')
    expect(wrapper.find('[data-testid="password-input"]').attributes('autocomplete')).toBe(
      'current-password',
    )
  })
})
