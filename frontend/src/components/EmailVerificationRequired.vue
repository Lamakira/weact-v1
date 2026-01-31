<script setup lang="ts">
/**
 * EmailVerificationRequired Component
 * Full-page or section blocking when email verification is required.
 * Shows a message explaining why access is blocked and provides resend option.
 */
import { ref } from 'vue'
import { ShieldAlert, Mail, Loader2 } from 'lucide-vue-next'
import { authApi } from '@/features/auth/services/authApi'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'

interface Props {
  title?: string
  message?: string
}

withDefaults(defineProps<Props>(), {
  title: 'Vérification email requise',
  message: 'Vous devez vérifier votre adresse email pour accéder à cette fonctionnalité.',
})

const authStore = useAuthStore()
const toast = useToast()
const isResending = ref(false)

async function handleResend(): Promise<void> {
  isResending.value = true
  try {
    const result = await authApi.resendVerificationEmail()
    if (result.sent) {
      toast.success('Un email de vérification a été envoyé.')
    } else if (result.verified) {
      toast.success('Votre email est déjà vérifié.')
      // Refresh user data to update the UI
      await authStore.refreshUser()
    }
  } catch (err) {
    if ((err as { response?: { status?: number } })?.response?.status === 429) {
      toast.warning('Veuillez patienter avant de renvoyer un email.')
    } else {
      toast.error("Impossible d'envoyer l'email. Veuillez réessayer.")
    }
  } finally {
    isResending.value = false
  }
}
</script>

<template>
  <div
    class="flex flex-col items-center justify-center py-16 px-6 text-center"
    data-testid="email-verification-required"
  >
    <div class="mb-6 w-20 h-20 rounded-full bg-amber-100 flex items-center justify-center">
      <ShieldAlert class="w-10 h-10 text-amber-600" />
    </div>

    <h2 class="text-xl font-semibold text-slate-800 mb-2">{{ title }}</h2>
    <p class="text-slate-500 max-w-md mb-6">{{ message }}</p>

    <button
      type="button"
      :disabled="isResending"
      class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium rounded-lg bg-amber-600 text-white hover:bg-amber-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
      @click="handleResend"
      data-testid="resend-verification-button"
    >
      <Mail v-if="!isResending" class="w-4 h-4" />
      <Loader2 v-else class="w-4 h-4 animate-spin" />
      {{ isResending ? 'Envoi en cours...' : "Renvoyer l'email de vérification" }}
    </button>
  </div>
</template>
