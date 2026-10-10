<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { AlertCircle, CheckCircle2, HelpCircle } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { useAuthStore } from '@/stores/auth'

/**
 * Neutral landing page for the FedaPay browser return when the backend could not
 * match the transaction to a payment (unknown id, or the webhook already cleaned
 * the entity up — e.g. a declined hybrid escrow entry). It only receives the
 * whitelisted `fedapay_status` DISPLAY hint: nothing here reads or changes any
 * payment state.
 */
const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const outcome = computed<'failed' | 'approved' | 'unknown'>(() => {
  const raw = Array.isArray(route.query.fedapay_status)
    ? route.query.fedapay_status[0]
    : route.query.fedapay_status
  if (raw === 'canceled' || raw === 'declined') return 'failed'
  if (raw === 'approved') return 'approved'
  return 'unknown'
})

const message = computed(() => {
  switch (outcome.value) {
    case 'failed':
      return 'Votre paiement a été annulé ou refusé. Vous pouvez réessayer depuis la page concernée.'
    case 'approved':
      return 'Paiement reçu, sa confirmation peut prendre quelques instants.'
    default:
      return 'Nous n\'avons pas pu identifier ce paiement.'
  }
})

function goToDashboard(): void {
  const type = authStore.user?.userable_type
  void router.push({ name: type === 'Face' ? 'face-dashboard' : 'producer-dashboard' })
}
</script>

<template>
  <div class="mx-auto flex min-h-[50vh] max-w-md flex-col items-center justify-center gap-6 px-4 py-16 text-center">
    <CheckCircle2 v-if="outcome === 'approved'" class="h-12 w-12 text-emerald-600" />
    <AlertCircle v-else-if="outcome === 'failed'" class="h-12 w-12 text-red-600" />
    <HelpCircle v-else class="h-12 w-12 text-gray-400" />

    <p class="text-base text-gray-800" data-testid="payment-return-message" :data-outcome="outcome">
      {{ message }}
    </p>

    <Button data-testid="payment-return-dashboard" @click="goToDashboard">
      Aller à mon tableau de bord
    </Button>
  </div>
</template>
