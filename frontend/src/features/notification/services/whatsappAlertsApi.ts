import apiClient from '@/services/apiClient'
import { useAuthStore } from '@/stores/auth'
import type { WhatsappAlerts } from '@/features/auth/types'

/**
 * Alertes WhatsApp — réglage de l'inscription et masquage de la carte d'invitation.
 * Après succès, le bloc `whatsapp_alerts` du store d'auth est mis à jour sur place
 * (pas de rechargement de l'utilisateur).
 */
function patchStore(whatsappAlerts: WhatsappAlerts): void {
  const authStore = useAuthStore()
  if (authStore.user) {
    authStore.setUser({ ...authStore.user, whatsapp_alerts: whatsappAlerts })
  }
}

export const whatsappAlertsApi = {
  /** Active ou désactive les alertes. 422 WHATSAPP_NUMBER_REQUIRED sans numéro valide. */
  async setWhatsappAlerts(enabled: boolean): Promise<WhatsappAlerts> {
    const response = await apiClient.put<{ data: WhatsappAlerts }>('/me/whatsapp-alerts', {
      enabled,
    })
    patchStore(response.data.data)
    return response.data.data
  },

  /** Masque définitivement la carte d'invitation du tableau de bord. */
  async dismissWhatsappInvite(): Promise<void> {
    await apiClient.post('/me/whatsapp-alerts/dismiss-invite')
    const authStore = useAuthStore()
    const current = authStore.user?.whatsapp_alerts
    if (current) patchStore({ ...current, invite_dismissed: true })
  },
}

/** Message d'erreur serveur (ex. 422 WHATSAPP_NUMBER_REQUIRED) ou repli générique. */
export function whatsappErrorMessage(err: unknown, fallback: string): string {
  const message = (err as { response?: { data?: { error?: { message?: unknown } } } })?.response
    ?.data?.error?.message
  return typeof message === 'string' && message ? message : fallback
}
