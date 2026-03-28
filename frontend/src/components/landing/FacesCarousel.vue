<script setup lang="ts">
import { ref, computed } from 'vue'
import { RouterLink } from 'vue-router'
import { Zap } from 'lucide-vue-next'
import type { LandingFace } from '@/features/landing/types'

const props = defineProps<{
  profiles: LandingFace[]
  totalCount?: number
}>()

// --- Carousel Pause State ---
const isPaused = ref(false)

// --- Dynamic card repetition to fill the marquee track ---
const CARD_SLOT_PX = 232 // 200px card + 32px gap
const MIN_SET_WIDTH = 1600 // minimum width (px) for one set to fill viewport

const marqueeProfiles = computed(() => {
  if (props.profiles.length === 0) return []
  const singleSetWidth = props.profiles.length * CARD_SLOT_PX
  const repeats = Math.max(1, Math.ceil(MIN_SET_WIDTH / singleSetWidth))
  const oneSet: LandingFace[] = []
  for (let i = 0; i < repeats; i++) {
    oneSet.push(...props.profiles)
  }
  return oneSet
})

// Scale animation duration: ~3s per card in one set, minimum 15s
const marqueeSpeed = computed(() => {
  return `${Math.max(15, marqueeProfiles.value.length * 3)}s`
})

const countDisplay = computed((): string | null => {
  if (props.totalCount && props.totalCount > 20) {
    return `+${props.totalCount} faces ont déjà rejoint l'aventure`
  }
  return null
})
</script>

<template>
  <section class="py-10 lg:py-12 relative overflow-hidden">
    <!-- Gradient Background Layer -->
    <div
      class="absolute inset-0 bg-gradient-to-r from-[#198496]/10 via-[#1a9fb5]/10 to-[#1bbad4]/10 pointer-events-none"
    ></div>
    <div
      class="absolute top-0 right-0 w-96 h-96 bg-[#198496] opacity-[0.05] blur-[120px] rounded-full"
    ></div>

    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-16 text-center">
      <!-- Badge -->
      <div class="flex items-center justify-center gap-2 mb-4">
        <div class="bg-[#198496] text-white p-1 rounded">
          <Zap class="w-3 h-3 fill-current" />
        </div>
        <span class="text-xs font-bold tracking-widest uppercase text-[#198496]"
          >Tu attends quoi ?</span
        >
      </div>

      <!-- Title -->
      <h2
        class="text-4xl sm:text-5xl lg:text-6xl xl:text-7xl font-black text-gray-900 leading-none mb-4"
        data-testid="cta-section-title"
      >
        DEVIENS <span class="text-[#198496]">UNE FACE</span>.
      </h2>
      <p class="text-gray-600 text-lg">Rejoins la 1ère plateforme de casting au Bénin</p>
    </div>

    <!-- Infinite Marquee Carousel -->
    <div class="relative flex overflow-hidden" data-testid="faces-carousel">
      <div
        class="flex animate-marquee gap-8"
        :class="{ 'paused': isPaused }"
        :style="{ animationDuration: marqueeSpeed }"
      >
        <!-- First set (repeated to fill viewport) -->
        <div
          v-for="(profile, idx) in marqueeProfiles"
          :key="`a-${idx}`"
          class="flex-shrink-0 w-[200px] transition-all duration-300 hover:scale-105"
          @mouseenter="isPaused = true"
          @mouseleave="isPaused = false"
        >
          <div
            class="relative group/card aspect-[4/5] overflow-hidden rounded-2xl border border-gray-100 shadow-md hover:border-[#198496]/50 hover:shadow-2xl transition-all"
          >
            <img
              :src="profile.profile_photo_url ?? profile.profile_photo_thumbnail_url ?? ''"
              :alt="profile.prenom"
              class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover/card:scale-110"
              loading="lazy"
            />
            <div class="absolute inset-x-0 bottom-0 h-2/3 bg-gradient-to-t from-black/60 to-transparent" />
            <div class="absolute inset-x-0 bottom-0 p-4 text-left">
              <p class="font-bold text-white">{{ profile.prenom }}</p>
              <p class="text-sm text-white/70">{{ profile.categories[0]?.label ?? 'Talent' }}</p>
            </div>
          </div>
        </div>
        <!-- Duplicate set for seamless loop -->
        <div
          v-for="(profile, idx) in marqueeProfiles"
          :key="`b-${idx}`"
          class="flex-shrink-0 w-[200px] transition-all duration-300 hover:scale-105"
          @mouseenter="isPaused = true"
          @mouseleave="isPaused = false"
        >
          <div
            class="relative group/card aspect-[4/5] overflow-hidden rounded-2xl border border-gray-100 shadow-md hover:border-[#198496]/50 hover:shadow-2xl transition-all"
          >
            <img
              :src="profile.profile_photo_url ?? profile.profile_photo_thumbnail_url ?? ''"
              :alt="profile.prenom"
              class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover/card:scale-110"
              loading="lazy"
            />
            <div class="absolute inset-x-0 bottom-0 h-2/3 bg-gradient-to-t from-black/60 to-transparent" />
            <div class="absolute inset-x-0 bottom-0 p-4 text-left">
              <p class="font-bold text-white">{{ profile.prenom }}</p>
              <p class="text-sm text-white/70">{{ profile.categories[0]?.label ?? 'Talent' }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- CTA -->
    <div class="flex flex-col items-center mt-16 px-4">
      <RouterLink
        to="/faces"
        class="bg-[#198496] text-white px-10 py-4 rounded-md font-bold shadow-lg hover:shadow-[#198496]/20 hover:scale-105 transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#198496]/10"
        data-testid="deviens-face-cta"
      >
        Explorer nos Faces
      </RouterLink>
      <p v-if="countDisplay" class="mt-4 text-sm text-gray-500 font-medium" data-testid="faces-count">
        {{ countDisplay }}
      </p>
    </div>
  </section>
</template>

<style scoped>
@keyframes marquee {
  0% {
    transform: translateX(0);
  }
  100% {
    transform: translateX(-50%);
  }
}

.animate-marquee {
  animation: marquee 30s linear infinite;
}

.animate-marquee.paused {
  animation-play-state: paused;
}
</style>
