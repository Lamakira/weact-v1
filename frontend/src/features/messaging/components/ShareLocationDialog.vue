<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Loader2, MapPin, X } from 'lucide-vue-next'
import { useDismissOnDeactivate } from '@/composables/useDismissOnDeactivate'
import { useModalFocusTrap } from '@/composables/useModalFocusTrap'

const props = defineProps<{
  isOpen: boolean
  /** Nom de l'autre partie, qui verra la position. */
  otherPartyName: string
  /** Localisation ou envoi en cours. */
  isBusy: boolean
  error: string | null
}>()

const emit = defineEmits<{
  (e: 'confirm'): void
  (e: 'cancel'): void
}>()

const dialogRef = ref<HTMLDivElement | null>(null)
const { prepareModalFocus, restoreFocus, trapModalFocus } = useModalFocusTrap()

// Fermeture quand la page hôte est désactivée par un <keep-alive> (overlay téléporté)
useDismissOnDeactivate(() => props.isOpen, () => emit('cancel'))

watch(
  () => props.isOpen,
  (open) => {
    if (open) void prepareModalFocus(dialogRef)
    else restoreFocus()
  },
  { immediate: true },
)

function onKeydown(event: KeyboardEvent): void {
  if (!props.isOpen) return
  if (event.key === 'Escape') {
    emit('cancel')
  } else if (event.key === 'Tab') {
    trapModalFocus(event, dialogRef.value)
  }
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
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
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
        data-testid="share-location-dialog"
        @click.self="emit('cancel')"
      >
        <div
          ref="dialogRef"
          class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl focus:outline-none"
          role="dialog"
          aria-modal="true"
          aria-labelledby="share-location-title"
          aria-describedby="share-location-text"
          tabindex="-1"
        >
          <button
            type="button"
            class="absolute right-4 top-4 flex size-9 items-center justify-center rounded-full text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-weact-600"
            aria-label="Fermer"
            @click="emit('cancel')"
          >
            <X class="size-4" />
          </button>

          <div class="p-6 sm:p-8">
            <div class="flex flex-col items-center text-center">
              <div class="mb-5 flex size-14 items-center justify-center rounded-full bg-weact-50 text-weact-600">
                <MapPin class="size-7" />
              </div>
              <h3 id="share-location-title" class="mb-3 text-xl font-bold text-gray-900">
                Envoyer votre position ?
              </h3>
              <p id="share-location-text" class="max-w-[320px] text-[15px] leading-relaxed text-gray-500">
                {{ otherPartyName }} verra votre position exacte dans cette discussion. Elle ne se mettra pas à jour si vous bougez.
              </p>
            </div>

            <p
              v-if="error"
              class="mt-5 rounded-lg bg-destructive/10 px-3 py-2 text-center text-[13px] font-medium text-destructive"
              role="alert"
              data-testid="share-location-error"
            >
              {{ error }}
            </p>

            <div class="mt-7 flex flex-col items-center justify-center gap-3 sm:flex-row">
              <button
                type="button"
                class="w-full rounded-lg border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 transition-colors hover:border-gray-300 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-300 sm:flex-1"
                data-testid="share-location-cancel"
                @click="emit('cancel')"
              >
                Annuler
              </button>
              <button
                type="button"
                :disabled="isBusy"
                class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-weact-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-weact-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-weact-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 sm:flex-1"
                data-testid="share-location-confirm"
                @click="emit('confirm')"
              >
                <Loader2 v-if="isBusy" class="size-4 animate-spin" aria-hidden="true" />
                Envoyer ma position
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
