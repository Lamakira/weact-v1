<script setup lang="ts">
import { ref, computed } from 'vue'
import { X, AlertCircle, Loader2, Smartphone } from 'lucide-vue-next'
import type { WithdrawPayload } from '../services/walletApi'

const props = defineProps<{
  balance: number
  withdrawalMode: 'manual' | 'fedapay'
  isWithdrawing: boolean
  error: string | null
}>()

const emit = defineEmits<{
  submit: [payload: WithdrawPayload]
  cancel: []
}>()

/** Minimum withdrawal amount in XOF — must mirror backend WithdrawWalletRequest::MIN_AMOUNT */
const MIN_AMOUNT = 500

/**
 * ARCEP Bénin official 4-digit EZAB prefixes per operator.
 * Source: arcep.bj
 */
const BENIN_PREFIXES: Record<string, string[]> = {
  mtn:     ['0142','0146','0150','0151','0152','0153','0154','0156','0157','0159','0161','0162','0166','0167','0169','0190','0191','0196','0197'],
  moov:    ['0145','0155','0158','0160','0163','0164','0165','0168','0194','0195','0198','0199'],
  celtiis: ['0120','0121','0122','0123','0124','0128','0129','0140','0141','0143','0144','0147','0148','0149','0192','0193'],
}

const PAYMENT_MODES: { value: WithdrawPayload['payment_mode']; label: string }[] = [
  { value: 'mtn', label: 'MTN MoMo' },
  { value: 'moov', label: 'Moov Money' },
  { value: 'celtiis', label: 'Celtiis Cash' },
  ...(import.meta.env.DEV ? [{ value: 'momo_test' as const, label: 'Test MoMo' }] : []),
]

const PHONE_COUNTRIES: { value: WithdrawPayload['phone_country']; label: string; prefix: string }[] = [
  { value: 'bj', label: 'Bénin', prefix: '+229' },
  { value: 'tg', label: 'Togo', prefix: '+228' },
  { value: 'ci', label: "Côte d'Ivoire", prefix: '+225' },
  { value: 'sn', label: 'Sénégal', prefix: '+221' },
  { value: 'bf', label: 'Burkina Faso', prefix: '+226' },
]

const amount = ref<number | null>(null)
const selectedMode = ref<WithdrawPayload['payment_mode'] | null>(null)
const phoneNumber = ref('')
const phoneCountry = ref<WithdrawPayload['phone_country']>('bj')

const formatCurrency = (value: number) =>
  new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'XOF',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value)

/** Inline error for amount field — null means valid. */
const amountError = computed((): string | null => {
  if (amount.value === null) return null
  if (amount.value < MIN_AMOUNT)
    return `Montant minimum : ${formatCurrency(MIN_AMOUNT)}`
  if (amount.value > props.balance)
    return `Solde insuffisant (disponible : ${formatCurrency(props.balance)})`
  return null
})

/** Inline error for phone field — null means valid. */
const phoneError = computed((): string | null => {
  if (!phoneNumber.value) return null

  const digits = phoneNumber.value.replace(/\D/g, '')

  if (!/^[0-9]{8,15}$/.test(digits))
    return 'Numéro invalide (8 à 15 chiffres requis)'

  if (phoneCountry.value === 'bj' && selectedMode.value && selectedMode.value in BENIN_PREFIXES) {
    if (digits.length !== 10)
      return 'Les numéros béninois doivent comporter 10 chiffres (ex : 0197XXXXXX)'

    const prefix = digits.substring(0, 4)
    if (!BENIN_PREFIXES[selectedMode.value]?.includes(prefix))
      return `Ce numéro ne correspond pas à un préfixe ${selectedMode.value.toUpperCase()} valide au Bénin`
  }

  return null
})

const canSubmit = computed(() =>
  amount.value !== null &&
  amountError.value === null &&
  selectedMode.value !== null &&
  phoneNumber.value.length >= 8 &&
  phoneError.value === null,
)

const isManualMode = computed(() => props.withdrawalMode === 'manual')

function handleSubmit(): void {
  if (!canSubmit.value || !selectedMode.value || amount.value === null) return
  emit('submit', {
    amount: amount.value,
    payment_mode: selectedMode.value,
    phone_number: phoneNumber.value,
    phone_country: phoneCountry.value,
  })
}
</script>

<template>
  <Teleport to="body">
    <Transition name="overlay">
      <div v-if="true" class="fixed inset-0 z-[200] flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="relative mx-4 w-full max-w-md rounded-xl bg-white shadow-2xl border border-gray-100 p-6">
          <!-- Header -->
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-base font-semibold text-gray-900">Retirer des fonds</h2>
            <button
              class="rounded-full p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
              :disabled="isWithdrawing"
              aria-label="Fermer"
              @click="emit('cancel')"
            >
              <X class="w-4 h-4" />
            </button>
          </div>

          <div class="space-y-5">
            <!-- Balance info -->
            <div class="rounded-md bg-gray-50 border border-gray-100 p-3">
              <p class="text-xs text-gray-500 uppercase tracking-wider font-medium">Solde disponible</p>
              <p class="text-lg font-bold text-gray-900 mt-0.5">{{ formatCurrency(balance) }}</p>
            </div>

            <!-- Amount input -->
            <div class="space-y-1.5">
              <label class="text-sm font-medium text-gray-700" for="withdraw-amount">
                Montant à retirer (XOF)
              </label>
              <input
                id="withdraw-amount"
                v-model.number="amount"
                type="number"
                inputmode="numeric"
                :min="MIN_AMOUNT"
                :max="balance"
                placeholder="Ex: 20000"
                class="w-full rounded-md border px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-1 transition-colors"
                :class="amountError ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200 focus:border-[#198496] focus:ring-[#198496]'"
              />
              <p v-if="amountError" class="text-xs text-red-600">{{ amountError }}</p>
            </div>

            <!-- Provider selection -->
            <div class="space-y-2">
              <p class="text-sm font-medium text-gray-700">Opérateur Mobile Money</p>
              <div class="grid grid-cols-3 gap-2">
                <button
                  v-for="mode in PAYMENT_MODES"
                  :key="mode.value"
                  type="button"
                  class="flex flex-col items-center gap-1 rounded-md border-2 p-3 text-xs transition-all"
                  :class="
                    selectedMode === mode.value
                      ? 'border-[#198496] bg-[#198496]/5 text-[#198496]'
                      : 'border-gray-200 hover:border-gray-300 text-gray-600'
                  "
                  @click="selectedMode = mode.value"
                >
                  <Smartphone
                    class="w-4 h-4"
                    :class="selectedMode === mode.value ? 'text-[#198496]' : 'text-gray-400'"
                  />
                  <span class="font-medium leading-tight text-center">{{ mode.label }}</span>
                </button>
              </div>
            </div>

            <!-- Phone input -->
            <div class="space-y-1.5">
              <label class="text-sm font-medium text-gray-700">Numéro Mobile Money</label>
              <div class="flex gap-2">
                <select
                  v-model="phoneCountry"
                  class="rounded-md border border-gray-200 bg-white px-2 py-2 text-sm text-gray-700 focus:border-[#198496] focus:outline-none focus:ring-1 focus:ring-[#198496] transition-colors"
                >
                  <option v-for="c in PHONE_COUNTRIES" :key="c.value" :value="c.value">
                    {{ c.prefix }} {{ c.label }}
                  </option>
                </select>
                <input
                  v-model="phoneNumber"
                  type="tel"
                  inputmode="numeric"
                  :placeholder="phoneCountry === 'bj' ? '0197XXXXXX' : '64000001'"
                  maxlength="15"
                  class="flex-1 rounded-md border px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-1 transition-colors"
                  :class="phoneError ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200 focus:border-[#198496] focus:ring-[#198496]'"
                />
              </div>
              <p v-if="phoneError" class="text-xs text-red-600">{{ phoneError }}</p>
            </div>

            <div
              v-if="isManualMode"
              class="rounded-md border border-amber-100 bg-amber-50 p-3 text-sm leading-relaxed text-amber-800"
            >
              Votre demande sera traitée manuellement sous 48h. Vous recevrez un email de confirmation dès que le virement est effectué.
            </div>

            <!-- Error -->
            <div
              v-if="error"
              class="flex items-center gap-2 rounded-md bg-red-50 border border-red-100 p-3 text-sm text-red-700"
            >
              <AlertCircle class="w-4 h-4 flex-shrink-0" />
              <span>{{ error }}</span>
            </div>

            <!-- Actions -->
            <div class="flex gap-3 pt-1">
              <button
                type="button"
                class="flex-1 text-sm font-medium text-gray-600 border border-gray-200 px-4 py-2 rounded-md hover:bg-gray-50 transition-colors disabled:opacity-50"
                :disabled="isWithdrawing"
                @click="emit('cancel')"
              >
                Annuler
              </button>
              <button
                type="button"
                class="flex-1 text-sm font-medium bg-[#198496] text-white px-4 py-2 rounded-md hover:bg-[#146c7a] transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                :disabled="!canSubmit || isWithdrawing"
                @click="handleSubmit"
              >
                <Loader2 v-if="isWithdrawing" class="w-4 h-4 animate-spin" />
                <span>
                  {{
                    isWithdrawing
                      ? isManualMode
                        ? 'Soumission en cours...'
                        : 'Retrait en cours...'
                      : isManualMode
                        ? 'Soumettre la demande'
                        : 'Retirer'
                  }}
                </span>
              </button>
            </div>
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
