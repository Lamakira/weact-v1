<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { RouterLink } from 'vue-router'
import { ArrowRight } from 'lucide-vue-next'

// --- Hero Text Cycling Animation ---
const words = ['film', 'série télévisée', 'vidéo publicitaire', 'clip musical']
const activeWordIndex = ref(0)
let wordInterval: ReturnType<typeof setInterval> | null = null

// --- Scroll Progress for Silhouette Rotation (throttled with RAF) ---
const scrollProgress = ref(0)
const rotation = computed(() => scrollProgress.value * 360)
let rafId: number | null = null

function handleScroll(): void {
  // Throttle with requestAnimationFrame to avoid excessive updates
  if (rafId !== null) return

  rafId = requestAnimationFrame(() => {
    const scrollTop = window.scrollY
    const docHeight = document.documentElement.scrollHeight - window.innerHeight
    scrollProgress.value = docHeight > 0 ? scrollTop / docHeight : 0
    rafId = null
  })
}

// --- Lifecycle ---
onMounted(() => {
  // Start word cycling animation
  wordInterval = setInterval(() => {
    activeWordIndex.value = (activeWordIndex.value + 1) % words.length
  }, 2500)

  // Add scroll listener for rotation (passive for better performance)
  window.addEventListener('scroll', handleScroll, { passive: true })
})

onUnmounted(() => {
  if (wordInterval) {
    clearInterval(wordInterval)
  }
  // Cancel any pending RAF
  if (rafId !== null) {
    cancelAnimationFrame(rafId)
  }
  window.removeEventListener('scroll', handleScroll)
})
</script>

<template>
  <section class="relative pt-2 pb-10 lg:pt-4 lg:pb-12 overflow-hidden bg-gray-50/70">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-start justify-between gap-12">
        <!-- Left: Text Content -->
        <div class="max-w-2xl text-center lg:text-left">
          <!-- Hero Title -->
          <div>
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold text-gray-900 leading-tight">
              <span class="block">Monétisez votre</span>
              <span class="block">image dans :</span>
            </h1>

            <!-- Dynamic Animated Word -->
            <div class="h-[50px] sm:h-[60px] lg:h-[70px] relative flex justify-center lg:justify-start items-center overflow-hidden">
              <Transition name="slide-up">
                <span
                  :key="words[activeWordIndex]"
                  class="absolute text-4xl sm:text-5xl lg:text-6xl font-bold text-[#198496]"
                  data-testid="hero-animated-word"
                  aria-live="polite"
                  aria-atomic="true"
                >
                  {{ words[activeWordIndex] }}
                </span>
              </Transition>
            </div>
          </div>
          <p class="text-gray-500 text-lg lg:text-xl max-w-lg mx-auto lg:mx-0 mb-10 leading-relaxed">
            La première plateforme qui met en relation marques et créateurs pour des castings
            sécurisés au Bénin.
          </p>
          <RouterLink
            to="/register/face"
            class="group inline-flex w-full sm:w-auto justify-center items-center gap-2 bg-[#198496] text-white px-8 py-3.5 rounded-md font-medium text-base hover:bg-[#146c7a] transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#198496] focus-visible:ring-offset-2"
            data-testid="hero-cta"
          >
            Créer mon profil
            <ArrowRight class="w-5 h-5 group-hover:translate-x-1 transition-transform" />
          </RouterLink>
        </div>

        <!-- Right: Silhouette Visual (Desktop Only) -->
        <div class="hidden lg:block relative w-1/3 min-h-[400px]">
          <div
            class="sticky top-32 flex items-center justify-center transition-transform duration-75"
            :style="{ transform: `rotate(${rotation}deg)` }"
            data-testid="hero-silhouette"
          >
            <!-- Decorative Silhouette Circle -->
            <div
              class="relative w-80 h-80 bg-gray-100 rounded-full flex items-center justify-center overflow-hidden border border-gray-200"
            >
              <div
                class="absolute inset-0 bg-gradient-to-br from-gray-200 to-transparent opacity-50"
              ></div>
              <div class="relative z-10 text-8xl font-black text-[#198496]/10 select-none">
                FACE
              </div>

              <!-- Floating Thought Bubbles -->
              <div
                class="absolute -top-4 -right-4 bg-white shadow-xl px-4 py-2 rounded-full border border-gray-100 text-xs font-bold text-[#198496] animate-bounce"
                style="animation-delay: 0.1s"
              >
                Content creator
              </div>
              <div
                class="absolute top-1/2 -left-8 bg-white shadow-xl px-4 py-2 rounded-full border border-gray-100 text-xs font-bold text-[#198496] animate-bounce"
                style="animation-delay: 0.3s"
              >
                Acteur
              </div>
              <div
                class="absolute bottom-4 right-0 bg-white shadow-xl px-4 py-2 rounded-full border border-gray-100 text-xs font-bold text-[#198496] animate-bounce"
                style="animation-delay: 0.5s"
              >
                Figurant
              </div>
              <div
                class="absolute -bottom-2 left-4 bg-white shadow-xl px-4 py-2 rounded-full border border-gray-100 text-xs font-bold text-[#198496] animate-bounce"
                style="animation-delay: 0.7s"
              >
                Influenceur
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
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
</style>
