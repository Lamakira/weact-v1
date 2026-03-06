<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { AlertTriangle, Loader2 } from 'lucide-vue-next'
import {
  BookingStatus,
  CANCELLATION_REASONS,
  type Booking,
  type CancellationReasonValue,
} from '../types'

const props = defineProps<{
  booking: Booking
  isOpen: boolean
  isCancelling: boolean
}>()

const emit = defineEmits<{
  confirm: [reason: CancellationReasonValue]
  cancel: []
}>()

const selectedReason = ref<CancellationReasonValue | ''>('')

const isPaid = computed(() => props.booking.status === BookingStatus.PAID)
const retainedAmount = computed(() => Math.round(props.booking.montant_total_producteur * 0.15))
const refundAmount = computed(() => Math.round(props.booking.montant_total_producteur * 0.85))
const canConfirm = computed(() => selectedReason.value !== '' && !props.isCancelling)

watch(
  () => props.isOpen,
  (isOpen) => {
    if (isOpen) {
      selectedReason.value = ''
    }
  },
)

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'XOF',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount)
}

function handleConfirm(): void {
  if (!selectedReason.value || props.isCancelling) return
  emit('confirm', selectedReason.value)
}

function handleClose(): void {
  if (props.isCancelling) return
  emit('cancel')
}

function handleKeydown(e: KeyboardEvent): void {
  if (e.key === 'Escape' && props.isOpen) handleClose()
}

onMounted(() => document.addEventListener('keydown', handleKeydown))
onUnmounted(() => document.removeEventListener('keydown', handleKeydown))
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
        @click.self="handleClose"
      >
        <div class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-6 shadow-xl">
          <div class="mb-4 flex items-start gap-3">
            <div class="rounded-full bg-red-100 p-2 text-red-600">
              <AlertTriangle class="h-5 w-5" />
            </div>
            <div>
              <h3 class="text-lg font-semibold text-gray-900">Annuler le booking</h3>
              <p class="mt-1 text-sm text-gray-500">
                Cette action est définitive.
              </p>
            </div>
          </div>

          <div v-if="isPaid" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
            <p class="mb-2 text-sm font-medium text-amber-900">Conséquences financières</p>
            <div class="space-y-1.5 text-sm text-amber-800">
              <div class="flex items-center justify-between">
                <span>Montant payé</span>
                <span class="font-medium">{{ formatCurrency(booking.montant_total_producteur) }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span>15% retenus par WEACT</span>
                <span class="font-medium">{{ formatCurrency(retainedAmount) }}</span>
              </div>
              <div class="flex items-center justify-between border-t border-amber-200 pt-2 text-emerald-700">
                <span class="font-semibold">Montant remboursé</span>
                <span class="font-semibold">{{ formatCurrency(refundAmount) }}</span>
              </div>
            </div>
          </div>

          <p v-else class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-600">
            Le booking sera annulé immédiatement, sans transaction financière.
          </p>

          <div class="mb-5">
            <label for="cancellation-reason" class="mb-1.5 block text-sm font-medium text-gray-700">
              Raison de l'annulation *
            </label>
            <select
              id="cancellation-reason"
              v-model="selectedReason"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 outline-none focus:border-weact focus:ring-2 focus:ring-weact/20"
            >
              <option disabled value="">Sélectionnez une raison</option>
              <option
                v-for="reason in CANCELLATION_REASONS"
                :key="reason.value"
                :value="reason.value"
              >
                {{ reason.label }}
              </option>
            </select>
          </div>

          <div class="flex items-center justify-end gap-3">
            <button
              class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="isCancelling"
              @click="handleClose"
            >
              Retour
            </button>
            <button
              class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="!canConfirm"
              @click="handleConfirm"
            >
              <Loader2 v-if="isCancelling" class="mr-1 inline h-4 w-4 animate-spin" />
              Confirmer l'annulation
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
