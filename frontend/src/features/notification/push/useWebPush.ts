import { computed, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import {
  fetchVapidPublicKey,
  getExistingSubscription,
  getPushSupport,
  syncSubscriptionToServer,
  subscribeThisDevice,
  unsubscribeThisDevice,
} from './webPush'

/**
 * - checking : état pas encore déterminé
 * - unsupported : navigateur sans push (toggle masqué)
 * - ios-install-required : iPhone/iPad hors écran d'accueil (explication)
 * - unavailable : push désactivé côté serveur, clés VAPID absentes (toggle masqué)
 * - email-unverified : le serveur refuse l'abonnement tant que l'e-mail n'est pas vérifié
 * - denied : permission refusée dans le navigateur (explication)
 * - disabled / enabled : bascule disponible
 */
export type PushStatus =
  | 'checking'
  | 'unsupported'
  | 'ios-install-required'
  | 'unavailable'
  | 'email-unverified'
  | 'denied'
  | 'disabled'
  | 'enabled'

// État partagé : la bascule du panneau et l'invite du tableau de bord restent synchronisées.
const status = ref<PushStatus>('checking')
const isBusy = ref(false)

async function refresh(): Promise<void> {
  const support = getPushSupport()
  if (support === 'unsupported') {
    status.value = 'unsupported'
    return
  }
  if (support === 'ios-install-required') {
    status.value = 'ios-install-required'
    return
  }

  const key = await fetchVapidPublicKey()
  if (!key) {
    status.value = 'unavailable'
    return
  }

  if (!useAuthStore().isEmailVerified) {
    status.value = 'email-unverified'
    return
  }

  if (Notification.permission === 'denied') {
    status.value = 'denied'
    return
  }

  const subscription = Notification.permission === 'granted' ? await getExistingSubscription() : null
  // « Activé » seulement si le serveur a bien (ré)attaché cet appareil au compte courant.
  status.value = subscription && (await syncSubscriptionToServer(subscription)) ? 'enabled' : 'disabled'
}

async function enable(): Promise<'enabled' | 'denied' | 'dismissed' | 'unavailable' | 'error'> {
  if (isBusy.value) return 'error'
  isBusy.value = true
  try {
    const result = await subscribeThisDevice()
    await refresh()
    return result
  } catch (error) {
    console.warn('[Push] Enable failed', error)
    await refresh()
    return 'error'
  } finally {
    isBusy.value = false
  }
}

async function disable(): Promise<void> {
  if (isBusy.value) return
  isBusy.value = true
  try {
    await unsubscribeThisDevice()
  } finally {
    await refresh()
    isBusy.value = false
  }
}

export function useWebPush() {
  return {
    status,
    isBusy,
    isEnabled: computed(() => status.value === 'enabled'),
    refresh,
    enable,
    disable,
  }
}

/** Remise à zéro de l'état partagé (déconnexion, tests). */
export function resetWebPushState(): void {
  status.value = 'checking'
  isBusy.value = false
}
