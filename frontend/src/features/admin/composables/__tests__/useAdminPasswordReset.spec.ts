import { describe, it, expect, vi, beforeEach } from 'vitest'

// Mock adminAuthApi
const mockForgotPassword = vi.fn()
const mockResetPassword = vi.fn()
vi.mock('../../services/adminAuthApi', () => ({
  adminAuthApi: {
    forgotPassword: (...args: unknown[]) => mockForgotPassword(...args),
    resetPassword: (...args: unknown[]) => mockResetPassword(...args),
  },
  getApiErrorMessage: vi.fn((error: unknown) => {
    const err = error as { response?: { data?: { error?: { message?: string } } } }
    return err?.response?.data?.error?.message ?? null
  }),
}))

// Import after mocks
import { useAdminPasswordReset } from '../useAdminPasswordReset'

describe('useAdminPasswordReset - forgotPassword', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('returns success on successful forgot-password request', async () => {
    mockForgotPassword.mockResolvedValue({
      message: 'Email de réinitialisation envoyé',
    })

    const { forgotPassword } = useAdminPasswordReset()
    const result = await forgotPassword('admin@weact.bj')

    expect(result.success).toBe(true)
    expect(result.message).toBe('Email de réinitialisation envoyé')
    expect(mockForgotPassword).toHaveBeenCalledWith('admin@weact.bj')
  })

  it('returns failure with API error message on error', async () => {
    mockForgotPassword.mockRejectedValue({
      response: {
        data: {
          error: { message: 'Veuillez patienter avant de réessayer' },
        },
      },
    })

    const { forgotPassword } = useAdminPasswordReset()
    const result = await forgotPassword('admin@weact.bj')

    expect(result.success).toBe(false)
    expect(result.message).toBe('Veuillez patienter avant de réessayer')
  })

  it('returns generic error message when API error has no message', async () => {
    mockForgotPassword.mockRejectedValue(new Error('Network error'))

    const { forgotPassword } = useAdminPasswordReset()
    const result = await forgotPassword('admin@weact.bj')

    expect(result.success).toBe(false)
    expect(result.message).toBe('Une erreur est survenue')
  })

  it('sets isLoading during request', async () => {
    let resolveRequest: (value: unknown) => void
    mockForgotPassword.mockReturnValue(
      new Promise((resolve) => {
        resolveRequest = resolve
      }),
    )

    const { forgotPassword, isLoading } = useAdminPasswordReset()

    const promise = forgotPassword('admin@weact.bj')
    expect(isLoading.value).toBe(true)

    resolveRequest!({ message: 'OK' })
    await promise

    expect(isLoading.value).toBe(false)
  })

  it('resets isLoading after error', async () => {
    mockForgotPassword.mockRejectedValue(new Error('fail'))

    const { forgotPassword, isLoading } = useAdminPasswordReset()
    await forgotPassword('admin@weact.bj')

    expect(isLoading.value).toBe(false)
  })
})

describe('useAdminPasswordReset - resetPassword', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  const resetData = {
    token: 'valid-token',
    email: 'admin@weact.bj',
    password: 'NewPassword1',
    password_confirmation: 'NewPassword1',
  }

  it('returns success on successful password reset', async () => {
    mockResetPassword.mockResolvedValue({
      message: 'Mot de passe réinitialisé avec succès',
    })

    const { resetPassword } = useAdminPasswordReset()
    const result = await resetPassword(resetData)

    expect(result.success).toBe(true)
    expect(result.message).toBe('Mot de passe réinitialisé avec succès')
    expect(mockResetPassword).toHaveBeenCalledWith(resetData)
  })

  it('returns failure with API error message on error', async () => {
    mockResetPassword.mockRejectedValue({
      response: {
        data: {
          error: { message: 'Lien expiré ou invalide' },
        },
      },
    })

    const { resetPassword } = useAdminPasswordReset()
    const result = await resetPassword(resetData)

    expect(result.success).toBe(false)
    expect(result.message).toBe('Lien expiré ou invalide')
  })

  it('returns generic error message when API error has no message', async () => {
    mockResetPassword.mockRejectedValue(new Error('Network error'))

    const { resetPassword } = useAdminPasswordReset()
    const result = await resetPassword(resetData)

    expect(result.success).toBe(false)
    expect(result.message).toBe('Une erreur est survenue')
  })

  it('sets isLoading during request', async () => {
    let resolveRequest: (value: unknown) => void
    mockResetPassword.mockReturnValue(
      new Promise((resolve) => {
        resolveRequest = resolve
      }),
    )

    const { resetPassword, isLoading } = useAdminPasswordReset()

    const promise = resetPassword(resetData)
    expect(isLoading.value).toBe(true)

    resolveRequest!({ message: 'OK' })
    await promise

    expect(isLoading.value).toBe(false)
  })

  it('resets isLoading after error', async () => {
    mockResetPassword.mockRejectedValue(new Error('fail'))

    const { resetPassword, isLoading } = useAdminPasswordReset()
    await resetPassword(resetData)

    expect(isLoading.value).toBe(false)
  })
})
