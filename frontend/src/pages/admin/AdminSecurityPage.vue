<script setup lang="ts">
/**
 * AdminSecurityPage
 * Two-factor management for the logged-in admin: regenerate recovery codes
 * or disable 2FA (both require the current password + a current code).
 * Disabling forces a new enrolment at the next admin request.
 */
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ShieldCheck, Lock, KeyRound, Loader2 } from 'lucide-vue-next'
import { useAdminTwoFactor } from '@/features/admin/composables/useAdminTwoFactor'
import { useToast } from '@/composables/useToast'
import AdminRecoveryCodes from '@/features/admin/components/AdminRecoveryCodes.vue'
import { FloatingField } from '@/components/ui/form'

const router = useRouter()
const toast = useToast()
const { status, recoveryCodes, isLoading, fetchStatus, regenerateRecoveryCodes, disable, clearRecoveryCodes } =
  useAdminTwoFactor()

type Action = 'regenerate' | 'disable' | null

const action = ref<Action>(null)
const password = ref('')
const code = ref('')
const useRecoveryCode = ref(false)
const apiError = ref<string | null>(null)

onMounted(async () => {
  const result = await fetchStatus()
  if (!result.success) {
    apiError.value = result.message ?? 'Une erreur est survenue'
  }
})

function openAction(next: Action): void {
  action.value = next
  password.value = ''
  code.value = ''
  useRecoveryCode.value = false
  apiError.value = null
  clearRecoveryCodes()
}

function cancelAction(): void {
  openAction(null)
}

async function submitAction(): Promise<void> {
  apiError.value = null
  if (!password.value || !code.value.trim()) {
    apiError.value = 'Le mot de passe et le code de vérification sont obligatoires.'
    return
  }

  const payload = {
    password: password.value,
    ...(useRecoveryCode.value ? { recovery_code: code.value.trim() } : { code: code.value.trim() }),
  }

  if (action.value === 'regenerate') {
    const result = await regenerateRecoveryCodes(payload)
    if (!result.success) {
      apiError.value = result.message ?? 'Une erreur est survenue'
      return
    }
    action.value = null
    await fetchStatus()
    return
  }

  if (action.value === 'disable') {
    const result = await disable(payload)
    if (!result.success) {
      apiError.value = result.message ?? 'Une erreur est survenue'
      return
    }
    toast.success('Double authentification désactivée. Vous devrez la réactiver pour continuer.')
    await router.push({ name: 'admin-two-factor-setup' })
  }
}
</script>

<template>
  <div class="space-y-6 max-w-2xl">
    <div>
      <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
        <ShieldCheck class="h-6 w-6 text-primary-600" />
        Sécurité du compte
      </h1>
      <p class="mt-1 text-sm text-gray-500">Gérez la double authentification de votre compte.</p>
    </div>

    <div v-if="isLoading && !status" class="flex justify-center py-8" data-testid="security-loading">
      <Loader2 class="h-8 w-8 text-primary-500 animate-spin" />
    </div>

    <div
      v-if="apiError && !action"
      class="rounded-lg bg-red-50 p-3 border border-red-200"
      role="alert"
      data-testid="api-error"
    >
      <p class="text-sm text-red-700">{{ apiError }}</p>
    </div>

    <div
      v-if="status"
      class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm space-y-4"
      data-testid="two-factor-card"
    >
      <div class="flex items-center justify-between">
        <h2 class="text-sm font-medium text-gray-900">Application d'authentification (TOTP)</h2>
        <span
          class="text-xs font-medium px-2 py-1 rounded-full"
          :class="status.enabled ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'"
          data-testid="two-factor-status"
        >
          {{ status.enabled ? 'Activée' : 'Non activée' }}
        </span>
      </div>

      <template v-if="status.enabled">
        <p class="text-sm text-gray-600" data-testid="recovery-remaining">
          Codes de secours restants : <strong>{{ status.recovery_codes_remaining }}</strong>
        </p>

        <!-- Freshly regenerated codes (shown once) -->
        <AdminRecoveryCodes v-if="recoveryCodes.length > 0" :codes="recoveryCodes" />

        <div v-if="!action" class="flex flex-wrap gap-3">
          <button
            type="button"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
            data-testid="regenerate-button"
            @click="openAction('regenerate')"
          >
            <KeyRound class="h-4 w-4" />
            Régénérer les codes de secours
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-colors"
            data-testid="disable-button"
            @click="openAction('disable')"
          >
            Désactiver la double authentification
          </button>
        </div>

        <!-- Re-authentication form -->
        <form v-else class="space-y-4" data-testid="reauth-form" @submit.prevent="submitAction">
          <p class="text-sm text-gray-600">
            {{
              action === 'disable'
                ? 'Désactiver la double authentification vous obligera à la reconfigurer immédiatement. Confirmez avec votre mot de passe et un code.'
                : 'Les anciens codes de secours seront invalidés. Confirmez avec votre mot de passe et un code.'
            }}
          </p>

          <div
            v-if="apiError"
            class="rounded-lg bg-red-50 p-3 border border-red-200"
            role="alert"
            data-testid="api-error"
          >
            <p class="text-sm text-red-700">{{ apiError }}</p>
          </div>

          <FloatingField
            id="admin-security-password"
            v-model="password"
            type="password"
            label="Mot de passe actuel"
            :icon="Lock"
            autocomplete="current-password"
            password-toggle
            required
            data-testid="reauth-password"
          />
          <FloatingField
            id="admin-security-code"
            v-model="code"
            type="text"
            :label="useRecoveryCode ? 'Code de secours' : 'Code de vérification'"
            :icon="KeyRound"
            autocomplete="one-time-code"
            required
            data-testid="reauth-code"
          />
          <button
            type="button"
            class="text-sm text-primary-600 hover:text-primary-700"
            data-testid="reauth-toggle-recovery"
            @click="useRecoveryCode = !useRecoveryCode; code = ''"
          >
            {{ useRecoveryCode ? "Utiliser le code de l'application" : 'Utiliser un code de secours' }}
          </button>

          <div class="flex gap-3">
            <button
              type="submit"
              :disabled="isLoading"
              class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              :class="action === 'disable' ? 'bg-red-600 hover:bg-red-700' : 'bg-primary-500 hover:bg-primary-700'"
              data-testid="reauth-submit"
            >
              <Loader2 v-if="isLoading" class="h-4 w-4 animate-spin" />
              {{ action === 'disable' ? 'Désactiver' : 'Régénérer' }}
            </button>
            <button
              type="button"
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
              data-testid="reauth-cancel"
              @click="cancelAction"
            >
              Annuler
            </button>
          </div>
        </form>
      </template>

      <template v-else>
        <p class="text-sm text-gray-600">
          La double authentification n'est pas activée sur votre compte.
        </p>
        <button
          type="button"
          class="px-4 py-2 text-sm font-medium text-white bg-primary-500 rounded-lg hover:bg-primary-700 transition-colors"
          data-testid="go-setup-button"
          @click="router.push({ name: 'admin-two-factor-setup' })"
        >
          Activer la double authentification
        </button>
      </template>
    </div>
  </div>
</template>
