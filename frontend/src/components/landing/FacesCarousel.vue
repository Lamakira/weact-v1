<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { Zap } from 'lucide-vue-next'

// --- Carousel Pause State ---
const isPaused = ref(false)

// --- Mock Profiles (to be replaced with API data) ---
const mockProfiles = [
  {
    id: 1,
    name: 'Kofi',
    age: 28,
    img: 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&q=80&w=200&h=250',
  },
  {
    id: 2,
    name: 'Amina',
    age: 23,
    img: 'https://images.unsplash.com/photo-1531123897727-8f129e1688ce?auto=format&fit=crop&q=80&w=200&h=250',
  },
  {
    id: 3,
    name: 'Fatou',
    age: 35,
    img: 'https://images.unsplash.com/photo-1523824921871-d6f1a15151f1?auto=format&fit=crop&q=80&w=200&h=250',
  },
  {
    id: 4,
    name: 'Malik',
    age: 19,
    img: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&q=80&w=200&h=250',
  },
  {
    id: 5,
    name: 'Zara',
    age: 27,
    img: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&q=80&w=200&h=250',
  },
  {
    id: 6,
    name: 'Aliou',
    age: 31,
    img: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&q=80&w=200&h=250',
  },
]
</script>

<template>
  <section class="py-16 lg:py-20 relative overflow-hidden">
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
      >
        <!-- First set -->
        <div
          v-for="profile in mockProfiles"
          :key="profile.id"
          class="flex-shrink-0 w-[200px] transition-all duration-300 hover:scale-105"
          @mouseenter="isPaused = true"
          @mouseleave="isPaused = false"
        >
          <div
            class="relative group/card overflow-hidden rounded-2xl border border-gray-100 shadow-md hover:border-[#198496]/50 hover:shadow-2xl transition-all"
          >
            <img
              :src="profile.img"
              :alt="profile.name"
              class="w-full aspect-[4/5] object-cover transition-transform duration-500 group-hover/card:scale-110"
              loading="lazy"
            />
            <div class="p-4 bg-white text-center">
              <p class="font-bold text-gray-900">{{ profile.name }}</p>
              <p class="text-sm text-gray-500">{{ profile.age }} ans</p>
            </div>
          </div>
        </div>
        <!-- Duplicate set for seamless loop -->
        <div
          v-for="profile in mockProfiles"
          :key="`dup-${profile.id}`"
          class="flex-shrink-0 w-[200px] transition-all duration-300 hover:scale-105"
          @mouseenter="isPaused = true"
          @mouseleave="isPaused = false"
        >
          <div
            class="relative group/card overflow-hidden rounded-2xl border border-gray-100 shadow-md hover:border-[#198496]/50 hover:shadow-2xl transition-all"
          >
            <img
              :src="profile.img"
              :alt="profile.name"
              class="w-full aspect-[4/5] object-cover transition-transform duration-500 group-hover/card:scale-110"
              loading="lazy"
            />
            <div class="p-4 bg-white text-center">
              <p class="font-bold text-gray-900">{{ profile.name }}</p>
              <p class="text-sm text-gray-500">{{ profile.age }} ans</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- CTA -->
    <div class="flex flex-col items-center mt-16 px-4">
      <RouterLink
        to="/register/face"
        class="bg-[#198496] text-white px-10 py-4 rounded-full font-bold shadow-lg hover:shadow-[#198496]/20 hover:scale-105 transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#198496]/10"
        data-testid="deviens-face-cta"
      >
        Je crée mon profil
      </RouterLink>
      <p class="mt-4 text-sm text-gray-500 font-medium">+500 faces ont déjà rejoint l'aventure</p>
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
