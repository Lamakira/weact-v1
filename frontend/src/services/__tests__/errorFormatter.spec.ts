import { describe, expect, it } from 'vitest'
import { AxiosError, type AxiosResponse } from 'axios'
import {
  formatApiError,
  getApiErrorCode,
  getApiErrorDetails,
  isNetworkError,
} from '../errorFormatter'

type ApiErrorEnvelope = {
  error?: {
    message?: string
    code?: string
    details?: Record<string, string[]>
  }
  errors?: Record<string, string[]>
  message?: string
}

function makeAxiosError(
  status: number,
  data: ApiErrorEnvelope | string,
  code?: string,
): AxiosError<ApiErrorEnvelope | string> {
  return new AxiosError(
    'Request failed',
    code ?? AxiosError.ERR_BAD_REQUEST,
    undefined,
    undefined,
    {
      status,
      data,
      statusText: '',
      headers: {},
      config: {} as never,
    } as AxiosResponse<ApiErrorEnvelope | string>,
  )
}

describe('formatApiError', () => {
  it('returns the standard error envelope business message', () => {
    const error = makeAxiosError(422, {
      error: {
        message: 'Solde insuffisant',
        code: 'INSUFFICIENT_BALANCE',
      },
    })

    expect(formatApiError(error)).toBe('Solde insuffisant')
  })

  it('returns the first validation detail when the standardized 422 message is generic', () => {
    const error = makeAxiosError(422, {
      error: {
        code: 'VALIDATION_ERROR',
        message: 'Les données fournies ne sont pas valides',
        details: {
          amount: ['Le champ amount est obligatoire.'],
        },
      },
      errors: {
        amount: ['Le champ amount est obligatoire.'],
      },
      message: 'Les données fournies ne sont pas valides',
    })

    expect(formatApiError(error)).toBe('Le champ amount est obligatoire.')
  })

  it('returns the generic standardized 422 message when details are empty', () => {
    const error = makeAxiosError(422, {
      error: {
        code: 'VALIDATION_ERROR',
        message: 'Les données fournies ne sont pas valides',
        details: {},
      },
    })

    expect(formatApiError(error)).toBe('Les données fournies ne sont pas valides')
  })

  it('returns the first legacy top-level validation error', () => {
    const error = makeAxiosError(422, {
      errors: {
        email: ["L'email est obligatoire."],
      },
    })

    expect(formatApiError(error)).toBe("L'email est obligatoire.")
  })

  it('returns the legacy top-level message when no envelope is present', () => {
    const error = makeAxiosError(400, {
      message: 'Quelque chose',
    })

    expect(formatApiError(error)).toBe('Quelque chose')
  })

  it('returns the default network message on a network error', () => {
    const error = {
      isAxiosError: true,
      response: undefined,
      code: 'ERR_NETWORK',
    } as unknown as AxiosError

    expect(formatApiError(error)).toBe(
      'Impossible de se connecter au serveur. Vérifiez votre connexion.',
    )
  })

  it('returns the provided fallback on a network error when one is supplied', () => {
    const error = {
      isAxiosError: true,
      response: undefined,
      code: 'ERR_NETWORK',
    } as unknown as AxiosError

    expect(formatApiError(error, 'Erreur réseau personnalisée.')).toBe('Erreur réseau personnalisée.')
  })

  it('returns the status-based fallback when a CDN returns a non-JSON 429 response', () => {
    const error = makeAxiosError(429, '<html>...</html>')

    expect(formatApiError(error)).toBe(
      'Trop de tentatives. Veuillez réessayer dans quelques instants.',
    )
  })

  it('returns the generic fallback for a non-Axios error', () => {
    expect(formatApiError(new Error('boom'))).toBe(
      'Une erreur est survenue. Veuillez réessayer.',
    )
  })

  it('returns the provided fallback for a non-Axios error', () => {
    expect(formatApiError(new Error('boom'), 'Fallback explicite')).toBe('Fallback explicite')
  })

  it('returns the generic fallback for a null error', () => {
    expect(formatApiError(null)).toBe('Une erreur est survenue. Veuillez réessayer.')
  })

  it('returns the provided fallback for an undefined error', () => {
    expect(formatApiError(undefined, 'Fallback undefined')).toBe('Fallback undefined')
  })
})

describe('getApiErrorCode', () => {
  it('returns the error code from the standardized envelope', () => {
    const error = makeAxiosError(422, {
      error: {
        code: 'INSUFFICIENT_BALANCE',
      },
    })

    expect(getApiErrorCode(error)).toBe('INSUFFICIENT_BALANCE')
  })

  it('returns null when the error code is absent', () => {
    const error = makeAxiosError(422, {
      error: {},
    })

    expect(getApiErrorCode(error)).toBeNull()
  })
})

describe('getApiErrorDetails', () => {
  it('returns standardized error details when present', () => {
    const error = makeAxiosError(422, {
      error: {
        details: {
          amount: ['x'],
        },
      },
    })

    expect(getApiErrorDetails(error)).toEqual({
      amount: ['x'],
    })
  })

  it('returns legacy top-level validation errors when standardized details are absent', () => {
    const error = makeAxiosError(422, {
      errors: {
        email: ['y'],
      },
    })

    expect(getApiErrorDetails(error)).toEqual({
      email: ['y'],
    })
  })

  it('falls back to legacy top-level errors when standardized details are empty', () => {
    const error = makeAxiosError(422, {
      error: {
        details: {},
      },
      errors: {
        content: ['Le message est requis.'],
      },
    })

    expect(getApiErrorDetails(error)).toEqual({
      content: ['Le message est requis.'],
    })
    expect(formatApiError(error)).toBe('Le message est requis.')
  })

  it('returns an empty object when no details are available', () => {
    const error = makeAxiosError(500, {
      error: {},
    })

    expect(getApiErrorDetails(error)).toEqual({})
  })
})

describe('isNetworkError', () => {
  it('returns true for an Axios network error without response', () => {
    const error = {
      isAxiosError: true,
      response: undefined,
      code: 'ERR_NETWORK',
    } as unknown as AxiosError

    expect(isNetworkError(error)).toBe(true)
  })

  it('returns false for an Axios error with an HTTP response', () => {
    const error = makeAxiosError(500, {
      error: {
        message: 'Erreur serveur',
      },
    })

    expect(isNetworkError(error)).toBe(false)
  })

  it('returns false for a non-Axios error', () => {
    expect(isNetworkError(new Error('boom'))).toBe(false)
  })
})
