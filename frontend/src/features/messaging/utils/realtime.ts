/**
 * Plomberie temps réel partagée de la messagerie (Echo chargé à la demande).
 *
 * Echo (pusher-js + laravel-echo) n'est JAMAIS importé statiquement : il reste hors du
 * chunk d'entrée et n'est chargé que pour un utilisateur connecté qui ouvre la messagerie.
 */
import { onBeforeUnmount } from 'vue'
import { getAuthToken } from '@/services/apiClient'
import { getXsrfTokenFromCookie } from '@/utils/csrf'

export type EchoInstance = (typeof import('@/plugins/echo'))['echo']

export interface EchoChannel {
  listen: (event: string, callback: (payload: never) => void) => EchoChannel
  stopListening: (event: string, callback?: (payload: never) => void) => EchoChannel
  error: (callback: () => void) => EchoChannel
  subscribed?: (callback: () => void) => EchoChannel
}

export const THREAD_POLL_INTERVAL_MS = 15_000
export const LIST_POLL_INTERVAL_MS = 30_000

// Un seul import() en vol : les chargements concurrents (fil + liste) partagent la promesse
let echoPromise: Promise<EchoInstance> | null = null

/** Charge Echo et rafraîchit ses en-têtes d'authentification (jeton courant). */
export async function loadEcho(): Promise<EchoInstance> {
  if (!echoPromise) {
    echoPromise = import('@/plugins/echo').then((module) => module.echo)
    // Échec (chunk périmé, réseau coupé) : on retentera au prochain appel
    echoPromise.catch(() => {
      echoPromise = null
    })
  }
  const echo = await echoPromise
  const token = getAuthToken()
  const xsrfToken = getXsrfTokenFromCookie()
  echo.connector.options.auth = {
    headers: {
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
      Accept: 'application/json',
    },
  }
  return echo
}

/**
 * En-tête X-Socket-ID : permet au serveur d'exclure cette connexion de la diffusion
 * (`toOthers()`), l'expéditeur ne reçoit jamais son propre message en retour.
 */
export async function getSocketIdHeaders(): Promise<Record<string, string> | undefined> {
  try {
    const { echo } = await import('@/plugins/echo')
    const socketId = echo.socketId()
    return socketId ? { 'X-Socket-ID': socketId } : undefined
  } catch {
    return undefined
  }
}

/**
 * Polling de repli : ne tire que lorsque l'onglet est visible et sans chevauchement.
 */
export function useVisiblePolling(task: () => Promise<unknown> | unknown, intervalMs: number) {
  let timer: ReturnType<typeof setInterval> | null = null
  let inFlight = false

  function start(): void {
    if (timer !== null) return
    timer = setInterval(async () => {
      if (typeof document !== 'undefined' && document.visibilityState === 'hidden') return
      if (inFlight) return
      inFlight = true
      try {
        await task()
      } catch {
        // Le repli ne doit jamais faire échouer l'interface
      } finally {
        inFlight = false
      }
    }, intervalMs)
  }

  function stop(): void {
    if (timer === null) return
    clearInterval(timer)
    timer = null
  }

  onBeforeUnmount(stop)

  return { start, stop, isActive: () => timer !== null }
}
