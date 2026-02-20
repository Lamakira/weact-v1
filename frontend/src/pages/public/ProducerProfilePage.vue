<script setup lang="ts">
import { watch, computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ChevronLeft,
  Star,
  Briefcase,
  Calendar,
  ShieldCheck,
  User,
  AlertCircle,
  RefreshCw,
  SearchX,
} from 'lucide-vue-next'
import { usePublicProducer } from '@/features/public/composables/usePublicProducer'
import { publicApi } from '@/features/public/services/publicApi'
import type { Review } from '@/features/rating/types'
import ReviewsList from '@/components/ReviewsList.vue'
import { Skeleton } from '@/components/ui/skeleton'

const route = useRoute()
const router = useRouter()
const { producer, isLoading, error, notFound, isAgency, fetchProducer } = usePublicProducer()

// Track if profile photo failed to load
const profilePhotoError = ref(false)
const agencyLogoError = ref(false)

// Reviews state
const reviews = ref<Review[]>([])
const reviewsLoading = ref(false)
const reviewsCurrentPage = ref(1)
const reviewsLastPage = ref(1)
const reviewsTotal = ref(0)
const reviewsError = ref(false)

// Fetch reviews for the producer
async function fetchReviews(producerId: number, page: number = 1): Promise<void> {
  reviewsLoading.value = true
  reviewsError.value = false
  try {
    const response = await publicApi.getProducerReviews(producerId, page)
    reviews.value = response.data
    reviewsCurrentPage.value = response.meta.current_page
    reviewsLastPage.value = response.meta.last_page
    reviewsTotal.value = response.meta.total
  } catch {
    reviews.value = []
    reviewsError.value = true
  } finally {
    reviewsLoading.value = false
  }
}

// Handle page change for reviews
function handleReviewsPageChange(page: number): void {
  if (producerId.value) {
    fetchReviews(producerId.value, page)
  }
}

// Get producer ID from route params with NaN validation
const producerId = computed(() => {
  const id = route.params.id
  if (typeof id !== 'string') return null
  const parsed = parseInt(id, 10)
  return Number.isNaN(parsed) ? null : parsed
})

// Watch for route param changes and fetch producer (handles initial load + navigation)
watch(
  producerId,
  async (newId) => {
    profilePhotoError.value = false
    agencyLogoError.value = false
    reviews.value = []
    reviewsCurrentPage.value = 1
    reviewsError.value = false

    if (newId) {
      await fetchProducer(newId)
      await fetchReviews(newId)
    }
  },
  { immediate: true }
)

// Computed helpers
const initials = computed(() => {
  if (!producer.value?.display_name) return '??'
  return producer.value.display_name
    .split(' ')
    .map((n: string) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
})

const formattedDate = computed(() => {
  if (!producer.value?.member_since) return ''
  return new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric' }).format(
    new Date(producer.value.member_since)
  )
})

// For agencies: show agency logo as main image. For particuliers: show profile photo.
const mainPhotoUrl = computed(() => {
  if (!producer.value) return null
  if (isAgency.value) {
    if (agencyLogoError.value) return null
    return producer.value.agency_logo_url || producer.value.agency_logo_thumbnail_url || null
  }
  if (profilePhotoError.value) return null
  return producer.value.thumbnail_url || producer.value.profile_photo_url || null
})

// For agencies: use object-contain (logo). For particuliers: use object-cover (photo).
const mainPhotoFit = computed(() => isAgency.value ? 'object-contain rounded-[24px]' : 'object-cover')

// Retry handler for errors
async function handleRetry(): Promise<void> {
  if (producerId.value) {
    await fetchProducer(producerId.value)
  }
}

// Go back handler
function handleGoBack(): void {
  router.back()
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 selection:bg-teal-100 selection:text-[#198496]">
    <main class="max-w-7xl mx-auto px-4 md:px-6 py-8 lg:p-12 animate-page-in">

      <!-- Top Navigation -->
      <button
        @click="handleGoBack"
        class="group flex items-center gap-2 mb-8 text-slate-500 hover:text-[#198496] transition-colors font-medium"
        data-testid="back-button"
      >
        <div class="w-8 h-8 rounded-full bg-white shadow-sm border border-gray-50 flex items-center justify-center group-hover:shadow-md group-hover:scale-105 transition-all">
          <ChevronLeft class="w-4 h-4" />
        </div>
        <span class="text-sm">Retour</span>
      </button>

      <!-- LOADING STATE -->
      <div
        v-if="isLoading"
        class="grid grid-cols-1 lg:grid-cols-[1fr_1.8fr] gap-6 lg:gap-10"
        data-testid="loading-state"
      >
        <div>
          <Skeleton class="aspect-square w-full rounded-[24px]" />
        </div>
        <div class="space-y-6">
          <div class="space-y-3">
            <Skeleton class="h-6 w-24 rounded-full" />
            <Skeleton class="h-12 w-2/3 rounded-xl" />
          </div>
          <div class="flex gap-4">
            <Skeleton class="h-14 w-40 rounded-full" />
            <Skeleton class="h-14 w-40 rounded-full" />
          </div>
          <Skeleton class="h-48 w-full rounded-[24px]" />
        </div>
      </div>

      <!-- ERROR STATE -->
      <div
        v-else-if="error"
        class="flex flex-col items-center justify-center py-24 text-center"
        data-testid="error-state"
      >
        <div class="w-20 h-20 bg-red-50 text-red-500 rounded-[24px] flex items-center justify-center mb-6">
          <AlertCircle class="w-10 h-10" />
        </div>
        <h2 class="text-2xl font-bold text-slate-800 mb-2">Une erreur est survenue</h2>
        <p class="text-slate-500 mb-8 max-w-md">{{ error }}</p>
        <button
          @click="handleRetry"
          class="flex items-center gap-2 px-8 py-3 bg-[#198496] text-white rounded-full font-bold shadow-lg shadow-teal-500/30 hover:scale-105 transition-transform active:scale-95"
          data-testid="retry-button"
        >
          <RefreshCw class="w-4 h-4" />
          Réessayer
        </button>
      </div>

      <!-- NOT FOUND STATE -->
      <div
        v-else-if="notFound"
        class="flex flex-col items-center justify-center py-24 text-center"
        data-testid="not-found-state"
      >
        <div class="w-20 h-20 bg-teal-50 text-[#198496] rounded-[24px] flex items-center justify-center mb-6">
          <SearchX class="w-10 h-10" />
        </div>
        <h2 class="text-2xl font-bold text-slate-800 mb-2">Producteur introuvable</h2>
        <p class="text-slate-500 mb-8 max-w-md">
          Le profil que vous recherchez n'existe pas ou a été supprimé.
        </p>
        <button
          @click="handleGoBack"
          class="px-8 py-3 border-2 border-[#198496] text-[#198496] rounded-full font-bold hover:bg-teal-50 transition-colors"
        >
          Retour
        </button>
      </div>

      <!-- SUCCESS STATE -->
      <div
        v-else-if="producer"
        class="space-y-8 lg:space-y-12"
        data-testid="producer-profile"
      >
        <div class="grid grid-cols-1 lg:grid-cols-[1fr_1.8fr] gap-6 lg:gap-10 items-start">

          <!-- Column 1: Photo / Logo -->
          <aside class="lg:sticky lg:top-8">
            <div class="group">
              <div class="aspect-square bg-white rounded-2xl sm:rounded-[24px] overflow-hidden shadow-sm border border-gray-100 transition-all duration-500 group-hover:shadow-md group-hover:scale-[1.01]">
                <img
                  v-if="mainPhotoUrl"
                  :src="mainPhotoUrl"
                  :alt="producer.display_name"
                  class="w-full h-full"
                  :class="mainPhotoFit"
                  :data-testid="isAgency ? 'agency-logo' : 'profile-photo'"
                  @error="isAgency ? (agencyLogoError = true) : (profilePhotoError = true)"
                />
                <div
                  v-else
                  class="w-full h-full bg-gradient-to-br from-[#198496] to-teal-300 flex items-center justify-center"
                  data-testid="profile-placeholder"
                >
                  <span class="text-white text-6xl font-bold tracking-tighter opacity-80">{{ initials }}</span>
                </div>
              </div>
            </div>
          </aside>

          <!-- Column 2: Information -->
          <section class="space-y-6 lg:space-y-10">
            <!-- Header Info -->
            <div>
              <div class="flex flex-wrap items-center gap-2 sm:gap-3 mb-3 sm:mb-4">
                <span
                  class="px-3 py-1 bg-teal-50 text-[#198496] text-xs font-bold uppercase tracking-wider rounded-full flex items-center gap-1.5"
                  data-testid="producer-type-badge"
                >
                  <component :is="isAgency ? ShieldCheck : User" class="w-3 h-3" />
                  {{ isAgency ? 'Agence' : 'Particulier' }}
                </span>
                <span
                  v-if="producer.member_since"
                  class="text-sm text-slate-400 flex items-center gap-1.5"
                  data-testid="member-since"
                >
                  <Calendar class="w-3.5 h-3.5" />
                  Membre depuis {{ formattedDate }}
                </span>
              </div>

              <h1
                class="text-2xl sm:text-3xl lg:text-5xl font-bold text-slate-800 tracking-tight leading-tight mb-4 sm:mb-6"
                data-testid="display-name"
              >
                {{ producer.display_name }}
              </h1>

              <!-- Stats: Unified bar on mobile, separate pills on desktop -->

              <!-- Mobile: Unified Stats Bar -->
              <div class="inline-flex items-center bg-white border border-gray-100 p-1.5 rounded-2xl shadow-sm lg:hidden">
                <div class="flex items-center gap-3 px-3 py-1.5">
                  <div class="w-10 h-10 bg-teal-50 rounded-xl flex items-center justify-center text-[#198496]">
                    <Briefcase class="w-5 h-5" />
                  </div>
                  <div class="flex flex-col">
                    <span class="text-lg font-bold text-slate-800 leading-none">{{ producer.missions_count }}</span>
                    <span class="text-[10px] text-slate-500 font-medium uppercase tracking-wider mt-1">Missions</span>
                  </div>
                </div>
                <div class="w-px h-8 bg-gray-100 mx-1" aria-hidden="true" />
                <div v-if="producer.average_rating !== null" class="flex items-center gap-3 px-3 py-1.5">
                  <div class="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center text-amber-500">
                    <Star class="w-5 h-5 fill-current" />
                  </div>
                  <div class="flex flex-col">
                    <div class="flex items-center gap-1.5 leading-none">
                      <span class="text-lg font-bold text-slate-800">{{ producer.average_rating.toFixed(1) }}</span>
                      <span class="text-[10px] text-slate-400 font-medium">({{ producer.ratings_count }})</span>
                    </div>
                    <div class="flex gap-0.5 mt-1">
                      <Star v-for="i in 5" :key="i" :class="['w-2.5 h-2.5', i <= Math.round(producer.average_rating) ? 'text-amber-400 fill-current' : 'text-slate-200']" />
                    </div>
                  </div>
                </div>
                <div v-else class="flex items-center gap-3 px-3 py-1.5">
                  <div class="w-10 h-10 bg-slate-50 rounded-xl flex items-center justify-center text-slate-300">
                    <Star class="w-5 h-5" />
                  </div>
                  <span class="text-sm font-medium text-slate-400">Aucun avis</span>
                </div>
              </div>

              <!-- Desktop: Separate Pills -->
              <div class="hidden lg:flex flex-wrap gap-4">
                <div class="flex items-center gap-3 bg-white p-3 pr-5 rounded-full border border-gray-50 shadow-sm transition-shadow hover:shadow-md">
                  <div class="w-10 h-10 bg-teal-50 rounded-full flex items-center justify-center text-[#198496]">
                    <Briefcase class="w-5 h-5" />
                  </div>
                  <div>
                    <div class="text-lg font-bold text-slate-800 leading-none" data-testid="missions-count">{{ producer.missions_count }}</div>
                    <div class="text-xs text-slate-400 font-medium">Missions publiées</div>
                  </div>
                </div>

                <div v-if="producer.average_rating !== null" class="flex items-center gap-3 bg-white p-3 pr-5 rounded-full border border-gray-50 shadow-sm transition-shadow hover:shadow-md">
                  <div class="w-10 h-10 bg-amber-50 rounded-full flex items-center justify-center text-amber-500">
                    <Star class="w-5 h-5 fill-current" />
                  </div>
                  <div>
                    <div class="flex items-center gap-1.5" data-testid="rating-display">
                      <span class="text-lg font-bold text-slate-800 leading-none">{{ producer.average_rating.toFixed(1) }}</span>
                      <span class="text-xs text-slate-300 font-normal">({{ producer.ratings_count }} avis)</span>
                    </div>
                    <div class="flex gap-0.5 mt-1">
                      <Star v-for="i in 5" :key="i" :class="['w-3 h-3', i <= Math.round(producer.average_rating) ? 'text-amber-400 fill-current' : 'text-slate-200']" />
                    </div>
                  </div>
                </div>

                <div v-else class="flex items-center gap-3 bg-white p-3 pr-5 rounded-full border border-gray-50 shadow-sm" data-testid="no-rating">
                  <div class="w-10 h-10 bg-slate-50 rounded-full flex items-center justify-center text-slate-300">
                    <Star class="w-5 h-5" />
                  </div>
                  <div>
                    <div class="text-sm font-medium text-slate-400 leading-none">Aucune note</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Bio Card -->
            <div class="p-6 sm:p-8 bg-white rounded-[24px] shadow-sm border border-gray-50 relative overflow-hidden">
              <div class="absolute top-0 right-0 p-4 opacity-[0.03] pointer-events-none" aria-hidden="true">
                <User class="w-24 h-24" />
              </div>
              <h3 class="text-lg font-semibold text-slate-800 mb-4">À propos</h3>
              <p
                v-if="producer.bio"
                class="text-slate-500 leading-relaxed whitespace-pre-wrap"
                data-testid="bio-content"
              >
                {{ producer.bio }}
              </p>
              <p
                v-else
                class="text-slate-400 italic"
                data-testid="no-bio"
              >
                Aucune description disponible.
              </p>
            </div>
          </section>
        </div>

        <!-- Reviews Section -->
        <section class="animate-section-in">
          <div class="flex items-center gap-4 sm:gap-6 mb-4 sm:mb-6">
            <h2 class="text-xl sm:text-2xl font-bold text-slate-800 flex-shrink-0">Avis et évaluations</h2>
            <div class="h-px flex-1 bg-gradient-to-r from-teal-100 to-transparent" aria-hidden="true" />
          </div>

          <div class="bg-white rounded-2xl sm:rounded-[24px] shadow-sm border border-gray-50 p-4 sm:p-8">
            <!-- Reviews Error State -->
            <div
              v-if="reviewsError"
              class="text-center py-6"
              data-testid="reviews-error"
            >
              <p class="text-slate-500 text-sm">Impossible de charger les avis.</p>
              <button
                type="button"
                class="mt-2 text-sm text-[#198496] hover:text-[#146c7a] font-medium underline underline-offset-2"
                data-testid="reviews-retry"
                @click="fetchReviews(producerId!)"
              >
                Réessayer
              </button>
            </div>

            <!-- Reviews List -->
            <ReviewsList
              v-else
              :reviews="reviews"
              :current-page="reviewsCurrentPage"
              :last-page="reviewsLastPage"
              :total="reviewsTotal"
              :loading="reviewsLoading"
              @page-change="handleReviewsPageChange"
            />
          </div>
        </section>
      </div>
    </main>
  </div>
</template>

<style scoped>
/* Page entrance animation */
.animate-page-in {
  animation: pageIn 0.6s ease-out both;
}

@keyframes pageIn {
  from {
    opacity: 0;
    transform: translateY(12px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Reviews section staggered entrance */
.animate-section-in {
  animation: sectionIn 0.7s cubic-bezier(0.16, 1, 0.3, 1) 0.2s both;
}

@keyframes sectionIn {
  from {
    opacity: 0;
    transform: translateY(20px);
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
