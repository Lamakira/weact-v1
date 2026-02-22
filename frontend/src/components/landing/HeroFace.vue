<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { RouterLink } from 'vue-router'
import { ArrowRight } from 'lucide-vue-next'
import heroLandscape from '@/assets/images/hero-section/full-shot-woman-posing-chair.webp'
import heroPortrait from '@/assets/images/hero-section/portrait-smiley-woman-posing-studio.webp'

// --- Hero Text Cycling Animation ---
const words = ['film', 'série télévisée', 'vidéo publicitaire', 'clip musical']
const activeWordIndex = ref(0)
let wordInterval: ReturnType<typeof setInterval> | null = null

// --- Lifecycle ---
onMounted(() => {
  wordInterval = setInterval(() => {
    activeWordIndex.value = (activeWordIndex.value + 1) % words.length
  }, 2500)
})

onUnmounted(() => {
  if (wordInterval) {
    clearInterval(wordInterval)
  }
})
</script>

<template>
  <section class="relative min-h-[calc(100vh-120px)] flex items-start overflow-hidden bg-gray-50/70 pt-4 sm:pt-[5vh] lg:pt-[10vh]">
    <div class="max-w-7xl mx-auto w-full">
      <div class="grid md:grid-cols-2 lg:grid-cols-[1fr_1fr] gap-4 md:gap-8 lg:gap-10 items-center">
        <!-- Left: Text Content -->
        <div class="text-center md:text-left px-4 sm:px-6 md:px-0">
          <!-- Hero Title -->
          <div>
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold text-gray-900 leading-tight">
              <span class="block">Monétisez votre</span>
              <span class="block">image dans :</span>
            </h1>

            <!-- Dynamic Animated Word -->
            <div class="h-[3.5rem] sm:h-[3.5rem] lg:h-[4.5rem] relative flex justify-center md:justify-start items-start overflow-hidden mt-1">
              <Transition name="slide-up">
                <span
                  :key="words[activeWordIndex]"
                  class="absolute whitespace-nowrap text-4xl sm:text-5xl lg:text-6xl font-bold text-[#198496]"
                  data-testid="hero-animated-word"
                  aria-live="polite"
                  aria-atomic="true"
                >
                  {{ words[activeWordIndex] }}
                </span>
              </Transition>
            </div>
          </div>
          <p class="text-gray-500 text-lg md:pt-4 lg:text-xl max-w-lg mx-auto md:mx-0 md:mb-8 leading-relaxed">
            La première plateforme qui met en relation marques et créateurs pour des castings
            sécurisés au Bénin.
          </p>
          <!-- Desktop CTA -->
          <RouterLink
            to="/register/face"
            class="hidden md:inline-flex group items-center gap-2 bg-[#198496] text-white px-8 py-3.5 rounded-md font-medium text-base hover:bg-[#146c7a] transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#198496] focus-visible:ring-offset-2"
            data-testid="hero-cta"
          >
            Créer mon profil
            <ArrowRight class="w-5 h-5 group-hover:translate-x-1 transition-transform" />
          </RouterLink>
        </div>

        <!-- Right: Hero Images -->
        <div data-testid="hero-images">
          <!-- Mobile/Tablet: Portrait only -->
          <div class="lg:hidden flex justify-center px-4 sm:px-6 md:px-0 mt-4 md:mt-0">
            <img
              :src="heroPortrait"
              alt="Femme souriante en studio"
              class="w-full max-w-xs md:max-w-none md:w-full h-[280px] sm:h-[320px] md:h-[420px] object-cover rounded-2xl"
              loading="eager"
            />
          </div>
          <!-- Desktop: Both images -->
          <div class="hidden lg:grid grid-cols-[1fr_auto] gap-4 items-center">
            <img
              :src="heroLandscape"
              alt="Femme posant sur une chaise"
              class="w-full rounded-2xl object-cover"
              loading="eager"
            />
            <img
              :src="heroPortrait"
              alt="Femme souriante en studio"
              class="w-52 h-[32rem] object-cover rounded-2xl"
              loading="eager"
            />
          </div>
        </div>

        <!-- Mobile CTA: after image -->
        <div class="md:hidden flex justify-center px-4 sm:px-6 mt-4">
          <RouterLink
            to="/register/face"
            class="group inline-flex w-full max-w-xs justify-center items-center gap-2 bg-[#198496] text-white px-8 py-3.5 rounded-md font-medium text-base hover:bg-[#146c7a] transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#198496] focus-visible:ring-offset-2"
            data-testid="hero-cta-mobile"
          >
            Créer mon profil
            <ArrowRight class="w-5 h-5 group-hover:translate-x-1 transition-transform" />
          </RouterLink>
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
