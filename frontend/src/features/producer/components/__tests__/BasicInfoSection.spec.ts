import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import BasicInfoSection from '../BasicInfoSection.vue'
import type { ProducerBasicInfo } from '../../types'

// Mock the composable
const mockFetchBasicInfo = vi.fn()
const mockUpdateBasicInfo = vi.fn()
const mockClearError = vi.fn()
const mockBasicInfo = ref<ProducerBasicInfo | null>(null)
const mockIsLoading = ref(false)
const mockIsSaving = ref(false)
const mockError = ref<string | null>(null)

vi.mock('../../composables/useProducerBasicInfo', () => ({
  useProducerBasicInfo: () => ({
    basicInfo: mockBasicInfo,
    isLoading: mockIsLoading,
    isSaving: mockIsSaving,
    error: mockError,
    fetchBasicInfo: mockFetchBasicInfo,
    updateBasicInfo: mockUpdateBasicInfo,
    clearError: mockClearError,
  }),
}))

describe('BasicInfoSection (Producer)', () => {
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

  describe('Agency type', () => {
    beforeEach(() => {
      mockBasicInfo.value = {
        type: 'agency',
        agency_name: 'Production ABC',
      }
    })

    it('shows agency_name field for agency type', async () => {
      const wrapper = mount(BasicInfoSection)
      await flushPromises()

      const agencyNameInput = wrapper.find('[data-testid="agency-name-input"]')
      expect(agencyNameInput.exists()).toBe(true)
      expect((agencyNameInput.element as HTMLInputElement).value).toBe('Production ABC')
    })

    it('does not show first_name/last_name fields for agency type', async () => {
      const wrapper = mount(BasicInfoSection)
      await flushPromises()

      expect(wrapper.find('[data-testid="first-name-input"]').exists()).toBe(false)
      expect(wrapper.find('[data-testid="last-name-input"]').exists()).toBe(false)
    })

    it('updates agency_name on form submit', async () => {
      mockUpdateBasicInfo.mockResolvedValue({
        success: true,
        message: 'Informations mises à jour avec succès',
      })

      const wrapper = mount(BasicInfoSection)
      await flushPromises()

      await wrapper.find('[data-testid="agency-name-input"]').setValue('Nouvelle Agence')
      await wrapper.find('form').trigger('submit')
      await flushPromises()

      expect(mockClearError).toHaveBeenCalled()
      expect(mockUpdateBasicInfo).toHaveBeenCalledWith({
        agency_name: 'Nouvelle Agence',
      })
    })

    it('shows correct header text for agency', async () => {
      const wrapper = mount(BasicInfoSection)
      await flushPromises()

      expect(wrapper.text()).toContain("Modifiez le nom de votre agence")
    })
  })

  describe('Particulier type', () => {
    beforeEach(() => {
      mockBasicInfo.value = {
        type: 'particulier',
        first_name: 'Jean',
        last_name: 'Dupont',
      }
    })

    it('shows first_name and last_name fields for particulier type', async () => {
      const wrapper = mount(BasicInfoSection)
      await flushPromises()

      const firstNameInput = wrapper.find('[data-testid="first-name-input"]')
      const lastNameInput = wrapper.find('[data-testid="last-name-input"]')

      expect(firstNameInput.exists()).toBe(true)
      expect(lastNameInput.exists()).toBe(true)
      expect((firstNameInput.element as HTMLInputElement).value).toBe('Jean')
      expect((lastNameInput.element as HTMLInputElement).value).toBe('Dupont')
    })

    it('does not show agency_name field for particulier type', async () => {
      const wrapper = mount(BasicInfoSection)
      await flushPromises()

      expect(wrapper.find('[data-testid="agency-name-input"]').exists()).toBe(false)
    })

    it('updates first_name and last_name on form submit', async () => {
      mockUpdateBasicInfo.mockResolvedValue({
        success: true,
        message: 'Informations mises à jour avec succès',
      })

      const wrapper = mount(BasicInfoSection)
      await flushPromises()

      await wrapper.find('[data-testid="first-name-input"]').setValue('Marie')
      await wrapper.find('[data-testid="last-name-input"]').setValue('Martin')
      await wrapper.find('form').trigger('submit')
      await flushPromises()

      expect(mockClearError).toHaveBeenCalled()
      expect(mockUpdateBasicInfo).toHaveBeenCalledWith({
        first_name: 'Marie',
        last_name: 'Martin',
      })
    })

    it('shows correct header text for particulier', async () => {
      const wrapper = mount(BasicInfoSection)
      await flushPromises()

      expect(wrapper.text()).toContain('Modifiez votre nom')
    })
  })

  describe('Common behaviors', () => {
    it('shows error message when error is set', () => {
      mockError.value = 'Le nom de l\'agence est requis'

      const wrapper = mount(BasicInfoSection)

      const errorMessage = wrapper.find('[data-testid="error-message"]')
      expect(errorMessage.exists()).toBe(true)
      expect(errorMessage.text()).toContain('Le nom de l\'agence est requis')
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

    it('shows success message after successful update', async () => {
      mockBasicInfo.value = {
        type: 'agency',
        agency_name: 'Production ABC',
      }
      mockUpdateBasicInfo.mockResolvedValue({
        success: true,
        message: 'Informations mises à jour avec succès',
      })

      const wrapper = mount(BasicInfoSection)
      await flushPromises()

      await wrapper.find('form').trigger('submit')
      await flushPromises()

      const successMessage = wrapper.find('[data-testid="success-message"]')
      expect(successMessage.exists()).toBe(true)
      expect(successMessage.text()).toContain('Informations mises à jour avec succès')
    })

    it('success message has role="status" for accessibility', async () => {
      mockBasicInfo.value = {
        type: 'agency',
        agency_name: 'Production ABC',
      }
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
      mockBasicInfo.value = {
        type: 'agency',
        agency_name: 'Production ABC',
      }
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

    it('renders header section with correct title for particulier', () => {
      mockBasicInfo.value = {
        type: 'particulier',
        first_name: 'Jean',
        last_name: 'Dupont',
      }
      const wrapper = mount(BasicInfoSection)

      expect(wrapper.find('h2').text()).toBe('Votre nom')
    })

    it('renders header section with correct title for agency', () => {
      mockBasicInfo.value = {
        type: 'agency',
        agency_name: 'Production ABC',
      }
      const wrapper = mount(BasicInfoSection)

      expect(wrapper.find('h2').text()).toBe("Nom de l'agence")
    })
  })

  describe('Form field updates when basicInfo changes', () => {
    it('updates agency_name field when basicInfo changes', async () => {
      mockBasicInfo.value = {
        type: 'agency',
        agency_name: 'Production ABC',
      }

      const wrapper = mount(BasicInfoSection)
      await flushPromises()

      expect(
        (wrapper.find('[data-testid="agency-name-input"]').element as HTMLInputElement).value,
      ).toBe('Production ABC')

      // Update basicInfo
      mockBasicInfo.value = {
        type: 'agency',
        agency_name: 'New Agency Name',
      }
      await flushPromises()

      expect(
        (wrapper.find('[data-testid="agency-name-input"]').element as HTMLInputElement).value,
      ).toBe('New Agency Name')
    })

    it('updates particulier fields when basicInfo changes', async () => {
      mockBasicInfo.value = {
        type: 'particulier',
        first_name: 'Jean',
        last_name: 'Dupont',
      }

      const wrapper = mount(BasicInfoSection)
      await flushPromises()

      expect(
        (wrapper.find('[data-testid="first-name-input"]').element as HTMLInputElement).value,
      ).toBe('Jean')

      // Update basicInfo
      mockBasicInfo.value = {
        type: 'particulier',
        first_name: 'Marie',
        last_name: 'Martin',
      }
      await flushPromises()

      expect(
        (wrapper.find('[data-testid="first-name-input"]').element as HTMLInputElement).value,
      ).toBe('Marie')
      expect(
        (wrapper.find('[data-testid="last-name-input"]').element as HTMLInputElement).value,
      ).toBe('Martin')
    })
  })

  describe('Default success message', () => {
    it('uses default success message when API returns no message', async () => {
      mockBasicInfo.value = {
        type: 'agency',
        agency_name: 'Production ABC',
      }
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
})
