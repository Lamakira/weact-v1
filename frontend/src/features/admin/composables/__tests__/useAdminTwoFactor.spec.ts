import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAdminAuthStore } from '@/stores/adminAuth'

const mockGetStatus = vi.fn()
const mockEnable = vi.fn()
const mockConfirm = vi.fn()
const mockDisable = vi.fn()
const mockRegenerate = vi.fn()
vi.mock('../../services/adminTwoFactorApi', () => ({
  adminTwoFactorApi: {
    getStatus: (...args: unknown[]) => mockGetStatus(...args),
    enable: (...args: unknown[]) => mockEnable(...args),
    confirm: (...args: unknown[]) => mockConfirm(...args),
    disable: (...args: unknown[]) => mockDisable(...args),
    regenerateRecoveryCodes: (...args: unknown[]) => mockRegenerate(...args),
  },
}))

vi.mock('../../services/adminAuthApi', () => ({
  getApiErrorMessage: vi.fn(
    (e: { response?: { data?: { error?: { message?: string } } } }) =>
      e?.response?.data?.error?.message ?? 'Erreur',
  ),
}))

import { useAdminTwoFactor } from '../useAdminTwoFactor'

describe('useAdminTwoFactor', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    localStorage.clear()
    useAdminAuthStore().setAdmin({
      id: 'a1',
      name: 'Admin',
      email: 'admin@weact.bj',
      role: 'admin',
      two_factor_enabled: false,
    })
  })

  it('fetches the status', async () => {
    mockGetStatus.mockResolvedValue({ enabled: false, pending: false, recovery_codes_remaining: 0 })
    const { fetchStatus, status } = useAdminTwoFactor()

    const result = await fetchStatus()

    expect(result.success).toBe(true)
    expect(status.value?.enabled).toBe(false)
  })

  it('starts the setup and exposes secret + QR', async () => {
    mockEnable.mockResolvedValue({ secret: 'ABC', otpauth_uri: 'otpauth://totp/x', qr_svg: '<svg/>' })
    const { startSetup, setup } = useAdminTwoFactor()

    await startSetup('MyPassword1')

    expect(mockEnable).toHaveBeenCalledWith('MyPassword1')
    expect(setup.value?.secret).toBe('ABC')
  })

  it('keeps recovery codes in memory and flags the admin enrolled after confirmation', async () => {
    mockConfirm.mockResolvedValue({
      recovery_codes: ['aaaaa-bbbbb', 'ccccc-ddddd'],
      token: 'new-full-token',
    })
    const { confirmSetup, recoveryCodes, setup } = useAdminTwoFactor()

    const result = await confirmSetup('123456')

    expect(mockConfirm).toHaveBeenCalledWith('123456')
    expect(result.success).toBe(true)
    expect(recoveryCodes.value).toEqual(['aaaaa-bbbbb', 'ccccc-ddddd'])
    expect(useAdminAuthStore().token).toBe('new-full-token')
    expect(setup.value).toBeNull()
    expect(useAdminAuthStore().admin?.two_factor_enabled).toBe(true)
    // Recovery codes are never persisted
    expect(localStorage.getItem('admin_auth_user')).not.toContain('aaaaa-bbbbb')
  })

  it('returns the API message and does not flag enrolment on a wrong code', async () => {
    mockConfirm.mockRejectedValue({
      response: { data: { error: { message: 'Code de vérification incorrect' } } },
    })
    const { confirmSetup, recoveryCodes } = useAdminTwoFactor()

    const result = await confirmSetup('000000')

    expect(result).toEqual({ success: false, message: 'Code de vérification incorrect' })
    expect(recoveryCodes.value).toEqual([])
    expect(useAdminAuthStore().admin?.two_factor_enabled).toBe(false)
  })

  it('disables 2FA and flags the admin as not enrolled', async () => {
    useAdminAuthStore().setAdmin({
      id: 'a1',
      name: 'Admin',
      email: 'admin@weact.bj',
      role: 'admin',
      two_factor_enabled: true,
    })
    mockDisable.mockResolvedValue(undefined)
    const { disable } = useAdminTwoFactor()

    const result = await disable({ password: 'secret', code: '123456' })

    expect(mockDisable).toHaveBeenCalledWith({ password: 'secret', code: '123456' })
    expect(result.success).toBe(true)
    expect(useAdminAuthStore().admin?.two_factor_enabled).toBe(false)
  })

  it('regenerates recovery codes', async () => {
    mockRegenerate.mockResolvedValue(['new-code-1', 'new-code-2'])
    const { regenerateRecoveryCodes, recoveryCodes } = useAdminTwoFactor()

    await regenerateRecoveryCodes({ password: 'secret', code: '123456' })

    expect(recoveryCodes.value).toEqual(['new-code-1', 'new-code-2'])
  })
})
