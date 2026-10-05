import { describe, it, expect, vi, beforeEach } from 'vitest'
import { passwordChangeApi } from '../../services/passwordChangeApi'
import { usePasswordChange } from '../usePasswordChange'
import { AxiosError, type AxiosResponse } from 'axios'

vi.mock('../../services/passwordChangeApi', () => ({
  passwordChangeApi: {
    changePassword: vi.fn(),
    setPassword: vi.fn(),
  },
}))

const mockedApi = vi.mocked(passwordChangeApi)

function makeAxiosError(status: number, data: Record<string, unknown>): AxiosError {
  return new AxiosError(
    'Request failed',
    AxiosError.ERR_BAD_REQUEST,
    undefined,
    undefined,
    {
      status,
      data,
      statusText: '',
      headers: {},
      config: {} as never,
    } as AxiosResponse,
  )
}

describe('usePasswordChange', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('has correct initial state', () => {
    const { isLoading, error, fieldErrors } = usePasswordChange()

    expect(isLoading.value).toBe(false)
    expect(error.value).toBeNull()
    expect(fieldErrors.value).toEqual({})
  })

  it('returns true on success', async () => {
    mockedApi.changePassword.mockResolvedValue({ password_changed: true })

    const { changePassword } = usePasswordChange()
    const result = await changePassword('old', 'New1234567', 'New1234567')

    expect(result).toBe(true)
    expect(mockedApi.changePassword).toHaveBeenCalledWith('old', 'New1234567', 'New1234567')
  })

  it('sets field errors on validation failure', async () => {
    mockedApi.changePassword.mockRejectedValue(
      makeAxiosError(422, {
        error: {
          code: 'validation_error',
          message: 'Les données fournies ne sont pas valides',
          details: { current_password: ['Le mot de passe actuel est incorrect.'] },
        },
      }),
    )

    const { changePassword, fieldErrors, error } = usePasswordChange()
    const result = await changePassword('wrong', 'New1234567', 'New1234567')

    expect(result).toBe(false)
    expect(fieldErrors.value.current_password).toEqual(['Le mot de passe actuel est incorrect.'])
    expect(error.value).toBe('Le mot de passe actuel est incorrect.')
  })

  it('sets isLoading during request', async () => {
    let resolvePromise: ((value: { password_changed: boolean }) => void) | undefined
    mockedApi.changePassword.mockImplementation(
      () =>
        new Promise((resolve) => {
          resolvePromise = resolve
        }),
    )

    const { changePassword, isLoading } = usePasswordChange()

    const promise = changePassword('old', 'New1234567', 'New1234567')
    expect(isLoading.value).toBe(true)

    resolvePromise!({ password_changed: true })
    await promise

    expect(isLoading.value).toBe(false)
  })

  it('clears previous errors on new request', async () => {
    mockedApi.changePassword.mockRejectedValueOnce(
      makeAxiosError(422, {
        error: { code: 'validation_error', message: 'Error', details: {} },
      }),
    )

    const { changePassword, error } = usePasswordChange()
    await changePassword('wrong', 'bad', 'bad')
    expect(error.value).toBeTruthy()

    mockedApi.changePassword.mockResolvedValueOnce({ password_changed: true })
    await changePassword('old', 'New1234567', 'New1234567')
    expect(error.value).toBeNull()
  })

  it('clearError resets error and fieldErrors', async () => {
    mockedApi.changePassword.mockRejectedValue(
      makeAxiosError(422, {
        error: {
          code: 'validation_error',
          message: 'Error',
          details: { current_password: ['wrong'] },
        },
      }),
    )

    const { changePassword, clearError, error, fieldErrors } = usePasswordChange()
    await changePassword('wrong', 'bad', 'bad')

    expect(error.value).toBeTruthy()
    expect(Object.keys(fieldErrors.value).length).toBeGreaterThan(0)

    clearError()
    expect(error.value).toBeNull()
    expect(fieldErrors.value).toEqual({})
  })

  describe('setPassword (first password, Google re-auth ticket)', () => {
    it('returns true on success and forwards the ticket', async () => {
      mockedApi.setPassword.mockResolvedValue({ password_changed: true })

      const { setPassword } = usePasswordChange()
      const result = await setPassword('New1234567', 'New1234567', 'ticket')

      expect(result).toBe(true)
      expect(mockedApi.setPassword).toHaveBeenCalledWith('New1234567', 'New1234567', 'ticket')
      expect(mockedApi.changePassword).not.toHaveBeenCalled()
    })

    it('exposes the error code and message of an invalid ticket', async () => {
      mockedApi.setPassword.mockRejectedValue(
        makeAxiosError(422, {
          error: {
            code: 'REAUTH_TOKEN_INVALID',
            message: 'Confirmation expirée. Reprenez la confirmation avec Google.',
          },
        }),
      )

      const { setPassword, error, errorCode } = usePasswordChange()
      const result = await setPassword('New1234567', 'New1234567', 'ticket')

      expect(result).toBe(false)
      expect(errorCode.value).toBe('REAUTH_TOKEN_INVALID')
      expect(error.value).toBe('Confirmation expirée. Reprenez la confirmation avec Google.')
    })

    it('clearError resets the error code', async () => {
      mockedApi.setPassword.mockRejectedValue(
        makeAxiosError(422, { error: { code: 'REAUTH_TOKEN_INVALID', message: 'x' } }),
      )

      const { setPassword, clearError, errorCode } = usePasswordChange()
      await setPassword('New1234567', 'New1234567', 'ticket')
      expect(errorCode.value).toBe('REAUTH_TOKEN_INVALID')

      clearError()
      expect(errorCode.value).toBeNull()
    })
  })
})
