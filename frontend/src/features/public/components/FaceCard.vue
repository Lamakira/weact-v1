<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { User } from 'lucide-vue-next'
import type { PublicFace } from '../services/publicFacesApi'

interface Props {
  face: PublicFace
}

const props = defineProps<Props>()
const route = useRoute()

// Compute availability indicator classes
const availabilityClasses = computed(() => {
  return props.face.is_available
    ? 'bg-green-500'
    : 'bg-gray-400'
})

const availabilityLabel = computed(() => {
  return props.face.is_available ? 'Disponible' : 'Indisponible'
})

// Use medium (800px WebP) for cards — much faster than full originals, still sharp on Retina
const photoUrl = computed(() => {
  return props.face.profile_photo_medium_url || props.face.profile_photo_url || props.face.profile_photo_thumbnail_url
})

const hasPhoto = computed(() => {
  return !!photoUrl.value
})

const profileUrl = computed(() => {
  const returnTo = encodeURIComponent(route.fullPath)
  return `/faces/${props.face.username}?returnTo=${returnTo}`
})
</script>

<template>
  <RouterLink
    :to="profileUrl"
    :data-testid="`face-card-${face.id}`"
    class="group/card relative block aspect-[4/5] overflow-hidden rounded-2xl border border-gray-100 shadow-sm hover:shadow-lg hover:border-[#198496]/30 transition-all duration-300 hover:scale-[1.02] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#198496] focus-visible:ring-offset-2"
    :aria-label="`Voir le profil de ${face.prenom}`"
  >
    <!-- Actual Photo -->
    <img
      v-if="hasPhoto"
      :src="photoUrl!"
      :alt="`Photo de ${face.prenom}`"
      class="absolute inset-0 h-full w-full object-cover transition-transform duration-500 group-hover/card:scale-110"
      loading="lazy"
    />

    <!-- Placeholder when no photo -->
    <div
      v-else
      class="absolute inset-0 flex h-full w-full items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200"
      :aria-label="`Pas de photo pour ${face.prenom}`"
    >
      <div class="flex flex-col items-center gap-2">
        <div class="rounded-full bg-white/80 p-4 shadow-sm">
          <User class="h-12 w-12 text-gray-400" />
        </div>
        <span class="text-xs font-medium text-gray-400">Pas de photo</span>
      </div>
    </div>

    <!-- Gradient Overlay -->
    <div
      class="absolute inset-x-0 bottom-0 h-2/3 bg-gradient-to-t from-black/60 to-transparent"
    />

    <!-- Availability Indicator -->
    <div
      class="absolute top-3 right-3 flex items-center gap-1.5 rounded-full bg-white/95 backdrop-blur-sm px-2 py-1 shadow-sm"
      :title="availabilityLabel"
    >
      <span
        :class="['h-2 w-2 rounded-full', availabilityClasses]"
        :aria-label="availabilityLabel"
      />
      <span class="text-[10px] font-medium text-gray-700">
        {{ availabilityLabel }}
      </span>
    </div>

    <!-- Info overlay (bottom) -->
    <div class="absolute inset-x-0 bottom-0 p-2.5 sm:p-3 flex flex-col gap-1">
      <p class="text-sm sm:text-base font-bold text-white truncate">
        {{ face.prenom }}
      </p>
      <p
        v-if="face.ville"
        class="text-xs sm:text-sm text-white/70 truncate"
      >
        {{ face.ville }}
      </p>
      <div class="flex flex-wrap gap-1 mt-0.5">
        <span
          v-for="cat in face.categories.slice(0, 2)"
          :key="cat.value"
          class="rounded-full bg-white/95 backdrop-blur-sm px-2 sm:px-3 py-0.5 sm:py-1 text-[8px] sm:text-[10px] font-bold uppercase tracking-widest text-[#198496] shadow-sm truncate max-w-[120px]"
        >
          {{ cat.label }}
        </span>
        <span
          v-if="face.categories.length > 2"
          class="rounded-full bg-white/80 backdrop-blur-sm px-2 py-0.5 sm:py-1 text-[8px] sm:text-[10px] font-bold text-[#198496]/70"
        >
          +{{ face.categories.length - 2 }}
        </span>
      </div>
    </div>
  </RouterLink>
</template>
