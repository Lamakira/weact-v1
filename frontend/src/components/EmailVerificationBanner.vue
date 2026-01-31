<script setup lang="ts">
/**
 * EmailVerificationBanner Component
 * Displays a warning banner when user's email is not verified.
 * Includes resend functionality with throttle countdown.
 * Banner cannot be dismissed - always visible until email is verified.
 */
import { ref, computed, onUnmounted } from 'vue'
import { AlertTriangle, Mail, Loader2 } from 'lucide-vue-next'
import { authApi } from '@/features/auth/services/authApi'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'

const authStore = useAuthStore()
const toast = useToast()

// State
const isLoading = ref(false)
const cooldownSeconds = ref(0)
const cooldownInterval = ref<ReturnType<typeof setInterval> | null>(null)

// Computed
const canResend = computed(() => !isLoading.value && cooldownSeconds.value === 0)

// Start cooldown timer
function startCooldown(): void {
  cooldownSeconds.value = 60
  cooldownInterval.value = setInterval(() => {
    cooldownSeconds.value--
    if (cooldownSeconds.value <= 0) {
      if (cooldownInterval.value) {
        clearInterval(cooldownInterval.value)
        cooldownInterval.value = null
      }
    }
  }, 1000)
}

// Resend verification email
async function handleResend(): Promise<void> {
  if (!canResend.value) return

  isLoading.value = true
  try {
    const result = await authApi.resendVerificationEmail()

    if (result.verified) {
      toast.success('Votre email est déjà vérifié.')
      // Refresh user data to update the UI
      await authStore.refreshUser()
    } else if (result.sent) {
      toast.success('Un email de vérification a été envoyé.')
      startCooldown()
    }
  } catch (error) {
    // Handle throttle error (429)
    if ((error as { response?: { status?: number } })?.response?.status === 429) {
      toast.warning('Veuillez patienter avant de renvoyer un email.')
      startCooldown()
    } else {
      toast.error('Impossible d\'envoyer l\'email. Veuillez réessayer.')
    }
  } finally {
    isLoading.value = false
  }
}

// Cleanup interval on unmount
onUnmounted(() => {
  if (cooldownInterval.value) {
    clearInterval(cooldownInterval.value)
  }
})
</script>

<template>
  <div
    class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6"
    role="alert"
    data-testid="email-verification-banner"
  >
    <div class="flex items-start gap-3">
      <!-- Icon -->
      <div class="flex-shrink-0 w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center">
        <AlertTriangle class="w-5 h-5 text-amber-600" aria-hidden="true" />
      </div>

      <!-- Content -->
      <div class="flex-1 min-w-0">
        <h3 class="text-sm font-semibold text-amber-800" data-testid="banner-title">
          Vérifiez votre adresse email
        </h3>
        <p class="mt-1 text-sm text-amber-700" data-testid="banner-message">
          Pour accéder à toutes les fonctionnalités de la plateforme, veuillez vérifier votre adresse email en cliquant sur le lien envoyé lors de votre inscription.
        </p>

        <!-- Action buttons -->
        <div class="mt-3 flex items-center gap-3">
          <button
            @click="handleResend"
            :disabled="!canResend"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg transition-colors"
            :class="canResend
              ? 'bg-amber-600 text-white hover:bg-amber-700'
              : 'bg-amber-200 text-amber-500 cursor-not-allowed'"
            data-testid="resend-button"
          >
            <Mail v-if="!isLoading" class="w-4 h-4" />
            <Loader2 v-else class="w-4 h-4 animate-spin" />
            <span v-if="cooldownSeconds > 0">
              Renvoyer ({{ cooldownSeconds }}s)
            </span>
            <span v-else-if="isLoading">
              Envoi en cours...
            </span>
            <span v-else>
              Renvoyer l'email
            </span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
