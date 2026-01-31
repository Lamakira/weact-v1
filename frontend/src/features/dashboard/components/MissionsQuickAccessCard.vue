<script setup lang="ts">
/**
 * MissionsQuickAccessCard Component
 * Quick access card for browsing available missions (FR54).
 * Shows count of available missions as a badge.
 * Pattern matched to existing WalletCard component styles.
 */
import { Search } from 'lucide-vue-next'
import { Skeleton } from '@/components/ui/skeleton'

defineProps<{
  count: number
  isLoading: boolean
}>()

const emit = defineEmits<{
  click: []
}>()

function handleClick(): void {
  emit('click')
}
</script>

<template>
  <div
    class="relative bg-white border border-gray-100 rounded-2xl p-4 shadow-sm cursor-pointer hover:shadow-md hover:border-primary/30 transition-all overflow-hidden"
    aria-label="Voir les missions disponibles"
    role="button"
    tabindex="0"
    data-testid="missions-quick-access-card"
    @click="handleClick"
    @keydown.enter="handleClick"
    @keydown.space.prevent="handleClick"
  >
    <div class="flex flex-col gap-3">
      <div class="flex items-center justify-between">
        <!-- Icon Container -->
        <div
          class="h-11 w-11 rounded-xl bg-primary/10 flex items-center justify-center border border-primary/20"
          data-testid="missions-card-icon"
        >
          <Search class="h-5 w-5 text-primary" />
        </div>

        <!-- Count Badge -->
        <template v-if="isLoading">
          <Skeleton class="h-6 w-10 rounded-full" data-testid="missions-card-badge-loading" />
        </template>
        <template v-else-if="count > 0">
          <span
            class="inline-flex items-center justify-center min-w-[1.75rem] h-7 px-2 rounded-full text-xs font-bold bg-primary text-white"
            data-testid="missions-card-badge"
          >
            {{ count }}
          </span>
        </template>
      </div>

      <div class="space-y-0.5">
        <!-- Title -->
        <h3
          class="text-base font-semibold text-gray-900"
          data-testid="missions-card-title"
        >
          Voir les missions
        </h3>

        <!-- Subtitle -->
        <p
          class="text-sm text-gray-500"
          data-testid="missions-card-subtitle"
        >
          Découvrez les opportunités disponibles
        </p>
      </div>
    </div>

    <!-- Subdued Decorative Element -->
    <div class="absolute -bottom-2 -right-2 opacity-5 pointer-events-none">
      <Search class="h-16 w-16 text-gray-900" />
    </div>
  </div>
</template>

<style scoped>
.text-primary {
  color: var(--color-weact, #198496);
}
.bg-primary {
  background-color: var(--color-weact, #198496);
}
.bg-primary\/10 {
  background-color: rgba(25, 132, 150, 0.1);
}
.border-primary\/20 {
  border-color: rgba(25, 132, 150, 0.2);
}
.border-primary\/30 {
  border-color: rgba(25, 132, 150, 0.3);
}
.hover\:border-primary\/30:hover {
  border-color: rgba(25, 132, 150, 0.3);
}
</style>
