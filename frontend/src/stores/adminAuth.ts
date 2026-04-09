import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import {
  getAdminAuthToken,
  setAdminAuthToken,
  removeAdminAuthToken,
  getStoredAdmin,
  setStoredAdmin,
  removeStoredAdmin,
} from '@/features/admin/services/adminApiClient'
import { adminAuthApi } from '@/features/admin/services/adminAuthApi'

/**
 * Admin data shape
 */
export interface AdminUser {
  id: string
  name: string
  email: string
  role: 'superadmin' | 'admin' | 'editor'
}

export const useAdminAuthStore = defineStore('adminAuth', () => {
  // State - restore from localStorage on init
  const admin = ref<AdminUser | null>(getStoredAdmin())
  const token = ref<string | null>(getAdminAuthToken())
  const isLoading = ref(false)

  // Getters
  const isAuthenticated = computed(() => !!token.value && !!admin.value)
  const isSuperAdmin = computed(() => admin.value?.role === 'superadmin')
  const isEditor = computed(() => admin.value?.role === 'editor')
  const adminName = computed(() => admin.value?.name ?? '')
  const adminEmail = computed(() => admin.value?.email ?? '')

  // Actions
  function setAdmin(newAdmin: AdminUser): void {
    admin.value = newAdmin
    setStoredAdmin(newAdmin)
  }

  function setToken(newToken: string): void {
    token.value = newToken
    setAdminAuthToken(newToken)
  }

  function setLoading(loading: boolean): void {
    isLoading.value = loading
  }

  function clearAuth(): void {
    admin.value = null
    token.value = null
    removeAdminAuthToken()
    removeStoredAdmin()
  }

  /**
   * Refresh admin data from the API
   */
  async function refreshAdmin(): Promise<boolean> {
    if (!token.value) return false

    try {
      const response = await adminAuthApi.getMe()
      setAdmin(response.data)
      return true
    } catch {
      return false
    }
  }

  function $reset(): void {
    clearAuth()
    isLoading.value = false
  }

  return {
    // State
    admin,
    token,
    isLoading,
    // Getters
    isAuthenticated,
    isSuperAdmin,
    isEditor,
    adminName,
    adminEmail,
    // Actions
    setAdmin,
    setToken,
    setLoading,
    clearAuth,
    refreshAdmin,
    $reset,
  }
})
