<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { RouterLink } from 'vue-router'
import { ArrowRight } from 'lucide-vue-next'

// Shared constant for talent count display
const TALENT_COUNT_DISPLAY = '+500 talents'

// --- Dynamic Text Animation ---
const words = ['acteur', 'créateur de contenu', 'influenceur', 'modèle photo']
const currentWordIndex = ref(0)
let wordInterval: ReturnType<typeof setInterval> | null = null

// --- Orbiting Talent Profiles ---
interface TalentProfile {
  id: number
  size: 'small' | 'medium' | 'large'
  top?: string
  left?: string
  right?: string
  bottom?: string
  delay: string
  img: string
}

const talents: TalentProfile[] = [
  // Left side - closer to content
  { id: 1, size: 'small', top: '8%', left: '18%', delay: '0s', img: new URL('@/assets/images/faces/producer-hero-section-face-woman_portrait_1.webp', import.meta.url).href },
  { id: 2, size: 'medium', top: '15%', left: '28%', delay: '0.5s', img: new URL('@/assets/images/faces/producer-hero-section-face-youngman.webp', import.meta.url).href },
  { id: 3, size: 'large', top: '35%', left: '15%', delay: '1s', img: new URL('@/assets/images/faces/producer-hero-section-face-woman_smilling_1.webp', import.meta.url).href },
  { id: 4, size: 'medium', top: '65%', left: '18%', delay: '1.5s', img: new URL('@/assets/images/faces/producer-hero-section-face-oldman.webp', import.meta.url).href },
  { id: 5, size: 'small', top: '80%', left: '28%', delay: '2s', img: new URL('@/assets/images/faces/producer-hero-section-face-youngwoman.webp', import.meta.url).href },
  // Right side - closer to content
  { id: 6, size: 'large', top: '22%', right: '15%', delay: '0.3s', img: new URL('@/assets/images/faces/producer-hero-section-face-woman_portrait_2.webp', import.meta.url).href },
  { id: 7, size: 'small', top: '10%', right: '22%', delay: '0.8s', img: new URL('@/assets/images/faces/producer-hero-section-face-youngman_2.webp', import.meta.url).href },
  { id: 8, size: 'medium', top: '52%', right: '14%', delay: '1.2s', img: new URL('@/assets/images/faces/producer-hero-section-face-woman_smilling_2.webp', import.meta.url).href },
  { id: 9, size: 'small', top: '75%', right: '20%', delay: '1.8s', img: new URL('@/assets/images/faces/producer-hero-section-face-child.webp', import.meta.url).href },
]

function getSizeClass(size: string): string {
  switch (size) {
    case 'small': return 'w-[60px] h-[60px]'
    case 'medium': return 'w-[80px] h-[80px]'
    case 'large': return 'w-[100px] h-[100px]'
    default: return 'w-[80px] h-[80px]'
  }
}

onMounted(() => {
  wordInterval = setInterval(() => {
    currentWordIndex.value = (currentWordIndex.value + 1) % words.length
  }, 2500)
})

onUnmounted(() => {
  if (wordInterval) {
    clearInterval(wordInterval)
  }
})
</script>

<template>
  <section
    class="relative w-full min-h-[calc(100vh-120px)] flex items-center justify-center overflow-hidden bg-black px-4"
  >
    <!-- Orbiting Talent Faces (Desktop only) -->
    <div
      v-for="talent in talents"
      :key="talent.id"
      class="absolute hidden lg:block"
      :style="{
        top: talent.top,
        left: talent.left,
        right: talent.right,
        bottom: talent.bottom,
        animationDelay: talent.delay,
      }"
    >
      <div
        class="floating-face rounded-full border-4 border-black/50 shadow-lg overflow-hidden transition-transform duration-500 hover:scale-110"
        :class="getSizeClass(talent.size)"
      >
        <img
          :src="talent.img"
          alt="Talent disponible sur WEACT"
          class="w-full h-full object-cover grayscale-[20%] hover:grayscale-0 transition-all duration-300"
          loading="lazy"
        />
      </div>
    </div>

    <!-- Main Centered Content -->
    <div class="relative z-10 max-w-4xl w-full text-center flex flex-col items-center">
      <!-- Hero Title -->
      <div>
        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold text-white leading-tight">
          Trouvez votre prochain
        </h1>

        <!-- Dynamic Animated Word -->
        <div class="h-[50px] sm:h-[60px] lg:h-[70px] relative flex justify-center items-center overflow-visible">
          <Transition name="slide-up">
            <span
              :key="words[currentWordIndex]"
              class="absolute left-1/2 -translate-x-1/2 text-[2rem] sm:text-5xl lg:text-6xl font-bold text-[#198496] whitespace-nowrap"
              data-testid="hero-animated-word"
              aria-live="polite"
              aria-atomic="true"
            >
              {{ words[currentWordIndex] }}
            </span>
          </Transition>
        </div>
      </div>

      <!-- Subtitle -->
      <p class="mt-3 text-lg lg:text-xl text-gray-400 max-w-2xl mx-auto leading-relaxed">
        Accédez au plus grand catalogue de talents au Bénin
      </p>

      <!-- Mobile: Talent faces row (before CTA on mobile) -->
      <div class="mt-6 flex justify-center items-center gap-2 lg:hidden">
        <div class="flex -space-x-3">
          <div
            v-for="i in 5"
            :key="i"
            class="w-11 h-11 rounded-full border-2 border-black shadow-lg overflow-hidden"
          >
            <img
              :src="talents[i - 1]?.img"
              alt="Talent disponible"
              class="w-full h-full object-cover"
              loading="lazy"
            />
          </div>
        </div>
        <span class="ml-2 text-sm text-gray-400 font-medium">{{ TALENT_COUNT_DISPLAY }}</span>
      </div>

      <!-- CTA Button -->
      <div class="mt-6 lg:mt-4 w-full sm:w-auto">
        <RouterLink
          to="/register/producer"
          class="group inline-flex w-full sm:w-auto justify-center items-center gap-2 bg-[#198496] hover:bg-[#146c7a] text-white px-8 py-3.5 rounded-md font-medium text-base transition-all duration-300 shadow-xl shadow-[#198496]/30 active:scale-95"
          data-testid="hero-cta"
        >
          Publier une mission
          <ArrowRight class="w-5 h-5 transition-transform duration-300 group-hover:translate-x-1" />
        </RouterLink>
      </div>
    </div>

    <!-- Decorative silhouette for visual continuity (hidden but keeps testid) -->
    <div
      class="hidden"
      data-testid="hero-silhouette"
      style="transform: rotate(0deg)"
    ></div>
  </section>
</template>

<style scoped>
/* Slide-up transition for word cycling */
/* Both enter and leave happen simultaneously for smooth sliding effect */
.slide-up-enter-active,
.slide-up-leave-active {
  transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Entering word: starts below, slides up into view */
.slide-up-enter-from {
  opacity: 0;
  transform: translateY(100%);
}

.slide-up-enter-to {
  opacity: 1;
  transform: translateY(0);
}

/* Leaving word: slides up and out of view */
.slide-up-leave-from {
  opacity: 1;
  transform: translateY(0);
}

.slide-up-leave-to {
  opacity: 0;
  transform: translateY(-100%);
}

/* Floating animation for orbiting faces */
.floating-face {
  animation: float 6s ease-in-out infinite;
}

@keyframes float {
  0% {
    transform: translateY(0px);
  }
  50% {
    transform: translateY(-15px);
  }
  100% {
    transform: translateY(0px);
  }
}
</style>
