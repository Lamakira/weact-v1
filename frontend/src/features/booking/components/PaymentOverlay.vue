<script setup lang="ts">
import { computed, watch } from 'vue'
import { useBookingPayment } from '../composables/useBookingPayment'
import type { Booking } from '../types'
import { Button } from '@/components/ui/button'
import {
  X,
  Loader2,
  CheckCircle2,
  AlertCircle,
  CreditCard,
  ExternalLink,
} from 'lucide-vue-next'

const props = defineProps<{
  booking: Booking
  modelValue: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  'payment-success': [booking: Booking]
}>()

const { isInitiating, isPolling, paymentStatus, error, initiatePayment, stopPolling, reset } =
  useBookingPayment()

const pricing = computed(() => ({
  tarifBase: props.booking.tarif_base,
  producerCommission: props.booking.montant_total_producteur - props.booking.tarif_base,
  totalProducerPays: props.booking.montant_total_producteur,
}))

const step = computed((): 'select' | 'waiting' | 'success' | 'failed' => {
  switch (paymentStatus.value) {
    case 'waiting':
      return 'waiting'
    case 'confirmed':
      return 'success'
    case 'failed':
      return 'failed'
    default:
      return 'select'
  }
})

function formatXOF(amount: number): string {
  return new Intl.NumberFormat('fr-FR').format(amount) + ' FCFA'
}

async function handlePay(): Promise<void> {
  await initiatePayment(props.booking.id)
}

function handleRetry(): void {
  reset()
}

function handleClose(): void {
  stopPolling()
  reset()
  emit('update:modelValue', false)
}

function handleViewBooking(): void {
  emit('payment-success', props.booking)
  handleClose()
}

// Clean up on close
watch(
  () => props.modelValue,
  (isOpen) => {
    if (!isOpen) {
      stopPolling()
      reset()
    }
  },
)
</script>

<template>
  <Teleport to="body">
    <Transition name="overlay">
      <div
        v-if="modelValue"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
      >
        <div
          class="relative mx-4 w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-gray-900"
        >
          <!-- Close button -->
          <button
            v-if="step !== 'waiting'"
            class="absolute right-4 top-4 rounded-full p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800"
            @click="handleClose"
          >
            <X :size="20" />
          </button>

          <!-- Step 1: Confirm & Pay -->
          <div v-if="step === 'select'" class="space-y-6">
            <div class="text-center">
              <div
                class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/30"
              >
                <CreditCard :size="24" class="text-emerald-600" />
              </div>
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Paiement du booking
              </h2>
            </div>

            <!-- Pricing breakdown -->
            <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800/50">
              <div class="space-y-2 text-sm">
                <div class="flex justify-between text-gray-600 dark:text-gray-400">
                  <span>Tarif de base</span>
                  <span>{{ formatXOF(pricing.tarifBase) }}</span>
                </div>
                <div class="flex justify-between text-gray-600 dark:text-gray-400">
                  <span>Frais de service</span>
                  <span>+ {{ formatXOF(pricing.producerCommission) }}</span>
                </div>
                <div
                  class="flex justify-between border-t border-gray-200 pt-2 font-semibold text-gray-900 dark:border-gray-700 dark:text-white"
                >
                  <span>Total à payer</span>
                  <span>{{ formatXOF(pricing.totalProducerPays) }}</span>
                </div>
              </div>
            </div>

            <p class="text-center text-sm text-gray-500 dark:text-gray-400">
              Vous serez redirigé vers la page de paiement sécurisée FedaPay dans un nouvel onglet.
            </p>

            <!-- Error -->
            <div
              v-if="error"
              class="flex items-center gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-600 dark:bg-red-900/20 dark:text-red-400"
            >
              <AlertCircle :size="16" />
              <span>{{ error }}</span>
            </div>

            <!-- Pay button -->
            <Button
              class="w-full"
              :disabled="isInitiating"
              @click="handlePay"
            >
              <Loader2 v-if="isInitiating" :size="16" class="mr-2 animate-spin" />
              <ExternalLink v-else :size="16" class="mr-2" />
              <span>{{ isInitiating ? 'Chargement...' : 'Payer via FedaPay' }}</span>
            </Button>
          </div>

          <!-- Step 2: Waiting for payment -->
          <div v-if="step === 'waiting'" class="space-y-6 text-center">
            <div
              class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30"
            >
              <Loader2 :size="32" class="animate-spin text-amber-600" />
            </div>
            <div>
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                En attente de votre paiement...
              </h2>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Complétez le paiement dans l'onglet FedaPay ouvert. Cette page se mettra à jour automatiquement.
              </p>
            </div>
            <div class="flex flex-col gap-3">
              <p class="text-xs text-gray-400">
                L'onglet FedaPay s'est fermé ?
              </p>
              <Button variant="outline" class="w-full" @click="handleRetry">
                Recommencer
              </Button>
            </div>
            <button
              class="text-sm text-gray-400 underline hover:text-gray-600 dark:hover:text-gray-300"
              @click="handleClose"
            >
              Annuler
            </button>
          </div>

          <!-- Step 3a: Success -->
          <div v-if="step === 'success'" class="space-y-6 text-center">
            <div
              class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/30"
            >
              <CheckCircle2 :size="32" class="text-emerald-600" />
            </div>
            <div>
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Paiement confirme !
              </h2>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Le booking est maintenant confirme. La Face a ete notifiee.
              </p>
            </div>
            <Button class="w-full" @click="handleViewBooking">
              Voir le booking
            </Button>
          </div>

          <!-- Step 3b: Failed -->
          <div v-if="step === 'failed'" class="space-y-6 text-center">
            <div
              class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30"
            >
              <AlertCircle :size="32" class="text-red-600" />
            </div>
            <div>
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Paiement echoue
              </h2>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ error || 'Le paiement n\'a pas pu aboutir. Veuillez reessayer.' }}
              </p>
            </div>
            <Button class="w-full" @click="handleRetry">
              Reessayer
            </Button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.overlay-enter-active,
.overlay-leave-active {
  transition: opacity 0.2s ease;
}

.overlay-enter-from,
.overlay-leave-to {
  opacity: 0;
}
</style>
