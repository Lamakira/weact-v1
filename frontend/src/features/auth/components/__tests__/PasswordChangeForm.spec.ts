import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import PasswordChangeForm from '../PasswordChangeForm.vue'
import { setGoogleReauthTicket, takeGoogleReauthTicket } from '../../googleReauth'

// The form reads has_password from the auth store: with a password it changes one,
// without it sets one (the OAuth-only case).
const mockRefreshUser = vi.fn().mockResolvedValue(true)
const mockHasPassword = ref(true)

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    get hasPassword() {
      return mockHasPassword.value
    },
    refreshUser: mockRefreshUser,
  }),
}))

// Mock usePasswordChange composable
const mockIsLoading = ref(false)
const mockError = ref<string | null>(null)
const mockFieldErrors = ref<Record<string, string[]>>({})
const mockErrorCode = ref<string | null>(null)
const mockChangePassword = vi.fn()
const mockSetPassword = vi.fn()
const mockClearError = vi.fn()

vi.mock('../../composables/usePasswordChange', () => ({
  usePasswordChange: () => ({
    isLoading: mockIsLoading,
    error: mockError,
    fieldErrors: mockFieldErrors,
    errorCode: mockErrorCode,
    changePassword: mockChangePassword,
    setPassword: mockSetPassword,
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

const GoogleSignInButtonStub = {
  props: ['intent', 'label', 'reauthPurpose'],
  template:
    '<button type="button" data-testid="google-sign-in-button" :data-intent="intent" :data-purpose="reauthPurpose" />',
}

function mountForm() {
  return mount(PasswordChangeForm, {
    global: { stubs: { GoogleSignInButton: GoogleSignInButtonStub } },
  })
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
    mockError.value = null
    mockFieldErrors.value = {}
    mockErrorCode.value = null
    sessionStorage.clear()
    mockChangePassword.mockResolvedValue(true)
    mockSetPassword.mockResolvedValue(true)
    mockHasPassword.value = true
    mockRefreshUser.mockResolvedValue(true)
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

  describe('OAuth-only account (no password set)', () => {
    beforeEach(() => {
      mockHasPassword.value = false
    })

    async function fillNewPassword(wrapper: ReturnType<typeof mount>) {
      await wrapper.find('[data-testid="new-password-input"]').setValue('NewPassword2')
      await wrapper.find('[data-testid="confirm-password-input"]').setValue('NewPassword2')
      await wrapper.find('[data-testid="password-change-form"]').trigger('submit')
      await waitForValidation()
    }

    it('reframes the section as setting a password', () => {
      const wrapper = mountForm()

      expect(wrapper.text()).toContain('Définir un mot de passe')
      expect(wrapper.text()).not.toContain('Changer de mot de passe')
      expect(wrapper.find('[data-testid="set-password-hint"]').exists()).toBe(true)
    })

    describe('without a Google ticket', () => {
      it('offers the Google confirmation and no fields', () => {
        const wrapper = mountForm()

        const button = wrapper.find('[data-testid="google-sign-in-button"]')
        expect(button.exists()).toBe(true)
        expect(button.attributes('data-intent')).toBe('reauth')
        expect(button.attributes('data-purpose')).toBe('set_password')
        expect(wrapper.find('[data-testid="show-form-button"]').exists()).toBe(false)
        expect(wrapper.find('[data-testid="password-change-form"]').exists()).toBe(false)
        expect(wrapper.find('[data-testid="new-password-input"]').exists()).toBe(false)
      })
    })

    describe('with a set_password ticket', () => {
      beforeEach(() => {
        setGoogleReauthTicket('reauth-pwd', 'set_password')
      })

      it('shows the new password fields without a current password and consumes the ticket from storage', () => {
        const wrapper = mountForm()

        expect(wrapper.find('[data-testid="current-password-input"]').exists()).toBe(false)
        expect(wrapper.find('[data-testid="new-password-input"]').exists()).toBe(true)
        expect(wrapper.find('[data-testid="confirm-password-input"]').exists()).toBe(true)
        expect(wrapper.find('[data-testid="google-sign-in-button"]').exists()).toBe(false)
        expect(takeGoogleReauthTicket('set_password')).toBeNull()
      })

      it('submits the ticket and never a current password', async () => {
        const wrapper = mountForm()
        await fillNewPassword(wrapper)

        expect(mockSetPassword).toHaveBeenCalledWith('NewPassword2', 'NewPassword2', 'reauth-pwd')
        expect(mockChangePassword).not.toHaveBeenCalled()
      })

      it('refreshes the user so email change and deletion unlock without a reload', async () => {
        const wrapper = mountForm()
        await fillNewPassword(wrapper)

        expect(mockRefreshUser).toHaveBeenCalled()
        expect(mockToastSuccess).toHaveBeenCalledWith('Votre mot de passe a été défini avec succès.')
        expect(wrapper.find('[data-testid="password-change-form"]').exists()).toBe(false)
      })

      it('drops the ticket and returns to the Google button on REAUTH_TOKEN_INVALID', async () => {
        mockSetPassword.mockImplementation(async () => {
          mockErrorCode.value = 'REAUTH_TOKEN_INVALID'
          mockError.value = 'Confirmation expirée. Reprenez la confirmation avec Google.'
          return false
        })

        const wrapper = mountForm()
        await fillNewPassword(wrapper)

        expect(wrapper.find('[data-testid="password-change-form"]').exists()).toBe(false)
        expect(wrapper.find('[data-testid="google-sign-in-button"]').exists()).toBe(true)
        expect(wrapper.find('[data-testid="form-error"]').text()).toContain(
          'Confirmation expirée. Reprenez la confirmation avec Google.'
        )
        expect(mockRefreshUser).not.toHaveBeenCalled()
      })

      it('also drops the ticket on a reauth_token field error', async () => {
        mockSetPassword.mockImplementation(async () => {
          mockFieldErrors.value = { reauth_token: ['La confirmation Google est requise.'] }
          mockError.value = 'La confirmation Google est requise.'
          return false
        })

        const wrapper = mountForm()
        await fillNewPassword(wrapper)

        expect(wrapper.find('[data-testid="password-change-form"]').exists()).toBe(false)
        expect(wrapper.find('[data-testid="google-sign-in-button"]').exists()).toBe(true)
      })

      it('keeps the form on an ordinary field error', async () => {
        mockSetPassword.mockImplementation(async () => {
          mockFieldErrors.value = { new_password: ['Le mot de passe est trop faible.'] }
          mockError.value = 'Le mot de passe est trop faible.'
          return false
        })

        const wrapper = mountForm()
        await fillNewPassword(wrapper)

        expect(wrapper.find('[data-testid="password-change-form"]').exists()).toBe(true)
      })

      it('goes back to the Google button on cancel (the ticket is spent)', async () => {
        const wrapper = mountForm()
        await wrapper.find('[data-testid="cancel-form-button"]').trigger('click')

        expect(wrapper.find('[data-testid="password-change-form"]').exists()).toBe(false)
        expect(wrapper.find('[data-testid="google-sign-in-button"]').exists()).toBe(true)
      })
    })

    it('does not consume a delete_account ticket', () => {
      setGoogleReauthTicket('reauth-del', 'delete_account')

      const wrapper = mountForm()

      expect(wrapper.find('[data-testid="google-sign-in-button"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="password-change-form"]').exists()).toBe(false)
      expect(takeGoogleReauthTicket('delete_account')).toBe('reauth-del')
    })
  })
})
