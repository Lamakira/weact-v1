<script setup lang="ts">
import { Loader2 } from 'lucide-vue-next'

interface Props {
  isAvailable: boolean
  isLoading?: boolean
  isSaving?: boolean
  error?: string | null
}

withDefaults(defineProps<Props>(), {
  isLoading: false,
  isSaving: false,
  error: null,
})

defineEmits<{
  toggle: []
}>()
</script>

<template>
  <div
    class="flex items-center justify-between py-3 px-4 border border-gray-100 rounded-xl bg-white shadow-sm transition-all"
    data-testid="availability-toggle-container"
  >
    <div class="flex flex-col gap-1.5">
      <span class="text-sm font-semibold text-foreground">Statut de disponibilité</span>
      <div
        class="inline-flex w-fit items-center rounded-full px-2.5 py-0.5 text-xs font-medium transition-all duration-300"
        :class="isAvailable ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
        data-testid="availability-status-badge"
      >
        {{ isAvailable ? 'Disponible' : 'Indisponible' }}
      </div>
    </div>

    <div class="relative flex items-center">
      <button
        type="button"
        role="switch"
        :aria-checked="isAvailable"
        aria-label="Basculer la disponibilité"
        :disabled="isLoading || isSaving"
        class="group relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full transition-colors duration-200 ease-in-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-weact focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70"
        :class="isAvailable ? 'bg-[var(--color-weact)]' : 'bg-gray-200'"
        data-testid="availability-toggle-button"
        @click="$emit('toggle')"
      >
        <span
          aria-hidden="true"
          class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out"
          :class="isAvailable ? 'translate-x-5' : 'translate-x-1'"
        />

        <!-- Loading state overlay -->
        <Transition
          enter-active-class="transition duration-100 ease-out"
          enter-from-class="opacity-0"
          enter-to-class="opacity-100"
          leave-active-class="transition duration-75 ease-in"
          leave-from-class="opacity-100"
          leave-to-class="opacity-0"
        >
          <div
            v-if="isSaving"
            class="absolute inset-0 flex items-center justify-center bg-white/40 rounded-full"
            data-testid="availability-loading-spinner"
          >
            <Loader2 class="h-3.5 w-3.5 animate-spin text-[var(--color-weact)]" />
          </div>
        </Transition>
      </button>
    </div>

    <!-- Error message -->
    <Transition
      enter-active-class="transition duration-100 ease-out"
      enter-from-class="opacity-0 -translate-y-1"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-75 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-1"
    >
      <p
        v-if="error"
        class="text-xs text-red-600 mt-2"
        data-testid="availability-error-message"
      >
        {{ error }}
      </p>
    </Transition>
  </div>
</template>
