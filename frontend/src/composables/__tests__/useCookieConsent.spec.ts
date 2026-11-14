import { describe, it, expect, vi, beforeEach } from 'vitest'
import { defineComponent, h } from 'vue'
import { mount } from '@vue/test-utils'

const syncAnalyticsConsent = vi.fn()
vi.mock('@/lib/analytics', () => ({ syncAnalyticsConsent }))

const STORAGE_KEY = 'weact_cookie_consent'

function stored(version: string, analytics: boolean) {
  localStorage.setItem(
    STORAGE_KEY,
    JSON.stringify({
      version,
      state: {
        consented: true,
        preferences: { necessary: true, analytics },
        consentedAt: new Date().toISOString(),
      },
    }),
  )
}

async function setup() {
  vi.resetModules()
  const mod = await import('../useCookieConsent')
  let api!: ReturnType<typeof mod.useCookieConsent>
  const Comp = defineComponent({
    setup() {
      api = mod.useCookieConsent()
      return () => h('div')
    },
  })
  mount(Comp)
  return { api, mod }
}

beforeEach(() => {
  localStorage.clear()
  syncAnalyticsConsent.mockClear()
})

describe('useCookieConsent', () => {
  it('version 1.0 stockée : le bandeau est réaffiché, choix en attente', async () => {
    stored('1.0', true)
    const { api } = await setup()
    expect(api.showBanner.value).toBe(true)
    expect(syncAnalyticsConsent).toHaveBeenLastCalledWith('pending')
  })

  it('consentement 2.0 valide : bandeau masqué et choix appliqué', async () => {
    stored('2.0', true)
    const { api, mod } = await setup()
    expect(api.showBanner.value).toBe(false)
    expect(syncAnalyticsConsent).toHaveBeenLastCalledWith('granted')
    expect(mod.getStoredAnalyticsChoice()).toBe('granted')
  })

  it('acceptAll => granted, rejectAll => denied', async () => {
    const { api, mod } = await setup()
    api.acceptAll()
    expect(syncAnalyticsConsent).toHaveBeenLastCalledWith('granted')
    expect(mod.getStoredAnalyticsChoice()).toBe('granted')
    api.rejectAll()
    expect(syncAnalyticsConsent).toHaveBeenLastCalledWith('denied')
    expect(mod.getStoredAnalyticsChoice()).toBe('denied')
  })

  it('savePreferences transmet le choix correspondant', async () => {
    const { api } = await setup()
    api.savePreferences({ analytics: true })
    expect(syncAnalyticsConsent).toHaveBeenLastCalledWith('granted')
    api.savePreferences({ analytics: false })
    expect(syncAnalyticsConsent).toHaveBeenLastCalledWith('denied')
  })

  it('init est idempotent', async () => {
    const { mod } = await setup()
    const callsAfterFirst = syncAnalyticsConsent.mock.calls.length
    mount(defineComponent({ setup() { mod.useCookieConsent(); return () => h('div') } }))
    expect(syncAnalyticsConsent.mock.calls.length).toBe(callsAfterFirst)
  })
})
