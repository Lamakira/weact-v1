<script setup lang="ts">
/**
 * Dashboard-wide nudge shown on every Face page while a subscription payment is
 * pending confirmation, guiding the Face to resume it on the Facturation tab.
 *
 * Mirrors the existing FaceLayout banner pattern (Email / Tarifs / WhatsApp).
 * The actual resume / verify / cancel controls live on the Facturation tab
 * (FaceBillingPage) — this banner is purely the discoverability layer.
 */
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Loader2 } from 'lucide-vue-next'
import { useSubscriptionStatus } from '@/features/face/composables/useSubscriptionStatus'

const router = useRouter()
const { current, cta, fetchStatus } = useSubscriptionStatus()

// Same predicate as the billing tab / pricing page: FP-2.3 forces every CTA false
// while a payment is pending. Never true for a free, never-paid Face (they keep
// upgrade_available), so the banner only fires on a genuine pending payment.
const hasPendingPayment = computed(() => {
  if (!current.value) return false
  const c = cta.value
  return !c.upgrade_available && !c.downgrade_available && !c.renew_available
})

onMounted(() => {
  void fetchStatus().catch(() => {
    // Silently fail — the banner stays hidden; the Facturation tab surfaces errors.
  })
})
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
</template>
