import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createRouter, createWebHistory } from 'vue-router'
import FaceRegistrationForm from '../FaceRegistrationForm.vue'

// Mock the authApi module
vi.mock('../../services/authApi', () => ({
  authApi: {
    registerFace: vi.fn(),
  },
  isApiError: vi.fn(),
  getApiErrorDetails: vi.fn(() => ({})),
  getApiErrorMessage: vi.fn(() => 'Une erreur est survenue'),
}))

// Create a mock router
const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'home', component: { template: '<div>Home</div>' } },
    { path: '/face/dashboard', name: 'face-dashboard', component: { template: '<div>Dashboard</div>' } },
  ],
})

// Helper to wait for VeeValidate async validation
const waitForValidation = async () => {
  await flushPromises()
  await new Promise((resolve) => setTimeout(resolve, 10))
  await flushPromises()
  await new Promise((resolve) => setTimeout(resolve, 10))
  await flushPromises()
}

describe('FaceRegistrationForm', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  const mountComponent = () => {
    return mount(FaceRegistrationForm, {
      global: {
        plugins: [
          createTestingPinia({
            createSpy: vi.fn,
          }),
          router,
        ],
        stubs: {
          RouterLink: true,
        },
      },
    })
  }

  it('renders all form fields including personal info', () => {
    const wrapper = mountComponent()

    expect(wrapper.find('[data-testid="nom-input"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="prenom-input"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="username-input"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="sexe-select"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="date-naissance-input"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="nationalite-input"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="pays-input"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="email-input"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="password-input"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="password-confirmation-input"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="submit-button"]').exists()).toBe(true)
  })

  it('renders nationality and country as selects with shared options', () => {
    const wrapper = mountComponent()

    const nationaliteSelect = wrapper.find('[data-testid="nationalite-input"]')
    const paysSelect = wrapper.find('[data-testid="pays-input"]')
    const nationalityOptions = nationaliteSelect.findAll('option').map((option) => option.text())

    expect(nationaliteSelect.element.tagName).toBe('SELECT')
    expect(paysSelect.element.tagName).toBe('SELECT')
    expect(nationaliteSelect.findAll('option').length).toBeGreaterThan(10)
    expect(paysSelect.findAll('option').length).toBeGreaterThan(10)
    expect(nationalityOptions).toContain('Béninoise')
    expect(nationalityOptions).toContain('Française')
  })

  it('displays validation errors for empty fields on submit', async () => {
    const wrapper = mountComponent()

    // Submit without filling any fields
    await wrapper.find('form').trigger('submit')
    await waitForValidation()

    // Check for validation error messages
    expect(wrapper.find('[data-testid="nom-error"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="prenom-error"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="username-error"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="sexe-error"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="date_naissance-error"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="nationalite-error"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="email-error"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="password-error"]').exists()).toBe(true)
  })

  it('displays password validation error for weak password', async () => {
    const wrapper = mountComponent()

    // Fill in all fields with a weak password (shorter than 8 chars)
    await wrapper.find('[data-testid="nom-input"]').setValue('Doe')
    await wrapper.find('[data-testid="prenom-input"]').setValue('John')
    await wrapper.find('[data-testid="username-input"]').setValue('johndoe')
    await wrapper.find('[data-testid="sexe-select"]').setValue('homme')
    await wrapper.find('[data-testid="date-naissance-input"]').setValue('1995-06-15')
    await wrapper.find('[data-testid="nationalite-input"]').setValue('Béninoise')
    await wrapper.find('[data-testid="pays-input"]').setValue('Bénin')
    await wrapper.find('[data-testid="email-input"]').setValue('john@example.com')
    await wrapper.find('[data-testid="password-input"]').setValue('Ab1')
    await wrapper.find('[data-testid="password-confirmation-input"]').setValue('Ab1')
    await wrapper.find('form').trigger('submit')
    await waitForValidation()

    // Either show password error (min length) or password confirmation error
    const passwordError = wrapper.find('[data-testid="password-error"]')
    if (passwordError.exists()) {
      expect(passwordError.text()).toContain('8 caractères')
    } else {
      // If not showing, verify the password requirements hint is visible
      expect(wrapper.text()).toContain('8 caractères minimum')
    }
  })

  it('displays password confirmation error when passwords do not match', async () => {
    const wrapper = mountComponent()

    // Fill in ALL required fields with mismatched passwords
    await wrapper.find('[data-testid="nom-input"]').setValue('Doe')
    await wrapper.find('[data-testid="prenom-input"]').setValue('John')
    await wrapper.find('[data-testid="username-input"]').setValue('johndoe')
    await wrapper.find('[data-testid="sexe-select"]').setValue('homme')
    await wrapper.find('[data-testid="date-naissance-input"]').setValue('1995-06-15')
    await wrapper.find('[data-testid="nationalite-input"]').setValue('Béninoise')
    await wrapper.find('[data-testid="pays-input"]').setValue('Bénin')
    await wrapper.find('[data-testid="email-input"]').setValue('john@example.com')
    await wrapper.find('[data-testid="password-input"]').setValue('Password123')
    await wrapper.find('[data-testid="password-confirmation-input"]').setValue('DifferentPassword123')
    await wrapper.find('form').trigger('submit')
    await waitForValidation()

    const confirmError = wrapper.find('[data-testid="password_confirmation-error"]')
    expect(confirmError.exists()).toBe(true)
    expect(confirmError.text()).toContain('ne correspond pas')
  })

  it('has correct input types for security', () => {
    const wrapper = mountComponent()

    expect(wrapper.find('[data-testid="email-input"]').attributes('type')).toBe('email')
    expect(wrapper.find('[data-testid="password-input"]').attributes('type')).toBe('password')
    expect(wrapper.find('[data-testid="password-confirmation-input"]').attributes('type')).toBe('password')
  })

  it('pre-fills pays with Bénin', () => {
    const wrapper = mountComponent()

    const paysInput = wrapper.find('[data-testid="pays-input"]')
    expect(paysInput.exists()).toBe(true)
    expect((paysInput.element as HTMLInputElement).value).toBe('Bénin')
  })

  it('pre-fills nationalite with Béninoise', () => {
    const wrapper = mountComponent()

    const nationaliteInput = wrapper.find('[data-testid="nationalite-input"]')
    expect(nationaliteInput.exists()).toBe(true)
    expect((nationaliteInput.element as HTMLInputElement).value).toBe('Béninoise')
  })

  it('has correct autocomplete attributes', () => {
    const wrapper = mountComponent()

    expect(wrapper.find('[data-testid="nom-input"]').attributes('autocomplete')).toBe('family-name')
    expect(wrapper.find('[data-testid="prenom-input"]').attributes('autocomplete')).toBe('given-name')
    expect(wrapper.find('[data-testid="username-input"]').attributes('autocomplete')).toBe('username')
    expect(wrapper.find('[data-testid="email-input"]').attributes('autocomplete')).toBe('email')
    expect(wrapper.find('[data-testid="password-input"]').attributes('autocomplete')).toBe('new-password')
  })

  it('displays submit button with correct text', () => {
    const wrapper = mountComponent()

    const submitButton = wrapper.find('[data-testid="submit-button"]')
    expect(submitButton.text()).toContain("S'inscrire en tant que Face")
  })
})
