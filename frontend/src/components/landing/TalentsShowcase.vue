<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { ArrowRight, Zap } from 'lucide-vue-next'
import type { LandingFace } from '@/features/landing/types'

const props = defineProps<{
  talents: LandingFace[]
  totalCount?: number
}>()

// --- Dynamic card repetition to fill the marquee track ---
// Cards are responsive: 160px (sm), 240px (md), 300px (lg) + 32px gap
// Use the largest card size for worst-case calculation
const CARD_SLOT_PX = 332 // 300px card + 32px gap
const MIN_SET_WIDTH = 1800 // minimum width (px) for one set

const marqueeTalents = computed(() => {
  if (props.talents.length === 0) return []
  const singleSetWidth = props.talents.length * CARD_SLOT_PX
  const repeats = Math.max(1, Math.ceil(MIN_SET_WIDTH / singleSetWidth))
  const oneSet: LandingFace[] = []
  for (let i = 0; i < repeats; i++) {
    oneSet.push(...props.talents)
  }
  return oneSet
})

// Scale animation duration: ~4s per card in one set, minimum 15s
const marqueeSpeed = computed(() => {
  return `${Math.max(15, marqueeTalents.value.length * 4)}s`
})

const countDisplay = computed((): string | null => {
  if (props.totalCount && props.totalCount > 20) {
    return `+${props.totalCount} talents disponibles sur la plateforme`
  }
  return null
})
</script>

<template>
  <section class="-mt-px py-10 lg:py-12 relative overflow-hidden bg-black" data-testid="talents-showcase">

    <!-- Header -->
    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center mb-12">
      <div class="flex items-center justify-center gap-2 mb-4">
        <div class="bg-[#198496] text-white p-1 rounded">
          <Zap class="w-3 h-3 fill-current" />
        </div>
        <span class="text-xs font-bold tracking-widest uppercase text-[#198496]">
          Vivier de talents
        </span>
      </div>
      <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-white mb-4">
        Découvrez nos talents
      </h2>
      <p class="text-gray-400 text-lg max-w-2xl mx-auto">
        Des profils variés et qualifiés pour tous vos projets audiovisuels
      </p>
    </div>

    <!-- Talents Showcase Carousel (full viewport width) -->
    <div class="relative z-10 w-full overflow-hidden mb-12 py-6 group/carousel" data-testid="talents-carousel">
      <!-- Edge Fade Overlays — single element per side, solid then fade -->
      <div class="absolute inset-y-0 left-0 w-44 sm:w-64 lg:w-96 z-20 pointer-events-none" style="background: linear-gradient(to right, rgb(0,0,0) 0%, rgb(0,0,0) 45%, rgba(0,0,0,0.85) 60%, rgba(0,0,0,0.3) 80%, transparent 100%)"></div>
      <div class="absolute inset-y-0 right-0 w-44 sm:w-64 lg:w-96 z-20 pointer-events-none" style="background: linear-gradient(to left, rgb(0,0,0) 0%, rgb(0,0,0) 45%, rgba(0,0,0,0.85) 60%, rgba(0,0,0,0.3) 80%, transparent 100%)"></div>

      <div
        class="flex w-max gap-8 px-4 animate-marquee-loop group-hover/carousel:[animation-play-state:paused]"
        :style="{ animationDuration: marqueeSpeed }"
      >
        <!-- Repeated + duplicated list for seamless infinite loop -->
        <div
          v-for="(talent, index) in [...marqueeTalents, ...marqueeTalents]"
          :key="`${talent.id}-${index}`"
          :data-testid="index < marqueeTalents.length ? `talent-card-${talent.id}` : `talent-card-${talent.id}-duplicate`"
          class="w-[160px] sm:w-[240px] lg:w-[300px] flex-none group/card relative aspect-[4/5] overflow-hidden rounded-[24px] transition-all duration-500 hover:-translate-y-2"
        >
          <img
            :src="talent.profile_photo_url ?? talent.profile_photo_thumbnail_url ?? ''"
            :alt="talent.prenom"
            class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover/card:scale-110"
            loading="lazy"
          />
          <!-- Gradient overlay -->
          <div class="absolute inset-x-0 bottom-0 h-2/3 bg-gradient-to-t from-black/60 to-transparent" />
          <!-- Info overlay -->
          <div class="absolute inset-x-0 bottom-0 p-3 sm:p-4 flex flex-col gap-1.5">
            <p class="text-sm sm:text-base font-bold text-white truncate">{{ talent.prenom }}</p>
            <span class="self-start px-2.5 py-0.5 bg-white/95 backdrop-blur-sm rounded-full text-[9px] sm:text-[10px] font-bold uppercase tracking-widest text-[#198496] shadow-sm">
              {{ talent.categorie_label }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- CTA -->
    <div class="relative z-10 text-center">
      <RouterLink
        to="/faces"
        class="group inline-flex items-center gap-2 bg-[#198496] text-white px-8 py-4 rounded-full font-bold shadow-lg hover:shadow-[#198496]/20 hover:scale-105 transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#198496]/10"
        data-testid="talents-cta"
      >
        Explorer tous les talents
        <ArrowRight class="w-5 h-5 group-hover:translate-x-1 transition-transform" />
      </RouterLink>
      <p v-if="countDisplay" class="mt-4 text-sm text-gray-400 font-medium" data-testid="talents-count">
        {{ countDisplay }}
      </p>
    </div>
  </section>
</template>

<style scoped>
@keyframes marquee-loop {
  0% {
    transform: translateX(0);
  }
  100% {
    transform: translateX(calc(-50% - 1rem));
  }
}

.animate-marquee-loop {
  animation: marquee-loop 40s linear infinite;
}

/* Accessibility: Respect user's motion preferences */
@media (prefers-reduced-motion: reduce) {
  .animate-marquee-loop {
    animation: none;
  }
}
</style>
