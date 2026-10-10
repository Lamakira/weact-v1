import { ref } from 'vue'
import { useAdminAuthStore } from '@/stores/adminAuth'
import {
  adminTwoFactorApi,
  type AdminTwoFactorReauthForm,
  type AdminTwoFactorSetup,
  type AdminTwoFactorStatus,
} from '../services/adminTwoFactorApi'
import { getApiErrorMessage } from '../services/adminAuthApi'

interface ActionResult {
  success: boolean
  message?: string
}

/**
 * Composable for admin TOTP enrolment and management.
 * Recovery codes are only ever held in memory (shown once, never persisted).
 */
export function useAdminTwoFactor() {
  const adminAuthStore = useAdminAuthStore()

  const status = ref<AdminTwoFactorStatus | null>(null)
  const setup = ref<AdminTwoFactorSetup | null>(null)
  const recoveryCodes = ref<string[]>([])
  const isLoading = ref(false)

  async function run(action: () => Promise<void>): Promise<ActionResult> {
    isLoading.value = true
    try {
      await action()
      return { success: true }
    } catch (error) {
      return { success: false, message: getApiErrorMessage(error) }
    } finally {
      isLoading.value = false
    }
  }

  function markEnabled(enabled: boolean): void {
    if (adminAuthStore.admin) {
      adminAuthStore.setAdmin({ ...adminAuthStore.admin, two_factor_enabled: enabled })
    }
  }

  function fetchStatus(): Promise<ActionResult> {
    return run(async () => {
      status.value = await adminTwoFactorApi.getStatus()
    })
  }

  /** Start enrolment: generates a fresh secret + QR code */
  function startSetup(password: string): Promise<ActionResult> {
    return run(async () => {
      setup.value = await adminTwoFactorApi.enable(password)
    })
  }

  /** Confirm enrolment with a code from the authenticator app */
  function confirmSetup(code: string): Promise<ActionResult> {
    return run(async () => {
      const confirmed = await adminTwoFactorApi.confirm(code)
      recoveryCodes.value = confirmed.recovery_codes
      // The enrolment-limited token was revoked: swap in the full-access one
      adminAuthStore.setToken(confirmed.token)
      setup.value = null
      markEnabled(true)
    })
  }

  function regenerateRecoveryCodes(data: AdminTwoFactorReauthForm): Promise<ActionResult> {
    return run(async () => {
      recoveryCodes.value = await adminTwoFactorApi.regenerateRecoveryCodes(data)
    })
  }

  function disable(data: AdminTwoFactorReauthForm): Promise<ActionResult> {
    return run(async () => {
      await adminTwoFactorApi.disable(data)
      recoveryCodes.value = []
      markEnabled(false)
    })
  }

  function clearRecoveryCodes(): void {
    recoveryCodes.value = []
  }

  return {
    status,
    setup,
    recoveryCodes,
    isLoading,
    fetchStatus,
    startSetup,
    confirmSetup,
    regenerateRecoveryCodes,
    disable,
    clearRecoveryCodes,
  }
}
