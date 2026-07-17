<script setup lang="ts">
import { computed, watch } from 'vue'
import {
  useUgcCommissionPayment,
  type UgcPaymentOwnerKind,
} from '@/composables/useUgcCommissionPayment'
import { Button } from '@/components/ui/button'
import { X, Loader2, CheckCircle2, AlertCircle, ShieldCheck, ExternalLink } from 'lucide-vue-next'
import PayTile from './PayTile.vue'
import { useDismissOnDeactivate } from '@/composables/useDismissOnDeactivate'

const props = defineProps<{
  modelValue: boolean
  kind: UgcPaymentOwnerKind
  ownerId: string
  amount: number
  reference?: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  settled: []
}>()

// Close when the host page is deactivated by <keep-alive> — the teleported
// overlay would otherwise stay in <body> on top of the next page. (Same
// semantics as before keep-alive: navigating away dismissed the tunnel.)
useDismissOnDeactivate(() => props.modelValue, () => emit('update:modelValue', false))

const { isInitiating, paymentStatus, error, initiate, stopPolling, reset } =
  useUgcCommissionPayment()

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

const successSubtitle = computed((): string =>
  props.kind === 'booking'
    ? 'Votre demande a été envoyée à la Face.'
    : 'Votre mission est maintenant publiée.',
)

// RH.2 : le booking règle le total (cash + frais service, séquestré) ; la mission paie sa commission.
const title = computed((): string =>
  props.kind === 'booking' ? 'Paiement du règlement' : 'Paiement de la commission',
)

const reassurance = computed((): string =>
  props.kind === 'booking'
    ? 'Paiement sécurisé par FedaPay. La rémunération est séquestrée par WeAct et versée à la Face après validation des vidéos.'
    : "Paiement sécurisé par FedaPay. La commission n'est encaissée qu'après acceptation par la Face.",
)

const refShort = computed((): string => (props.reference ?? props.ownerId).slice(0, 8).toUpperCase())

function formatXOF(amount: number): string {
  return new Intl.NumberFormat('fr-FR').format(amount) + ' FCFA'
}

async function handlePay(): Promise<void> {
  await initiate(props.kind, props.ownerId)
}

function handleRetry(): void {
  reset()
}

function handleClose(): void {
  stopPolling()
  reset()
  emit('update:modelValue', false)
}

function handleDone(): void {
  emit('settled')
  handleClose()
}

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
        data-testid="ugc-payment-overlay"
      >
        <div class="relative mx-4 w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
          <!-- Close (hidden while waiting) -->
          <button
            v-if="step !== 'waiting'"
            class="absolute right-4 top-4 rounded-full p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
            @click="handleClose"
          >
            <X :size="20" />
          </button>

          <!-- Step: select -->
          <div v-if="step === 'select'" class="space-y-5">
            <div class="text-center">
              <h2 class="text-lg font-semibold text-gray-900">{{ title }}</h2>
              <p class="mt-1 text-2xl font-bold text-weact">{{ formatXOF(amount) }}</p>
            </div>

            <div class="space-y-2">
              <PayTile provider="mtn" />
              <PayTile provider="moov" />
              <PayTile provider="fedapay" />
            </div>

            <div class="flex items-start gap-2 rounded-lg bg-weact/[0.04] p-3 text-xs text-gray-600">
              <ShieldCheck :size="16" class="mt-0.5 flex-shrink-0 text-weact" />
              <span>{{ reassurance }}</span>
            </div>

            <div
              v-if="error"
              class="flex items-center gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-600"
            >
              <AlertCircle :size="16" />
              <span>{{ error }}</span>
            </div>

            <Button
              class="w-full"
              :disabled="isInitiating"
              data-testid="ugc-pay-button"
              @click="handlePay"
            >
              <Loader2 v-if="isInitiating" :size="16" class="mr-2 animate-spin" />
              <ExternalLink v-else :size="16" class="mr-2" />
              {{ isInitiating ? 'Chargement...' : 'Payer via FedaPay' }}
            </Button>
          </div>

          <!-- Step: waiting -->
          <div v-else-if="step === 'waiting'" class="space-y-6 text-center">
            <Loader2 :size="32" class="mx-auto animate-spin text-amber-600" />
            <div>
              <h2 class="text-lg font-semibold text-gray-900">En attente de votre paiement...</h2>
              <p class="mt-1 text-sm text-gray-500">
                Complétez le paiement dans l'onglet FedaPay ouvert. Cette page se mettra à jour
                automatiquement.
              </p>
            </div>
            <Button variant="outline" class="w-full" @click="handleRetry">Recommencer</Button>
            <button
              class="text-sm text-gray-400 underline hover:text-gray-600"
              @click="handleClose"
            >
              Annuler
            </button>
          </div>

          <!-- Step: success -->
          <div v-else-if="step === 'success'" class="space-y-6 text-center">
            <CheckCircle2 :size="32" class="mx-auto text-emerald-600" />
            <div>
              <h2 class="text-lg font-semibold text-gray-900">Commission payée</h2>
              <p class="mt-1 text-sm text-gray-500">{{ successSubtitle }}</p>
              <div
                class="mx-auto mt-4 inline-block rounded-md bg-gray-50 px-3 py-2 font-mono text-[11px] text-gray-700"
              >
                Réf. {{ refShort }}
              </div>
            </div>
            <Button class="w-full" data-testid="ugc-done-button" @click="handleDone">Terminé</Button>
          </div>

          <!-- Step: failed -->
          <div v-else class="space-y-6 text-center">
            <AlertCircle :size="32" class="mx-auto text-red-600" />
            <div>
              <h2 class="text-lg font-semibold text-gray-900">Paiement échoué</h2>
              <p class="mt-1 text-sm text-gray-500">
                {{ error || 'Le paiement n\'a pas pu aboutir. Veuillez réessayer.' }}
              </p>
            </div>
            <Button class="w-full" data-testid="ugc-retry-button" @click="handleRetry">Réessayer</Button>
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
