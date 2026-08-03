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

  const mountComponent = (props: Record<string, unknown> = {}) => {
    return mount(ProducerRegistrationForm, {
      props,
      global: {
        plugins: [router],
        stubs: {
          GoogleSignInButton: {
            props: ['intent', 'disabled', 'label'],
            template:
              '<button data-testid="google-sign-in-button" :data-intent="intent" :disabled="disabled" />',
          },
        },
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

  it('renders exactly the four signup fields', () => {
    const wrapper = mountComponent()

    expect(wrapper.find('[data-testid="nom-input"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="email-input"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="password-input"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="accept-cgu-checkbox"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="submit-button"]').exists()).toBe(true)
  })

  it('no longer renders the split name inputs or the password confirmation', () => {
    const wrapper = mountComponent()

    expect(wrapper.find('[data-testid="agency-name-input"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="first-name-input"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="last-name-input"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="password-confirmation-input"]').exists()).toBe(false)
  })

  it('keeps the same single name input across both account types', async () => {
    const wrapper = mountComponent()

    await wrapper.find('[data-testid="nom-input"]').setValue('Studio Pro')
    await wrapper.find('[data-testid="type-particulier-button"]').trigger('click')
    await flushPromises()

    const nom = wrapper.find('[data-testid="nom-input"]')
    expect(nom.exists()).toBe(true)
    expect((nom.element as HTMLInputElement).value).toBe('Studio Pro')
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
  })

  it('has correct autocomplete attributes for accessibility', () => {
    const wrapper = mountComponent()

    expect(wrapper.find('[data-testid="nom-input"]').attributes('autocomplete')).toBe('organization')
    expect(wrapper.find('[data-testid="email-input"]').attributes('autocomplete')).toBe('email')
    expect(wrapper.find('[data-testid="password-input"]').attributes('autocomplete')).toBe(
      'new-password'
    )
  })

  it('shows password hint text', () => {
    const wrapper = mountComponent()

    expect(wrapper.text()).toContain('Min. 8 car., 1 majuscule, 1 chiffre')
  })

  it('submits agency registration payload', async () => {
    mockRegisterProducer.mockResolvedValue({ success: true })
    const wrapper = mountComponent()

    await wrapper.find('[data-testid="nom-input"]').setValue('Studio Pro')
    await wrapper.find('[data-testid="email-input"]').setValue('agency@example.com')
    await wrapper.find('[data-testid="password-input"]').setValue('Password123')
    await wrapper.find('[data-testid="accept-cgu-checkbox"]').setValue(true)
    await wrapper.find('[data-testid="producer-registration-form"]').trigger('submit')
    await flushPromises()

    await vi.waitFor(() => expect(mockRegisterProducer).toHaveBeenCalledTimes(1))
    expect(mockRegisterProducer).toHaveBeenCalledWith({
      type: 'agency',
      agency_name: 'Studio Pro',
      email: 'agency@example.com',
      password: 'Password123',
      accept_cgu: true,
    })
  })

  it('submits the name as nom_complet after switching to particulier', async () => {
    mockRegisterProducer.mockResolvedValue({ success: true })
    const wrapper = mountComponent()

    await wrapper.find('[data-testid="type-particulier-button"]').trigger('click')
    await flushPromises()

    await wrapper.find('[data-testid="nom-input"]').setValue('Jean Dupont')
    await wrapper.find('[data-testid="email-input"]').setValue('jean@example.com')
    await wrapper.find('[data-testid="password-input"]').setValue('Password123')
    await wrapper.find('[data-testid="accept-cgu-checkbox"]').setValue(true)
    await wrapper.find('[data-testid="producer-registration-form"]').trigger('submit')
    await flushPromises()

    await vi.waitFor(() => expect(mockRegisterProducer).toHaveBeenCalledTimes(1))
    expect(mockRegisterProducer).toHaveBeenCalledWith({
      type: 'particulier',
      nom_complet: 'Jean Dupont',
      email: 'jean@example.com',
      password: 'Password123',
      accept_cgu: true,
    })
  })

  describe('Google Sign-In', () => {
    it('is absent unless the backend advertises it', () => {
      const wrapper = mountComponent()

      expect(wrapper.find('[data-testid="google-sign-in-button"]').exists()).toBe(false)
    })

    it('carries the producer intent and stays disabled until the CGU are ticked', async () => {
      const wrapper = mountComponent({ googleEnabled: true })

      const button = wrapper.find('[data-testid="google-sign-in-button"]')
      expect(button.exists()).toBe(true)
      expect(button.attributes('data-intent')).toBe('producer')
      expect(button.attributes('disabled')).toBeDefined()

      await wrapper.find('[data-testid="accept-cgu-checkbox"]').setValue(true)

      expect(
        wrapper.find('[data-testid="google-sign-in-button"]').attributes('disabled')
      ).toBeUndefined()
    })
  })

  it('maps the per-type server error key onto the shared name input', async () => {
    mockRegisterProducer.mockResolvedValue({
      success: false,
      errors: { agency_name: ["Le nom de l'agence est obligatoire"] },
    })
    const wrapper = mountComponent()

    await wrapper.find('[data-testid="nom-input"]').setValue('Studio Pro')
    await wrapper.find('[data-testid="email-input"]').setValue('agency@example.com')
    await wrapper.find('[data-testid="password-input"]').setValue('Password123')
    await wrapper.find('[data-testid="accept-cgu-checkbox"]').setValue(true)
    await wrapper.find('[data-testid="producer-registration-form"]').trigger('submit')
    await flushPromises()

    await vi.waitFor(() =>
      expect(wrapper.find('[data-testid="nom-error"]').text()).toContain(
        "Le nom de l'agence est obligatoire"
      )
    )
  })
})
