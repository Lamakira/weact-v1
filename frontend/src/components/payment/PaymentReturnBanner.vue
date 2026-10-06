<script setup lang="ts">
import { AlertCircle, Info, Loader2 } from 'lucide-vue-next'
import { PAYMENT_RETURN_MESSAGES, type PaymentReturnState } from '@/composables/usePaymentReturn'

defineProps<{
  state: PaymentReturnState
}>()

const emit = defineEmits<{
  retry: []
  dismiss: []
}>()
</script>

<template>
  <div
    v-if="state === 'verifying'"
    class="mb-4 flex items-start gap-2 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800"
    role="status"
    data-testid="payment-return-verifying"
  >
    <Loader2 class="mt-0.5 h-4 w-4 flex-shrink-0 animate-spin" />
    <span>{{ PAYMENT_RETURN_MESSAGES.verifying }}</span>
  </div>

  <div
    v-else-if="state === 'failed'"
    class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"
    role="alert"
    data-testid="payment-return-failed"
  >
    <div class="flex items-start gap-2">
      <AlertCircle class="mt-0.5 h-4 w-4 flex-shrink-0" />
      <span>{{ PAYMENT_RETURN_MESSAGES.failed }}</span>
    </div>
    <div class="mt-3 flex gap-3">
      <button
        type="button"
        class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-red-700"
        data-testid="payment-return-retry"
        @click="emit('retry')"
      >
        Réessayer le paiement
      </button>
      <button
        type="button"
        class="rounded-md px-4 py-2 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-100"
        data-testid="payment-return-dismiss"
        @click="emit('dismiss')"
      >
        Fermer
      </button>
    </div>
  </div>

  <div
    v-else-if="state === 'timeout'"
    class="mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800"
    role="status"
    data-testid="payment-return-timeout"
  >
    <Info class="mt-0.5 h-4 w-4 flex-shrink-0" />
    <span>{{ PAYMENT_RETURN_MESSAGES.timeout }}</span>
    <button
      type="button"
      class="ml-auto text-xs font-semibold underline"
      data-testid="payment-return-dismiss"
      @click="emit('dismiss')"
    >
      Fermer
    </button>
  </div>
</template>
