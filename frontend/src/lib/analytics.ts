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
      capture_pageview: 'history_change',
      capture_pageleave: true,
      person_profiles: 'identified_only',
      session_recording: { maskAllInputs: true },
      before_send: (event) => (isAdminPath(window.location.pathname) ? null : event),
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
  } catch {
    // La mesure ne doit jamais perturber l'application.
  }
}

export function captureAnalyticsEvent(name: string, props?: Record<string, unknown>): void {
  if (!posthog) return
  try {
    posthog.capture(name, props)
  } catch {
    // ignoré
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
