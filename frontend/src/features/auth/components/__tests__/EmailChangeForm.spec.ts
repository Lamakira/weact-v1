import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import EmailChangeForm from '../EmailChangeForm.vue'

// Mock useEmailChange composable
const mockPendingEmail = ref<string | null>(null)
const mockIsLoading = ref(false)
const mockIsSuccess = ref(false)
const mockError = ref<string | null>(null)
const mockFieldErrors = ref<Record<string, string[]>>({})
const mockRequestChange = vi.fn()
const mockCancelChange = vi.fn()
const mockFetchStatus = vi.fn()
const mockClearError = vi.fn()

vi.mock('../../composables/useEmailChange', () => ({
  useEmailChange: () => ({
    pendingEmail: mockPendingEmail,
    isLoading: mockIsLoading,
    isSuccess: mockIsSuccess,
    error: mockError,
    fieldErrors: mockFieldErrors,
    requestChange: mockRequestChange,
    cancelChange: mockCancelChange,
    fetchStatus: mockFetchStatus,
    clearError: mockClearError,
  }),
}))

// Mock useToast
const mockToastSuccess = vi.fn()
vi.mock('@/composables/useToast', () => ({
  useToast: () => ({
    success: mockToastSuccess,
    error: vi.fn(),
  }),
}))

describe('EmailChangeForm', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockPendingEmail.value = null
    mockIsLoading.value = false
    mockIsSuccess.value = false
    mockError.value = null
    mockFieldErrors.value = {}
    mockRequestChange.mockResolvedValue(true)
    mockCancelChange.mockResolvedValue(true)
  })

  it('calls fetchStatus on mount', async () => {
    mount(EmailChangeForm)
    await flushPromises()

    expect(mockFetchStatus).toHaveBeenCalledOnce()
  })

  it('shows "Modifier mon adresse email" button by default', () => {
    const wrapper = mount(EmailChangeForm)

    expect(wrapper.find('[data-testid="show-form-button"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="email-change-form"]').exists()).toBe(false)
  })

  it('shows form when toggle button is clicked', async () => {
    const wrapper = mount(EmailChangeForm)

    await wrapper.find('[data-testid="show-form-button"]').trigger('click')

    expect(wrapper.find('[data-testid="email-change-form"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="show-form-button"]').exists()).toBe(false)
  })

  it('hides form when cancel button is clicked', async () => {
    const wrapper = mount(EmailChangeForm)

    await wrapper.find('[data-testid="show-form-button"]').trigger('click')
    expect(wrapper.find('[data-testid="email-change-form"]').exists()).toBe(true)

    await wrapper.find('[data-testid="cancel-form-button"]').trigger('click')
    expect(wrapper.find('[data-testid="email-change-form"]').exists()).toBe(false)
    expect(mockClearError).toHaveBeenCalled()
  })

  describe('pending email', () => {
    beforeEach(() => {
      mockPendingEmail.value = 'new@example.com'
    })

    it('shows pending email notice', () => {
      const wrapper = mount(EmailChangeForm)

      const notice = wrapper.find('[data-testid="pending-email-notice"]')
      expect(notice.exists()).toBe(true)
      expect(notice.text()).toContain('new@example.com')
    })

    it('hides show-form button when pending email exists', () => {
      const wrapper = mount(EmailChangeForm)

      expect(wrapper.find('[data-testid="show-form-button"]').exists()).toBe(false)
    })

    it('calls cancelChange when cancel button is clicked', async () => {
      const wrapper = mount(EmailChangeForm)

      await wrapper.find('[data-testid="cancel-pending-button"]').trigger('click')
      await flushPromises()

      expect(mockCancelChange).toHaveBeenCalledOnce()
    })

    it('shows toast on successful cancel', async () => {
      const wrapper = mount(EmailChangeForm)

      await wrapper.find('[data-testid="cancel-pending-button"]').trigger('click')
      await flushPromises()

      expect(mockToastSuccess).toHaveBeenCalledWith(
        "La demande de changement d'email a été annulée.",
      )
    })
  })

  describe('form submission', () => {
    it('calls requestChange with email and password', async () => {
      const wrapper = mount(EmailChangeForm)

      await wrapper.find('[data-testid="show-form-button"]').trigger('click')

      await wrapper.find('[data-testid="new-email-input"]').setValue('new@example.com')
      await wrapper.find('[data-testid="password-input"]').setValue('password123')
      await wrapper.find('[data-testid="email-change-form"]').trigger('submit')
      await flushPromises()

      expect(mockClearError).toHaveBeenCalled()
      expect(mockRequestChange).toHaveBeenCalledWith('new@example.com', 'password123')
    })

    it('shows toast and hides form on success', async () => {
      const wrapper = mount(EmailChangeForm)

      await wrapper.find('[data-testid="show-form-button"]').trigger('click')
      await wrapper.find('[data-testid="new-email-input"]').setValue('new@example.com')
      await wrapper.find('[data-testid="password-input"]').setValue('password123')
      await wrapper.find('[data-testid="email-change-form"]').trigger('submit')
      await flushPromises()

      expect(mockToastSuccess).toHaveBeenCalledWith(
        'Un email de confirmation a été envoyé à votre nouvelle adresse.',
      )
      expect(wrapper.find('[data-testid="email-change-form"]').exists()).toBe(false)
    })

    it('keeps form visible on failure', async () => {
      mockRequestChange.mockResolvedValue(false)

      const wrapper = mount(EmailChangeForm)

      await wrapper.find('[data-testid="show-form-button"]').trigger('click')
      await wrapper.find('[data-testid="new-email-input"]').setValue('bad@example.com')
      await wrapper.find('[data-testid="password-input"]').setValue('wrong')
      await wrapper.find('[data-testid="email-change-form"]').trigger('submit')
      await flushPromises()

      expect(wrapper.find('[data-testid="email-change-form"]').exists()).toBe(true)
    })
  })

  describe('error display', () => {
    it('shows field error for email', async () => {
      mockFieldErrors.value = { email: ['Cette adresse email est déjà utilisée.'] }

      const wrapper = mount(EmailChangeForm)
      // Need to show the form first
      await wrapper.find('[data-testid="show-form-button"]').trigger('click')

      expect(wrapper.find('[data-testid="email-error"]').text()).toBe(
        'Cette adresse email est déjà utilisée.',
      )
    })

    it('shows field error for password', async () => {
      mockFieldErrors.value = { password: ['Le mot de passe est incorrect.'] }

      const wrapper = mount(EmailChangeForm)
      await wrapper.find('[data-testid="show-form-button"]').trigger('click')

      expect(wrapper.find('[data-testid="password-error"]').text()).toBe(
        'Le mot de passe est incorrect.',
      )
    })

    it('shows general error when no field errors', async () => {
      mockError.value = 'Une erreur est survenue'

      const wrapper = mount(EmailChangeForm)
      await wrapper.find('[data-testid="show-form-button"]').trigger('click')

      expect(wrapper.find('[data-testid="form-error"]').text()).toBe('Une erreur est survenue')
    })
  })

  describe('loading state', () => {
    it('disables submit button when loading', async () => {
      mockIsLoading.value = true

      const wrapper = mount(EmailChangeForm)
      await wrapper.find('[data-testid="show-form-button"]').trigger('click')

      expect(wrapper.find('[data-testid="submit-button"]').attributes('disabled')).toBeDefined()
    })
  })
})
