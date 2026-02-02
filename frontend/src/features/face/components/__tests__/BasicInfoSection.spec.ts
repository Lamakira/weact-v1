import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import BasicInfoSection from '../BasicInfoSection.vue'

// Mock the composable
const mockFetchBasicInfo = vi.fn()
const mockUpdateBasicInfo = vi.fn()
const mockClearError = vi.fn()
const mockBasicInfo = ref<{ nom: string; prenom: string; username: string } | null>(null)
const mockIsLoading = ref(false)
const mockIsSaving = ref(false)
const mockError = ref<string | null>(null)

vi.mock('../../composables/useBasicInfo', () => ({
  useBasicInfo: () => ({
    basicInfo: mockBasicInfo,
    isLoading: mockIsLoading,
    isSaving: mockIsSaving,
    error: mockError,
    fetchBasicInfo: mockFetchBasicInfo,
    updateBasicInfo: mockUpdateBasicInfo,
    clearError: mockClearError,
  }),
}))

describe('BasicInfoSection', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockBasicInfo.value = null
    mockIsLoading.value = false
    mockIsSaving.value = false
    mockError.value = null
  })

  it('calls fetchBasicInfo on mount', async () => {
    mount(BasicInfoSection)
    await flushPromises()

    expect(mockFetchBasicInfo).toHaveBeenCalledOnce()
  })

  it('shows loading state when isLoading is true', () => {
    mockIsLoading.value = true

    const wrapper = mount(BasicInfoSection)

    expect(wrapper.find('[data-testid="loading-state"]').exists()).toBe(true)
    expect(wrapper.find('form').exists()).toBe(false)
  })

  it('shows form when isLoading is false', () => {
    mockIsLoading.value = false

    const wrapper = mount(BasicInfoSection)

    expect(wrapper.find('[data-testid="loading-state"]').exists()).toBe(false)
    expect(wrapper.find('form').exists()).toBe(true)
  })

  it('populates form with basicInfo data', async () => {
    mockBasicInfo.value = {
      nom: 'Dupont',
      prenom: 'Jean',
      username: 'jeandupont',
    }

    const wrapper = mount(BasicInfoSection)
    await flushPromises()

    const nomInput = wrapper.find('[data-testid="nom-input"]')
    const prenomInput = wrapper.find('[data-testid="prenom-input"]')
    const usernameInput = wrapper.find('[data-testid="username-input"]')

    expect((nomInput.element as HTMLInputElement).value).toBe('Dupont')
    expect((prenomInput.element as HTMLInputElement).value).toBe('Jean')
    expect((usernameInput.element as HTMLInputElement).value).toBe('jeandupont')
  })

  it('shows error message when error is set', () => {
    mockError.value = 'Ce nom d\'utilisateur est déjà utilisé'

    const wrapper = mount(BasicInfoSection)

    const errorMessage = wrapper.find('[data-testid="error-message"]')
    expect(errorMessage.exists()).toBe(true)
    expect(errorMessage.text()).toContain('Ce nom d\'utilisateur est déjà utilisé')
  })

  it('does not show error message when error is null', () => {
    mockError.value = null

    const wrapper = mount(BasicInfoSection)

    expect(wrapper.find('[data-testid="error-message"]').exists()).toBe(false)
  })

  it('error message has role="alert" for accessibility', () => {
    mockError.value = 'Une erreur'

    const wrapper = mount(BasicInfoSection)

    const errorMessage = wrapper.find('[data-testid="error-message"]')
    expect(errorMessage.attributes('role')).toBe('alert')
  })

  it('disables save button when isSaving is true', () => {
    mockIsSaving.value = true

    const wrapper = mount(BasicInfoSection)

    const saveButton = wrapper.find('[data-testid="save-button"]')
    expect(saveButton.attributes('disabled')).toBeDefined()
  })

  it('shows loading spinner in button when isSaving is true', () => {
    mockIsSaving.value = true

    const wrapper = mount(BasicInfoSection)

    const saveButton = wrapper.find('[data-testid="save-button"]')
    expect(saveButton.text()).toContain('Enregistrement...')
    expect(saveButton.find('svg.animate-spin').exists()).toBe(true)
  })

  it('shows "Enregistrer" text when not saving', () => {
    mockIsSaving.value = false

    const wrapper = mount(BasicInfoSection)

    const saveButton = wrapper.find('[data-testid="save-button"]')
    expect(saveButton.text()).toBe('Enregistrer')
  })

  it('calls updateBasicInfo on form submit with form data', async () => {
    mockUpdateBasicInfo.mockResolvedValue({
      success: true,
      message: 'Informations mises à jour avec succès',
    })

    const wrapper = mount(BasicInfoSection)
    await flushPromises()

    // Fill in the form
    await wrapper.find('[data-testid="nom-input"]').setValue('Nouveau Nom')
    await wrapper.find('[data-testid="prenom-input"]').setValue('Nouveau Prénom')
    await wrapper.find('[data-testid="username-input"]').setValue('nouveauusername')

    // Submit the form
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockClearError).toHaveBeenCalled()
    expect(mockUpdateBasicInfo).toHaveBeenCalledWith({
      nom: 'Nouveau Nom',
      prenom: 'Nouveau Prénom',
      username: 'nouveauusername',
    })
  })

  it('shows success message after successful update', async () => {
    mockUpdateBasicInfo.mockResolvedValue({
      success: true,
      message: 'Informations mises à jour avec succès',
    })

    const wrapper = mount(BasicInfoSection)
    await flushPromises()

    // Submit the form
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    const successMessage = wrapper.find('[data-testid="success-message"]')
    expect(successMessage.exists()).toBe(true)
    expect(successMessage.text()).toContain('Informations mises à jour avec succès')
  })

  it('success message has role="status" for accessibility', async () => {
    mockUpdateBasicInfo.mockResolvedValue({
      success: true,
      message: 'Informations mises à jour avec succès',
    })

    const wrapper = mount(BasicInfoSection)
    await flushPromises()

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    const successMessage = wrapper.find('[data-testid="success-message"]')
    expect(successMessage.attributes('role')).toBe('status')
  })

  it('does not show success message after failed update', async () => {
    mockUpdateBasicInfo.mockResolvedValue({
      success: false,
      message: 'Erreur de validation',
    })

    const wrapper = mount(BasicInfoSection)
    await flushPromises()

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.find('[data-testid="success-message"]').exists()).toBe(false)
  })

  it('has proper accessibility labels', () => {
    const wrapper = mount(BasicInfoSection)

    expect(wrapper.find('label[for="nom"]').exists()).toBe(true)
    expect(wrapper.find('label[for="prenom"]').exists()).toBe(true)
    expect(wrapper.find('label[for="username"]').exists()).toBe(true)

    expect(wrapper.find('#nom').exists()).toBe(true)
    expect(wrapper.find('#prenom').exists()).toBe(true)
    expect(wrapper.find('#username').exists()).toBe(true)
  })

  it('shows @ prefix for username field', () => {
    const wrapper = mount(BasicInfoSection)

    const usernameContainer = wrapper.find('[data-testid="username-input"]').element.parentElement
    expect(usernameContainer?.textContent).toContain('@')
  })

  it('renders header section with correct title', () => {
    const wrapper = mount(BasicInfoSection)

    expect(wrapper.find('h2').text()).toBe('Informations personnelles')
    expect(wrapper.text()).toContain('Modifiez votre nom et nom d\'utilisateur')
  })

  it('updates form when basicInfo changes', async () => {
    const wrapper = mount(BasicInfoSection)
    await flushPromises()

    // Initially empty
    expect((wrapper.find('[data-testid="nom-input"]').element as HTMLInputElement).value).toBe('')

    // Update basicInfo
    mockBasicInfo.value = {
      nom: 'Updated Nom',
      prenom: 'Updated Prénom',
      username: 'updatedusername',
    }
    await flushPromises()

    expect((wrapper.find('[data-testid="nom-input"]').element as HTMLInputElement).value).toBe(
      'Updated Nom',
    )
    expect((wrapper.find('[data-testid="prenom-input"]').element as HTMLInputElement).value).toBe(
      'Updated Prénom',
    )
    expect(
      (wrapper.find('[data-testid="username-input"]').element as HTMLInputElement).value,
    ).toBe('updatedusername')
  })

  it('all form inputs are required', () => {
    const wrapper = mount(BasicInfoSection)

    const nomInput = wrapper.find('[data-testid="nom-input"]')
    const prenomInput = wrapper.find('[data-testid="prenom-input"]')
    const usernameInput = wrapper.find('[data-testid="username-input"]')

    expect(nomInput.attributes('required')).toBeDefined()
    expect(prenomInput.attributes('required')).toBeDefined()
    expect(usernameInput.attributes('required')).toBeDefined()
  })

  it('renders with default success message when API returns no message', async () => {
    mockUpdateBasicInfo.mockResolvedValue({
      success: true,
      // No message provided
    })

    const wrapper = mount(BasicInfoSection)
    await flushPromises()

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    const successMessage = wrapper.find('[data-testid="success-message"]')
    expect(successMessage.exists()).toBe(true)
    expect(successMessage.text()).toContain('Informations mises à jour avec succès')
  })
})
