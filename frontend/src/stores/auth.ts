import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { User } from '@/features/auth/types'
import { resetAllSharedCachedResources } from '@/lib/createSharedCachedResource'
import apiClient, { getAuthToken, setAuthToken, removeAuthToken } from '@/services/apiClient'

/**
 * Storage key for user data
 */
const USER_KEY = 'auth_user'

/**
 * Get stored user from localStorage
 */
function getStoredUser(): User | null {
  const stored = localStorage.getItem(USER_KEY)
  if (stored) {
    try {
      return JSON.parse(stored) as User
    } catch {
      return null
    }
  }
  return null
}

/**
 * Store user in localStorage
 */
function setStoredUser(user: User): void {
  localStorage.setItem(USER_KEY, JSON.stringify(user))
}

/**
 * Remove user from localStorage
 */
function removeStoredUser(): void {
  localStorage.removeItem(USER_KEY)
}

export const useAuthStore = defineStore('auth', () => {
  // State - restore both token AND user from localStorage
  const user = ref<User | null>(getStoredUser())
  const token = ref<string | null>(getAuthToken())
  const isLoading = ref(false)

  // Getters
  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const userType = computed(() => user.value?.userable_type ?? null)
  const isFace = computed(() => userType.value === 'Face')
  const isProducer = computed(() => userType.value === 'Producer')
  const isEmailVerified = computed(() => user.value?.email_verified ?? false)
  const emailVerifiedAt = computed(() => user.value?.email_verified_at ?? null)

  // Actions
  function setUser(newUser: User) {
    // Identity switch without a teardown (no clearAuth in between — e.g. a
    // future in-place re-auth or account-switch flow): purge the per-account
    // shared caches here too, so the invariant "no account reads another
    // account's cached data" is guaranteed by the store itself rather than by
    // the router's guest-guard topology. Same-id updates (profile refresh)
    // keep their caches.
    const previousId = user.value?.id
    if (previousId != null && previousId !== newUser.id) {
      resetAllSharedCachedResources()
    }
    user.value = newUser
    setStoredUser(newUser)
  }

  function setToken(newToken: string) {
    token.value = newToken
    setAuthToken(newToken)
  }

  function setLoading(loading: boolean) {
    isLoading.value = loading
  }

  function clearAuth() {
    user.value = null
    token.value = null
    removeAuthToken()
    removeStoredUser()
    // Every shared cached resource holds per-account server state (profile
    // fields, subscription status…) behind a TTL: without this reset, the
    // next account logging in within the TTL would read the previous
    // account's data — e.g. the site-wide payment banner would show (and try
    // to reconcile) someone else's pending payment.
    resetAllSharedCachedResources()
  }

  /**
   * Refresh user data from the API
   * Useful after email verification or other profile updates
   */
  async function refreshUser(): Promise<boolean> {
    if (!token.value) return false

    try {
      const response = await apiClient.get('/user')
      const userData = response.data.data as User
      setUser(userData)
      return true
    } catch {
      return false
    }
  }

  function $reset() {
    clearAuth()
    isLoading.value = false
  }

  return {
    // State
    user,
    token,
    isLoading,
    // Getters
    isAuthenticated,
    userType,
    isFace,
    isProducer,
    isEmailVerified,
    emailVerifiedAt,
    // Actions
    setUser,
    setToken,
    setLoading,
    clearAuth,
    refreshUser,
    $reset,
  }
})
