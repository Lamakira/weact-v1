import { ref, computed, onMounted } from 'vue'

export interface CookiePreferences {
  necessary: true // Always true, cannot be disabled
  analytics: boolean
}

export interface CookieConsentState {
  consented: boolean
  preferences: CookiePreferences
  consentedAt: string | null
}

const STORAGE_KEY = 'weact_cookie_consent'
const CONSENT_VERSION = '1.0'
const CONSENT_MAX_AGE_MS = 13 * 30 * 24 * 60 * 60 * 1000 // ~13 months

const state = ref<CookieConsentState>({
  consented: false,
  preferences: { necessary: true, analytics: false },
  consentedAt: null,
})

const showBanner = ref(false)
const showPreferences = ref(false)

function loadFromStorage(): CookieConsentState | null {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (!raw) return null
    const parsed = JSON.parse(raw)
    if (parsed.version !== CONSENT_VERSION) return null
    // Check 13-month expiration
    const consentState = parsed.state as CookieConsentState
    if (consentState.consentedAt) {
      const elapsed = Date.now() - new Date(consentState.consentedAt).getTime()
      if (elapsed > CONSENT_MAX_AGE_MS) return null
    }
    return consentState
  } catch {
    return null
  }
}

function saveToStorage() {
  localStorage.setItem(
    STORAGE_KEY,
    JSON.stringify({ version: CONSENT_VERSION, state: state.value }),
  )
}

export function useCookieConsent() {
  const hasConsented = computed(() => state.value.consented)
  const analyticsEnabled = computed(() => state.value.preferences.analytics)

  function init() {
    const saved = loadFromStorage()
    if (saved && saved.consented) {
      state.value = saved
      showBanner.value = false
    } else {
      showBanner.value = true
    }
  }

  function acceptAll() {
    state.value = {
      consented: true,
      preferences: { necessary: true, analytics: true },
      consentedAt: new Date().toISOString(),
    }
    saveToStorage()
    showBanner.value = false
    showPreferences.value = false
  }

  function rejectAll() {
    state.value = {
      consented: true,
      preferences: { necessary: true, analytics: false },
      consentedAt: new Date().toISOString(),
    }
    saveToStorage()
    showBanner.value = false
    showPreferences.value = false
  }

  function savePreferences(prefs: Omit<CookiePreferences, 'necessary'>) {
    state.value = {
      consented: true,
      preferences: { necessary: true, ...prefs },
      consentedAt: new Date().toISOString(),
    }
    saveToStorage()
    showBanner.value = false
    showPreferences.value = false
  }

  function openPreferences() {
    showPreferences.value = true
  }

  function closePreferences() {
    showPreferences.value = false
  }

  onMounted(() => {
    init()
  })

  return {
    showBanner,
    showPreferences,
    hasConsented,
    analyticsEnabled,
    preferences: computed(() => state.value.preferences),
    acceptAll,
    rejectAll,
    savePreferences,
    openPreferences,
    closePreferences,
  }
}
