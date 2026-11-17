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
      opt_out_capturing_by_default: true,
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
    const event = { event: '$pageview', properties: {} }

    window.history.pushState({}, '', '/admin/users')
    expect(before_send(event)).toBeNull()
    window.history.pushState({}, '', '/faces')
    expect(before_send(event)).toBe(event)
  })

  it('sans consentement : seuls les visites, les étapes clés et erreur_api partent', async () => {
    const a = await load()
    await a.initAnalytics(fakeRouter(), pinia)
    const { before_send } = phMock.init.mock.calls[0][1]
    window.history.pushState({}, '', '/faces')
    const ev = (name: string) => ({ event: name, properties: {} })

    for (const name of ['$pageview', '$pageleave', 'erreur_api', 'booking_demande_envoyee', 'paiement_confirme']) {
      expect(before_send(ev(name))).not.toBeNull()
    }
    for (const name of ['$autocapture', '$rageclick', '$dead_click', '$$heatmap', '$web_vitals', '$exception']) {
      expect(before_send(ev(name))).toBeNull()
    }

    a.syncAnalyticsConsent('granted')
    expect(before_send(ev('$autocapture'))).not.toBeNull()
    a.syncAnalyticsConsent('denied')
    expect(before_send(ev('$autocapture'))).toBeNull()
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

describe('captureAnalyticsEvent', () => {
  it('avant l\'initialisation : gardé en mémoire puis envoyé à l\'init, avec le rôle connu à ce moment', async () => {
    const a = await load()
    a.captureAnalyticsEvent('email_verifie', {})
    a.captureAnalyticsEvent('paiement_confirme', { type: 'booking' })
    expect(phMock.capture).not.toHaveBeenCalled()

    authStore.user = { id: 3, userable_type: 'Face' }
    await a.initAnalytics(fakeRouter(), pinia)

    expect(phMock.capture).toHaveBeenNthCalledWith(1, 'email_verifie', { role: 'face' }, undefined)
    expect(phMock.capture).toHaveBeenNthCalledWith(2, 'paiement_confirme', { type: 'booking', role: 'face' }, undefined)
  })

  it('sans clé ou en mode test : rien n\'est gardé ni envoyé', async () => {
    const a = await load({ key: '' })
    a.captureAnalyticsEvent('email_verifie', {})
    await a.initAnalytics(fakeRouter(), pinia)
    expect(phMock.capture).not.toHaveBeenCalled()
  })

  it('au plus 20 événements gardés avant l\'init', async () => {
    const a = await load()
    for (let i = 0; i < 25; i++) a.captureAnalyticsEvent('candidature_envoyee', {})
    await a.initAnalytics(fakeRouter(), pinia)
    expect(phMock.capture).toHaveBeenCalledTimes(20)
  })

  it('ajoute le rôle : visiteur sans compte, face ou producteur une fois connecté', async () => {
    const a = await load()
    await a.initAnalytics(fakeRouter(), pinia)

    a.captureAnalyticsEvent('inscription_soumise', { compte: 'face', methode: 'email' })
    expect(phMock.capture).toHaveBeenLastCalledWith(
      'inscription_soumise',
      { compte: 'face', methode: 'email', role: 'visiteur' },
      undefined,
    )

    authStore.user = { id: 7, userable_type: 'Producer' }
    a.captureAnalyticsEvent('mission_publiee', { type: 'ugc', paiement_requis: true })
    expect(phMock.capture).toHaveBeenLastCalledWith(
      'mission_publiee',
      { type: 'ugc', paiement_requis: true, role: 'producteur' },
      undefined,
    )

    authStore.user = { id: 8, userable_type: 'Face' }
    a.captureAnalyticsEvent('booking_acceptee', { type: 'cash' })
    expect(phMock.capture).toHaveBeenLastCalledWith('booking_acceptee', { type: 'cash', role: 'face' }, undefined)
  })

  it('sendInstantly : envoi immédiat (départ vers FedaPay)', async () => {
    const a = await load()
    await a.initAnalytics(fakeRouter(), pinia)
    a.captureAnalyticsEvent('paiement_lance', { type: 'abonnement', palier: 'pro' }, { sendInstantly: true })
    expect(phMock.capture).toHaveBeenLastCalledWith(
      'paiement_lance',
      { type: 'abonnement', palier: 'pro', role: 'visiteur' },
      { send_instantly: true },
    )
  })

  it('une erreur de PostHog ne remonte jamais', async () => {
    const a = await load()
    await a.initAnalytics(fakeRouter(), pinia)
    phMock.capture.mockImplementationOnce(() => {
      throw new Error('boom')
    })
    expect(() => a.captureAnalyticsEvent('candidature_envoyee', {})).not.toThrow()
  })
})
