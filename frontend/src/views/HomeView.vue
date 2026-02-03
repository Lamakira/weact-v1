<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { RouterLink } from 'vue-router'
import {
  UserPlus,
  Mail,
  Clapperboard,
  Wallet,
  MapPin,
  Shield,
  CreditCard,
  Award,
  ArrowRight,
  Zap,
  ChevronRight,
  CheckCircle,
} from 'lucide-vue-next'

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

// --- Loading States (for future API integration) ---
const isLoadingMissions = ref(false)
const missionsError = ref<string | null>(null)

// --- Mock Data (to be replaced with API calls in future stories) ---
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

const mockMissions = [
  {
    id: 1,
    type: 'FILM',
    typeColor: 'bg-pink-500/10 text-pink-600',
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
    typeColor: 'bg-purple-500/10 text-purple-600',
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
    typeColor: 'bg-blue-500/10 text-blue-600',
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
    typeColor: 'bg-purple-500/10 text-purple-600',
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
    typeColor: 'bg-pink-500/10 text-pink-600',
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
    typeColor: 'bg-blue-500/10 text-blue-600',
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
  <div class="overflow-x-hidden">
    <!-- ===== SECTION 1: HERO ===== -->
    <section class="relative pt-24 pb-20 lg:pt-32 lg:pb-32 overflow-hidden">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-start justify-between gap-12">
          <!-- Left: Text Content -->
          <div class="max-w-2xl">
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold text-gray-900 leading-[1.1] mb-6">
              Monétisez votre image
              <br />
              en jouant dans :
              <br />
              <span
                class="text-[#198496] inline-block min-w-[200px] transition-all duration-500"
                data-testid="hero-animated-word"
                aria-live="polite"
                aria-atomic="true"
              >
                {{ words[activeWordIndex] }}
              </span>
            </h1>
            <p class="text-gray-500 text-lg lg:text-xl max-w-lg mb-10 leading-relaxed">
              La première plateforme qui met en relation marques et créateurs pour des castings
              sécurisés au Bénin.
            </p>
            <RouterLink
              to="/register/face"
              class="group inline-flex items-center gap-2 bg-[#198496] text-white px-8 py-3.5 rounded-md font-medium text-base hover:bg-[#146c7a] transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#198496] focus-visible:ring-offset-2"
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

    <!-- ===== SECTION 2: COMMENT ÇA MARCHE ===== -->
    <section class="py-24 bg-white relative overflow-hidden">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="text-center mb-20">
          <h2
            class="text-xs font-bold text-[#198496] tracking-[0.2em] uppercase mb-4"
            data-testid="how-it-works-title"
          >
            Comment ça marche
          </h2>
          <p class="text-3xl lg:text-4xl font-bold text-gray-900">
            4 étapes simples pour commencer votre carrière
          </p>
        </div>

        <!-- Steps -->
        <div class="relative space-y-12 lg:space-y-0">
          <!-- Step 1 -->
          <div class="flex flex-col lg:flex-row items-center gap-12 lg:gap-24">
            <div class="lg:w-1/2 flex justify-center">
              <div
                class="group relative bg-white p-8 rounded-2xl border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 max-w-sm"
                data-testid="step-1-card"
              >
                <div
                  class="absolute -top-4 -left-4 w-10 h-10 bg-[#198496] text-white flex items-center justify-center rounded-md font-bold text-sm"
                >
                  01
                </div>
                <div
                  class="w-14 h-14 bg-[#198496]/10 rounded-xl flex items-center justify-center text-[#198496] mb-6"
                >
                  <UserPlus class="w-7 h-7" />
                </div>
                <h3 class="text-lg font-bold mb-3">CRÉER VOTRE PROFIL</h3>
                <p class="text-gray-500 text-sm leading-relaxed">
                  Ajoutez photos, vidéos, définissez vos tarifs.
                </p>
              </div>
            </div>
            <div class="lg:w-1/2 hidden lg:flex items-center justify-start">
              <svg class="w-48 h-24 text-gray-200" viewBox="0 0 200 100" fill="none">
                <path
                  d="M10 10C80 10 120 90 190 90"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-dasharray="8 8"
                />
              </svg>
            </div>
          </div>

          <!-- Step 2 -->
          <div class="flex flex-col lg:flex-row-reverse items-center gap-12 lg:gap-24">
            <div class="lg:w-1/2 flex justify-center">
              <div
                class="group relative bg-white p-8 rounded-2xl border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 max-w-sm"
                data-testid="step-2-card"
              >
                <div
                  class="absolute -top-4 -left-4 w-10 h-10 bg-[#198496] text-white flex items-center justify-center rounded-md font-bold text-sm"
                >
                  02
                </div>
                <div
                  class="w-14 h-14 bg-[#198496]/10 rounded-xl flex items-center justify-center text-[#198496] mb-6"
                >
                  <Mail class="w-7 h-7" />
                </div>
                <h3 class="text-lg font-bold mb-3">RECEVOIR OU CANDIDATER</h3>
                <p class="text-gray-500 text-sm leading-relaxed">
                  Les producteurs vous contactent ou vous postulez aux offres.
                </p>
              </div>
            </div>
            <div class="lg:w-1/2 hidden lg:flex items-center justify-end">
              <svg class="w-48 h-24 text-gray-200 rotate-180" viewBox="0 0 200 100" fill="none">
                <path
                  d="M10 10C80 10 120 90 190 90"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-dasharray="8 8"
                />
              </svg>
            </div>
          </div>

          <!-- Step 3 -->
          <div class="flex flex-col lg:flex-row items-center gap-12 lg:gap-24">
            <div class="lg:w-1/2 flex justify-center">
              <div
                class="group relative bg-white p-8 rounded-2xl border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 max-w-sm"
                data-testid="step-3-card"
              >
                <div
                  class="absolute -top-4 -left-4 w-10 h-10 bg-[#198496] text-white flex items-center justify-center rounded-md font-bold text-sm"
                >
                  03
                </div>
                <div
                  class="w-14 h-14 bg-[#198496]/10 rounded-xl flex items-center justify-center text-[#198496] mb-6"
                >
                  <Clapperboard class="w-7 h-7" />
                </div>
                <h3 class="text-lg font-bold mb-3">TRAVAILLER</h3>
                <p class="text-gray-500 text-sm leading-relaxed">
                  Participez aux shootings et créations de contenu.
                </p>
              </div>
            </div>
            <div class="lg:w-1/2 hidden lg:flex items-center justify-start">
              <svg class="w-48 h-24 text-gray-200" viewBox="0 0 200 100" fill="none">
                <path
                  d="M10 10C80 10 120 90 190 90"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-dasharray="8 8"
                />
              </svg>
            </div>
          </div>

          <!-- Step 4 -->
          <div class="flex flex-col lg:flex-row items-center gap-12 lg:gap-24">
            <div class="lg:w-full flex justify-center">
              <div
                class="group relative bg-white p-8 rounded-2xl border border-gray-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 max-w-sm"
                data-testid="step-4-card"
              >
                <div
                  class="absolute -top-4 -left-4 w-10 h-10 bg-[#198496] text-white flex items-center justify-center rounded-md font-bold text-sm"
                >
                  04
                </div>
                <div
                  class="w-14 h-14 bg-[#198496]/10 rounded-xl flex items-center justify-center text-[#198496] mb-6"
                >
                  <Wallet class="w-7 h-7" />
                </div>
                <h3 class="text-lg font-bold mb-3">RECEVOIR VOS GAINS</h3>
                <p class="text-gray-500 text-sm leading-relaxed">
                  Recevez votre versement via Mobile Money.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== SECTION 3: TU ATTENDS QUOI ? DEVIENS UNE FACE ===== -->
    <section class="py-20 lg:py-32 relative overflow-hidden">
      <!-- Gradient Background Layer -->
      <div
        class="absolute inset-0 bg-gradient-to-r from-[#198496]/10 via-[#1a9fb5]/10 to-[#1bbad4]/10 pointer-events-none"
      ></div>
      <div
        class="absolute top-0 right-0 w-96 h-96 bg-[#198496] opacity-[0.05] blur-[120px] rounded-full"
      ></div>

      <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-16">
        <!-- Badge -->
        <div class="flex items-center gap-2 mb-4">
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
      <div class="relative flex overflow-hidden group" data-testid="faces-carousel">
        <div class="flex animate-marquee group-hover:[animation-play-state:paused] py-10 gap-8">
          <!-- First set -->
          <div
            v-for="profile in mockProfiles"
            :key="profile.id"
            class="flex-shrink-0 w-[200px] transition-all duration-300 hover:scale-105"
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

    <!-- ===== SECTION 4: MISSIONS EN COURS ===== -->
    <section id="missions" class="py-24 bg-gray-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-12 gap-6">
          <div>
            <h2
              class="text-3xl lg:text-4xl font-bold text-gray-900 tracking-tight mb-2"
              data-testid="missions-section-title"
            >
              Je recherche des missions en cours
            </h2>
            <p class="text-gray-500 text-lg">Découvre les dernières opportunités disponibles</p>
          </div>
          <!-- TODO: Update to /missions when story 11-6 (public missions list) is implemented -->
          <a
            href="#missions"
            class="flex items-center gap-2 text-sm font-bold text-[#198496] border border-[#198496] px-5 py-2 rounded-md hover:bg-[#198496] hover:text-white transition-colors group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#198496] focus-visible:ring-offset-2"
            data-testid="see-all-missions"
          >
            Voir toutes les missions
            <ChevronRight class="w-4 h-4 group-hover:translate-x-1 transition-transform" />
          </a>
        </div>

        <!-- Mission Cards Grid -->
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
        <div
          v-else-if="missionsError"
          class="text-center py-12"
          data-testid="missions-error"
        >
          <p class="text-red-600 mb-4">{{ missionsError }}</p>
          <button
            class="text-[#198496] font-medium hover:underline"
            @click="missionsError = null"
          >
            Réessayer
          </button>
        </div>

        <!-- Missions Content -->
        <div
          v-else
          class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8"
        >
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
                <span
                  :class="[mission.typeColor, 'text-xs font-bold px-3 py-1 rounded-full']"
                  >{{ mission.type }}</span
                >
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
    </section>

    <!-- ===== SECTION 5: POURQUOI WEACT ===== -->
    <section class="py-24 bg-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="text-center mb-16">
          <h2
            class="text-3xl lg:text-4xl font-bold text-gray-900 tracking-tight mb-4"
            data-testid="why-weact-title"
          >
            Pourquoi WEACT
          </h2>
          <p class="text-gray-500 text-lg max-w-xl mx-auto">
            La plateforme de confiance pour les talents et les producteurs
          </p>
        </div>

        <!-- Feature Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 lg:gap-12">
          <!-- Card 1 -->
          <div
            class="text-center p-8 rounded-2xl border border-gray-100 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 group"
            data-testid="feature-card-1"
          >
            <div
              class="w-16 h-16 bg-[#198496] text-white rounded-xl flex items-center justify-center mx-auto shadow-lg shadow-[#198496]/20 transition-transform group-hover:rotate-6"
            >
              <Shield class="w-8 h-8" />
            </div>
            <h3 class="font-bold text-lg mt-6 mb-3">Plateforme sécurisée et professionnelle</h3>
            <p class="text-sm text-gray-500 leading-relaxed">
              Vos données sont protégées et vos transactions sécurisées sur une plateforme fiable.
            </p>
          </div>

          <!-- Card 2 -->
          <div
            class="text-center p-8 rounded-2xl border border-gray-100 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 group"
            data-testid="feature-card-2"
          >
            <div
              class="w-16 h-16 bg-[#198496] text-white rounded-xl flex items-center justify-center mx-auto shadow-lg shadow-[#198496]/20 transition-transform group-hover:rotate-6"
            >
              <CreditCard class="w-8 h-8" />
            </div>
            <h3 class="font-bold text-lg mt-6 mb-3">Paiement sécurisé</h3>
            <p class="text-sm text-gray-500 leading-relaxed">
              Recevez vos paiements en toute sécurité via Mobile Money après chaque mission.
            </p>
          </div>

          <!-- Card 3 -->
          <div
            class="text-center p-8 rounded-2xl border border-gray-100 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 group"
            data-testid="feature-card-3"
          >
            <div
              class="w-16 h-16 bg-[#198496] text-white rounded-xl flex items-center justify-center mx-auto shadow-lg shadow-[#198496]/20 transition-transform group-hover:rotate-6"
            >
              <Award class="w-8 h-8" />
            </div>
            <h3 class="font-bold text-lg mt-6 mb-3">Transactions protégées et sans souci</h3>
            <p class="text-sm text-gray-500 leading-relaxed">
              Travaillez en toute confiance avec des producteurs vérifiés et des projets de qualité.
            </p>
          </div>
        </div>

        <!-- Certified Badge -->
        <div class="mt-16 flex flex-col items-center">
          <div
            class="bg-gray-50 border border-gray-100 px-6 py-3 rounded-full flex items-center gap-3"
          >
            <CheckCircle class="w-5 h-5 text-green-500" />
            <span class="text-sm font-bold text-gray-900">Plateforme certifiée</span>
          </div>
          <p class="mt-4 text-sm text-gray-500 text-center max-w-2xl">
            Weact est la première plateforme de casting digitale au Bénin, connectant talents et
            professionnels de l'audiovisuel depuis 2024
          </p>
        </div>
      </div>
    </section>

    <!-- ===== SECTION 6: PRÊT À COMMENCER ? ===== -->
    <section class="py-16 px-4 sm:px-6 lg:px-8">
      <div
        class="max-w-5xl mx-auto relative bg-[#198496] rounded-3xl overflow-hidden py-16 px-8 text-center"
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
            Prêt à commencer ?
          </h2>
          <p class="text-white/80 text-lg max-w-xl mx-auto">
            Rejoins maintenant la communauté WeAct et monétise ton talent.
          </p>
          <div class="pt-4">
            <RouterLink
              to="/register/face"
              class="inline-block bg-white text-[#198496] px-10 py-4 rounded-full font-bold text-sm shadow-xl hover:scale-105 hover:shadow-2xl transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#198496]"
              data-testid="final-cta-button"
            >
              S'inscrire comme Face
            </RouterLink>
          </div>
        </div>
      </div>
    </section>
  </div>
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
</style>
