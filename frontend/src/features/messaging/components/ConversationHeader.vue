<script setup lang="ts">
import { computed } from 'vue'
import { ChevronLeft, RefreshCw } from 'lucide-vue-next'
import type { OtherParticipant } from '../types'

const props = withDefaults(defineProps<{
  participant: OtherParticipant
  missionTitle: string
  isRefreshing?: boolean
  showBack?: boolean
}>(), {
  isRefreshing: false,
  showBack: true,
})

const emit = defineEmits<{
  (e: 'back'): void
  (e: 'refresh'): void
}>()

const initials = computed(() => {
  return props.participant.name
    .split(' ')
    .map((n) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
})

const participantLabel = computed(() => {
  return props.participant.type === 'producer' ? 'Producteur' : 'Face'
})
</script>

<template>
  <header
    class="sticky top-0 z-20 flex w-full items-center gap-3 border-b border-border bg-background px-4 py-3"
  >
    <!-- Back Button -->
    <button
      v-if="showBack"
      type="button"
      class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-primary/5 hover:text-foreground"
      aria-label="Retour"
      @click="emit('back')"
    >
      <ChevronLeft class="size-6" />
    </button>

    <!-- Participant Info -->
    <div class="flex flex-1 items-center gap-3 overflow-hidden">
      <div
        class="relative flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary/10 text-primary"
      >
        <img
          v-if="participant.profile_photo_thumbnail_url || participant.photo_url"
          :src="participant.profile_photo_thumbnail_url || participant.photo_url || undefined"
          :alt="participant.name"
          class="size-full rounded-full object-cover"
          loading="lazy"
        />
        <span v-else class="text-sm font-semibold tracking-wider">
          {{ initials }}
        </span>
      </div>

      <div class="flex min-w-0 flex-col">
        <h2 class="truncate text-sm font-bold leading-tight text-foreground sm:text-base">
          {{ participant.name }}
        </h2>
        <p class="truncate text-xs text-muted-foreground">
          <span class="font-medium">{{ participantLabel }}</span> · {{ missionTitle }}
        </p>
      </div>
    </div>

    <!-- Refresh Button -->
    <button
      type="button"
      :disabled="isRefreshing"
      class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-primary/5 hover:text-foreground disabled:pointer-events-none disabled:opacity-50"
      aria-label="Actualiser les messages"
      @click="emit('refresh')"
    >
      <RefreshCw :class="['size-5', isRefreshing && 'animate-spin']" />
    </button>
  </header>
</template>
