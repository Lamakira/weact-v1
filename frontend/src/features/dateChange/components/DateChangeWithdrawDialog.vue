<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import { AlertTriangle, Loader2 } from 'lucide-vue-next'
import { useDismissOnDeactivate } from '@/composables/useDismissOnDeactivate'
import { formatMoney } from '@/utils/money'
import { formatDayLong } from '../utils/dateChangeFormat'

/** Confirmation du retrait d'une Face après un changement de date : jamais de retrait direct. */
const props = defineProps<{
  isOpen: boolean
  /** Nouvelle date de tournage (AAAA-MM-JJ). */
  newDate: string
  /** Montant connu de la page (net payé, sinon budget proposé) ; null s'il est inconnu. */
  amount: number | null
  isSubmitting?: boolean
}>()

const emit = defineEmits<{
  confirm: []
  cancel: []
}>()

useDismissOnDeactivate(() => props.isOpen, () => emit('cancel'))

function handleClose(): void {
  if (props.isSubmitting) return
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
        data-testid="date-change-withdraw-dialog"
        @click.self="handleClose"
      >
        <div
          class="max-h-[calc(100dvh-2rem)] w-full max-w-md overflow-y-auto rounded-xl border border-gray-200 bg-white p-5 shadow-xl sm:p-6"
          role="dialog"
          aria-modal="true"
          aria-labelledby="date-change-withdraw-title"
        >
          <div class="mb-4 flex items-start gap-3">
            <div class="rounded-full bg-amber-100 p-2 text-amber-700">
              <AlertTriangle class="h-5 w-5" aria-hidden="true" />
            </div>
            <div class="min-w-0">
              <h3 id="date-change-withdraw-title" class="text-lg font-semibold text-gray-900">Quitter cette mission ?</h3>
              <p class="mt-1 text-sm text-gray-500">
                Vous ne pouvez pas venir le {{ formatDayLong(newDate) }}.
              </p>
            </div>
          </div>

          <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-[13.5px] text-amber-800">
            <b class="block font-semibold text-amber-900">Ce que cela change</b>
            <template v-if="amount !== null">Vous ne recevrez pas les {{ formatMoney(amount) }} de cette mission. </template>
            <template v-else>Vous ne recevrez pas la rémunération de cette mission. </template>
            Votre note ne bouge pas : ce retrait ne compte pas comme une annulation.
          </div>

          <div class="flex flex-wrap items-center justify-end gap-3">
            <button
              type="button"
              class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="isSubmitting"
              data-testid="date-change-withdraw-back"
              @click="handleClose"
            >
              Retour
            </button>
            <button
              type="button"
              class="flex-1 rounded-lg bg-weact px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-weact/90 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="isSubmitting"
              data-testid="date-change-withdraw-confirm"
              @click="emit('confirm')"
            >
              <Loader2 v-if="isSubmitting" class="mr-1 inline h-4 w-4 animate-spin" aria-hidden="true" />
              Quitter la mission
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
