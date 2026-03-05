<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { MapPin, ChevronRight, ChevronLeft, Users } from 'lucide-vue-next'
import { Skeleton } from '@/components/ui/skeleton'

// Landing page components
import HeroFace from '@/components/landing/HeroFace.vue'
import HeroProducer from '@/components/landing/HeroProducer.vue'
import FacesCarousel from '@/components/landing/FacesCarousel.vue'
import TalentsShowcase from '@/components/landing/TalentsShowcase.vue'
import PerspectiveToggle from '@/components/landing/PerspectiveToggle.vue'

// Content configuration
import { getContent } from '@/components/landing/content'
import type { Perspective } from '@/components/landing/types'

// Composables for real data
import { useLandingMissions } from '@/features/landing/composables/useLandingMissions'
import { useLandingFaces } from '@/features/landing/composables/useLandingFaces'

// --- Perspective State ---
const perspective = ref<Perspective>('face')
const content = computed(() => getContent(perspective.value))

// --- Sticky Toggle State ---
const isToggleSticky = ref(false)
const toggleContainerRef = ref<HTMLElement | null>(null)
const togglePlaceholderRef = ref<HTMLElement | null>(null)

function handleScroll(): void {
  if (!togglePlaceholderRef.value) return
  const rect = togglePlaceholderRef.value.getBoundingClientRect()
  isToggleSticky.value = rect.top <= 64
}

// --- Real Data from API ---
const {
  missions,
  isLoading: isLoadingMissions,
  error: missionsError,
  fetchMissions,
  retry: retryMissions,
} = useLandingMissions()

const {
  faces,
  isLoading: isLoadingFaces,
  totalCount: facesCount,
  fetchFaces,
} = useLandingFaces()

const hasMissions = computed(() => missions.value.length > 0)
const hasFaces = computed(() => faces.value.length > 0)

onMounted(() => {
  window.addEventListener('scroll', handleScroll, { passive: true })
  fetchMissions()
  fetchFaces()
})

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll)
})

// Dynamic component resolution
const heroComponent = computed(() => (perspective.value === 'face' ? HeroFace : HeroProducer))
const showcaseComponent = computed(() =>
  perspective.value === 'face' ? FacesCarousel : TalentsShowcase
)

// --- Mobile Missions Carousel State ---
const missionCarouselIndex = ref(0)
const missionCarouselRef = ref<HTMLElement | null>(null)

function scrollToMission(index: number): void {
  if (!missionCarouselRef.value) return
  const cards = missionCarouselRef.value.querySelectorAll('[data-mission-card]')
  const card = cards[index] as HTMLElement | undefined
  if (card) {
    const containerWidth = missionCarouselRef.value.offsetWidth
    const cardWidth = card.offsetWidth
    // Center the card horizontally within the carousel (no page scroll)
    const scrollLeft = card.offsetLeft - (containerWidth - cardWidth) / 2
    missionCarouselRef.value.scrollTo({ left: scrollLeft, behavior: 'smooth' })
    missionCarouselIndex.value = index
  }
}

function nextMission(): void {
  const total = missions.value.length
  if (total === 0) return
  const nextIndex = (missionCarouselIndex.value + 1) % total
  scrollToMission(nextIndex)
}

function prevMission(): void {
  const total = missions.value.length
  if (total === 0) return
  const prevIndex = missionCarouselIndex.value === 0 ? total - 1 : missionCarouselIndex.value - 1
  scrollToMission(prevIndex)
}

// Start on 2nd mission when there are 3+ missions
watch(missions, (list) => {
  if (list.length >= 3) {
    scrollToMission(1)
  }
}, { flush: 'post' })

function handleMissionScroll(): void {
  if (!missionCarouselRef.value) return
  const scrollLeft = missionCarouselRef.value.scrollLeft
  const firstCard = missionCarouselRef.value.querySelector('[data-mission-card]') as HTMLElement | null
  if (!firstCard) return
  const cardWidth = firstCard.offsetWidth + 16 // card width + gap
  const newIndex = Math.round(scrollLeft / cardWidth)
  missionCarouselIndex.value = Math.min(Math.max(newIndex, 0), missions.value.length - 1)
}

function formatBudget(budget: number): string {
  return new Intl.NumberFormat('fr-FR').format(budget) + ' FCFA'
}

function formatDate(date: string): string {
  return new Date(date).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })
}
</script>

<template>
  <div class="overflow-x-hidden">
    <!-- ===== PERSPECTIVE TOGGLE (Sticky after header) ===== -->
    <!-- Placeholder to detect scroll position -->
    <div ref="togglePlaceholderRef" class="h-[56px]"></div>

    <!-- Toggle container (compact + sticky below header when scrolling) -->
    <div
      ref="toggleContainerRef"
      :class="[
        'left-0 right-0 transition-all duration-200',
        isToggleSticky
          ? 'fixed top-[72px] z-40 py-2'
          : 'relative pt-2 pb-0',
        !isToggleSticky && perspective === 'producer' ? 'bg-black' : '',
        !isToggleSticky && perspective === 'face' ? 'bg-gray-50/70' : '',
      ]"
      :style="isToggleSticky ? {} : { marginTop: '-56px' }"
      data-testid="perspective-toggle-container"
    >
      <div class="flex justify-center">
        <PerspectiveToggle v-model="perspective" :compact="isToggleSticky" :dark="perspective === 'producer' && !isToggleSticky" />
      </div>
    </div>

    <!-- ===== SECTION 1: HERO (Component-based - different per perspective) ===== -->
    <div class="relative">
      <!-- Hero Component -->
      <Transition name="perspective" mode="out-in">
        <component :is="heroComponent" :key="perspective" />
      </Transition>
    </div>

    <!-- ===== SECTION 2: COMMENT ÇA MARCHE (Data-driven) ===== -->
    <section class="py-10 bg-white relative overflow-hidden">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="text-center mb-10 lg:mb-20">
          <h2
            class="text-xs font-bold text-[#198496] tracking-[0.2em] uppercase mb-4"
            data-testid="how-it-works-title"
          >
            {{ content.howItWorks.title }}
          </h2>
          <p class="text-3xl lg:text-4xl font-bold text-gray-900">
            {{ content.howItWorks.subtitle }}
          </p>
        </div>

        <!-- Steps with Curved Arrows -->
        <div class="relative">
          <!-- Desktop: Zigzag layout with overlapping cards & arrows -->
          <div class="hidden lg:block">
            <!-- Row 1: Card 1 (left) -->
            <div class="relative z-[4] flex justify-start">
              <div class="w-1/2 flex justify-center">
                <div
                  class="group relative bg-white p-8 rounded-2xl border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 w-full max-w-sm h-64"
                  data-testid="step-1-card"
                >
                  <div
                    class="absolute -top-4 -left-4 w-10 h-10 bg-[#198496] text-white flex items-center justify-center rounded-md font-bold text-sm"
                  >
                    {{ content.howItWorks.steps[0]!.number }}
                  </div>
                  <div
                    class="w-14 h-14 bg-[#198496]/10 rounded-xl flex items-center justify-center text-[#198496] mb-6"
                  >
                    <component :is="content.howItWorks.steps[0]!.icon" class="w-7 h-7" />
                  </div>
                  <h3 class="text-lg font-bold mb-3">{{ content.howItWorks.steps[0]!.title }}</h3>
                  <p class="text-gray-500 text-sm leading-relaxed">
                    {{ content.howItWorks.steps[0]!.description }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Arrow 1: From card 1 to card 2 (curves down-right) -->
            <svg
              class="absolute top-[200px] left-[calc(25%+120px)] w-[calc(50%-80px)] h-28 text-gray-300 z-0"
              viewBox="0 0 400 112"
              fill="none"
              preserveAspectRatio="none"
            >
              <defs>
                <marker id="arrowhead1" markerWidth="10" markerHeight="7" refX="9" refY="3.5" orient="auto">
                  <polygon points="0 0, 10 3.5, 0 7" fill="currentColor" />
                </marker>
              </defs>
              <path
                d="M0 0 Q200 0 200 56 Q200 112 400 112"
                stroke="currentColor"
                stroke-width="2"
                stroke-dasharray="8 6"
                marker-end="url(#arrowhead1)"
              />
            </svg>

            <!-- Row 2: Card 2 (right) — starts at ¾ of card 1 -->
            <div class="relative z-[3] flex justify-end -mt-16">
              <div class="w-1/2 flex justify-center">
                <div
                  class="group relative bg-white p-8 rounded-2xl border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 w-full max-w-sm h-64"
                  data-testid="step-2-card"
                >
                  <div
                    class="absolute -top-4 -left-4 w-10 h-10 bg-[#198496] text-white flex items-center justify-center rounded-md font-bold text-sm"
                  >
                    {{ content.howItWorks.steps[1]!.number }}
                  </div>
                  <div
                    class="w-14 h-14 bg-[#198496]/10 rounded-xl flex items-center justify-center text-[#198496] mb-6"
                  >
                    <component :is="content.howItWorks.steps[1]!.icon" class="w-7 h-7" />
                  </div>
                  <h3 class="text-lg font-bold mb-3">{{ content.howItWorks.steps[1]!.title }}</h3>
                  <p class="text-gray-500 text-sm leading-relaxed">
                    {{ content.howItWorks.steps[1]!.description }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Arrow 2: From card 2 to card 3 (curves down-left) -->
            <svg
              class="absolute top-[392px] left-[calc(25%-60px)] w-[calc(50%-80px)] h-28 text-gray-300 z-0"
              viewBox="0 0 400 112"
              fill="none"
              preserveAspectRatio="none"
            >
              <defs>
                <marker id="arrowhead2" markerWidth="10" markerHeight="7" refX="9" refY="3.5" orient="auto">
                  <polygon points="0 0, 10 3.5, 0 7" fill="currentColor" />
                </marker>
              </defs>
              <path
                d="M400 0 Q200 0 200 56 Q200 112 0 112"
                stroke="currentColor"
                stroke-width="2"
                stroke-dasharray="8 6"
                marker-end="url(#arrowhead2)"
              />
            </svg>

            <!-- Row 3: Card 3 (left) — starts at ¾ of card 2 -->
            <div class="relative z-[2] flex justify-start -mt-16">
              <div class="w-1/2 flex justify-center">
                <div
                  class="group relative bg-white p-8 rounded-2xl border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 w-full max-w-sm h-64"
                  data-testid="step-3-card"
                >
                  <div
                    class="absolute -top-4 -left-4 w-10 h-10 bg-[#198496] text-white flex items-center justify-center rounded-md font-bold text-sm"
                  >
                    {{ content.howItWorks.steps[2]!.number }}
                  </div>
                  <div
                    class="w-14 h-14 bg-[#198496]/10 rounded-xl flex items-center justify-center text-[#198496] mb-6"
                  >
                    <component :is="content.howItWorks.steps[2]!.icon" class="w-7 h-7" />
                  </div>
                  <h3 class="text-lg font-bold mb-3">{{ content.howItWorks.steps[2]!.title }}</h3>
                  <p class="text-gray-500 text-sm leading-relaxed">
                    {{ content.howItWorks.steps[2]!.description }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Arrow 3: From card 3 to card 4 (curves down-right) -->
            <svg
              class="absolute top-[584px] left-[calc(25%+120px)] w-[calc(50%-80px)] h-28 text-gray-300 z-0"
              viewBox="0 0 400 112"
              fill="none"
              preserveAspectRatio="none"
            >
              <defs>
                <marker id="arrowhead3" markerWidth="10" markerHeight="7" refX="9" refY="3.5" orient="auto">
                  <polygon points="0 0, 10 3.5, 0 7" fill="currentColor" />
                </marker>
              </defs>
              <path
                d="M0 0 Q200 0 200 56 Q200 112 400 112"
                stroke="currentColor"
                stroke-width="2"
                stroke-dasharray="8 6"
                marker-end="url(#arrowhead3)"
              />
            </svg>

            <!-- Row 4: Card 4 (right) — starts at ¾ of card 3 -->
            <div class="relative z-[1] flex justify-end -mt-16">
              <div class="w-1/2 flex justify-center">
                <div
                  class="group relative bg-white p-8 rounded-2xl border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 w-full max-w-sm h-64"
                  data-testid="step-4-card"
                >
                  <div
                    class="absolute -top-4 -left-4 w-10 h-10 bg-[#198496] text-white flex items-center justify-center rounded-md font-bold text-sm"
                  >
                    {{ content.howItWorks.steps[3]!.number }}
                  </div>
                  <div
                    class="w-14 h-14 bg-[#198496]/10 rounded-xl flex items-center justify-center text-[#198496] mb-6"
                  >
                    <component :is="content.howItWorks.steps[3]!.icon" class="w-7 h-7" />
                  </div>
                  <h3 class="text-lg font-bold mb-3">{{ content.howItWorks.steps[3]!.title }}</h3>
                  <p class="text-gray-500 text-sm leading-relaxed">
                    {{ content.howItWorks.steps[3]!.description }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Mobile: Streamlined Vertical Timeline -->
          <div class="lg:hidden relative px-4">
            <!-- Continuous Connecting Line -->
            <div class="absolute left-[44px] top-4 bottom-4 w-px bg-gray-100"></div>

            <div class="space-y-12">
              <div
                v-for="(step, index) in content.howItWorks.steps"
                :key="step.number"
                class="relative flex items-start gap-6"
                :data-testid="`step-${index + 1}-mobile`"
              >
                <!-- Step Indicator & Icon -->
                <div class="relative flex-shrink-0">
                  <div
                    class="relative z-10 w-14 h-14 bg-white border border-gray-100 rounded-xl shadow-sm flex items-center justify-center text-[#198496] transition-transform duration-300 group-active:scale-95"
                  >
                    <component :is="step.icon" class="w-7 h-7" />
                  </div>
                  <!-- Step Number Badge -->
                  <div
                    class="absolute -top-2 -left-2 z-20 w-6 h-6 bg-[#198496] text-white flex items-center justify-center rounded-md font-bold text-[10px] shadow-sm border-2 border-white"
                  >
                    {{ step.number }}
                  </div>
                </div>

                <!-- Content Area -->
                <div class="flex-1 pt-1">
                  <h3 class="font-bold text-gray-900 text-base mb-1.5 leading-tight">
                    {{ step.title }}
                  </h3>
                  <p class="text-sm text-gray-500 leading-relaxed">
                    {{ step.description }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== SECTION 3: FACES/TALENTS SHOWCASE (Component-based - different per perspective) ===== -->
    <!-- Loading State -->
    <div
      v-if="isLoadingFaces"
      class="py-10 lg:py-12"
      data-testid="faces-loading"
    >
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
          <Skeleton class="h-4 w-32 mx-auto mb-4" />
          <Skeleton class="h-10 w-64 mx-auto mb-4" />
          <Skeleton class="h-5 w-80 mx-auto" />
        </div>
        <div class="flex gap-8 overflow-hidden">
          <div v-for="i in 6" :key="`face-skeleton-${i}`" class="flex-shrink-0 w-[200px]">
            <Skeleton class="aspect-[4/5] rounded-2xl" />
          </div>
        </div>
      </div>
    </div>

    <!-- Faces/Talents Content (only if data exists) -->
    <template v-else-if="hasFaces">
      <Transition name="perspective" mode="out-in">
        <component
          :is="showcaseComponent"
          :key="perspective"
          v-bind="perspective === 'face'
            ? { profiles: faces, totalCount: facesCount }
            : { talents: faces, totalCount: facesCount }"
        />
      </Transition>
    </template>

    <!-- ===== SECTION 4: MISSIONS EN COURS (Data-driven) ===== -->
    <section
      v-if="isLoadingMissions || missionsError || hasMissions"
      id="missions"
      class="py-10 bg-gray-50"
      data-testid="missions-section"
    >
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="text-center mb-12">
          <h2
            class="text-3xl lg:text-4xl font-bold text-gray-900 tracking-tight mb-2"
            data-testid="missions-section-title"
          >
            {{ content.missions.title }}
          </h2>
          <p class="text-gray-500 text-lg">{{ content.missions.subtitle }}</p>
        </div>

        <!-- Loading State -->
        <div
          v-if="isLoadingMissions"
          class="flex flex-col gap-4"
          data-testid="missions-loading"
        >
          <div
            v-for="i in 4"
            :key="`skeleton-${i}`"
            class="bg-white rounded-xl border border-gray-200 overflow-hidden flex"
          >
            <div class="flex-1 p-6 space-y-3">
              <Skeleton class="h-5 w-20" />
              <Skeleton class="h-6 w-3/4" />
              <Skeleton class="h-4 w-full" />
              <Skeleton class="h-4 w-24" />
            </div>
            <div class="hidden md:block w-72 border-l border-gray-100 p-5 space-y-3">
              <Skeleton class="h-4 w-full" />
              <Skeleton class="h-4 w-full" />
              <Skeleton class="h-4 w-full" />
            </div>
          </div>
        </div>

        <!-- Error State -->
        <div v-else-if="missionsError" class="text-center py-12" data-testid="missions-error">
          <p class="text-red-600 mb-4">{{ missionsError }}</p>
          <button
            class="text-[#198496] font-medium hover:underline"
            @click="retryMissions"
            data-testid="missions-retry"
          >
            Réessayer
          </button>
        </div>

        <!-- Missions Content — Carousel with side fade -->
        <div v-else>
          <div class="relative">
            <!-- Left fade -->
            <div class="absolute left-0 top-0 bottom-0 w-16 md:w-32 bg-gradient-to-r from-gray-50 to-transparent z-10 pointer-events-none"></div>
            <!-- Right fade -->
            <div class="absolute right-0 top-0 bottom-0 w-16 md:w-32 bg-gradient-to-l from-gray-50 to-transparent z-10 pointer-events-none"></div>

            <!-- Left Arrow -->
            <button
              v-if="missions.length > 1"
              @click="prevMission"
              class="absolute left-2 md:left-6 top-1/2 -translate-y-1/2 z-20 w-10 h-10 flex items-center justify-center rounded-full border border-gray-200 bg-white text-gray-600 hover:border-[#198496] hover:text-[#198496] transition-colors shadow-sm"
              aria-label="Mission précédente"
            >
              <ChevronLeft class="w-5 h-5" />
            </button>

            <!-- Right Arrow -->
            <button
              v-if="missions.length > 1"
              @click="nextMission"
              class="absolute right-2 md:right-6 top-1/2 -translate-y-1/2 z-20 w-10 h-10 flex items-center justify-center rounded-full border border-gray-200 bg-white text-gray-600 hover:border-[#198496] hover:text-[#198496] transition-colors shadow-sm"
              aria-label="Mission suivante"
            >
              <ChevronRight class="w-5 h-5" />
            </button>

            <div
              ref="missionCarouselRef"
              class="flex gap-6 overflow-x-auto snap-x snap-mandatory scroll-smooth py-2 scrollbar-hide mission-carousel-padding"
              @scroll="handleMissionScroll"
              data-testid="missions-carousel"
            >
              <RouterLink
                v-for="mission in missions"
                :key="mission.id"
                :to="{ name: 'public-mission-detail', params: { slug: mission.slug } }"
                data-mission-card
                class="group flex-shrink-0 w-[80%] md:w-[65%] snap-center bg-white rounded-xl border border-gray-200 overflow-hidden hover:border-[#198496]/30 hover:shadow-lg transition-all duration-300"
                :data-testid="`mission-card-${mission.id}`"
              >
              <div class="flex flex-col md:flex-row">
                <!-- Main Info -->
                <div class="flex-1 p-5 md:p-6">
                  <div class="flex items-center gap-3 mb-3">
                    <span class="hidden md:inline text-xs font-semibold px-2.5 py-0.5 rounded bg-[#198496]/10 text-[#198496]">
                      {{ mission.type_mission_label }}
                    </span>
                    <span v-if="mission.date_tournage" class="text-xs text-gray-400">
                      {{ formatDate(mission.date_tournage) }}
                    </span>
                  </div>

                  <h3 class="text-base md:text-lg font-bold text-gray-900 mb-1.5 group-hover:text-[#198496] transition-colors">
                    {{ mission.titre }}
                  </h3>
                  <p class="text-sm text-gray-500 line-clamp-2 mb-3 md:mb-4">{{ mission.description }}</p>

                  <!-- Mobile: compact info row with badge -->
                  <div class="flex items-center gap-3 flex-wrap text-xs text-gray-400 mb-3 md:hidden">
                    <span class="text-xs font-semibold px-2.5 py-0.5 rounded bg-[#198496]/10 text-[#198496]">
                      {{ mission.type_mission_label }}
                    </span>
                    <div class="flex items-center gap-1">
                      <MapPin class="w-3.5 h-3.5" />
                      {{ mission.lieu }}
                    </div>
                    <div class="flex items-center gap-1">
                      <Users class="w-3.5 h-3.5" />
                      {{ mission.nombre_faces_voulu }} face{{ mission.nombre_faces_voulu > 1 ? 's' : '' }}
                    </div>
                  </div>

                  <!-- Desktop: location -->
                  <div class="hidden md:flex items-center gap-1.5 text-sm text-gray-400">
                    <MapPin class="w-3.5 h-3.5" />
                    {{ mission.lieu }}
                  </div>

                  <!-- Mobile: bottom -->
                  <div class="flex items-center justify-between pt-3 border-t border-gray-100 md:hidden">
                    <span class="text-sm font-bold text-[#198496]">{{ formatBudget(mission.budget) }}</span>
                    <span class="text-xs font-medium text-[#198496] flex items-center gap-0.5">
                      Voir détails
                      <ChevronRight class="w-3.5 h-3.5" />
                    </span>
                  </div>
                </div>

                <!-- Desktop: Right Details Panel -->
                <div class="hidden md:flex w-64 border-l border-gray-100 flex-col">
                  <div class="flex-1 divide-y divide-gray-100">
                    <div class="flex items-center justify-between px-5 py-3">
                      <span class="text-xs text-gray-400">Budget</span>
                      <span class="text-sm font-semibold text-gray-900">{{ formatBudget(mission.budget) }}</span>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3">
                      <span class="text-xs text-gray-400">Profil</span>
                      <span class="text-sm text-gray-700">{{ mission.genre_voulu_label }}</span>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3">
                      <span class="text-xs text-gray-400">Faces recherchées</span>
                      <span class="text-sm text-gray-700">{{ mission.nombre_faces_voulu }}</span>
                    </div>
                  </div>
                  <div class="px-5 py-3 border-t border-gray-100 flex items-center justify-end gap-1 text-sm font-medium text-[#198496]">
                    Voir les détails
                    <ChevronRight class="w-4 h-4 group-hover:translate-x-1 transition-transform" />
                  </div>
                </div>
              </div>
            </RouterLink>
            </div>
          </div>

          <!-- Dots Indicator -->
          <div v-if="missions.length > 1" class="flex items-center justify-center gap-2 mt-6">
            <button
              v-for="(_, index) in missions"
              :key="index"
              @click="scrollToMission(index)"
              :class="[
                'w-2 h-2 rounded-full transition-all duration-300',
                missionCarouselIndex === index
                  ? 'bg-[#198496] w-4'
                  : 'bg-gray-300 hover:bg-gray-400'
              ]"
              :aria-label="`Aller à la mission ${index + 1}`"
            />
          </div>

          <!-- CTA -->
          <div class="flex justify-center mt-8">
            <a
              :href="content.missions.ctaLink"
              class="flex items-center gap-2 text-sm font-bold text-[#198496] border border-[#198496] px-5 py-2 rounded-md hover:bg-[#198496] hover:text-white transition-colors group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#198496] focus-visible:ring-offset-2"
              data-testid="see-all-missions"
            >
              {{ content.missions.ctaText }}
              <ChevronRight class="w-4 h-4 group-hover:translate-x-1 transition-transform" />
            </a>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== SECTION 5: POURQUOI WEACT (Data-driven) ===== -->
    <section class="py-10 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="text-center mb-8">
          <h2
            class="text-3xl lg:text-4xl font-bold text-gray-900 tracking-tight mb-4"
            data-testid="why-weact-title"
          >
            {{ content.whyWeact.title }}
          </h2>
          <p class="text-gray-500 text-lg max-w-xl mx-auto">
            {{ content.whyWeact.subtitle }}
          </p>
        </div>

        <!-- Feature Cards -->
        <!-- Mobile: Compact horizontal layout -->
        <div class="md:hidden space-y-4">
          <div
            v-for="(feature, index) in content.whyWeact.features"
            :key="index"
            class="relative flex items-start gap-4 p-5 rounded-xl bg-gradient-to-r from-[#198496]/5 to-transparent border-l-4 border-[#198496]"
            :data-testid="`feature-card-mobile-${index + 1}`"
          >
            <!-- Icon -->
            <div
              class="flex-shrink-0 w-12 h-12 bg-white border border-gray-100 text-[#198496] rounded-xl flex items-center justify-center shadow-sm"
            >
              <component :is="feature.icon" class="w-6 h-6" />
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0">
              <h3 class="font-bold text-gray-900 text-base mb-1">
                {{ feature.title }}
              </h3>
              <p class="text-sm text-gray-600 leading-relaxed">
                {{ feature.description }}
              </p>
            </div>
          </div>
        </div>

        <!-- Desktop: Premium centered cards -->
        <div class="hidden md:grid md:grid-cols-3 gap-6 lg:gap-8">
          <div
            v-for="(feature, index) in content.whyWeact.features"
            :key="index"
            class="relative text-center p-6 lg:p-8 rounded-2xl border border-gray-200 bg-white hover:border-[#198496]/30 hover:shadow-[0_20px_50px_rgba(25,132,150,0.08)] hover:-translate-y-2 transition-all duration-500 group overflow-hidden"
            :data-testid="`feature-card-${index + 1}`"
          >
            <!-- Decorative Background Elements -->
            <div
              class="absolute -top-10 -right-10 w-32 h-32 bg-[#198496]/5 rounded-full blur-3xl group-hover:bg-[#198496]/10 transition-colors duration-500"
            ></div>
            <div
              class="absolute -bottom-10 -left-10 w-24 h-24 bg-gray-50 rounded-full blur-2xl opacity-50"
            ></div>

            <div class="relative z-10">
              <!-- Icon with refined border & shadow -->
              <div
                class="w-16 h-16 bg-white border border-gray-100 text-[#198496] rounded-2xl flex items-center justify-center mx-auto shadow-sm ring-4 ring-gray-50/50 group-hover:scale-110 group-hover:border-[#198496]/20 group-hover:ring-[#198496]/5 transition-all duration-500"
              >
                <component :is="feature.icon" class="w-7 h-7" />
              </div>

              <!-- Content -->
              <div class="mt-5">
                <h3 class="font-bold text-gray-900 text-lg mb-2 group-hover:text-[#198496] transition-colors duration-300">
                  {{ feature.title }}
                </h3>
                <p class="text-sm text-gray-700 leading-relaxed">
                  {{ feature.description }}
                </p>
              </div>

              <!-- Premium Accent Indicator -->
              <div class="mt-5 flex justify-center items-center gap-1.5 opacity-0 group-hover:opacity-100 transition-all duration-500 translate-y-2 group-hover:translate-y-0">
                <div class="w-1.5 h-1.5 rounded-full bg-[#198496]"></div>
                <div class="w-8 h-[1px] bg-gradient-to-r from-[#198496] to-transparent"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Certified Badge -->
        <div class="mt-10 flex flex-col items-center">
          <div
            class="bg-gray-50 border border-gray-100 px-6 py-3 rounded-full flex items-center gap-3"
          >
            <svg class="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="text-sm font-bold text-gray-900">{{ content.whyWeact.certifiedText }}</span>
          </div>
          <p class="mt-4 text-sm text-gray-500 text-center max-w-2xl">
            {{ content.whyWeact.footerText }}
          </p>
        </div>
      </div>
    </section>

    <!-- ===== SECTION 6: PRÊT À COMMENCER ? (Data-driven) ===== -->
    <section class="pt-4 pb-10 px-4 sm:px-6 lg:px-8">
      <div
        class="max-w-7xl mx-auto relative bg-gradient-to-br from-[#198496]/90 via-[#1a9fb5]/85 to-[#1bbad4]/80 rounded-3xl overflow-hidden py-16 px-8 text-center"
      >
        <!-- Decorative Blur Circles -->
        <div class="absolute -top-12 -left-12 w-48 h-48 bg-white/5 blur-3xl rounded-full"></div>
        <div class="absolute -bottom-12 -right-12 w-48 h-48 bg-white/5 blur-3xl rounded-full"></div>
        <div class="absolute top-1/2 left-1/4 w-32 h-32 bg-[#1bbad4]/20 blur-3xl rounded-full"></div>

        <!-- Content -->
        <div class="relative z-10 space-y-6">
          <h2
            class="text-3xl sm:text-4xl lg:text-5xl font-bold text-white tracking-tight"
            data-testid="final-cta-title"
          >
            {{ content.finalCta.title }}
          </h2>
          <p class="text-white/80 text-lg max-w-xl mx-auto">
            {{ content.finalCta.subtitle }}
          </p>
          <div class="pt-4">
            <RouterLink
              :to="content.finalCta.buttonLink"
              class="inline-block bg-white text-[#198496] px-10 py-4 rounded-full font-bold text-sm shadow-xl hover:scale-105 hover:shadow-2xl transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#198496]"
              data-testid="final-cta-button"
            >
              {{ content.finalCta.buttonText }}
            </RouterLink>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
/* Hide scrollbar for carousel */
.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}

/* Carousel horizontal padding so first/last card can be centered */
.mission-carousel-padding {
  padding-left: 10%;
  padding-right: 10%;
}

@media (min-width: 768px) {
  .mission-carousel-padding {
    padding-left: 17.5%;
    padding-right: 17.5%;
  }
}

/* Line clamp for card descriptions */
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Perspective transition (300ms fade/slide) */
.perspective-enter-active,
.perspective-leave-active {
  transition: all 0.3s ease-in-out;
}

.perspective-enter-from {
  opacity: 0;
  transform: translateX(20px);
}

.perspective-leave-to {
  opacity: 0;
  transform: translateX(-20px);
}
</style>
