import axios from 'axios'
import { watch } from 'vue'
import type { Pinia } from 'pinia'
import type { Router } from 'vue-router'
import type { PostHog } from 'posthog-js'
import type { useAuthStore } from '@/stores/auth'
import { getStoredAnalyticsChoice } from '@/composables/useCookieConsent'

/**
 * Mesure d'usage (PostHog). Sans clé (`VITE_POSTHOG_KEY`) ou en mode test, tout
 * est no-op et `posthog-js` n'est jamais importé.
 *
 * Confidentialité : jamais d'e-mail, de nom ni de téléphone ; jamais de corps,
 * de paramètres ni de réponse d'API ; espace administrateur exclu.
 */

export type AnalyticsChoice = 'granted' | 'denied' | 'pending'

const POSTHOG_KEY = import.meta.env.VITE_POSTHOG_KEY as string | undefined
const POSTHOG_HOST = (import.meta.env.VITE_POSTHOG_HOST as string | undefined) || 'https://eu.i.posthog.com'
const DEDUP_WINDOW_MS = 60_000
const UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i

type AnalyticsUser = ReturnType<typeof useAuthStore>['user']

let posthog: PostHog | null = null
let routerRef: Router | null = null
let authStore: ReturnType<typeof useAuthStore> | null = null
let currentChoice: AnalyticsChoice = 'pending'
let choiceKnown = false

const recentApiErrors = new Map<string, number>()

function isEnabled(): boolean {
  return Boolean(POSTHOG_KEY) && import.meta.env.MODE !== 'test'
}

function isAdminPath(path: string): boolean {
  return path === '/admin' || path.startsWith('/admin/')
}

function identifyUser(user: AnalyticsUser | null | undefined): void {
  if (!posthog || !user || currentChoice !== 'granted') return
  const props: Record<string, string> = {
    role: user.userable_type === 'Producer' ? 'producer' : 'face',
  }
  if (user.userable_type === 'Producer') {
    const type = (user.userable as { type?: string } | null | undefined)?.type
    if (type) props.producer_type = type
  }
  posthog.identify(String(user.id), props)
}

function syncSessionRecording(): void {
  if (!posthog || !routerRef) return
  if (isAdminPath(routerRef.currentRoute.value.path)) {
    posthog.stopSessionRecording()
  } else if (currentChoice === 'granted') {
    posthog.startSessionRecording()
  }
}

function applyChoice(choice: AnalyticsChoice): void {
  if (!posthog) return
  if (choice === 'granted') {
    if (posthog.get_explicit_consent_status() !== 'granted') posthog.opt_in_capturing()
    identifyUser(authStore?.user)
  } else if (choice === 'denied') {
    if (posthog.get_explicit_consent_status() !== 'denied') posthog.opt_out_capturing()
  }
  syncSessionRecording()
}

export function syncAnalyticsConsent(choice: AnalyticsChoice): void {
  currentChoice = choice
  choiceKnown = true
  applyChoice(choice)
}

export async function initAnalytics(router: Router, pinia: Pinia): Promise<void> {
  if (!isEnabled() || posthog) return
  try {
    const { default: ph } = await import('posthog-js')
    ph.init(POSTHOG_KEY as string, {
      api_host: POSTHOG_HOST,
      defaults: '2026-08-30',
      cookieless_mode: 'on_reject',
      // Sans choix dans le bandeau, le visiteur est traité comme un refus : comptage anonyme
      // sans cookie ni stockage, au lieu de ne rien mesurer. « Accepter » bascule en mesure complète.
      opt_out_capturing_by_default: true,
      capture_pageview: 'history_change',
      capture_pageleave: true,
      person_profiles: 'identified_only',
      session_recording: { maskAllInputs: true },
      before_send: (event) => (event && shouldSend(event.event) ? event : null),
    })
    posthog = ph
    routerRef = router
    // Import dynamique : évite le cycle apiClient -> analytics -> store auth -> apiClient.
    const { useAuthStore } = await import('@/stores/auth')
    authStore = useAuthStore(pinia)
    const store = authStore

    // Un choix passé à syncAnalyticsConsent avant le chargement prime sur le stockage.
    if (!choiceKnown) {
      currentChoice = getStoredAnalyticsChoice()
      choiceKnown = true
    }
    applyChoice(currentChoice)

    watch(
      () => router.currentRoute.value.path,
      () => syncSessionRecording(),
    )

    watch(
      () => store.user?.id,
      (id) => {
        if (id == null) posthog?.reset()
        else identifyUser(store.user)
      },
    )

    flushPendingEvents()
  } catch {
    // La mesure ne doit jamais perturber l'application.
    initFailed = true
    pendingEvents.length = 0
  }
}

/**
 * Événements émis avant le chargement de posthog-js (import dynamique) : une page ouverte depuis un
 * lien (vérification d'e-mail, retour de FedaPay) déclenche son événement avant la fin du chargement.
 * Ils sont gardés en mémoire puis envoyés à l'initialisation, après le consentement.
 */
const MAX_PENDING_EVENTS = 20
let initFailed = false
const pendingEvents: Array<{ name: AnalyticsEventName; props: object; sendInstantly: boolean }> = []

function flushPendingEvents(): void {
  const queued = pendingEvents.splice(0)
  for (const event of queued) sendEvent(event.name, event.props, event.sendInstantly)
}

/**
 * Événements des cinq parcours (cadrage du 14 novembre 2026). Noms en français, objet puis action.
 * Jamais de montant, de nom, d'e-mail ni de téléphone. Envoyés aussi sans consentement, en mode
 * anonyme sans cookie (décision PO du 17 novembre 2026, politique de confidentialité section 11).
 */
export type AnalyticsAccount = 'face' | 'producteur'
export type AnalyticsSignupMethod = 'email' | 'google'
export type AnalyticsPaymentType =
  | 'abonnement'
  | 'booking'
  | 'commission_booking'
  | 'commission_mission'
  | 'sequestre_candidature'
  | 'selection_mission'
export type AnalyticsTier = 'starter' | 'pro' | 'elite'

export interface AnalyticsPaymentProps {
  type: AnalyticsPaymentType
  /** Abonnement seulement : palier payé. */
  palier?: AnalyticsTier
}

export interface AnalyticsEvents {
  inscription_commencee: { compte: AnalyticsAccount; methode: AnalyticsSignupMethod }
  inscription_soumise: { compte: AnalyticsAccount; methode: 'email' }
  inscription_reussie: { compte: AnalyticsAccount; methode: AnalyticsSignupMethod }
  email_verifie: Record<string, never>
  booking_formulaire_ouvert: Record<string, never>
  booking_demande_envoyee: { type: 'cash' | 'ugc'; derniere_minute: boolean }
  booking_acceptee: { type: 'cash' | 'ugc' }
  mission_publication_commencee: Record<string, never>
  mission_publiee: { type: 'classique' | 'ugc'; paiement_requis: boolean }
  candidature_envoyee: Record<string, never>
  candidature_acceptee: { type: 'produit_seul' }
  palier_choisi: { palier: AnalyticsTier }
  paiement_lance: AnalyticsPaymentProps
  paiement_confirme: AnalyticsPaymentProps
}

export type AnalyticsEventName = keyof AnalyticsEvents

// Liste exhaustive (le compilateur exige chaque événement du catalogue).
const FUNNEL_EVENTS: Record<AnalyticsEventName, true> = {
  inscription_commencee: true,
  inscription_soumise: true,
  inscription_reussie: true,
  email_verifie: true,
  booking_formulaire_ouvert: true,
  booking_demande_envoyee: true,
  booking_acceptee: true,
  mission_publication_commencee: true,
  mission_publiee: true,
  candidature_envoyee: true,
  candidature_acceptee: true,
  palier_choisi: true,
  paiement_lance: true,
  paiement_confirme: true,
}

/**
 * Sans consentement (choix en attente ou refus), seuls partent les visites, les étapes clés et les
 * erreurs techniques, comme l'annonce la politique de confidentialité. Clics automatiques, cartes de
 * chaleur, mesures de performance, etc. sont jetés. L'espace administrateur n'envoie rien.
 */
const ANONYMOUS_ALLOWED = new Set<string>(['$pageview', '$pageleave', 'erreur_api', ...Object.keys(FUNNEL_EVENTS)])

function shouldSend(eventName: string): boolean {
  if (isAdminPath(window.location.pathname)) return false
  return currentChoice === 'granted' || ANONYMOUS_ALLOWED.has(eventName)
}

function currentRole(): 'face' | 'producteur' | 'visiteur' {
  const type = authStore?.user?.userable_type
  if (type === 'Face') return 'face'
  if (type === 'Producer') return 'producteur'
  return 'visiteur'
}

/**
 * Envoie un événement de parcours, avec le rôle de la personne connectée (`role`).
 * `sendInstantly` : à utiliser juste avant de quitter la page (départ vers FedaPay), pour ne pas
 * perdre l'événement resté en file d'attente.
 */
export function captureAnalyticsEvent<K extends AnalyticsEventName>(
  name: K,
  props: AnalyticsEvents[K],
  options: { sendInstantly?: boolean } = {},
): void {
  const sendInstantly = options.sendInstantly === true
  if (!posthog) {
    if (isEnabled() && !initFailed && pendingEvents.length < MAX_PENDING_EVENTS) {
      pendingEvents.push({ name, props: { ...props }, sendInstantly })
    }
    return
  }
  sendEvent(name, props, sendInstantly)
}

function sendEvent(name: AnalyticsEventName, props: object, sendInstantly: boolean): void {
  if (!posthog) return
  try {
    // Le rôle est lu à l'envoi : avant l'initialisation, le store n'est pas encore relié.
    posthog.capture(name, { ...props, role: currentRole() }, sendInstantly ? { send_instantly: true } : undefined)
  } catch {
    // La mesure ne doit jamais perturber l'application.
  }
}

function normalizeEndpoint(url: string | undefined): string {
  if (!url) return ''
  let path: string
  try {
    path = new URL(url, 'http://local.invalid').pathname
  } catch {
    path = url.split('?')[0] ?? ''
  }
  return path
    .split('/')
    .map((seg) => (/^\d+$/.test(seg) || UUID_RE.test(seg) ? ':id' : seg))
    .join('/')
}

interface ApiErrorLike {
  code?: string
  config?: { url?: string; method?: string }
  response?: { status?: number; data?: { error?: { code?: unknown } } }
}

export function captureApiError(error: unknown): void {
  if (!posthog) return
  try {
    if (axios.isCancel(error)) return
    const err = (error ?? {}) as ApiErrorLike
    if (err.code === 'ERR_CANCELED') return

    const status = err.response?.status ?? 0
    const apiCode = err.response?.data?.error?.code
    let code: string | null = null
    if (typeof apiCode === 'string' && apiCode) code = apiCode
    else if (err.code === 'ECONNABORTED') code = 'TIMEOUT'
    else if (!err.response || err.code === 'ERR_NETWORK') code = 'NETWORK_ERROR'

    const method = (err.config?.method ?? 'get').toUpperCase()
    const endpoint = normalizeEndpoint(err.config?.url)

    const key = `${method} ${endpoint} ${status}`
    const now = Date.now()
    const last = recentApiErrors.get(key)
    if (last !== undefined && now - last < DEDUP_WINDOW_MS) return
    recentApiErrors.set(key, now)

    posthog.capture('erreur_api', {
      status,
      code,
      method,
      endpoint,
      route_name: (routerRef?.currentRoute.value.name as string | undefined) ?? null,
    })
  } catch {
    // ignoré
  }
}
