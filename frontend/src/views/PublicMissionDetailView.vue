<script setup lang="ts">
import { watch, computed, watchEffect, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { useTitle } from '@vueuse/core'
import {
  ChevronLeft,
  AlertCircle,
  RefreshCw,
  FileSearch,
  Calendar,
  MapPin,
  Wallet,
  Users,
  Clock,
  Star,
} from 'lucide-vue-next'
import { useMissionDetail } from '@/features/public/composables/useMissionDetail'
import { Skeleton } from '@/components/ui/skeleton'

const route = useRoute()
const { mission, isLoading, error, notFound, fetchMission } = useMissionDetail()

// Get mission slug from route params with validation
const missionSlug = computed(() => {
  const slug = route.params.slug
  return typeof slug === 'string' && slug.length > 0 ? slug : null
})

// Fetch mission on mount and when slug changes
watch(
  missionSlug,
  async (newSlug) => {
    if (newSlug) {
      await fetchMission(newSlug)
    }
  },
  { immediate: true }
)

// SEO - Dynamic page title
const pageTitle = computed(() => {
  if (notFound.value) {
    return 'Mission non trouvée | WEACT'
  }
  if (!mission.value) {
    return 'Chargement... | WEACT'
  }
  return `${mission.value.titre} | WEACT`
})

useTitle(pageTitle)

// SEO - Dynamic meta tags (description + Open Graph)
const metaTags: HTMLMetaElement[] = []

function setMetaTag(name: string, content: string, isProperty = false): void {
  const attr = isProperty ? 'property' : 'name'
  let tag = document.querySelector(`meta[${attr}="${name}"]`) as HTMLMetaElement | null
  if (!tag) {
    tag = document.createElement('meta')
    tag.setAttribute(attr, name)
    document.head.appendChild(tag)
    metaTags.push(tag)
  }
  tag.content = content
}

watchEffect(() => {
  if (mission.value) {
    const description = `${mission.value.titre} — ${mission.value.type_mission_label} à ${mission.value.lieu}. Découvrez les détails et postulez sur WEACT.`
    setMetaTag('description', description)
    setMetaTag('og:title', `${mission.value.titre} | WEACT`, true)
    setMetaTag('og:description', description, true)
    setMetaTag('og:type', 'article', true)
  }
})

onUnmounted(() => {
  metaTags.forEach(tag => tag.remove())
})

// Format date in French locale
function formatDate(dateString: string | null): string {
  if (!dateString) return ''
  return new Date(dateString).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}

// Format budget in XOF
function formatBudget(amount: number): string {
  return amount.toLocaleString('fr-FR') + ' XOF'
}

// Check if deadline has passed (compare date-only strings to avoid timezone issues)
const isDeadlinePassed = computed(() => {
  if (!mission.value?.date_limite_candidature) return false
  const now = new Date()
  const todayStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
  return mission.value.date_limite_candidature < todayStr
})

// Producer initials fallback
const producerInitials = computed(() => {
  if (!mission.value?.producer?.display_name) return '?'
  return mission.value.producer.display_name.charAt(0).toUpperCase()
})

// Retry handler
async function handleRetry(): Promise<void> {
  if (missionSlug.value) {
    await fetchMission(missionSlug.value)
  }
}
</script>

<template>
  <div class="min-h-screen bg-gray-50" data-testid="public-mission-detail-view">
    <!-- Breadcrumb Navigation -->
    <nav
      aria-label="Breadcrumb"
      class="max-w-7xl mx-auto px-4 md:px-6 pt-8 md:pt-10"
    >
      <RouterLink
        to="/missions"
        class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-[#198496] transition-all duration-200 group"
        data-testid="back-to-list"
      >
        <ChevronLeft
          :size="16"
          class="transition-transform group-hover:-translate-x-0.5"
          aria-hidden="true"
        />
        Retour aux missions
      </RouterLink>
    </nav>

    <main id="main-content" class="max-w-7xl mx-auto px-4 md:px-6 pt-6 pb-8 md:pt-8 md:pb-12">
      <!-- LOADING STATE -->
      <div
        v-if="isLoading"
        class="space-y-6"
        data-testid="loading-state"
      >
        <div class="bg-white rounded-2xl p-6 md:p-8 border border-gray-100 shadow-sm space-y-6">
          <div class="space-y-3">
            <div class="flex gap-2">
              <Skeleton class="h-6 w-20 rounded-full" />
              <Skeleton class="h-6 w-16 rounded-full" />
            </div>
            <Skeleton class="h-9 w-3/4" />
          </div>
          <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <Skeleton class="h-12 w-full rounded-lg" />
            <Skeleton class="h-12 w-full rounded-lg" />
            <Skeleton class="h-12 w-full rounded-lg" />
            <Skeleton class="h-12 w-full rounded-lg" />
            <Skeleton class="h-12 w-full rounded-lg" />
            <Skeleton class="h-12 w-full rounded-lg" />
          </div>
          <Skeleton class="h-px w-full" />
          <Skeleton class="h-24 w-full" />
          <Skeleton class="h-16 w-full" />
        </div>
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
          <div class="flex items-center gap-4">
            <Skeleton class="h-12 w-12 rounded-full" />
            <div class="space-y-2">
              <Skeleton class="h-5 w-32" />
              <Skeleton class="h-4 w-24" />
            </div>
          </div>
        </div>
      </div>

      <!-- ERROR STATE -->
      <div
        v-else-if="error && !notFound"
        role="alert"
        class="flex flex-col items-center justify-center py-20 text-center space-y-4 bg-white rounded-2xl border border-gray-100"
        data-testid="error-state"
      >
        <div class="p-4 bg-red-50 text-red-600 rounded-full">
          <AlertCircle :size="32" aria-hidden="true" />
        </div>
        <div class="space-y-2">
          <h1 class="text-xl font-semibold text-gray-900">
            Une erreur est survenue
          </h1>
          <p class="text-gray-500 text-sm">
            {{ error }}
          </p>
        </div>
        <button
          type="button"
          class="inline-flex items-center gap-2 text-sm font-medium bg-[#198496] text-white px-6 py-2.5 rounded-md hover:bg-[#146c7a] transition-all active:scale-95 focus:outline-none focus:ring-2 focus:ring-[#198496]/20"
          data-testid="retry-button"
          @click="handleRetry"
        >
          <RefreshCw :size="16" aria-hidden="true" />
          Réessayer
        </button>
      </div>

      <!-- NOT FOUND STATE -->
      <div
        v-else-if="notFound"
        class="flex flex-col items-center justify-center py-20 text-center space-y-6 bg-white rounded-2xl border border-gray-100"
        data-testid="not-found-state"
      >
        <div class="p-4 bg-gray-100 text-gray-400 rounded-full">
          <FileSearch :size="32" aria-hidden="true" />
        </div>
        <div class="space-y-2">
          <h1 class="text-2xl font-semibold text-gray-900">
            Mission non trouvée
          </h1>
          <p class="text-gray-500">
            La mission que vous recherchez n'existe pas ou n'est plus disponible.
          </p>
        </div>
        <RouterLink
          to="/missions"
          class="text-sm font-medium bg-[#198496] text-white px-6 py-2.5 rounded-md hover:bg-[#146c7a] transition-colors focus:outline-none focus:ring-2 focus:ring-[#198496]/20"
          data-testid="back-to-missions-cta"
        >
          Voir toutes les missions
        </RouterLink>
      </div>

      <!-- SUCCESS STATE -->
      <div
        v-else-if="mission"
        class="space-y-6"
        data-testid="mission-detail"
      >
        <!-- Mission Card -->
        <article class="bg-white rounded-2xl p-6 md:p-8 border border-gray-100 shadow-sm">
          <!-- Badges & Title -->
          <div class="mb-6">
            <div class="flex flex-wrap items-center gap-2 mb-3">
              <span
                class="inline-flex items-center rounded-full bg-[#198496]/10 px-3 py-1 text-xs font-medium text-[#198496]"
                data-testid="mission-type-badge"
              >
                {{ mission.type_mission_label }}
              </span>
              <span
                v-if="mission.genre_voulu_label"
                class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600"
                data-testid="mission-genre-badge"
              >
                {{ mission.genre_voulu_label }}
              </span>
            </div>
            <h1
              class="text-2xl md:text-3xl font-bold text-gray-900"
              data-testid="mission-title"
            >
              {{ mission.titre }}
            </h1>
            <p class="mt-1 text-sm text-gray-400" data-testid="mission-posted-date">
              Publiée le {{ formatDate(mission.created_at) }}
            </p>
          </div>

          <!-- Mission Meta Grid -->
          <div
            class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6"
            data-testid="mission-meta-grid"
          >
            <!-- Budget -->
            <div class="flex items-start gap-3 rounded-xl bg-gray-50 p-4">
              <Wallet class="h-5 w-5 text-[#198496] mt-0.5 flex-shrink-0" aria-hidden="true" />
              <div>
                <p class="text-xs text-gray-500">Budget</p>
                <p class="text-sm font-semibold text-gray-900" data-testid="mission-budget">
                  {{ formatBudget(mission.budget) }}
                </p>
              </div>
            </div>

            <!-- Shooting Date -->
            <div v-if="mission.date_tournage" class="flex items-start gap-3 rounded-xl bg-gray-50 p-4">
              <Calendar class="h-5 w-5 text-[#198496] mt-0.5 flex-shrink-0" aria-hidden="true" />
              <div>
                <p class="text-xs text-gray-500">Tournage</p>
                <p class="text-sm font-medium text-gray-900" data-testid="mission-shooting-date">
                  {{ formatDate(mission.date_tournage) }}
                </p>
              </div>
            </div>

            <!-- Application Deadline -->
            <div v-if="mission.date_limite_candidature" class="flex items-start gap-3 rounded-xl bg-gray-50 p-4">
              <Calendar class="h-5 w-5 mt-0.5 flex-shrink-0" :class="isDeadlinePassed ? 'text-red-400' : 'text-[#198496]'" aria-hidden="true" />
              <div>
                <p class="text-xs text-gray-500">Date limite</p>
                <p
                  class="text-sm font-medium"
                  :class="isDeadlinePassed ? 'text-red-600' : 'text-gray-900'"
                  data-testid="mission-deadline"
                >
                  {{ formatDate(mission.date_limite_candidature) }}
                </p>
                <p v-if="isDeadlinePassed" class="text-xs text-red-500 mt-0.5">Expirée</p>
              </div>
            </div>

            <!-- Location -->
            <div class="flex items-start gap-3 rounded-xl bg-gray-50 p-4">
              <MapPin class="h-5 w-5 text-[#198496] mt-0.5 flex-shrink-0" aria-hidden="true" />
              <div>
                <p class="text-xs text-gray-500">Lieu</p>
                <p class="text-sm font-medium text-gray-900" data-testid="mission-lieu">
                  {{ mission.lieu }}
                </p>
              </div>
            </div>

            <!-- Duration -->
            <div v-if="mission.duree" class="flex items-start gap-3 rounded-xl bg-gray-50 p-4">
              <Clock class="h-5 w-5 text-[#198496] mt-0.5 flex-shrink-0" aria-hidden="true" />
              <div>
                <p class="text-xs text-gray-500">Durée</p>
                <p class="text-sm font-medium text-gray-900" data-testid="mission-duree">
                  {{ mission.duree }}
                </p>
              </div>
            </div>

            <!-- Faces needed -->
            <div class="flex items-start gap-3 rounded-xl bg-gray-50 p-4">
              <Users class="h-5 w-5 text-[#198496] mt-0.5 flex-shrink-0" aria-hidden="true" />
              <div>
                <p class="text-xs text-gray-500">Faces recherchées</p>
                <p class="text-sm font-medium text-gray-900" data-testid="mission-faces-count">
                  {{ mission.nombre_faces_voulu }} Face{{ mission.nombre_faces_voulu > 1 ? 's' : '' }}
                </p>
              </div>
            </div>
          </div>

          <!-- Divider -->
          <div class="h-px w-full bg-gray-100 mb-6"></div>

          <!-- Description -->
          <div class="mb-6">
            <h2 class="text-sm font-semibold text-gray-900 mb-2">Description</h2>
            <p
              class="text-sm text-gray-700 leading-relaxed whitespace-pre-line"
              data-testid="mission-description"
            >
              {{ mission.description }}
            </p>
          </div>

          <!-- Profil recherché -->
          <div v-if="mission.profil_recherche">
            <h2 class="text-sm font-semibold text-gray-900 mb-2">Profil recherché</h2>
            <p
              class="text-sm text-gray-700 leading-relaxed whitespace-pre-line"
              data-testid="mission-profil-recherche"
            >
              {{ mission.profil_recherche }}
            </p>
          </div>
        </article>

        <!-- Producer Card -->
        <div
          v-if="mission.producer"
          class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm"
          data-testid="producer-section"
        >
          <h2 class="text-sm font-semibold text-gray-900 mb-4">Producteur</h2>
          <div class="flex items-center gap-4">
            <!-- Producer Avatar -->
            <div class="relative h-12 w-12 flex-shrink-0 overflow-hidden rounded-full border border-gray-200 bg-gray-100">
              <img
                v-if="mission.producer.profile_photo_thumbnail_url"
                :src="mission.producer.profile_photo_thumbnail_url"
                :alt="`Photo de ${mission.producer.display_name}`"
                loading="lazy"
                class="h-full w-full object-cover"
                data-testid="producer-photo"
              />
              <div
                v-else
                class="flex h-full w-full items-center justify-center bg-[#198496]/10 text-sm font-bold uppercase text-[#198496]"
              >
                {{ producerInitials }}
              </div>
            </div>

            <!-- Producer Info -->
            <div>
              <p class="text-sm font-semibold text-gray-900" data-testid="producer-name">
                {{ mission.producer.display_name }}
              </p>
              <!-- Rating -->
              <div
                v-if="mission.producer.average_rating !== null"
                class="flex items-center gap-1.5 mt-1"
                :aria-label="`Note : ${mission.producer.average_rating} étoiles sur 5, basé sur ${mission.producer.ratings_count} avis`"
                data-testid="producer-rating"
              >
                <Star class="h-4 w-4 fill-amber-400 text-amber-400" aria-hidden="true" />
                <span class="text-sm font-medium text-gray-900">
                  {{ mission.producer.average_rating }}
                </span>
                <span class="text-xs text-gray-400">
                  ({{ mission.producer.ratings_count }} avis)
                </span>
              </div>
              <p
                v-else
                class="text-xs text-gray-400 mt-1"
              >
                Pas encore d'avis
              </p>
            </div>
          </div>
        </div>

        <!-- CTA Section -->
        <div
          class="bg-white rounded-2xl p-6 md:p-8 border border-gray-100 shadow-sm text-center"
          aria-label="Postuler à cette mission"
          data-testid="cta-section"
        >
          <!-- Deadline passed -->
          <div v-if="isDeadlinePassed" class="space-y-2">
            <span class="inline-flex items-center rounded-full bg-red-50 px-4 py-2 text-sm font-medium text-red-600" data-testid="deadline-passed-badge">
              Candidatures clôturées
            </span>
            <p class="text-sm text-gray-500">
              La date limite de candidature pour cette mission est dépassée.
            </p>
          </div>

          <!-- Active CTA -->
          <div v-else class="space-y-4">
            <p class="text-sm text-gray-600">
              Intéressé(e) par cette mission ?
            </p>
            <RouterLink
              to="/login"
              class="inline-flex items-center gap-2 text-sm font-medium bg-[#198496] text-white px-8 py-3 rounded-md hover:bg-[#146c7a] transition-all active:scale-95 focus:outline-none focus:ring-2 focus:ring-[#198496]/20"
              data-testid="login-cta"
            >
              Se connecter pour postuler
            </RouterLink>
            <p class="text-sm text-gray-500">
              Pas encore inscrit ?
              <RouterLink
                to="/register/face"
                class="text-[#198496] hover:text-[#146c7a] transition-colors font-medium"
                data-testid="register-cta"
              >
                Créez votre compte
              </RouterLink>
            </p>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<style scoped>
/* Subtle fade-in animation */
main {
  animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Focus states for accessibility */
a:focus-visible,
button:focus-visible {
  outline: 2px solid #198496;
  outline-offset: 4px;
}
</style>
