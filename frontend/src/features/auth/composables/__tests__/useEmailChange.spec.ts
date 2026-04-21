import { describe, it, expect, vi, beforeEach } from 'vitest'
import { emailChangeApi } from '../../services/emailChangeApi'
import { useEmailChange } from '../useEmailChange'
import { AxiosError, type AxiosResponse } from 'axios'

vi.mock('../../services/emailChangeApi', () => ({
  emailChangeApi: {
    requestChange: vi.fn(),
    cancelChange: vi.fn(),
    getStatus: vi.fn(),
  },
}))

const mockedApi = vi.mocked(emailChangeApi)

function makeAxiosError(status: number, data: Record<string, unknown>): AxiosError {
  const error = new AxiosError(
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
  return error
}

describe('useEmailChange', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('has correct initial state', () => {
    const { pendingEmail, isLoading, isSuccess, error, fieldErrors } = useEmailChange()

    expect(pendingEmail.value).toBeNull()
    expect(isLoading.value).toBe(false)
    expect(isSuccess.value).toBe(false)
    expect(error.value).toBeNull()
    expect(fieldErrors.value).toEqual({})
  })

  describe('requestChange', () => {
    it('sets pendingEmail and isSuccess on success', async () => {
      mockedApi.requestChange.mockResolvedValue({ pending_email: 'new@example.com' })

      const { requestChange, pendingEmail, isSuccess } = useEmailChange()
      const result = await requestChange('new@example.com', 'password123')

      expect(result).toBe(true)
      expect(pendingEmail.value).toBe('new@example.com')
      expect(isSuccess.value).toBe(true)
      expect(mockedApi.requestChange).toHaveBeenCalledWith('new@example.com', 'password123')
    })

    it('sets field errors on validation failure', async () => {
      mockedApi.requestChange.mockRejectedValue(
        makeAxiosError(422, {
          error: {
            code: 'validation_error',
            message: 'Les données fournies ne sont pas valides',
            details: { email: ['Cette adresse email est déjà utilisée.'] },
          },
        }),
      )

      const { requestChange, fieldErrors, error } = useEmailChange()
      const result = await requestChange('taken@example.com', 'password123')

      expect(result).toBe(false)
      expect(fieldErrors.value.email).toEqual(['Cette adresse email est déjà utilisée.'])
      expect(error.value).toBe('Cette adresse email est déjà utilisée.')
    })

    it('sets isLoading during request', async () => {
      let resolvePromise: ((value: { pending_email: string }) => void) | undefined
      mockedApi.requestChange.mockImplementation(
        () =>
          new Promise((resolve) => {
            resolvePromise = resolve
          }),
      )

      const { requestChange, isLoading } = useEmailChange()

      const promise = requestChange('new@example.com', 'password123')
      expect(isLoading.value).toBe(true)

      resolvePromise!({ pending_email: 'new@example.com' })
      await promise

      expect(isLoading.value).toBe(false)
    })

    it('clears previous errors on new request', async () => {
      mockedApi.requestChange.mockRejectedValueOnce(
        makeAxiosError(422, {
          error: { code: 'validation_error', message: 'Error', details: {} },
        }),
      )

      const { requestChange, error } = useEmailChange()
      await requestChange('bad@example.com', 'wrong')
      expect(error.value).toBeTruthy()

      mockedApi.requestChange.mockResolvedValueOnce({ pending_email: 'new@example.com' })
      await requestChange('new@example.com', 'correct')
      expect(error.value).toBeNull()
    })
  })

  describe('cancelChange', () => {
    it('clears pendingEmail on success', async () => {
      mockedApi.getStatus.mockResolvedValue({ pending_email: 'new@example.com' })
      mockedApi.cancelChange.mockResolvedValue(undefined)

      const { fetchStatus, cancelChange, pendingEmail } = useEmailChange()
      await fetchStatus()
      expect(pendingEmail.value).toBe('new@example.com')

      const result = await cancelChange()
      expect(result).toBe(true)
      expect(pendingEmail.value).toBeNull()
    })

    it('sets error on failure', async () => {
      mockedApi.cancelChange.mockRejectedValue(makeAxiosError(500, {}))

      const { cancelChange, error } = useEmailChange()
      const result = await cancelChange()

      expect(result).toBe(false)
      expect(error.value).toBeTruthy()
    })
  })

  describe('fetchStatus', () => {
    it('loads pending email from API', async () => {
      mockedApi.getStatus.mockResolvedValue({ pending_email: 'pending@example.com' })

      const { fetchStatus, pendingEmail } = useEmailChange()
      await fetchStatus()

      expect(pendingEmail.value).toBe('pending@example.com')
    })

    it('sets null when no pending email', async () => {
      mockedApi.getStatus.mockResolvedValue({ pending_email: null })

      const { fetchStatus, pendingEmail } = useEmailChange()
      await fetchStatus()

      expect(pendingEmail.value).toBeNull()
    })

    it('sets error on failure', async () => {
      mockedApi.getStatus.mockRejectedValue(makeAxiosError(500, {}))

      const { fetchStatus, error } = useEmailChange()
      await fetchStatus()

      expect(error.value).toBeTruthy()
    })
  })

  describe('clearError', () => {
    it('clears error and fieldErrors', async () => {
      mockedApi.requestChange.mockRejectedValue(
        makeAxiosError(422, {
          error: {
            code: 'validation_error',
            message: 'Error',
            details: { email: ['taken'] },
          },
        }),
      )

      const { requestChange, clearError, error, fieldErrors } = useEmailChange()
      await requestChange('bad@example.com', 'wrong')

      expect(error.value).toBeTruthy()
      expect(Object.keys(fieldErrors.value).length).toBeGreaterThan(0)

      clearError()
      expect(error.value).toBeNull()
      expect(fieldErrors.value).toEqual({})
    })
  })
})
