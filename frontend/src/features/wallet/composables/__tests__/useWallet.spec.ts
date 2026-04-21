import { beforeEach, describe, expect, it, vi } from 'vitest'
import { AxiosError, type AxiosResponse } from 'axios'
import { useWallet } from '../useWallet'
import { walletApi } from '../../services/walletApi'

vi.mock('../../services/walletApi', () => ({
  walletApi: {
    withdraw: vi.fn(),
    getWallet: vi.fn(),
  },
}))

type WithdrawErrorPayload = {
  error?: {
    message?: string
    code?: string
    details?: Record<string, string[]>
  }
  errors?: Record<string, string[]>
  message?: string
}

function makeAxiosError(status: number, data?: WithdrawErrorPayload, code?: string): AxiosError {
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
    } as AxiosResponse,
  )
}

describe('useWallet.withdraw error handling', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('red-phase: surfaces business error message over validation detail', async () => {
    vi.mocked(walletApi.withdraw).mockRejectedValue(
      makeAxiosError(422, {
        error: {
          message: 'Solde insuffisant.',
          code: 'INSUFFICIENT_BALANCE',
        },
        errors: {
          amount: ['Le champ amount ne peut pas dépasser 0.'],
        },
        message: 'Les données fournies ne sont pas valides',
      }),
    )

    const { withdraw, withdrawError } = useWallet()
    const result = await withdraw({
      amount: 25000,
      payment_mode: 'mtn',
      phone_number: '97000000',
      phone_country: 'bj',
    })

    expect(result).toBe(false)
    expect(withdrawError.value).toBe('Solde insuffisant.')
  })

  it('regression: surfaces first validation detail on a pure validation 422', async () => {
    vi.mocked(walletApi.withdraw).mockRejectedValue(
      makeAxiosError(422, {
        error: {
          message: 'Les données fournies ne sont pas valides',
          code: 'VALIDATION_ERROR',
          details: {
            amount: ['Le champ amount doit être un nombre.'],
          },
        },
        errors: {
          amount: ['Le champ amount doit être un nombre.'],
        },
        message: 'Les données fournies ne sont pas valides',
      }),
    )

    const { withdraw, withdrawError } = useWallet()
    const result = await withdraw({
      amount: 0,
      payment_mode: 'mtn',
      phone_number: '97000000',
      phone_country: 'bj',
    })

    expect(result).toBe(false)
    expect(withdrawError.value).toBe('Le champ amount doit être un nombre.')
  })

  it('regression: falls back to the local message on a network error', async () => {
    vi.mocked(walletApi.withdraw).mockRejectedValue({
      isAxiosError: true,
      response: undefined,
      code: 'ERR_NETWORK',
    } as unknown as AxiosError)

    const { withdraw, withdrawError } = useWallet()
    const result = await withdraw({
      amount: 10000,
      payment_mode: 'mtn',
      phone_number: '97000000',
      phone_country: 'bj',
    })

    expect(result).toBe(false)
    expect(withdrawError.value).toBe('Retrait échoué. Veuillez réessayer.')
  })
})
