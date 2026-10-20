<script setup lang="ts">
import BookingPrimaryAction from './BookingPrimaryAction.vue'
import type { BookingPrimaryActionModel } from '../types'

defineProps<{
  headline: string
  /** Échéance restante ou, à défaut, qui doit agir. */
  sub: string
  action: BookingPrimaryActionModel
}>()

defineEmits<{ (e: 'action'): void }>()
</script>

<template>
  <div
    class="fixed inset-x-0 bottom-0 z-30 flex items-center gap-2 border-t border-gray-200 bg-white px-3 pt-2.5 pb-[calc(10px+env(safe-area-inset-bottom,0px))] sm:hidden"
    data-testid="booking-action-bar"
  >
    <div class="min-w-0 text-xs leading-snug text-gray-500">
      <b class="block truncate text-[13px] font-semibold text-gray-900">{{ headline }}</b>
      {{ sub }}
    </div>
    <div class="ml-auto shrink-0">
      <BookingPrimaryAction :action="action" @click="$emit('action')" />
    </div>
  </div>
</template>
