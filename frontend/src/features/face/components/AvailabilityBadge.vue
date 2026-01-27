<script setup lang="ts">
defineProps<{
  isAvailable: boolean
}>()
</script>

<template>
  <div
    class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-medium leading-none transition-colors select-none"
    :class="isAvailable
      ? 'bg-green-100 text-green-800'
      : 'bg-gray-100 text-gray-600'"
    data-testid="availability-badge"
  >
    <span
      class="h-1 w-1 shrink-0 rounded-full"
      :class="isAvailable ? 'bg-green-500' : 'bg-gray-400'"
      data-testid="availability-badge-dot"
    />
    <span data-testid="availability-badge-text">{{ isAvailable ? 'Disponible' : 'Indisponible' }}</span>
  </div>
</template>
