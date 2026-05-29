<script setup lang="ts">
/**
 * Dashboard-wide nudge shown on every Face page while a subscription payment is
 * pending confirmation, guiding the Face to resume it on the Facturation tab.
 *
 * Mirrors the existing FaceLayout banner pattern (Email / Tarifs / WhatsApp).
 * The actual resume / verify / cancel controls live on the Facturation tab
 * (FaceBillingPage) — this banner is purely the discoverability layer.
 */
import { useRouter } from 'vue-router'
import { Loader2, AlertTriangle } from 'lucide-vue-next'
import { useSubscriptionReconciler } from '@/features/face/composables/useSubscriptionReconciler'

const router = useRouter()

// The reconciler both drives the banner's state (pending / failed) AND auto-reconciles
// a pending payment dashboard-wide (on mount, on tab focus, and while pending) so the
// Face never has to hit "Vérifier maintenant" manually. `paymentFailed` lets us surface
// a retry nudge if the payment was declined instead of silently hiding the banner.
const { hasPendingPayment, paymentFailed, dismissFailure } = useSubscriptionReconciler()
</script>

<template>
  <div
    v-if="hasPendingPayment"
    class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6"
    role="alert"
    data-testid="pending-payment-banner"
  >
    <div class="flex items-start gap-3">
      <div class="flex-shrink-0 w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
        <Loader2 class="w-5 h-5 text-blue-600 animate-spin" aria-hidden="true" />
      </div>

      <div class="flex-1 min-w-0">
        <h3 class="text-sm font-semibold text-blue-800">
          Paiement d'abonnement en attente
        </h3>
        <p class="mt-1 text-sm text-blue-700">
          Ton paiement n'a pas encore été confirmé. Reprends-le pour activer ton abonnement.
        </p>

        <div class="mt-3">
          <button
            type="button"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-[#198496] text-white hover:bg-[#146c7a] transition-colors"
            data-testid="pending-payment-banner-cta"
            @click="router.push({ name: 'face-billing' })"
          >
            Reprendre le paiement
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Failure nudge — a watched pending payment was declined / timed out. Without
       this the pending banner would simply vanish, leaving the Face with no clue
       why nothing activated. -->
  <div
    v-else-if="paymentFailed"
    class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6"
    role="alert"
    data-testid="pending-payment-failed-banner"
  >
    <div class="flex items-start gap-3">
      <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
        <AlertTriangle class="w-5 h-5 text-red-600" aria-hidden="true" />
      </div>

      <div class="flex-1 min-w-0">
        <h3 class="text-sm font-semibold text-red-800">Paiement non abouti</h3>
        <p class="mt-1 text-sm text-red-700">
          Ton dernier paiement d'abonnement n'a pas abouti. Tu peux réessayer.
        </p>

        <div class="mt-3 flex items-center gap-2">
          <button
            type="button"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-[#198496] text-white hover:bg-[#146c7a] transition-colors"
            data-testid="pending-payment-failed-retry"
            @click="router.push({ name: 'pricing' })"
          >
            Réessayer
          </button>
          <button
            type="button"
            class="px-3 py-2 text-sm font-medium rounded-lg text-gray-600 hover:bg-gray-100 transition-colors"
            data-testid="pending-payment-failed-dismiss"
            @click="dismissFailure"
          >
            Ignorer
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
