<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useBookingPayment } from '../composables/useBookingPayment'
import { PAYMENT_MODES, PHONE_COUNTRIES, type Booking, type PaymentMode, type PhoneCountry } from '../types'
import { Button } from '@/components/ui/button'
import {
  X,
  Loader2,
  CheckCircle2,
  AlertCircle,
  Smartphone,
  CreditCard,
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

const selectedMode = ref<PaymentMode | null>(null)
const phoneNumber = ref('')
const phoneCountry = ref<PhoneCountry>('bj')
const elapsedSeconds = ref(0)
let timerInterval: ReturnType<typeof setInterval> | null = null

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

function startTimer(): void {
  elapsedSeconds.value = 0
  timerInterval = setInterval(() => {
    elapsedSeconds.value++
  }, 1000)
}

function stopTimer(): void {
  if (timerInterval) {
    clearInterval(timerInterval)
    timerInterval = null
  }
}

const formattedTimer = computed((): string => {
  const mins = Math.floor(elapsedSeconds.value / 60)
  const secs = elapsedSeconds.value % 60
  return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
})

const selectedCountryPrefix = computed(() => {
  const country = PHONE_COUNTRIES.find((c) => c.value === phoneCountry.value)
  return country?.prefix ?? '+229'
})

const canPay = computed(() => {
  return selectedMode.value && phoneNumber.value.length >= 6
})

async function handlePay(): Promise<void> {
  if (!selectedMode.value || !phoneNumber.value) return

  startTimer()
  const result = await initiatePayment(
    props.booking.id,
    selectedMode.value,
    phoneNumber.value,
    phoneCountry.value,
  )

  if (!result) {
    stopTimer()
  }
}

function handleRetry(): void {
  stopTimer()
  reset()
  selectedMode.value = null
  phoneNumber.value = ''
}

function handleClose(): void {
  stopPolling()
  stopTimer()
  reset()
  selectedMode.value = null
  phoneNumber.value = ''
  emit('update:modelValue', false)
}

function handleViewBooking(): void {
  emit('payment-success', props.booking)
  handleClose()
}

// Watch for successful payment via polling
watch(paymentStatus, (newStatus) => {
  if (newStatus === 'confirmed') {
    stopTimer()
  }
  if (newStatus === 'failed') {
    stopTimer()
  }
})

// Clean up on close
watch(
  () => props.modelValue,
  (isOpen) => {
    if (!isOpen) {
      stopPolling()
      stopTimer()
      reset()
      selectedMode.value = null
      phoneNumber.value = ''
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

          <!-- Step 1: Provider Selection -->
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
                  <span>Commission (15%)</span>
                  <span>+ {{ formatXOF(pricing.producerCommission) }}</span>
                </div>
                <div
                  class="flex justify-between border-t border-gray-200 pt-2 font-semibold text-gray-900 dark:border-gray-700 dark:text-white"
                >
                  <span>Total a payer</span>
                  <span>{{ formatXOF(pricing.totalProducerPays) }}</span>
                </div>
              </div>
            </div>

            <!-- Provider selection -->
            <div class="space-y-2">
              <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                Choisissez votre operateur
              </p>
              <div class="grid grid-cols-3 gap-3">
                <button
                  v-for="mode in PAYMENT_MODES"
                  :key="mode.value"
                  class="flex flex-col items-center gap-1 rounded-xl border-2 p-3 text-sm transition-all"
                  :class="
                    selectedMode === mode.value
                      ? 'border-emerald-500 bg-emerald-50 dark:border-emerald-400 dark:bg-emerald-900/20'
                      : 'border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600'
                  "
                  @click="selectedMode = mode.value"
                >
                  <Smartphone
                    :size="20"
                    :class="
                      selectedMode === mode.value
                        ? 'text-emerald-600'
                        : 'text-gray-400'
                    "
                  />
                  <span
                    class="font-medium"
                    :class="
                      selectedMode === mode.value
                        ? 'text-emerald-700 dark:text-emerald-300'
                        : 'text-gray-700 dark:text-gray-300'
                    "
                  >
                    {{ mode.label }}
                  </span>
                </button>
              </div>
            </div>

            <!-- Phone number input -->
            <div class="space-y-2">
              <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                Numero de telephone Mobile Money
              </p>
              <div class="flex gap-2">
                <select
                  v-model="phoneCountry"
                  class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                >
                  <option v-for="c in PHONE_COUNTRIES" :key="c.value" :value="c.value">
                    {{ c.prefix }} {{ c.label }}
                  </option>
                </select>
                <input
                  v-model="phoneNumber"
                  type="tel"
                  inputmode="numeric"
                  placeholder="64000001"
                  class="flex-1 rounded-lg border border-gray-200 px-3 py-2 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500"
                  maxlength="15"
                />
              </div>
            </div>

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
              :disabled="!canPay || isInitiating"
              @click="handlePay"
            >
              <Loader2 v-if="isInitiating" :size="16" class="mr-2 animate-spin" />
              <span>{{ isInitiating ? 'Connexion...' : 'Payer' }}</span>
            </Button>
          </div>

          <!-- Step 2: Waiting -->
          <div v-if="step === 'waiting'" class="space-y-6 text-center">
            <div
              class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30"
            >
              <Loader2 :size="32" class="animate-spin text-amber-600" />
            </div>
            <div>
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Confirmez sur votre telephone...
              </h2>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Un message USSD a ete envoye. Composez votre code PIN pour confirmer.
              </p>
            </div>
            <div class="text-3xl font-mono font-semibold text-gray-700 dark:text-gray-300">
              {{ formattedTimer }}
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
