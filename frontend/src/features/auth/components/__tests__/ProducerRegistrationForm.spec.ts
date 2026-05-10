import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createWebHistory } from 'vue-router'
import { ref } from 'vue'
import ProducerRegistrationForm from '../ProducerRegistrationForm.vue'

// Mock the useAuth composable
const mockRegisterProducer = vi.fn()
const mockIsLoading = ref(false)

vi.mock('../../composables/useAuth', () => ({
  useAuth: () => ({
    registerProducer: mockRegisterProducer,
    isLoading: mockIsLoading,
  }),
}))

describe('ProducerRegistrationForm', () => {
  const router = createRouter({
    history: createWebHistory(),
    routes: [{ path: '/', component: { template: '<div />' } }],
  })

  beforeEach(() => {
    setActivePinia(createPinia())
    mockRegisterProducer.mockReset()
    mockIsLoading.value = false
  })

  const mountComponent = () => {
    return mount(ProducerRegistrationForm, {
      global: {
        plugins: [router],
      },
    })
  }

  it('renders the form with type selector', () => {
    const wrapper = mountComponent()

    expect(wrapper.find('[data-testid="producer-registration-form"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="type-selector"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="type-agency-button"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="type-particulier-button"]').exists()).toBe(true)
  })

  it('shows agency fields when type is agency (default)', () => {
    const wrapper = mountComponent()

    expect(wrapper.find('[data-testid="agency-fields"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="agency-name-input"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="particulier-fields"]').exists()).toBe(false)
  })

  it('shows particulier fields when type is particulier', async () => {
    const wrapper = mountComponent()

    await wrapper.find('[data-testid="type-particulier-button"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="particulier-fields"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="first-name-input"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="last-name-input"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="agency-fields"]').exists()).toBe(false)
  })

  it('renders common form fields', () => {
    const wrapper = mountComponent()

    expect(wrapper.find('[data-testid="email-input"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="password-input"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="password-confirmation-input"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="submit-button"]').exists()).toBe(true)
  })

  it('toggles password visibility when eye icon is clicked', async () => {
    const wrapper = mountComponent()

    const passwordInput = wrapper.find('[data-testid="password-input"]')
    const toggleButton = wrapper.find('[data-testid="toggle-password-visibility"]')

    // Initially password type
    expect(passwordInput.attributes('type')).toBe('password')

    // Click to show password
    await toggleButton.trigger('click')
    expect(passwordInput.attributes('type')).toBe('text')

    // Click to hide password
    await toggleButton.trigger('click')
    expect(passwordInput.attributes('type')).toBe('password')
  })

  it('switches type selector active state correctly', async () => {
    const wrapper = mountComponent()

    const agencyButton = wrapper.find('[data-testid="type-agency-button"]')
    const particulierButton = wrapper.find('[data-testid="type-particulier-button"]')

    // Agency should be active by default
    expect(agencyButton.classes()).toContain('bg-primary-500')
    expect(particulierButton.classes()).not.toContain('bg-primary-500')

    // Click particulier
    await particulierButton.trigger('click')

    // Particulier should now be active
    expect(particulierButton.classes()).toContain('bg-primary-500')
    expect(agencyButton.classes()).not.toContain('bg-primary-500')
  })

  it('has submit button with correct text', async () => {
    mockIsLoading.value = false
    const wrapper = mountComponent()
    await flushPromises()

    const submitButton = wrapper.find('[data-testid="submit-button"]')
    expect(submitButton.text()).toContain('Créer mon compte producteur')
  })

  it('shows loading state when isLoading is true', async () => {
    mockIsLoading.value = true

    const wrapper = mountComponent()
    await flushPromises()

    const submitButton = wrapper.find('[data-testid="submit-button"]')
    expect(submitButton.attributes('disabled')).toBeDefined()
    expect(submitButton.text()).toContain('Création en cours...')
  })

  it('has correct input types for security', () => {
    const wrapper = mountComponent()

    expect(wrapper.find('[data-testid="email-input"]').attributes('type')).toBe('email')
    expect(wrapper.find('[data-testid="password-input"]').attributes('type')).toBe('password')
    expect(wrapper.find('[data-testid="password-confirmation-input"]').attributes('type')).toBe(
      'password'
    )
  })

  it('has correct autocomplete attributes for accessibility', () => {
    const wrapper = mountComponent()

    expect(wrapper.find('[data-testid="email-input"]').attributes('autocomplete')).toBe('email')
    expect(wrapper.find('[data-testid="password-input"]').attributes('autocomplete')).toBe(
      'new-password'
    )
    expect(
      wrapper.find('[data-testid="password-confirmation-input"]').attributes('autocomplete')
    ).toBe('new-password')
  })

  it('has correct autocomplete attributes for particulier name fields', async () => {
    const wrapper = mountComponent()

    await wrapper.find('[data-testid="type-particulier-button"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="first-name-input"]').attributes('autocomplete')).toBe(
      'given-name'
    )
    expect(wrapper.find('[data-testid="last-name-input"]').attributes('autocomplete')).toBe(
      'family-name'
    )
  })

  it('shows password hint text', () => {
    const wrapper = mountComponent()

    expect(wrapper.text()).toContain('Min. 8 car., 1 majuscule, 1 chiffre')
  })

  it('submits agency registration payload', async () => {
    mockRegisterProducer.mockResolvedValue({ success: true })
    const wrapper = mountComponent()

    await wrapper.find('[data-testid="agency-name-input"]').setValue('Studio Pro')
    await wrapper.find('[data-testid="email-input"]').setValue('agency@example.com')
    await wrapper.find('[data-testid="password-input"]').setValue('Password123')
    await wrapper.find('[data-testid="password-confirmation-input"]').setValue('Password123')
    await wrapper.find('[data-testid="accept-cgu-checkbox"]').setValue(true)
    await wrapper.find('[data-testid="producer-registration-form"]').trigger('submit')
    await flushPromises()

    await vi.waitFor(() => expect(mockRegisterProducer).toHaveBeenCalledTimes(1))
    expect(mockRegisterProducer).toHaveBeenCalledWith({
      type: 'agency',
      agency_name: 'Studio Pro',
      email: 'agency@example.com',
      password: 'Password123',
      password_confirmation: 'Password123',
      accept_cgu: true,
    })
  })

  it('submits particulier registration payload after switching type', async () => {
    mockRegisterProducer.mockResolvedValue({ success: true })
    const wrapper = mountComponent()

    await wrapper.find('[data-testid="type-particulier-button"]').trigger('click')
    await flushPromises()

    await wrapper.find('[data-testid="first-name-input"]').setValue('Jean')
    await wrapper.find('[data-testid="last-name-input"]').setValue('Dupont')
    await wrapper.find('[data-testid="email-input"]').setValue('jean@example.com')
    await wrapper.find('[data-testid="password-input"]').setValue('Password123')
    await wrapper.find('[data-testid="password-confirmation-input"]').setValue('Password123')
    await wrapper.find('[data-testid="accept-cgu-checkbox"]').setValue(true)
    await wrapper.find('[data-testid="producer-registration-form"]').trigger('submit')
    await flushPromises()

    await vi.waitFor(() => expect(mockRegisterProducer).toHaveBeenCalledTimes(1))
    expect(mockRegisterProducer).toHaveBeenCalledWith({
      type: 'particulier',
      first_name: 'Jean',
      last_name: 'Dupont',
      email: 'jean@example.com',
      password: 'Password123',
      password_confirmation: 'Password123',
      accept_cgu: true,
    })
  })
})
