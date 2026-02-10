import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

// Mock adminsApi
const mockGetAdmins = vi.fn()
const mockCreateAdmin = vi.fn()
vi.mock('../../services/adminsApi', () => ({
  adminsApi: {
    getAdmins: (...args: unknown[]) => mockGetAdmins(...args),
    createAdmin: (...args: unknown[]) => mockCreateAdmin(...args),
  },
}))

// Mock error utilities
vi.mock('../../services/adminAuthApi', () => ({
  getApiErrorDetails: vi.fn((err: { response?: { data?: { error?: { details?: Record<string, string[]> } } } }) => {
    return err?.response?.data?.error?.details ?? {}
  }),
  getApiErrorMessage: vi.fn((err: { response?: { data?: { error?: { message?: string } } } }) => {
    return err?.response?.data?.error?.message ?? 'Error'
  }),
}))

import { useAdmins } from '../useAdmins'

describe('useAdmins - fetchAdmins', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    localStorage.clear()
  })

  it('fetches admins list successfully', async () => {
    const mockResponse = {
      data: [
        { id: 1, name: 'Admin 1', email: 'admin1@weact.bj', created_at: '2026-01-01T00:00:00.000Z' },
        { id: 2, name: 'Admin 2', email: 'admin2@weact.bj', created_at: '2026-01-02T00:00:00.000Z' },
      ],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 2 },
    }
    mockGetAdmins.mockResolvedValue(mockResponse)

    const { admins, pagination, fetchAdmins } = useAdmins()
    await fetchAdmins()

    expect(mockGetAdmins).toHaveBeenCalledWith(1)
    expect(admins.value).toHaveLength(2)
    expect(admins.value[0].email).toBe('admin1@weact.bj')
    expect(pagination.value?.total).toBe(2)
  })

  it('passes page parameter to API', async () => {
    mockGetAdmins.mockResolvedValue({
      data: [],
      meta: { current_page: 3, last_page: 5, per_page: 15, total: 65 },
    })

    const { fetchAdmins } = useAdmins()
    await fetchAdmins(3)

    expect(mockGetAdmins).toHaveBeenCalledWith(3)
  })

  it('sets loading state during fetch', async () => {
    let resolvePromise: (value: unknown) => void
    mockGetAdmins.mockReturnValue(
      new Promise((resolve) => {
        resolvePromise = resolve
      }),
    )

    const { isLoading, fetchAdmins } = useAdmins()
    const fetchPromise = fetchAdmins()

    expect(isLoading.value).toBe(true)

    resolvePromise!({
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
    })

    await fetchPromise

    expect(isLoading.value).toBe(false)
  })

  it('handles fetch error', async () => {
    mockGetAdmins.mockRejectedValue({
      response: {
        data: {
          error: { message: 'Unauthorized' },
        },
      },
    })

    const { admins, error, fetchAdmins } = useAdmins()
    await fetchAdmins()

    expect(error.value).toBe('Unauthorized')
    expect(admins.value).toHaveLength(0)
  })
})

describe('useAdmins - createAdmin', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    localStorage.clear()
  })

  it('creates admin successfully and returns API message', async () => {
    mockCreateAdmin.mockResolvedValue({
      data: { id: 2, name: 'New Admin', email: 'new@weact.bj', created_at: '2026-02-10T00:00:00.000Z' },
      message: 'Administrateur créé avec succès',
    })

    const { createAdmin } = useAdmins()
    const result = await createAdmin({
      name: 'New Admin',
      email: 'new@weact.bj',
      password: 'Password1!',
      password_confirmation: 'Password1!',
    })

    expect(result.success).toBe(true)
    expect(result.message).toBe('Administrateur créé avec succès')
    expect(mockCreateAdmin).toHaveBeenCalledWith({
      name: 'New Admin',
      email: 'new@weact.bj',
      password: 'Password1!',
      password_confirmation: 'Password1!',
    })
  })

  it('returns validation errors on failure', async () => {
    mockCreateAdmin.mockRejectedValue({
      response: {
        data: {
          error: {
            code: 'validation_error',
            message: 'Les données fournies ne sont pas valides',
            details: { email: ['Cet email est déjà utilisé.'] },
          },
        },
      },
    })

    const { createAdmin } = useAdmins()
    const result = await createAdmin({
      name: 'Admin',
      email: 'existing@weact.bj',
      password: 'Password1!',
      password_confirmation: 'Password1!',
    })

    expect(result.success).toBe(false)
    expect(result.message).toBe('Les données fournies ne sont pas valides')
    expect(result.errors?.email?.[0]).toBe('Cet email est déjà utilisé.')
  })

  it('returns general error message on unexpected failure', async () => {
    mockCreateAdmin.mockRejectedValue({
      response: {
        data: {
          error: { message: 'Internal server error' },
        },
      },
    })

    const { createAdmin } = useAdmins()
    const result = await createAdmin({
      name: 'Admin',
      email: 'test@weact.bj',
      password: 'Password1!',
      password_confirmation: 'Password1!',
    })

    expect(result.success).toBe(false)
    expect(result.message).toBe('Internal server error')
  })

  it('sets loading state during create', async () => {
    let resolvePromise: (value: unknown) => void
    mockCreateAdmin.mockReturnValue(
      new Promise((resolve) => {
        resolvePromise = resolve
      }),
    )

    const { isLoading, createAdmin } = useAdmins()
    const createPromise = createAdmin({
      name: 'Admin',
      email: 'test@weact.bj',
      password: 'Password1!',
      password_confirmation: 'Password1!',
    })

    expect(isLoading.value).toBe(true)

    resolvePromise!({
      data: { id: 2, name: 'Admin', email: 'test@weact.bj', created_at: '2026-02-10T00:00:00.000Z' },
      message: 'Administrateur créé avec succès',
    })

    await createPromise

    expect(isLoading.value).toBe(false)
  })
})
