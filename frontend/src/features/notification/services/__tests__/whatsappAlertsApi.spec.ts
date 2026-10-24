import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

const h = vi.hoisted(() => ({ put: vi.fn(), post: vi.fn() }))
vi.mock('@/services/apiClient', () => ({
  default: { put: h.put, post: h.post },
  getAuthToken: () => 'token',
  setAuthToken: vi.fn(),
  removeAuthToken: vi.fn(),
}))

import { useAuthStore } from '@/stores/auth'
import { whatsappAlertsApi, whatsappErrorMessage } from '../whatsappAlertsApi'
import type { User } from '@/features/auth/types'

const user = {
  id: 1,
  email: 'a@b.c',
  userable_type: 'Face',
  whatsapp_alerts: { available: true, enabled: false, has_valid_number: true, invite_dismissed: false },
} as unknown as User

describe('whatsappAlertsApi', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    localStorage.clear()
    setActivePinia(createPinia())
    useAuthStore().setUser(user)
  })

  it('setWhatsappAlerts : PUT /me/whatsapp-alerts puis met à jour le store', async () => {
    const payload = { available: true, enabled: true, has_valid_number: true, invite_dismissed: false }
    h.put.mockResolvedValue({ data: { data: payload } })
    await whatsappAlertsApi.setWhatsappAlerts(true)
    expect(h.put).toHaveBeenCalledWith('/me/whatsapp-alerts', { enabled: true })
    expect(useAuthStore().user?.whatsapp_alerts).toEqual(payload)
  })

  it('setWhatsappAlerts : laisse le store intact en cas d\'erreur', async () => {
    h.put.mockRejectedValue(new Error('422'))
    await expect(whatsappAlertsApi.setWhatsappAlerts(true)).rejects.toThrow()
    expect(useAuthStore().user?.whatsapp_alerts?.enabled).toBe(false)
  })

  it('dismissWhatsappInvite : POST dismiss-invite puis marque invite_dismissed dans le store', async () => {
    h.post.mockResolvedValue({ status: 204 })
    await whatsappAlertsApi.dismissWhatsappInvite()
    expect(h.post).toHaveBeenCalledWith('/me/whatsapp-alerts/dismiss-invite')
    expect(useAuthStore().user?.whatsapp_alerts?.invite_dismissed).toBe(true)
  })

  it('whatsappErrorMessage : message serveur sinon repli', () => {
    expect(
      whatsappErrorMessage({ response: { data: { error: { message: 'Msg serveur' } } } }, 'repli'),
    ).toBe('Msg serveur')
    expect(whatsappErrorMessage(new Error('x'), 'repli')).toBe('repli')
  })
})
