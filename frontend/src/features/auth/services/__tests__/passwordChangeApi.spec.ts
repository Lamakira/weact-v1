import { describe, it, expect, vi, beforeEach } from 'vitest'
import { passwordChangeApi } from '../passwordChangeApi'

const h = vi.hoisted(() => ({ put: vi.fn() }))

vi.mock('@/services/apiClient', () => ({
  default: { put: h.put },
  getCsrfCookie: vi.fn().mockResolvedValue(undefined),
}))

describe('passwordChangeApi', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    h.put.mockResolvedValue({ data: { data: { password_changed: true }, message: 'ok' } })
  })

  it('changePassword sends the current password', async () => {
    await passwordChangeApi.changePassword('Old1', 'New1', 'New1')

    expect(h.put).toHaveBeenCalledWith('/password', {
      current_password: 'Old1',
      new_password: 'New1',
      new_password_confirmation: 'New1',
    })
  })

  it('setPassword sends the ticket and no current_password key at all', async () => {
    await passwordChangeApi.setPassword('New1', 'New1', 'ticket')

    const payload = h.put.mock.calls[0][1] as Record<string, unknown>
    expect(payload).toEqual({
      new_password: 'New1',
      new_password_confirmation: 'New1',
      reauth_token: 'ticket',
    })
    expect('current_password' in payload).toBe(false)
  })
})
