<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { ArrowRight, Zap } from 'lucide-vue-next'

// Shared constant for talent count display
const TALENT_COUNT_DISPLAY = '+500 talents'

interface Talent {
  id: number
  name: string
  category: string
  img: string
}

// --- Mock Talents (to be replaced with API data) ---
const mockTalents: Talent[] = [
  {
    id: 1,
    name: 'Adjoua',
    category: 'Actrice',
    img: 'https://images.unsplash.com/photo-1531123897727-8f129e1688ce?auto=format&fit=crop&q=80&w=300&h=300',
  },
  {
    id: 2,
    name: 'Koffi',
    category: 'Influenceur',
    img: 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&q=80&w=300&h=300',
  },
  {
    id: 3,
    name: 'Aïcha',
    category: 'Mannequin',
    img: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&q=80&w=300&h=300',
  },
  {
    id: 4,
    name: 'Moussa',
    category: 'Créateur',
    img: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&q=80&w=300&h=300',
  },
  {
    id: 5,
    name: 'Fatou',
    category: 'Figurant',
    img: 'https://images.unsplash.com/photo-1523824921871-d6f1a15151f1?auto=format&fit=crop&q=80&w=300&h=300',
  },
  {
    id: 6,
    name: 'Ibrahim',
    category: 'Acteur',
    img: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&q=80&w=300&h=300',
  },
  {
    id: 7,
    name: 'Mariama',
    category: 'Influenceuse',
    img: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=300&h=300',
  },
  {
    id: 8,
    name: 'Yao',
    category: 'Mannequin',
    img: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&q=80&w=300&h=300',
  },
]
</script>

<template>
  <section class="py-10 lg:py-12 relative overflow-hidden" data-testid="talents-showcase">
    <!-- Gradient Background Layer -->
    <div
      class="absolute inset-0 bg-gradient-to-r from-[#198496]/10 via-[#1a9fb5]/10 to-[#1bbad4]/10 pointer-events-none"
    ></div>
    <div
      class="absolute top-0 right-0 w-96 h-96 bg-[#198496] opacity-[0.05] blur-[120px] rounded-full"
    ></div>

    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Header -->
      <div class="text-center mb-12">
        <div class="flex items-center justify-center gap-2 mb-4">
          <div class="bg-[#198496] text-white p-1 rounded">
            <Zap class="w-3 h-3 fill-current" />
          </div>
          <span class="text-xs font-bold tracking-widest uppercase text-[#198496]">
            Vivier de talents
          </span>
        </div>
        <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-gray-900 mb-4">
          Découvrez nos talents
        </h2>
        <p class="text-gray-600 text-lg max-w-2xl mx-auto">
          Des profils variés et qualifiés pour tous vos projets audiovisuels
        </p>
      </div>

      <!-- Talents Showcase Carousel -->
      <div class="relative w-full overflow-hidden mb-12 py-6 group/carousel" data-testid="talents-carousel">
        <!-- Soft Edge Gradients (match section background) -->
        <div class="absolute inset-y-0 left-0 w-16 sm:w-32 bg-gradient-to-r from-white/90 to-transparent z-20 pointer-events-none"></div>
        <div class="absolute inset-y-0 right-0 w-16 sm:w-32 bg-gradient-to-l from-white/90 to-transparent z-20 pointer-events-none"></div>

        <div
          class="flex w-max gap-8 px-4 animate-marquee-loop group-hover/carousel:[animation-play-state:paused]"
        >
          <!-- Duplicate list for seamless infinite loop -->
          <div
            v-for="(talent, index) in [...mockTalents, ...mockTalents]"
            :key="`${talent.id}-${index}`"
            :data-testid="index < mockTalents.length ? `talent-card-${talent.id}` : `talent-card-${talent.id}-duplicate`"
            class="w-[160px] sm:w-[240px] lg:w-[300px] flex-none group/card relative aspect-[4/5] overflow-hidden rounded-[24px] border border-teal-50 shadow-sm hover:shadow-md hover:border-[#198496]/20 transition-all duration-500 hover:-translate-y-2"
          >
            <img
              :src="talent.img"
              :alt="talent.name"
              class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover/card:scale-110"
              loading="lazy"
            />
            <!-- Gradient overlay -->
            <div class="absolute inset-x-0 bottom-0 h-2/3 bg-gradient-to-t from-black/60 to-transparent" />
            <!-- Info overlay -->
            <div class="absolute inset-x-0 bottom-0 p-4 flex items-end justify-between">
              <p class="text-base font-bold text-white">{{ talent.name }}</p>
              <span class="inline-block px-3 py-1 bg-white/95 backdrop-blur-sm rounded-full text-[10px] font-bold uppercase tracking-widest text-[#198496] shadow-sm shrink-0">
                {{ talent.category }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- CTA -->
      <div class="text-center">
        <RouterLink
          to="/faces"
          class="group inline-flex items-center gap-2 bg-[#198496] text-white px-8 py-4 rounded-full font-bold shadow-lg hover:shadow-[#198496]/20 hover:scale-105 transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#198496]/10"
          data-testid="talents-cta"
        >
          Explorer tous les talents
          <ArrowRight class="w-5 h-5 group-hover:translate-x-1 transition-transform" />
        </RouterLink>
        <p class="mt-4 text-sm text-gray-500 font-medium">
          {{ TALENT_COUNT_DISPLAY }} disponibles sur la plateforme
        </p>
      </div>
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
