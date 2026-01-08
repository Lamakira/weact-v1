import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { User } from '@/features/auth/types'
import { getAuthToken, setAuthToken, removeAuthToken } from '@/services/apiClient'

export const useAuthStore = defineStore('auth', () => {
  // State
  const user = ref<User | null>(null)
  const token = ref<string | null>(getAuthToken())
  const isLoading = ref(false)

  // Getters
  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const userType = computed(() => user.value?.userable_type ?? null)
  const isFace = computed(() => userType.value === 'Face')
  const isProducer = computed(() => userType.value === 'Producer')

  // Actions
  function setUser(newUser: User) {
    user.value = newUser
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
    // Actions
    setUser,
    setToken,
    setLoading,
    clearAuth,
    $reset,
  }
})
