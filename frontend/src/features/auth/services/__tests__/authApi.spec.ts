import { describe, expect, it } from 'vitest'
import type { AxiosError } from 'axios'
import { getApiErrorMessage } from '../authApi'

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

  it('returns the default French message on a network error with no response', () => {
    const error = {
      isAxiosError: true,
      response: undefined,
    } as unknown as AxiosError

    expect(getApiErrorMessage(error)).toBe(
      'Une erreur est survenue. Veuillez réessayer.'
    )
  })

  it('returns the default French message when the input is not an Axios error', () => {
    const error = new Error('unexpected')

    expect(getApiErrorMessage(error)).toBe(
      'Une erreur est survenue. Veuillez réessayer.'
    )
  })
})
