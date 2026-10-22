<script setup lang="ts">
/**
 * EmailVerificationBanner Component
 * Displays a warning banner when user's email is not verified.
 * Includes resend functionality with throttle countdown.
 * Banner cannot be dismissed - always visible until email is verified.
 */
import { AlertTriangle, Mail, Loader2 } from 'lucide-vue-next'
import { useResendVerificationEmail } from '@/composables/useResendVerificationEmail'

const { isLoading, cooldownSeconds, canResend, handleResend } = useResendVerificationEmail()
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
