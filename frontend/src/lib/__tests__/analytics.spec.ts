import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { nextTick, reactive, ref } from 'vue'

const phMock = {
  init: vi.fn(),
  opt_in_capturing: vi.fn(),
  opt_out_capturing: vi.fn(),
  get_explicit_consent_status: vi.fn(() => 'pending'),
  identify: vi.fn(),
  reset: vi.fn(),
  capture: vi.fn(),
  startSessionRecording: vi.fn(),
  stopSessionRecording: vi.fn(),
}
const posthogLoaded = vi.fn()
const storedChoice = vi.fn(() => 'pending')
let initialUser: unknown = null
let authStore = reactive<{ user: unknown }>({ user: null })

type AnalyticsModule = typeof import('../analytics')

async function load(opts: { key?: string; mode?: string } = {}): Promise<AnalyticsModule> {
  vi.resetModules()
  // Nouveau store par chargement : les watchers des tests précédents ne doivent pas réagir.
  authStore = reactive<{ user: unknown }>({ user: initialUser })
  vi.stubEnv('MODE', opts.mode ?? 'production')
  vi.stubEnv('VITE_POSTHOG_KEY', opts.key ?? 'phc_test')
  vi.doMock('posthog-js', () => {
    posthogLoaded()
    return { default: phMock }
  })
  vi.doMock('@/composables/useCookieConsent', () => ({
    getStoredAnalyticsChoice: () => storedChoice(),
  }))
  vi.doMock('@/stores/auth', () => ({ useAuthStore: () => authStore }))
  return await import('../analytics')
}

function fakeRouter(path = '/') {
  const currentRoute = ref<{ path: string; name?: string }>({ path, name: 'home' })
  return { currentRoute } as unknown as import('vue-router').Router & {
    currentRoute: typeof currentRoute
  }
}

const pinia = {} as import('pinia').Pinia

beforeEach(() => {
  Object.values(phMock).forEach((fn) => fn.mockClear())
  phMock.get_explicit_consent_status.mockReturnValue('pending')
  posthogLoaded.mockClear()
  storedChoice.mockReturnValue('pending')
  initialUser = null
})

afterEach(() => {
  vi.unstubAllEnvs()
  vi.useRealTimers()
})

describe('initAnalytics : désactivation', () => {
  it('sans clé : aucun import de posthog-js', async () => {
    const a = await load({ key: '' })
    await a.initAnalytics(fakeRouter(), pinia)
    expect(posthogLoaded).not.toHaveBeenCalled()
    expect(phMock.init).not.toHaveBeenCalled()
  })

  it('en mode test : aucun import de posthog-js', async () => {
    const a = await load({ mode: 'test' })
    await a.initAnalytics(fakeRouter(), pinia)
    expect(posthogLoaded).not.toHaveBeenCalled()
  })
})

describe('initAnalytics : options', () => {
  it('initialise PostHog avec les options attendues', async () => {
    const a = await load()
    await a.initAnalytics(fakeRouter(), pinia)

    expect(phMock.init).toHaveBeenCalledTimes(1)
    const [key, config] = phMock.init.mock.calls[0]
    expect(key).toBe('phc_test')
    expect(config).toMatchObject({
      api_host: 'https://eu.i.posthog.com',
      cookieless_mode: 'on_reject',
      capture_pageview: 'history_change',
      capture_pageleave: true,
      person_profiles: 'identified_only',
      session_recording: { maskAllInputs: true },
    })
  })

  it('before_send jette les événements de l\'espace admin', async () => {
    const a = await load()
    await a.initAnalytics(fakeRouter(), pinia)
    const { before_send } = phMock.init.mock.calls[0][1]
    const event = { event: 'x', properties: {} }

    window.history.pushState({}, '', '/admin/users')
    expect(before_send(event)).toBeNull()
    window.history.pushState({}, '', '/faces')
    expect(before_send(event)).toBe(event)
  })
})

describe('consentement', () => {
  it('granted : opt_in seulement si pas déjà granted', async () => {
    const a = await load()
    await a.initAnalytics(fakeRouter(), pinia)

    a.syncAnalyticsConsent('granted')
    expect(phMock.opt_in_capturing).toHaveBeenCalledTimes(1)

    phMock.get_explicit_consent_status.mockReturnValue('granted')
    a.syncAnalyticsConsent('granted')
    expect(phMock.opt_in_capturing).toHaveBeenCalledTimes(1)
  })

  it('denied : opt_out seulement si pas déjà denied ; pending : rien', async () => {
    const a = await load()
    await a.initAnalytics(fakeRouter(), pinia)

    a.syncAnalyticsConsent('pending')
    expect(phMock.opt_out_capturing).not.toHaveBeenCalled()
    expect(phMock.opt_in_capturing).not.toHaveBeenCalled()

    a.syncAnalyticsConsent('denied')
    expect(phMock.opt_out_capturing).toHaveBeenCalledTimes(1)

    phMock.get_explicit_consent_status.mockReturnValue('denied')
    a.syncAnalyticsConsent('denied')
    expect(phMock.opt_out_capturing).toHaveBeenCalledTimes(1)
  })

  it('choix mémorisé avant le chargement, appliqué après l\'init', async () => {
    const a = await load()
    a.syncAnalyticsConsent('granted')
    expect(phMock.opt_in_capturing).not.toHaveBeenCalled()

    await a.initAnalytics(fakeRouter(), pinia)
    expect(phMock.opt_in_capturing).toHaveBeenCalledTimes(1)
  })

  it('applique le consentement enregistré au chargement', async () => {
    storedChoice.mockReturnValue('denied')
    const a = await load()
    await a.initAnalytics(fakeRouter(), pinia)
    expect(phMock.opt_out_capturing).toHaveBeenCalledTimes(1)
  })
})

describe('identification', () => {
  const face = { id: 42, userable_type: 'Face', email: 'secret@example.com', name: 'Secret' }
  const producer = { id: 7, userable_type: 'Producer', userable: { type: 'agency' }, email: 'p@example.com' }

  it('identify avec { role } seulement si consentement accordé, sans e-mail', async () => {
    storedChoice.mockReturnValue('pending')
    initialUser = face
    const a = await load()
    await a.initAnalytics(fakeRouter(), pinia)
    expect(phMock.identify).not.toHaveBeenCalled()

    a.syncAnalyticsConsent('granted')
    expect(phMock.identify).toHaveBeenCalledWith('42', { role: 'face' })
    expect(JSON.stringify(phMock.identify.mock.calls)).not.toContain('example.com')
  })

  it('identifie à la connexion et ajoute producer_type pour un Producteur', async () => {
    storedChoice.mockReturnValue('granted')
    const a = await load()
    await a.initAnalytics(fakeRouter(), pinia)
    expect(phMock.identify).not.toHaveBeenCalled()

    authStore.user = producer
    await nextTick()
    expect(phMock.identify).toHaveBeenCalledWith('7', { role: 'producer', producer_type: 'agency' })
  })

  it('pas d\'identify à la connexion si consentement refusé', async () => {
    storedChoice.mockReturnValue('denied')
    const a = await load()
    await a.initAnalytics(fakeRouter(), pinia)
    authStore.user = face
    await nextTick()
    expect(phMock.identify).not.toHaveBeenCalled()
  })

  it('reset quand l\'utilisateur disparaît', async () => {
    storedChoice.mockReturnValue('granted')
    initialUser = face
    const a = await load()
    await a.initAnalytics(fakeRouter(), pinia)
    authStore.user = null
    await nextTick()
    expect(phMock.reset).toHaveBeenCalledTimes(1)
  })
})

describe('enregistrement de session et /admin', () => {
  it('arrête l\'enregistrement sur /admin et le reprend ailleurs si consenti', async () => {
    storedChoice.mockReturnValue('granted')
    const router = fakeRouter('/faces')
    const a = await load()
    await a.initAnalytics(router, pinia)

    router.currentRoute.value = { path: '/admin/users' }
    await nextTick()
    expect(phMock.stopSessionRecording).toHaveBeenCalled()

    phMock.startSessionRecording.mockClear()
    router.currentRoute.value = { path: '/faces' }
    await nextTick()
    expect(phMock.startSessionRecording).toHaveBeenCalled()
  })
})

describe('captureApiError', () => {
  async function ready() {
    const a = await load()
    await a.initAnalytics(fakeRouter(), pinia)
    return a
  }

  it('normalise l\'endpoint (ids, UUID, query string)', async () => {
    const a = await ready()
    a.captureApiError({
      config: { url: '/bookings/123/accept?x=1', method: 'post' },
      response: { status: 422, data: { error: { code: 'VALIDATION_ERROR' } } },
    })
    a.captureApiError({
      config: { url: '/files/3f2504e0-4f89-41d3-9a0c-0305e82c3301/download', method: 'get' },
      response: { status: 500, data: {} },
    })

    expect(phMock.capture).toHaveBeenNthCalledWith(1, 'erreur_api', {
      status: 422,
      code: 'VALIDATION_ERROR',
      method: 'POST',
      endpoint: '/bookings/:id/accept',
      route_name: 'home',
    })
    expect(phMock.capture.mock.calls[1][1]).toMatchObject({
      endpoint: '/files/:id/download',
      code: null,
      status: 500,
    })
  })

  it('NETWORK_ERROR sans réponse, TIMEOUT sur ECONNABORTED', async () => {
    const a = await ready()
    a.captureApiError({ code: 'ERR_NETWORK', config: { url: '/a', method: 'get' } })
    a.captureApiError({ code: 'ECONNABORTED', config: { url: '/b', method: 'get' } })
    expect(phMock.capture.mock.calls[0][1]).toMatchObject({ status: 0, code: 'NETWORK_ERROR' })
    expect(phMock.capture.mock.calls[1][1]).toMatchObject({ status: 0, code: 'TIMEOUT' })
  })

  it('ignore les requêtes annulées', async () => {
    const a = await ready()
    a.captureApiError({ code: 'ERR_CANCELED', config: { url: '/a', method: 'get' } })
    expect(phMock.capture).not.toHaveBeenCalled()
  })

  it('dédoublonne method+endpoint+status sur 60 s', async () => {
    vi.useFakeTimers()
    const a = await ready()
    const err = { config: { url: '/bookings/1', method: 'get' }, response: { status: 500, data: {} } }

    a.captureApiError(err)
    a.captureApiError({ ...err, config: { url: '/bookings/2', method: 'get' } })
    expect(phMock.capture).toHaveBeenCalledTimes(1)

    vi.advanceTimersByTime(61_000)
    a.captureApiError(err)
    expect(phMock.capture).toHaveBeenCalledTimes(2)
  })
})
