<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { OtherParticipant } from '../types'

const props = withDefaults(
  defineProps<{
    participant: Pick<OtherParticipant, 'name' | 'photo_url' | 'profile_photo_thumbnail_url'>
    /** Classes de taille (ex. `size-9`) */
    sizeClass?: string
  }>(),
  { sizeClass: 'size-9' },
)

const src = computed(
  () => props.participant.profile_photo_thumbnail_url || props.participant.photo_url || null,
)
// Échec de chargement : même rendu que sans photo (initiales)
const failed = ref(false)
watch(src, () => {
  failed.value = false
})
const initials = computed(() =>
  props.participant.name
    .split(' ')
    .filter(Boolean)
    .map((part) => part[0])
    .join('')
    .toUpperCase()
    .slice(0, 2),
)
</script>

<template>
  <span
    class="relative inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-weact-600/10 text-[12px] font-semibold text-weact-700"
    :class="sizeClass"
    data-testid="participant-avatar"
  >
    <img
      v-if="src && !failed"
      :src="src"
      :alt="participant.name"
      class="size-full object-cover"
      loading="lazy"
      @error="failed = true"
    />
    <span v-else aria-hidden="true">{{ initials }}</span>
  </span>
</template>
