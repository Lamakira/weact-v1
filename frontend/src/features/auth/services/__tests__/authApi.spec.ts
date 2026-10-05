import { describe, expect, it, vi, beforeEach } from 'vitest'
import type { AxiosError } from 'axios'
import { authApi, getApiErrorMessage } from '../authApi'

const h = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }))

vi.mock('@/services/apiClient', () => ({
  default: { get: h.get, post: h.post },
  getCsrfCookie: vi.fn().mockResolvedValue(undefined),
}))

describe('getApiErrorMessage', () => {
  it('returns the backend throttle message from the error envelope', () => {
    const error = {
      isAxiosError: true,
      response: {
        status: 429,
        data: {
          error: {
            message: 'Trop de tentatives de connexion. Veuillez réessayer dans une minute.',
            code: 'THROTTLED',
          },
        },
      },
    } as AxiosError

    expect(getApiErrorMessage(error)).toBe(
      'Trop de tentatives de connexion. Veuillez réessayer dans une minute.'
    )
  })

  it('returns the literal message when backend falls back to the legacy shape (English leak is documented)', () => {
    const error = {
      isAxiosError: true,
      response: {
        status: 429,
        data: {
          message: 'Too Many Attempts.',
        },
      },
    } as AxiosError

    expect(getApiErrorMessage(error)).toBe('Too Many Attempts.')
  })

  it('falls back to the generic French 429 message when a CDN/proxy serves a non-JSON 429', () => {
    const error = {
      isAxiosError: true,
      response: {
        status: 429,
        data: '<html><body>429 Too Many Requests</body></html>',
      },
    } as unknown as AxiosError

    expect(getApiErrorMessage(error)).toBe(
      'Trop de tentatives. Veuillez réessayer dans quelques instants.'
    )
  })

  it('returns the dedicated French network message on a network error with no response', () => {
    const error = {
      isAxiosError: true,
      response: undefined,
    } as unknown as AxiosError

    expect(getApiErrorMessage(error)).toBe(
      'Impossible de se connecter au serveur. Vérifiez votre connexion.'
    )
  })

  it('returns the default French message when the input is not an Axios error', () => {
    const error = new Error('unexpected')

    expect(getApiErrorMessage(error)).toBe(
      'Une erreur est survenue. Veuillez réessayer.'
    )
  })
})

describe('authApi — Google browser binding', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('sends the nonce with the redirect request', async () => {
    h.get.mockResolvedValue({ data: { data: { url: 'https://accounts.google.com/x' } } })

    const url = await authApi.getGoogleRedirectUrl('login', '/pricing', 'n'.repeat(43))

    expect(url).toBe('https://accounts.google.com/x')
    expect(h.get).toHaveBeenCalledWith('/auth/google/redirect', {
      params: { intent: 'login', nonce: 'n'.repeat(43), redirect: '/pricing' },
    })
  })

  it('sends the nonce with the code on exchange', async () => {
    h.post.mockResolvedValue({ data: { data: { needs_completion: false } } })

    await authApi.exchangeGoogleCode('the-code', 'the-nonce')

    expect(h.post).toHaveBeenCalledWith('/auth/google/exchange', {
      code: 'the-code',
      nonce: 'the-nonce',
    })
  })
})
