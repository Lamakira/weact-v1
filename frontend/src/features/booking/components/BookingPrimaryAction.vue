<script setup lang="ts">
import { Loader2 } from 'lucide-vue-next'
import BookingSwap from './BookingSwap.vue'
import type { BookingPrimaryActionModel } from '../types'

defineProps<{
  action: BookingPrimaryActionModel
}>()

defineEmits<{ (e: 'click'): void }>()
</script>

<template>
  <BookingSwap>
    <div :key="action.key" :data-testid="action.testid" class="inline-flex">
      <button
        type="button"
        class="primary-action inline-flex items-center justify-center gap-2 rounded-[9px] border px-4 py-2.5 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-50"
        :class="
          action.kind === 'ghost'
            ? 'border-gray-300 bg-white text-gray-700'
            : 'border-weact bg-weact text-white'
        "
        :disabled="action.disabled || action.loading"
        @click="$emit('click')"
      >
        <Loader2 v-if="action.loading" class="h-4 w-4 animate-spin" />
        <component :is="action.icon" v-else-if="action.icon" class="h-4 w-4" />
        {{ action.label }}
      </button>
    </div>
  </BookingSwap>
</template>

<style scoped>
.primary-action {
  transition: transform 160ms cubic-bezier(0.23, 1, 0.32, 1);
}

.primary-action:not(:disabled):active {
  transform: scale(0.97);
}

@media (hover: hover) and (pointer: fine) {
  .primary-action:not(:disabled):hover {
    filter: brightness(1.05);
  }
}

@media (prefers-reduced-motion: reduce) {
  .primary-action {
    transition: none;
  }

  .primary-action:not(:disabled):active {
    transform: none;
  }
}
</style>
