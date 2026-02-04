<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { RouterLink } from 'vue-router'
import { MapPin, ChevronRight, ChevronLeft, CheckCircle } from 'lucide-vue-next'

// Landing page components
import HeroFace from '@/components/landing/HeroFace.vue'
import HeroProducer from '@/components/landing/HeroProducer.vue'
import FacesCarousel from '@/components/landing/FacesCarousel.vue'
import TalentsShowcase from '@/components/landing/TalentsShowcase.vue'
import PerspectiveToggle from '@/components/landing/PerspectiveToggle.vue'

// Content configuration
import { getContent } from '@/components/landing/content'
import type { Perspective, Mission } from '@/components/landing/types'

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
  isToggleSticky.value = rect.top <= 0
}

onMounted(() => {
  window.addEventListener('scroll', handleScroll, { passive: true })
})

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll)
})

// Dynamic component resolution
const heroComponent = computed(() => (perspective.value === 'face' ? HeroFace : HeroProducer))
const showcaseComponent = computed(() =>
  perspective.value === 'face' ? FacesCarousel : TalentsShowcase
)

// --- Loading States (for future API integration) ---
const isLoadingMissions = ref(false)
const missionsError = ref<string | null>(null)

// --- Mobile Missions Carousel State ---
const missionCarouselIndex = ref(0)
const missionCarouselRef = ref<HTMLElement | null>(null)

function scrollToMission(index: number): void {
  if (!missionCarouselRef.value) return
  const cards = missionCarouselRef.value.querySelectorAll('[data-mission-card]')
  if (cards[index]) {
    cards[index].scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' })
    missionCarouselIndex.value = index
  }
}

function nextMission(): void {
  const nextIndex = (missionCarouselIndex.value + 1) % 6
  scrollToMission(nextIndex)
}

function prevMission(): void {
  const prevIndex = missionCarouselIndex.value === 0 ? 5 : missionCarouselIndex.value - 1
  scrollToMission(prevIndex)
}

function handleMissionScroll(): void {
  if (!missionCarouselRef.value) return
  const scrollLeft = missionCarouselRef.value.scrollLeft
  const cardWidth = missionCarouselRef.value.offsetWidth * 0.85 + 16 // card width + gap
  const newIndex = Math.round(scrollLeft / cardWidth)
  missionCarouselIndex.value = Math.min(Math.max(newIndex, 0), 5)
}

// --- Mock Missions Data (to be replaced with API calls) ---
const mockMissions: Mission[] = [
  {
    id: 1,
    type: 'FILM',
    typeColor: 'bg-pink-500 text-white',
    title: 'Court métrage Dramatique',
    description: 'Recherche acteurs pour court métrage dramatique tourné à Cotonou.',
    price: '120 000 FCFA',
    location: 'Cotonou',
    status: 'available',
    img: 'https://images.unsplash.com/photo-1485846234645-a62644f84728?auto=format&fit=crop&q=80&w=600',
  },
  {
    id: 2,
    type: 'PUB',
    typeColor: 'bg-purple-500 text-white',
    title: 'Publicité Téléphone Mobile',
    description: 'Casting pour une publicité télévisée nationale.',
    price: '75 000 FCFA',
    location: 'Porto-Novo',
    status: 'urgent',
    img: 'https://images.unsplash.com/photo-1557804506-669a67965ba0?auto=format&fit=crop&q=80&w=600',
  },
  {
    id: 3,
    type: 'CLIP',
    typeColor: 'bg-blue-500 text-white',
    title: 'Clip Musical Afrobeat',
    description: "Figurants pour clip musical d'un artiste béninois.",
    price: '50 000 FCFA',
    location: 'Ouidah',
    status: 'available',
    img: 'https://images.unsplash.com/photo-1514525253361-bee8a19740c1?auto=format&fit=crop&q=80&w=600',
  },
  {
    id: 4,
    type: 'PUB',
    typeColor: 'bg-purple-500 text-white',
    title: 'Vidéo Promotionnelle',
    description: 'Tournage vidéo promotionnelle pour une marque locale.',
    price: '45 000 FCFA',
    location: 'Abomey-Calavi',
    status: 'in_progress',
    img: 'https://images.unsplash.com/photo-1559136555-9303baea8ebd?auto=format&fit=crop&q=80&w=600',
  },
  {
    id: 5,
    type: 'FILM',
    typeColor: 'bg-pink-500 text-white',
    title: 'Série Web',
    description: 'Casting pour nouvelle série web diffusée en streaming.',
    price: '95 000 FCFA',
    location: 'Cotonou',
    status: 'available',
    img: 'https://images.unsplash.com/photo-1492691527719-9d1e07e534b4?auto=format&fit=crop&q=80&w=600',
  },
  {
    id: 6,
    type: 'CLIP',
    typeColor: 'bg-blue-500 text-white',
    title: 'Shooting Photo Mode',
    description: 'Modèles recherchés pour shooting photo professionnel.',
    price: '80 000 FCFA',
    location: 'Grand-Popo',
    status: 'available',
    img: 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?auto=format&fit=crop&q=80&w=600',
  },
]

// --- Status Badge Helper ---
function getStatusBadge(status: string): { class: string; label: string } {
  switch (status) {
    case 'urgent':
      return { class: 'bg-red-500/10 text-red-600 border-red-500/30', label: 'Urgent' }
    case 'in_progress':
      return { class: 'bg-yellow-500/10 text-yellow-600 border-yellow-500/30', label: 'En cours' }
    default:
      return { class: 'bg-green-500/10 text-green-600 border-green-500/30', label: 'Ouvert' }
  }
}
</script>

<template>
  <div class="overflow-x-hidden">
    <!-- ===== PERSPECTIVE TOGGLE (Sticky after header) ===== -->
    <!-- Placeholder to detect scroll position -->
    <div ref="togglePlaceholderRef" class="h-[72px]"></div>

    <!-- Fixed toggle when scrolling -->
    <div
      ref="toggleContainerRef"
      :class="[
        'left-0 right-0 z-50 bg-white/95 backdrop-blur-sm border-b border-gray-100 py-4 transition-shadow duration-200',
        isToggleSticky ? 'fixed top-0 shadow-md' : 'absolute',
      ]"
      :style="isToggleSticky ? {} : { top: '0px', position: 'relative', marginTop: '-72px' }"
      data-testid="perspective-toggle-container"
    >
      <div class="flex justify-center">
        <PerspectiveToggle v-model="perspective" />
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
    <section class="py-16 bg-white relative overflow-hidden">
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
          <!-- Desktop: Grid layout with arrows -->
          <div class="hidden lg:block">
            <!-- Row 1: Card 1 (left) -->
            <div class="flex justify-start mb-8">
              <div class="w-1/2 flex justify-center">
                <div
                  class="group relative bg-white p-8 rounded-2xl border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 w-full max-w-sm h-64"
                  data-testid="step-1-card"
                >
                  <div
                    class="absolute -top-4 -left-4 w-10 h-10 bg-[#198496] text-white flex items-center justify-center rounded-md font-bold text-sm"
                  >
                    {{ content.howItWorks.steps[0].number }}
                  </div>
                  <div
                    class="w-14 h-14 bg-[#198496]/10 rounded-xl flex items-center justify-center text-[#198496] mb-6"
                  >
                    <component :is="content.howItWorks.steps[0].icon" class="w-7 h-7" />
                  </div>
                  <h3 class="text-lg font-bold mb-3">{{ content.howItWorks.steps[0].title }}</h3>
                  <p class="text-gray-500 text-sm leading-relaxed">
                    {{ content.howItWorks.steps[0].description }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Arrow 1: From card 1 to card 2 (curves down-right) -->
            <svg
              class="absolute top-[200px] left-[calc(25%+120px)] w-[calc(50%-80px)] h-40 text-gray-300"
              viewBox="0 0 400 160"
              fill="none"
              preserveAspectRatio="none"
            >
              <defs>
                <marker id="arrowhead1" markerWidth="10" markerHeight="7" refX="9" refY="3.5" orient="auto">
                  <polygon points="0 0, 10 3.5, 0 7" fill="currentColor" />
                </marker>
              </defs>
              <path
                d="M0 0 Q200 0 200 80 Q200 160 400 160"
                stroke="currentColor"
                stroke-width="2"
                stroke-dasharray="8 6"
                marker-end="url(#arrowhead1)"
              />
            </svg>

            <!-- Row 2: Card 2 (right) -->
            <div class="flex justify-end mb-8 mt-16">
              <div class="w-1/2 flex justify-center">
                <div
                  class="group relative bg-white p-8 rounded-2xl border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 w-full max-w-sm h-64"
                  data-testid="step-2-card"
                >
                  <div
                    class="absolute -top-4 -left-4 w-10 h-10 bg-[#198496] text-white flex items-center justify-center rounded-md font-bold text-sm"
                  >
                    {{ content.howItWorks.steps[1].number }}
                  </div>
                  <div
                    class="w-14 h-14 bg-[#198496]/10 rounded-xl flex items-center justify-center text-[#198496] mb-6"
                  >
                    <component :is="content.howItWorks.steps[1].icon" class="w-7 h-7" />
                  </div>
                  <h3 class="text-lg font-bold mb-3">{{ content.howItWorks.steps[1].title }}</h3>
                  <p class="text-gray-500 text-sm leading-relaxed">
                    {{ content.howItWorks.steps[1].description }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Arrow 2: From card 2 to card 3 (curves down-left) -->
            <svg
              class="absolute top-[520px] left-[calc(25%-60px)] w-[calc(50%-80px)] h-40 text-gray-300"
              viewBox="0 0 400 160"
              fill="none"
              preserveAspectRatio="none"
            >
              <defs>
                <marker id="arrowhead2" markerWidth="10" markerHeight="7" refX="9" refY="3.5" orient="auto">
                  <polygon points="0 0, 10 3.5, 0 7" fill="currentColor" />
                </marker>
              </defs>
              <path
                d="M400 0 Q200 0 200 80 Q200 160 0 160"
                stroke="currentColor"
                stroke-width="2"
                stroke-dasharray="8 6"
                marker-end="url(#arrowhead2)"
              />
            </svg>

            <!-- Row 3: Card 3 (left) -->
            <div class="flex justify-start mb-8 mt-16">
              <div class="w-1/2 flex justify-center">
                <div
                  class="group relative bg-white p-8 rounded-2xl border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 w-full max-w-sm h-64"
                  data-testid="step-3-card"
                >
                  <div
                    class="absolute -top-4 -left-4 w-10 h-10 bg-[#198496] text-white flex items-center justify-center rounded-md font-bold text-sm"
                  >
                    {{ content.howItWorks.steps[2].number }}
                  </div>
                  <div
                    class="w-14 h-14 bg-[#198496]/10 rounded-xl flex items-center justify-center text-[#198496] mb-6"
                  >
                    <component :is="content.howItWorks.steps[2].icon" class="w-7 h-7" />
                  </div>
                  <h3 class="text-lg font-bold mb-3">{{ content.howItWorks.steps[2].title }}</h3>
                  <p class="text-gray-500 text-sm leading-relaxed">
                    {{ content.howItWorks.steps[2].description }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Arrow 3: From card 3 to card 4 (curves down-right) -->
            <svg
              class="absolute top-[840px] left-[calc(25%+120px)] w-[calc(50%-80px)] h-40 text-gray-300"
              viewBox="0 0 400 160"
              fill="none"
              preserveAspectRatio="none"
            >
              <defs>
                <marker id="arrowhead3" markerWidth="10" markerHeight="7" refX="9" refY="3.5" orient="auto">
                  <polygon points="0 0, 10 3.5, 0 7" fill="currentColor" />
                </marker>
              </defs>
              <path
                d="M0 0 Q200 0 200 80 Q200 160 400 160"
                stroke="currentColor"
                stroke-width="2"
                stroke-dasharray="8 6"
                marker-end="url(#arrowhead3)"
              />
            </svg>

            <!-- Row 4: Card 4 (right) -->
            <div class="flex justify-end mt-16">
              <div class="w-1/2 flex justify-center">
                <div
                  class="group relative bg-white p-8 rounded-2xl border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 w-full max-w-sm h-64"
                  data-testid="step-4-card"
                >
                  <div
                    class="absolute -top-4 -left-4 w-10 h-10 bg-[#198496] text-white flex items-center justify-center rounded-md font-bold text-sm"
                  >
                    {{ content.howItWorks.steps[3].number }}
                  </div>
                  <div
                    class="w-14 h-14 bg-[#198496]/10 rounded-xl flex items-center justify-center text-[#198496] mb-6"
                  >
                    <component :is="content.howItWorks.steps[3].icon" class="w-7 h-7" />
                  </div>
                  <h3 class="text-lg font-bold mb-3">{{ content.howItWorks.steps[3].title }}</h3>
                  <p class="text-gray-500 text-sm leading-relaxed">
                    {{ content.howItWorks.steps[3].description }}
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
    <Transition name="perspective" mode="out-in">
      <component :is="showcaseComponent" :key="perspective" />
    </Transition>

    <!-- ===== SECTION 4: MISSIONS EN COURS (Data-driven) ===== -->
    <section id="missions" class="py-16 bg-gray-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-12 gap-6">
          <div>
            <h2
              class="text-3xl lg:text-4xl font-bold text-gray-900 tracking-tight mb-2"
              data-testid="missions-section-title"
            >
              {{ content.missions.title }}
            </h2>
            <p class="text-gray-500 text-lg">{{ content.missions.subtitle }}</p>
          </div>
          <a
            :href="content.missions.ctaLink"
            class="flex items-center gap-2 text-sm font-bold text-[#198496] border border-[#198496] px-5 py-2 rounded-md hover:bg-[#198496] hover:text-white transition-colors group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#198496] focus-visible:ring-offset-2"
            data-testid="see-all-missions"
          >
            {{ content.missions.ctaText }}
            <ChevronRight class="w-4 h-4 group-hover:translate-x-1 transition-transform" />
          </a>
        </div>

        <!-- Loading State -->
        <div
          v-if="isLoadingMissions"
          class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8"
          data-testid="missions-loading"
        >
          <div
            v-for="i in 6"
            :key="`skeleton-${i}`"
            class="bg-white rounded-2xl border border-gray-200 overflow-hidden animate-pulse"
          >
            <div class="h-56 bg-gray-200"></div>
            <div class="p-6 space-y-4">
              <div class="h-6 bg-gray-200 rounded w-3/4"></div>
              <div class="h-4 bg-gray-200 rounded w-full"></div>
              <div class="h-4 bg-gray-200 rounded w-2/3"></div>
              <div class="flex justify-between pt-4 border-t border-gray-100">
                <div class="h-4 bg-gray-200 rounded w-24"></div>
                <div class="h-6 bg-gray-200 rounded w-16"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Error State -->
        <div v-else-if="missionsError" class="text-center py-12" data-testid="missions-error">
          <p class="text-red-600 mb-4">{{ missionsError }}</p>
          <button class="text-[#198496] font-medium hover:underline" @click="missionsError = null">
            Réessayer
          </button>
        </div>

        <!-- Missions Content -->
        <div v-else>
          <!-- Mobile: Swipeable Carousel -->
          <div class="md:hidden">
            <div
              ref="missionCarouselRef"
              class="flex gap-4 overflow-x-auto snap-x snap-mandatory scroll-smooth pb-4 -mx-4 px-4 scrollbar-hide"
              @scroll="handleMissionScroll"
              data-testid="missions-carousel-mobile"
            >
              <div
                v-for="mission in mockMissions"
                :key="mission.id"
                data-mission-card
                class="group flex-shrink-0 w-[85%] snap-center bg-white rounded-2xl border border-gray-200 overflow-hidden"
                :data-testid="`mission-card-mobile-${mission.id}`"
              >
                <!-- Image -->
                <div class="relative h-48 overflow-hidden">
                  <img
                    :src="mission.img"
                    :alt="mission.title"
                    class="w-full h-full object-cover"
                    loading="lazy"
                  />
                  <!-- Type Badge -->
                  <div class="absolute top-3 left-3">
                    <span :class="[mission.typeColor, 'text-xs font-bold px-3 py-1 rounded-full']">{{
                      mission.type
                    }}</span>
                  </div>
                  <!-- Price Badge -->
                  <div
                    class="absolute top-3 right-3 bg-white/90 backdrop-blur-sm px-3 py-1 rounded-full shadow-md"
                  >
                    <span class="text-sm font-bold text-[#198496]">{{ mission.price }}</span>
                  </div>
                </div>

                <!-- Content -->
                <div class="p-5">
                  <h3 class="text-lg font-bold text-gray-900 mb-1.5">
                    {{ mission.title }}
                  </h3>
                  <p class="text-gray-600 text-sm leading-relaxed mb-3 line-clamp-2">{{ mission.description }}</p>

                  <!-- Metadata -->
                  <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                    <div class="flex items-center gap-1.5 text-sm text-gray-500">
                      <MapPin class="w-4 h-4 text-[#198496]" />
                      {{ mission.location }}
                    </div>
                    <span
                      :class="[
                        getStatusBadge(mission.status).class,
                        'text-xs font-medium px-2 py-1 rounded-full border',
                      ]"
                    >
                      {{ getStatusBadge(mission.status).label }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Mobile Navigation -->
            <div class="flex items-center justify-center gap-6 mt-6">
              <button
                @click="prevMission"
                class="w-10 h-10 flex items-center justify-center rounded-full border border-gray-200 bg-white text-gray-600 hover:border-[#198496] hover:text-[#198496] transition-colors"
                aria-label="Mission précédente"
              >
                <ChevronLeft class="w-5 h-5" />
              </button>

              <!-- Dots Indicator -->
              <div class="flex items-center gap-2">
                <button
                  v-for="(_, index) in mockMissions"
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

              <button
                @click="nextMission"
                class="w-10 h-10 flex items-center justify-center rounded-full border border-gray-200 bg-white text-gray-600 hover:border-[#198496] hover:text-[#198496] transition-colors"
                aria-label="Mission suivante"
              >
                <ChevronRight class="w-5 h-5" />
              </button>
            </div>
          </div>

          <!-- Desktop: Grid Layout -->
          <div class="hidden md:grid md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
            <div
              v-for="mission in mockMissions"
              :key="mission.id"
              class="group bg-white rounded-2xl border border-gray-200 overflow-hidden hover:border-[#198496]/50 hover:scale-[1.02] hover:shadow-xl transition-all duration-300"
              :data-testid="`mission-card-${mission.id}`"
            >
              <!-- Image -->
              <div class="relative h-56 overflow-hidden">
                <img
                  :src="mission.img"
                  :alt="mission.title"
                  class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
                  loading="lazy"
                />
                <!-- Type Badge -->
                <div class="absolute top-4 left-4">
                  <span :class="[mission.typeColor, 'text-xs font-bold px-3 py-1 rounded-full']">{{
                    mission.type
                  }}</span>
                </div>
                <!-- Price Badge -->
                <div
                  class="absolute top-4 right-4 bg-white/90 backdrop-blur-sm px-3 py-1 rounded-full shadow-md"
                >
                  <span class="text-sm font-bold text-[#198496]">{{ mission.price }}</span>
                </div>
              </div>

              <!-- Content -->
              <div class="p-6">
                <h3
                  class="text-xl font-bold text-gray-900 mb-2 group-hover:text-[#198496] transition-colors"
                >
                  {{ mission.title }}
                </h3>
                <p class="text-gray-600 text-sm leading-relaxed mb-4">{{ mission.description }}</p>

                <!-- Metadata -->
                <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                  <div class="flex items-center gap-1.5 text-sm text-gray-500">
                    <MapPin class="w-4 h-4 text-[#198496]" />
                    {{ mission.location }}
                  </div>
                  <span
                    :class="[
                      getStatusBadge(mission.status).class,
                      'text-xs font-medium px-2 py-1 rounded-full border',
                    ]"
                  >
                    {{ getStatusBadge(mission.status).label }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== SECTION 5: POURQUOI WEACT (Data-driven) ===== -->
    <section class="py-16 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="text-center mb-16">
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
        <div class="hidden md:grid md:grid-cols-3 gap-6 lg:gap-16">
          <div
            v-for="(feature, index) in content.whyWeact.features"
            :key="index"
            class="relative text-center p-10 rounded-2xl border border-gray-200 bg-white hover:border-[#198496]/30 hover:shadow-[0_20px_50px_rgba(25,132,150,0.08)] hover:-translate-y-2 transition-all duration-500 group overflow-hidden"
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
              <div class="mt-8">
                <h3 class="font-bold text-gray-900 text-lg mb-4 group-hover:text-[#198496] transition-colors duration-300">
                  {{ feature.title }}
                </h3>
                <p class="text-sm text-gray-700 leading-relaxed">
                  {{ feature.description }}
                </p>
              </div>

              <!-- Premium Accent Indicator -->
              <div class="mt-8 flex justify-center items-center gap-1.5 opacity-0 group-hover:opacity-100 transition-all duration-500 translate-y-2 group-hover:translate-y-0">
                <div class="w-1.5 h-1.5 rounded-full bg-[#198496]"></div>
                <div class="w-8 h-[1px] bg-gradient-to-r from-[#198496] to-transparent"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Certified Badge -->
        <div class="mt-16 flex flex-col items-center">
          <div
            class="bg-gray-50 border border-gray-100 px-6 py-3 rounded-full flex items-center gap-3"
          >
            <CheckCircle class="w-5 h-5 text-green-500" />
            <span class="text-sm font-bold text-gray-900">{{ content.whyWeact.certifiedText }}</span>
          </div>
          <p class="mt-4 text-sm text-gray-500 text-center max-w-2xl">
            {{ content.whyWeact.footerText }}
          </p>
        </div>
      </div>
    </section>

    <!-- ===== SECTION 6: PRÊT À COMMENCER ? (Data-driven) ===== -->
    <section class="pt-4 pb-16 px-4 sm:px-6 lg:px-8">
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
