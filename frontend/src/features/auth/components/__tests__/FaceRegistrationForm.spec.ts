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

  const mountComponent = (props: Record<string, unknown> = {}) => {
    return mount(FaceRegistrationForm, {
      props,
      global: {
        plugins: [
          createTestingPinia({
            createSpy: vi.fn,
          }),
          router,
        ],
        stubs: {
          RouterLink: true,
          GoogleSignInButton: {
            props: ['intent', 'disabled', 'label'],
            template:
              '<button data-testid="google-sign-in-button" :data-intent="intent" :disabled="disabled" />',
          },
        },
      },
    })
  }

  it('renders exactly the six signup fields', () => {
    const wrapper = mountComponent()

    expect(wrapper.find('[data-testid="nom-input"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="prenom-input"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="email-input"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="date-naissance-input"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="password-input"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="accept-cgu-checkbox"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="submit-button"]').exists()).toBe(true)
  })

  it('no longer renders the fields deferred to profile completion', () => {
    const wrapper = mountComponent()

    expect(wrapper.find('[data-testid="username-input"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="sexe-select"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="nationalite-input"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="pays-input"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="whatsapp-number-input"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="password-confirmation-input"]').exists()).toBe(false)
  })

  it('displays validation errors for empty fields on submit', async () => {
    const wrapper = mountComponent()

    // Submit without filling any fields
    await wrapper.find('form').trigger('submit')
    await waitForValidation()

    // Check for validation error messages
    expect(wrapper.find('[data-testid="nom-error"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="prenom-error"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="email-error"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="date_naissance-error"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="password-error"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="accept-cgu-error"]').exists()).toBe(true)
  })

  it('displays password validation error for weak password', async () => {
    const wrapper = mountComponent()

    await wrapper.find('[data-testid="nom-input"]').setValue('Doe')
    await wrapper.find('[data-testid="prenom-input"]').setValue('John')
    await wrapper.find('[data-testid="email-input"]').setValue('john@example.com')
    await wrapper.find('[data-testid="date-naissance-input"]').setValue('1995-06-15')
    await wrapper.find('[data-testid="password-input"]').setValue('Ab1')
    await wrapper.find('[data-testid="accept-cgu-checkbox"]').setValue(true)
    await wrapper.find('form').trigger('submit')
    await waitForValidation()

    const passwordError = wrapper.find('[data-testid="password-error"]')
    expect(passwordError.exists()).toBe(true)
    expect(passwordError.text()).toContain('8 caractères')
  })

  it('rejects an applicant under 16', async () => {
    const wrapper = mountComponent()

    const underage = new Date()
    underage.setFullYear(underage.getFullYear() - 15)

    await wrapper.find('[data-testid="nom-input"]').setValue('Doe')
    await wrapper.find('[data-testid="prenom-input"]').setValue('John')
    await wrapper.find('[data-testid="email-input"]').setValue('john@example.com')
    await wrapper.find('[data-testid="date-naissance-input"]').setValue(underage.toISOString().slice(0, 10))
    await wrapper.find('[data-testid="password-input"]').setValue('Password123')
    await wrapper.find('[data-testid="accept-cgu-checkbox"]').setValue(true)
    await wrapper.find('form').trigger('submit')
    await waitForValidation()

    const dateError = wrapper.find('[data-testid="date_naissance-error"]')
    expect(dateError.exists()).toBe(true)
    expect(dateError.text()).toContain('16 ans')
  })

  it('has correct input types for security', () => {
    const wrapper = mountComponent()

    expect(wrapper.find('[data-testid="email-input"]').attributes('type')).toBe('email')
    expect(wrapper.find('[data-testid="date-naissance-input"]').attributes('type')).toBe('date')
    expect(wrapper.find('[data-testid="password-input"]').attributes('type')).toBe('password')
  })

  it('has correct autocomplete attributes', () => {
    const wrapper = mountComponent()

    expect(wrapper.find('[data-testid="nom-input"]').attributes('autocomplete')).toBe('family-name')
    expect(wrapper.find('[data-testid="prenom-input"]').attributes('autocomplete')).toBe('given-name')
    expect(wrapper.find('[data-testid="email-input"]').attributes('autocomplete')).toBe('email')
    expect(wrapper.find('[data-testid="password-input"]').attributes('autocomplete')).toBe('new-password')
  })

  it('states the 16+ requirement in the consent label', () => {
    const wrapper = mountComponent()

    expect(wrapper.find('[data-testid="accept-cgu-field"]').text()).toContain('16 ans ou plus')
  })

  it('displays submit button with correct text', () => {
    const wrapper = mountComponent()

    const submitButton = wrapper.find('[data-testid="submit-button"]')
    expect(submitButton.text()).toContain("S'inscrire en tant que Face")
  })

  describe('Google Sign-In', () => {
    it('is absent unless the backend advertises it', () => {
      const wrapper = mountComponent()

      expect(wrapper.find('[data-testid="google-sign-in-button"]').exists()).toBe(false)
    })

    it('carries the face intent and stays disabled until the CGU are ticked', async () => {
      const wrapper = mountComponent({ googleEnabled: true })

      const button = wrapper.find('[data-testid="google-sign-in-button"]')
      expect(button.exists()).toBe(true)
      expect(button.attributes('data-intent')).toBe('face')
      expect(button.attributes('disabled')).toBeDefined()
      expect(wrapper.find('[data-testid="google-cgu-hint"]').exists()).toBe(true)

      await wrapper.find('[data-testid="accept-cgu-checkbox"]').setValue(true)

      expect(
        wrapper.find('[data-testid="google-sign-in-button"]').attributes('disabled')
      ).toBeUndefined()
      expect(wrapper.find('[data-testid="google-cgu-hint"]').exists()).toBe(false)
    })
  })
})
