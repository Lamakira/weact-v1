<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import type { PublicFace } from '../services/publicFacesApi'

interface Props {
  face: PublicFace
}

const props = defineProps<Props>()

// Compute availability indicator classes
const availabilityClasses = computed(() => {
  return props.face.is_available
    ? 'bg-green-500'
    : 'bg-gray-400'
})

const availabilityLabel = computed(() => {
  return props.face.is_available ? 'Disponible' : 'Indisponible'
})

// Default placeholder image when no photo
const imageUrl = computed(() => {
  return props.face.profile_photo_thumbnail_url || '/placeholder-avatar.png'
})
</script>

<template>
  <RouterLink
    :to="`/faces/${face.id}`"
    :data-testid="`face-card-${face.id}`"
    class="group/card relative block overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm hover:shadow-lg hover:border-[#198496]/30 transition-all duration-300 hover:scale-[1.02] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#198496] focus-visible:ring-offset-2"
    :aria-label="`Voir le profil de ${face.prenom}`"
  >
    <!-- Portrait Image -->
    <div class="relative aspect-[4/5] overflow-hidden bg-gray-100">
      <img
        :src="imageUrl"
        :alt="`Photo de ${face.prenom}`"
        class="h-full w-full object-cover transition-transform duration-500 group-hover/card:scale-110"
        loading="lazy"
      />

      <!-- Gradient Overlay -->
      <div
        class="absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-slate-900/60 via-slate-900/20 to-transparent"
      />

      <!-- Category Badge -->
      <div class="absolute bottom-3 left-3 right-3">
        <span
          class="inline-block rounded-full bg-white/95 backdrop-blur-sm px-3 py-1 text-[10px] font-bold uppercase tracking-widest text-[#198496] shadow-sm"
        >
          {{ face.categorie_label || face.categorie }}
        </span>
      </div>

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
    </div>

    <!-- Info Section -->
    <div class="p-4 text-center bg-white">
      <p
        class="text-base font-bold text-slate-800 group-hover/card:text-[#198496] transition-colors duration-300"
      >
        {{ face.prenom }}
      </p>
      <p
        v-if="face.ville"
        class="mt-0.5 text-sm text-gray-500"
      >
        {{ face.ville }}
      </p>
    </div>
  </RouterLink>
</template>
