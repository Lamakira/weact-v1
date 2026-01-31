<script setup lang="ts">
/**
 * MessagesCard Component
 * Quick access card for viewing messages/conversations.
 * Pattern matched to existing WalletCard and MissionsQuickAccessCard styles.
 */
import { MessageCircle } from 'lucide-vue-next'

const emit = defineEmits<{
  click: []
}>()

/** Emit click event for parent navigation handling */
function handleClick(): void {
  emit('click')
}
</script>

<template>
  <div
    class="relative bg-white border border-gray-100 rounded-2xl p-4 shadow-sm cursor-pointer hover:shadow-md hover:border-blue-200 transition-all overflow-hidden"
    aria-label="Voir les messages"
    role="button"
    tabindex="0"
    data-testid="messages-card"
    @click="handleClick"
    @keydown.enter="handleClick"
    @keydown.space.prevent="handleClick"
  >
    <div class="flex flex-col gap-3">
      <div class="flex items-center justify-between">
        <!-- Icon Container -->
        <div
          class="h-11 w-11 rounded-xl bg-blue-50 flex items-center justify-center border border-blue-100"
          data-testid="messages-card-icon"
        >
          <MessageCircle class="h-5 w-5 text-blue-500" />
        </div>
      </div>

      <div class="space-y-0.5">
        <!-- Title -->
        <h3
          class="text-base font-semibold text-gray-900"
          data-testid="messages-card-title"
        >
          Messages
        </h3>

        <!-- Subtitle -->
        <p
          class="text-sm text-gray-500"
          data-testid="messages-card-subtitle"
        >
          Discussions avec les producteurs
        </p>
      </div>
    </div>

    <!-- Subdued Decorative Element -->
    <div class="absolute -bottom-2 -right-2 opacity-5 pointer-events-none">
      <MessageCircle class="h-16 w-16 text-gray-900" />
    </div>
  </div>
</template>
