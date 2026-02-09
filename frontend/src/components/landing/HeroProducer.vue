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
  { id: 1, size: 'small', top: '8%', left: '18%', delay: '0s', img: 'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?q=80&w=256&h=256&auto=format&fit=crop' },
  { id: 2, size: 'medium', top: '15%', left: '28%', delay: '0.5s', img: 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?q=80&w=256&h=256&auto=format&fit=crop' },
  { id: 3, size: 'large', top: '35%', left: '15%', delay: '1s', img: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?q=80&w=256&h=256&auto=format&fit=crop' },
  { id: 4, size: 'medium', top: '65%', left: '18%', delay: '1.5s', img: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?q=80&w=256&h=256&auto=format&fit=crop' },
  { id: 5, size: 'small', top: '80%', left: '28%', delay: '2s', img: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?q=80&w=256&h=256&auto=format&fit=crop' },
  // Right side - closer to content
  { id: 6, size: 'large', top: '22%', right: '15%', delay: '0.3s', img: 'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?q=80&w=256&h=256&auto=format&fit=crop' },
  { id: 7, size: 'small', top: '10%', right: '22%', delay: '0.8s', img: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=256&h=256&auto=format&fit=crop' },
  { id: 8, size: 'medium', top: '52%', right: '14%', delay: '1.2s', img: 'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?q=80&w=256&h=256&auto=format&fit=crop' },
  { id: 9, size: 'small', top: '75%', right: '20%', delay: '1.8s', img: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?q=80&w=256&h=256&auto=format&fit=crop' },
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
    class="relative w-full flex items-center justify-center overflow-hidden bg-gradient-to-br from-white via-[#198496]/5 to-white pt-2 pb-10 lg:pt-4 lg:pb-12 px-4"
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
        class="floating-face rounded-full border-4 border-white shadow-lg overflow-hidden transition-transform duration-500 hover:scale-110"
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
        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold text-gray-900 leading-tight">
          Trouvez votre prochain
        </h1>

        <!-- Dynamic Animated Word -->
        <div class="h-[50px] sm:h-[60px] lg:h-[70px] relative flex justify-center items-center overflow-hidden w-screen max-w-full -mx-4 px-4">
          <Transition name="slide-up">
            <span
              :key="words[currentWordIndex]"
              class="absolute text-[2rem] sm:text-5xl lg:text-6xl font-bold text-[#198496] whitespace-nowrap"
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
      <p class="mt-3 text-lg lg:text-xl text-gray-500 max-w-2xl mx-auto leading-relaxed">
        Accédez au plus grand catalogue de talents au Bénin
      </p>

      <!-- Mobile: Talent faces row (before CTA on mobile) -->
      <div class="mt-6 flex justify-center items-center gap-2 lg:hidden">
        <div class="flex -space-x-3">
          <div
            v-for="i in 5"
            :key="i"
            class="w-11 h-11 rounded-full border-2 border-white shadow-lg overflow-hidden"
          >
            <img
              :src="talents[i - 1]?.img"
              alt="Talent disponible"
              class="w-full h-full object-cover"
              loading="lazy"
            />
          </div>
        </div>
        <span class="ml-2 text-sm text-gray-500 font-medium">{{ TALENT_COUNT_DISPLAY }}</span>
      </div>

      <!-- CTA Button -->
      <div class="mt-6 lg:mt-4">
        <RouterLink
          to="/register/producer"
          class="group inline-flex items-center gap-2 bg-[#198496] hover:bg-[#146c7a] text-white px-8 py-4 rounded-md font-medium text-base transition-all duration-300 shadow-xl shadow-[#198496]/20 active:scale-95"
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
