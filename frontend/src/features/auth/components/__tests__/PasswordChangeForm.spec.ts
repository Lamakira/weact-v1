import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import PasswordChangeForm from '../PasswordChangeForm.vue'

// Mock usePasswordChange composable
const mockIsLoading = ref(false)
const mockIsSuccess = ref(false)
const mockError = ref<string | null>(null)
const mockFieldErrors = ref<Record<string, string[]>>({})
const mockChangePassword = vi.fn()
const mockClearError = vi.fn()

vi.mock('../../composables/usePasswordChange', () => ({
  usePasswordChange: () => ({
    isLoading: mockIsLoading,
    isSuccess: mockIsSuccess,
    error: mockError,
    fieldErrors: mockFieldErrors,
    changePassword: mockChangePassword,
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

// Helper to wait for VeeValidate async validation (same pattern as FaceRegistrationForm tests)
const waitForValidation = async () => {
  await flushPromises()
  await new Promise((resolve) => setTimeout(resolve, 10))
  await flushPromises()
  await new Promise((resolve) => setTimeout(resolve, 10))
  await flushPromises()
}

function mountForm() {
  return mount(PasswordChangeForm)
}

async function openForm(wrapper: ReturnType<typeof mount>) {
  await wrapper.find('[data-testid="show-form-button"]').trigger('click')
}

async function fillAndSubmit(
  wrapper: ReturnType<typeof mount>,
  current = 'OldPassword1',
  newPwd = 'NewPassword2',
  confirm = 'NewPassword2',
) {
  await wrapper.find('[data-testid="current-password-input"]').setValue(current)
  await wrapper.find('[data-testid="new-password-input"]').setValue(newPwd)
  await wrapper.find('[data-testid="confirm-password-input"]').setValue(confirm)
  await wrapper.find('[data-testid="password-change-form"]').trigger('submit')
  await waitForValidation()
}

describe('PasswordChangeForm', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockIsLoading.value = false
    mockIsSuccess.value = false
    mockError.value = null
    mockFieldErrors.value = {}
    mockChangePassword.mockResolvedValue(true)
  })

  it('shows toggle button by default', () => {
    const wrapper = mountForm()

    expect(wrapper.find('[data-testid="show-form-button"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="password-change-form"]').exists()).toBe(false)
  })

  it('shows form when toggle button is clicked', async () => {
    const wrapper = mountForm()
    await openForm(wrapper)

    expect(wrapper.find('[data-testid="password-change-form"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="show-form-button"]').exists()).toBe(false)
  })

  it('hides form and clears errors when cancel is clicked', async () => {
    const wrapper = mountForm()
    await openForm(wrapper)
    await wrapper.find('[data-testid="cancel-form-button"]').trigger('click')

    expect(wrapper.find('[data-testid="password-change-form"]').exists()).toBe(false)
    expect(mockClearError).toHaveBeenCalled()
  })

  it('calls changePassword with form values on submit', async () => {
    const wrapper = mountForm()
    await openForm(wrapper)
    await fillAndSubmit(wrapper)

    expect(mockClearError).toHaveBeenCalled()
    expect(mockChangePassword).toHaveBeenCalledWith('OldPassword1', 'NewPassword2', 'NewPassword2')
  })

  it('shows toast and hides form on success', async () => {
    const wrapper = mountForm()
    await openForm(wrapper)
    await fillAndSubmit(wrapper)

    expect(mockToastSuccess).toHaveBeenCalledWith(
      'Votre mot de passe a été modifié avec succès.',
    )
    expect(wrapper.find('[data-testid="password-change-form"]').exists()).toBe(false)
  })

  it('keeps form visible on failure', async () => {
    mockChangePassword.mockResolvedValue(false)

    const wrapper = mountForm()
    await openForm(wrapper)
    await fillAndSubmit(wrapper, 'wrong', 'NewPassword2', 'NewPassword2')

    expect(wrapper.find('[data-testid="password-change-form"]').exists()).toBe(true)
  })

  it('shows password requirement hint', async () => {
    const wrapper = mountForm()
    await openForm(wrapper)

    expect(wrapper.text()).toContain('Min. 8 car., 1 majuscule, 1 chiffre')
  })

  it('disables submit button when loading', async () => {
    mockIsLoading.value = true

    const wrapper = mountForm()
    await openForm(wrapper)

    expect(wrapper.find('[data-testid="submit-button"]').attributes('disabled')).toBeDefined()
  })

  it('shows general error from API', async () => {
    mockError.value = 'Une erreur est survenue'

    const wrapper = mountForm()
    await openForm(wrapper)

    expect(wrapper.find('[data-testid="form-error"]').text()).toBe('Une erreur est survenue')
  })

  describe('client-side validation via zod', () => {
    it('shows error when new password is too short', async () => {
      const wrapper = mountForm()
      await openForm(wrapper)
      await fillAndSubmit(wrapper, 'OldPassword1', 'Ab1', 'Ab1')

      const error = wrapper.find('[data-testid="new-password-change-error"]')
      expect(error.exists()).toBe(true)
      expect(error.text()).toContain('8 caractères')
      expect(mockChangePassword).not.toHaveBeenCalled()
    })

    it('shows error when new password has no uppercase', async () => {
      const wrapper = mountForm()
      await openForm(wrapper)
      await fillAndSubmit(wrapper, 'OldPassword1', 'abcdefg1', 'abcdefg1')

      const error = wrapper.find('[data-testid="new-password-change-error"]')
      expect(error.exists()).toBe(true)
      expect(error.text()).toContain('majuscule')
      expect(mockChangePassword).not.toHaveBeenCalled()
    })

    it('shows error when new password has no digit', async () => {
      const wrapper = mountForm()
      await openForm(wrapper)
      await fillAndSubmit(wrapper, 'OldPassword1', 'Abcdefgh', 'Abcdefgh')

      const error = wrapper.find('[data-testid="new-password-change-error"]')
      expect(error.exists()).toBe(true)
      expect(error.text()).toContain('chiffre')
      expect(mockChangePassword).not.toHaveBeenCalled()
    })

    it('shows error when passwords do not match', async () => {
      const wrapper = mountForm()
      await openForm(wrapper)
      await fillAndSubmit(wrapper, 'OldPassword1', 'NewPassword2', 'Different1')

      const error = wrapper.find('[data-testid="confirm-password-change-error"]')
      expect(error.exists()).toBe(true)
      expect(error.text()).toContain('ne correspondent pas')
      expect(mockChangePassword).not.toHaveBeenCalled()
    })

    it('shows error when new password is same as current password', async () => {
      const wrapper = mountForm()
      await openForm(wrapper)
      await fillAndSubmit(wrapper, 'SamePass1', 'SamePass1', 'SamePass1')

      const error = wrapper.find('[data-testid="new-password-change-error"]')
      expect(error.exists()).toBe(true)
      expect(error.text()).toContain('différent')
      expect(mockChangePassword).not.toHaveBeenCalled()
    })

    it('does not submit when current password is empty', async () => {
      const wrapper = mountForm()
      await openForm(wrapper)
      await fillAndSubmit(wrapper, '', 'NewPassword2', 'NewPassword2')

      expect(mockChangePassword).not.toHaveBeenCalled()
    })
  })

  describe('API field errors', () => {
    it('maps API current_password error to the field', async () => {
      mockChangePassword.mockResolvedValue(false)
      mockFieldErrors.value = { current_password: ['Le mot de passe actuel est incorrect.'] }

      const wrapper = mountForm()
      await openForm(wrapper)
      await fillAndSubmit(wrapper)

      await waitForValidation()
      expect(wrapper.text()).toContain('Le mot de passe actuel est incorrect.')
    })

    it('maps API new_password error to the field', async () => {
      mockChangePassword.mockResolvedValue(false)
      mockFieldErrors.value = { new_password: ['Le mot de passe est trop faible.'] }

      const wrapper = mountForm()
      await openForm(wrapper)
      await fillAndSubmit(wrapper)

      await waitForValidation()
      expect(wrapper.text()).toContain('Le mot de passe est trop faible.')
    })
  })
})
