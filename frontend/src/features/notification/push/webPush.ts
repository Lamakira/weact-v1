import apiClient from '@/services/apiClient'

/**
 * Web push, côté navigateur : détection du support, service worker, abonnement.
 * Aucune permission n'est jamais demandée ici hors d'un appel explicite à
 * subscribeThisDevice() (déclenché par un clic utilisateur).
 */

export type PushSupport = 'unsupported' | 'ios-install-required' | 'supported'

export type SubscribeResult = 'enabled' | 'denied' | 'dismissed' | 'unavailable'

const SW_URL = '/sw.js'

export function isIosDevice(): boolean {
  if (typeof navigator === 'undefined') return false
  const ua = navigator.userAgent ?? ''
  if (/iPad|iPhone|iPod/.test(ua)) return true
  // iPadOS 13+ se présente comme un Mac tactile
  return navigator.platform === 'MacIntel' && (navigator.maxTouchPoints ?? 0) > 1
}

export function isStandalone(): boolean {
  if (typeof window === 'undefined') return false
  const iosStandalone = (navigator as Navigator & { standalone?: boolean }).standalone === true
  const displayMode =
    typeof window.matchMedia === 'function' && window.matchMedia('(display-mode: standalone)').matches
  return iosStandalone || displayMode
}

/**
 * Sur iPhone, le push n'existe que pour une web app ajoutée à l'écran d'accueil
 * (iOS 16.4+) : hors de ce cas Safari n'expose même pas PushManager.
 */
export function getPushSupport(): PushSupport {
  if (typeof window === 'undefined' || typeof navigator === 'undefined') return 'unsupported'
  if (isIosDevice() && !isStandalone()) return 'ios-install-required'

  const supported =
    'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window
  return supported ? 'supported' : 'unsupported'
}

export function urlBase64ToUint8Array(base64: string): Uint8Array<ArrayBuffer> {
  const padded = base64 + '='.repeat((4 - (base64.length % 4)) % 4)
  const raw = atob(padded.replace(/-/g, '+').replace(/_/g, '/'))
  const bytes = new Uint8Array(new ArrayBuffer(raw.length))
  for (let i = 0; i < raw.length; i += 1) bytes[i] = raw.charCodeAt(i)
  return bytes
}

let publicKeyPromise: Promise<string | null> | null = null

/** Clé VAPID publique ; null si le push est désactivé côté serveur (clés absentes). */
export function fetchVapidPublicKey(): Promise<string | null> {
  if (!publicKeyPromise) {
    publicKeyPromise = apiClient
      .get<{ data: { public_key: string | null } }>('/push/public-key')
      .then((response) => response.data.data.public_key ?? null)
      .catch(() => {
        // Erreur transitoire : on retentera au prochain appel.
        publicKeyPromise = null
        return null
      })
  }
  return publicKeyPromise
}

export function resetVapidPublicKeyCache(): void {
  publicKeyPromise = null
}

/** Enregistre le service worker (push uniquement) ; sans effet si le push n'est pas supporté. */
export async function registerPushServiceWorker(): Promise<ServiceWorkerRegistration | null> {
  if (getPushSupport() !== 'supported') return null
  try {
    return await navigator.serviceWorker.register(SW_URL, { scope: '/' })
  } catch (error) {
    console.warn('[Push] Service worker registration failed', error)
    return null
  }
}

async function getRegistration(): Promise<ServiceWorkerRegistration | null> {
  if (!('serviceWorker' in navigator)) return null
  return (await navigator.serviceWorker.getRegistration('/')) ?? null
}

export async function getExistingSubscription(): Promise<PushSubscription | null> {
  const registration = await getRegistration()
  return registration ? await registration.pushManager.getSubscription() : null
}

function pickContentEncoding(): 'aes128gcm' | 'aesgcm' | undefined {
  const supported: readonly string[] =
    (typeof PushManager !== 'undefined' &&
      (PushManager as unknown as { supportedContentEncodings?: string[] }).supportedContentEncodings) ||
    []
  if (supported.includes('aes128gcm')) return 'aes128gcm'
  if (supported.includes('aesgcm')) return 'aesgcm'
  return undefined
}

/**
 * Demande la permission (si besoin), abonne l'appareil et l'enregistre côté serveur.
 * À appeler uniquement depuis un geste utilisateur.
 */
export async function subscribeThisDevice(): Promise<SubscribeResult> {
  if (getPushSupport() !== 'supported') return 'unavailable'

  const key = await fetchVapidPublicKey()
  if (!key) return 'unavailable'

  if (Notification.permission === 'denied') return 'denied'
  if (Notification.permission === 'default') {
    const permission = await Notification.requestPermission()
    if (permission === 'denied') return 'denied'
    if (permission !== 'granted') return 'dismissed'
  }

  const registration = (await registerPushServiceWorker()) ?? (await navigator.serviceWorker.ready)
  // Le worker doit être actif avant subscribe() (premier enregistrement)
  await navigator.serviceWorker.ready

  const subscription =
    (await registration.pushManager.getSubscription()) ??
    (await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(key),
    }))

  try {
    await postSubscription(subscription)
  } catch (error) {
    // Pas d'abonnement orphelin côté navigateur si le serveur ne l'a pas retenu
    await subscription.unsubscribe().catch(() => undefined)
    throw error
  }

  return 'enabled'
}

async function postSubscription(subscription: PushSubscription): Promise<void> {
  const json = subscription.toJSON()
  await apiClient.post('/me/push-subscriptions', {
    endpoint: json.endpoint,
    keys: json.keys,
    content_encoding: pickContentEncoding(),
  })
}

/**
 * Ré-attache l'abonnement du navigateur au compte COURANT (upsert serveur : un
 * endpoint d'un autre compte est repris). Sans cela, l'état « activé » de la
 * bascule viendrait du navigateur seul et pourrait mentir pour un autre compte.
 * Retourne false si le serveur refuse (e-mail non vérifié, hôte non pris en charge…).
 */
export async function syncSubscriptionToServer(subscription: PushSubscription): Promise<boolean> {
  try {
    await postSubscription(subscription)
    return true
  } catch {
    return false
  }
}

/** Démarrage d'une session : si la permission est accordée et un abonnement existe, le ré-attache. */
export async function resyncExistingSubscription(): Promise<void> {
  if (getPushSupport() !== 'supported' || Notification.permission !== 'granted') return
  const subscription = await getExistingSubscription()
  if (subscription) await syncSubscriptionToServer(subscription)
}

/**
 * Coupe l'abonnement du NAVIGATEUR uniquement (aucun appel serveur, aucun token
 * requis) : utilisé quand la session s'effondre (401, suppression de compte) pour
 * qu'un appareil partagé ne reste pas abonné au compte précédent. Ne lève jamais.
 */
export async function unsubscribeBrowserOnly(): Promise<void> {
  try {
    const subscription = await getExistingSubscription()
    await subscription?.unsubscribe()
  } catch {
    // Meilleur effort.
  }
}

/**
 * Désabonne CET appareil : suppression côté serveur (par endpoint) puis côté
 * navigateur. Ne lève jamais : appelé aussi à la déconnexion, où il ne doit
 * pas la bloquer.
 */
export async function unsubscribeThisDevice(): Promise<void> {
  try {
    const subscription = await getExistingSubscription()
    if (!subscription) return

    try {
      await apiClient.delete('/me/push-subscriptions', { data: { endpoint: subscription.endpoint } })
    } catch (error) {
      console.warn('[Push] Server-side unsubscribe failed', error)
    }

    await subscription.unsubscribe()
  } catch (error) {
    console.warn('[Push] Unsubscribe failed', error)
  }
}
