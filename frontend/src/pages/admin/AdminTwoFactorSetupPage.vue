<script setup lang="ts">
/**
 * AdminTwoFactorSetupPage
 * Mandatory TOTP enrolment. Reached after login (or after a 403 ADMIN_2FA_REQUIRED)
 * until the admin has confirmed a code from their authenticator app.
 */
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ShieldCheck, KeyRound, Lock, Loader2 } from 'lucide-vue-next'
import { useAdminAuth } from '@/features/admin/composables/useAdminAuth'
import { useAdminTwoFactor } from '@/features/admin/composables/useAdminTwoFactor'
import { useAdminAuthStore } from '@/stores/adminAuth'
import AdminRecoveryCodes from '@/features/admin/components/AdminRecoveryCodes.vue'
import { FloatingField } from '@/components/ui/form'
import logoNoir from '@/assets/images/logonoir.png'

const router = useRouter()
const adminAuthStore = useAdminAuthStore()
const { logout } = useAdminAuth()
const {
  status,
  setup,
  recoveryCodes,
  isLoading,
  fetchStatus,
  startSetup,
  confirmSetup,
  clearRecoveryCodes,
} = useAdminTwoFactor()

const code = ref('')
const password = ref('')
const apiError = ref<string | null>(null)
const initializing = ref(true)
const savedCodes = ref(false)

const hasRecoveryCodes = computed(() => recoveryCodes.value.length > 0)

onMounted(async () => {
  const statusResult = await fetchStatus()
  if (!statusResult.success) {
    apiError.value = statusResult.message ?? 'Une erreur est survenue'
    initializing.value = false
    return
  }

  // Already enrolled: nothing to do here (the Security page manages it)
  if (status.value?.enabled) {
    await router.replace({ name: 'admin-security' })
    return
  }

  initializing.value = false
})

// Step 0: the current password is required before a secret is generated
async function onStart(): Promise<void> {
  apiError.value = null
  if (!password.value) {
    apiError.value = 'Le mot de passe est obligatoire.'
    return
  }
  const result = await startSetup(password.value)
  password.value = ''
  if (!result.success) {
    apiError.value = result.message ?? 'Impossible de démarrer la configuration'
  }
}

async function onConfirm(): Promise<void> {
  apiError.value = null
  const trimmed = code.value.trim()
  if (!trimmed) {
    apiError.value = 'Le code de vérification est obligatoire.'
    return
  }

  const result = await confirmSetup(trimmed)
  if (!result.success) {
    apiError.value = result.message ?? 'Code de vérification incorrect'
    code.value = ''
  }
}

function finish(): void {
  clearRecoveryCodes()
  const target = adminAuthStore.isEditor ? 'admin-articles-list' : 'admin-dashboard'
  router.push({ name: target })
}

async function handleLogout(): Promise<void> {
  await logout()
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 px-4 py-12">
    <div class="w-full max-w-lg">
      <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-8">
        <div class="flex justify-center mb-6">
          <img :src="logoNoir" alt="WEACT" class="h-10" />
        </div>

        <div class="text-center mb-6">
          <h1 class="text-2xl font-bold text-gray-900 flex items-center justify-center gap-2">
            <ShieldCheck class="w-6 h-6 text-primary-600" />
            Double authentification
          </h1>
          <p class="mt-1 text-sm text-gray-500">
            L'accès à l'administration exige désormais un code à usage unique en plus de votre mot
            de passe.
          </p>
        </div>

        <div v-if="initializing" class="flex justify-center py-8" data-testid="setup-loading">
          <Loader2 class="h-8 w-8 text-primary-500 animate-spin" />
        </div>

        <!-- Step 3: recovery codes (shown once, after confirmation) -->
        <div v-else-if="hasRecoveryCodes" class="space-y-5" data-testid="setup-recovery-step">
          <div class="rounded-lg bg-green-50 border border-green-200 p-3">
            <p class="text-sm text-green-800">
              La double authentification est activée. Dernière étape : conservez vos codes de
              secours.
            </p>
          </div>

          <AdminRecoveryCodes :codes="recoveryCodes" />

          <label class="flex items-start gap-2 text-sm text-gray-700">
            <input
              v-model="savedCodes"
              type="checkbox"
              class="mt-0.5 rounded border-gray-300"
              data-testid="saved-codes-checkbox"
            />
            J'ai copié ou téléchargé mes codes de secours et je les ai rangés en lieu sûr.
          </label>

          <button
            type="button"
            :disabled="!savedCodes"
            class="w-full py-3 bg-primary-500 text-white font-medium rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            data-testid="finish-button"
            @click="finish"
          >
            Continuer vers l'administration
          </button>
        </div>

        <!-- Steps 1-2: scan the QR code, then confirm with a code -->
        <div v-else-if="setup" class="space-y-5" data-testid="setup-scan-step">
          <ol class="list-decimal list-inside text-sm text-gray-700 space-y-1">
            <li>
              Installez une application d'authentification (Google Authenticator, Microsoft
              Authenticator, Authy, 1Password…).
            </li>
            <li>Scannez le QR code ci-dessous, ou saisissez la clé manuellement.</li>
            <li>Saisissez le code à 6 chiffres affiché pour confirmer.</li>
          </ol>

          <!-- Server-generated SVG (QR modules only, no user-controlled markup) -->
          <div
            class="mx-auto w-56 h-56 rounded-lg border border-gray-200 bg-white p-2"
            data-testid="qr-code"
            v-html="setup.qr_svg"
          />

          <div class="text-center">
            <p class="text-xs text-gray-500 mb-1">Clé de configuration manuelle</p>
            <code
              class="inline-block rounded bg-gray-50 border border-gray-200 px-3 py-1.5 text-sm tracking-wider text-gray-900 select-all"
              data-testid="manual-secret"
              >{{ setup.secret }}</code
            >
          </div>

          <form class="space-y-4" data-testid="setup-confirm-form" @submit.prevent="onConfirm">
            <div
              v-if="apiError"
              class="rounded-lg bg-red-50 p-3 border border-red-200"
              role="alert"
              data-testid="api-error"
            >
              <p class="text-sm text-red-700">{{ apiError }}</p>
            </div>

            <FloatingField
              id="admin-setup-code"
              v-model="code"
              type="text"
              label="Code de vérification"
              :icon="KeyRound"
              autocomplete="one-time-code"
              inputmode="numeric"
              required
              data-testid="setup-code-input"
            />

            <button
              type="submit"
              :disabled="isLoading"
              class="w-full py-3 bg-primary-500 text-white font-medium rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              data-testid="setup-confirm-button"
            >
              {{ isLoading ? 'Vérification en cours...' : 'Activer la double authentification' }}
            </button>
          </form>
        </div>

        <!-- Step 0: confirm the current password, then generate the secret -->
        <form v-else class="space-y-4" data-testid="setup-password-form" @submit.prevent="onStart">
          <p class="text-sm text-gray-600">
            Pour votre sécurité, confirmez votre mot de passe avant de configurer l'application
            d'authentification.
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
            id="admin-setup-password"
            v-model="password"
            type="password"
            label="Mot de passe actuel"
            :icon="Lock"
            autocomplete="current-password"
            password-toggle
            required
            data-testid="setup-password-input"
          />
          <button
            type="submit"
            :disabled="isLoading"
            class="w-full py-3 bg-primary-500 text-white font-medium rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            data-testid="setup-password-button"
          >
            Continuer
          </button>
        </form>

        <div class="mt-6 text-center">
          <button
            type="button"
            class="text-sm text-gray-500 hover:text-gray-700 transition-colors"
            data-testid="setup-logout"
            @click="handleLogout"
          >
            Se déconnecter
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
