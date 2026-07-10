<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import { useDismissOnDeactivate } from '@/composables/useDismissOnDeactivate'
import { Trash2, AlertTriangle, X } from 'lucide-vue-next'

interface Props {
  isOpen: boolean
  title: string
  message: string
  confirmText?: string
  cancelText?: string
  variant?: 'danger' | 'warning'
  /** Désactive le bouton de confirmation (ex : contenu de slot obligatoire non rempli). */
  confirmDisabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  confirmText: 'Confirmer',
  cancelText: 'Annuler',
  variant: 'danger',
  confirmDisabled: false,
})

const emit = defineEmits<{
  (e: 'confirm'): void
  (e: 'cancel'): void
}>()

// Close when the host page is deactivated by <keep-alive> — the teleported
// overlay would otherwise stay in <body> on top of the next page.
useDismissOnDeactivate(() => props.isOpen, () => emit('cancel'))

const handleEscape = (e: KeyboardEvent) => {
  if (e.key === 'Escape' && props.isOpen) {
    emit('cancel')
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleEscape)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleEscape)
})
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
        @click.self="emit('cancel')"
      >
        <Transition
          appear
          enter-active-class="transition duration-300 ease-out"
          enter-from-class="opacity-0 scale-95 translate-y-2"
          enter-to-class="opacity-100 scale-100 translate-y-0"
          leave-active-class="transition duration-200 ease-in"
          leave-from-class="opacity-100 scale-100 translate-y-0"
          leave-to-class="opacity-0 scale-95 translate-y-2"
        >
          <div
            v-if="isOpen"
            class="relative w-full max-w-md overflow-hidden bg-white shadow-2xl rounded-2xl"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-title"
          >
            <!-- Close Button -->
            <button
              @click="emit('cancel')"
              class="absolute top-4 right-4 p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full transition-all duration-200"
              aria-label="Fermer"
            >
              <X class="w-4 h-4" />
            </button>

            <div class="p-6 sm:p-8">
              <div class="flex flex-col items-center text-center">
                <!-- Icon Header -->
                <div
                  :class="[
                    'flex items-center justify-center w-14 h-14 mb-5 rounded-full transition-colors',
                    variant === 'danger'
                      ? 'bg-red-50 text-red-500'
                      : 'bg-orange-50 text-orange-500',
                  ]"
                >
                  <Trash2 v-if="variant === 'danger'" class="w-7 h-7" />
                  <AlertTriangle v-else class="w-7 h-7" />
                </div>

                <!-- Content -->
                <h3 id="modal-title" class="text-xl font-bold text-gray-900 mb-3">
                  {{ title }}
                </h3>
                <p class="text-[15px] text-gray-500 leading-relaxed max-w-[320px]">
                  {{ message }}
                </p>
              </div>

              <!-- Contenu additionnel optionnel (ex : sélecteur de photos obligatoire) -->
              <div v-if="$slots.default" class="mt-6">
                <slot />
              </div>

              <!-- Actions -->
              <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
                <button
                  type="button"
                  @click="emit('cancel')"
                  class="w-full sm:flex-1 px-5 py-2.5 text-sm font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 hover:border-gray-300 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-gray-100"
                >
                  {{ cancelText }}
                </button>
                <button
                  type="button"
                  :disabled="confirmDisabled"
                  @click="emit('confirm')"
                  :class="[
                    'w-full sm:flex-1 px-5 py-2.5 text-sm font-semibold text-white rounded-lg transition-all duration-200 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
                    variant === 'danger'
                      ? 'bg-red-500 hover:bg-red-600 focus:ring-red-500'
                      : 'bg-weact-500 hover:bg-weact-600 focus:ring-weact-500',
                  ]"
                >
                  {{ confirmText }}
                </button>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
