<script setup lang="ts">
import { ref, watch, computed, watchEffect, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTitle } from '@vueuse/core'
import { ChevronLeft, AlertCircle, RefreshCw, UserX, FileText, Banknote, Lock, CalendarPlus } from 'lucide-vue-next'
import { useFaceProfile } from '@/features/public/composables/useFaceProfile'
import { usePublicFaceAccess } from '@/features/public/composables/usePublicFaceAccess'
import { publicApi } from '@/features/public/services/publicApi'
import ProfilePhotoSection from '@/features/public/components/ProfilePhotoSection.vue'
import ProfileInfoSection from '@/features/public/components/ProfileInfoSection.vue'
import LockedContentTeaser from '@/features/public/components/LockedContentTeaser.vue'
import CandidateVideosSection from '@/features/candidature/components/CandidateVideosSection.vue'
import CandidatePhotoGallery from '@/features/candidature/components/CandidatePhotoGallery.vue'
import CandidateInfoSection from '@/features/candidature/components/CandidateInfoSection.vue'
import CandidateResumeSummary from '@/features/candidature/components/CandidateResumeSummary.vue'
import CandidateExperiencesSection from '@/features/candidature/components/CandidateExperiencesSection.vue'
import RatingDisplay from '@/components/RatingDisplay.vue'
import ReviewsList from '@/components/ReviewsList.vue'
import { Skeleton } from '@/components/ui/skeleton'
import { BookingFormSheet } from '@/features/booking/components'
import type { Review } from '@/features/rating/types'

const route = useRoute()
const router = useRouter()
const {
  face,
  isLoading,
  error,
  notFound,
  hasVideos,
  videosCount,
  fetchFace,
} = useFaceProfile()

// Auth-aware access level
const faceId = computed(() => face.value?.id ?? null)
const { accessLevel, fullProfile, isLoadingFullProfile } = usePublicFaceAccess(faceId)

// Reviews state (for producers with access)
const reviews = ref<Review[]>([])
const reviewsLoading = ref(false)
const reviewsCurrentPage = ref(1)
const reviewsLastPage = ref(1)
const reviewsTotal = ref(0)
const reviewsError = ref(false)

// Booking sheet state
const isBookingSheetOpen = ref(false)

const showBookingButton = computed(() =>
  accessLevel.value === 'producer_with_access' ||
  accessLevel.value === 'guest'
)

const canBook = computed(() =>
  accessLevel.value === 'producer_with_access' &&
  fullProfile.value?.is_available === true
)

const bookingButtonDisabled = computed(() =>
  accessLevel.value === 'producer_with_access' &&
  fullProfile.value?.is_available !== true
)

function handleBookingClick(): void {
  if (accessLevel.value === 'guest') {
    router.push({ path: '/login', query: { redirect: route.fullPath } })
    return
  }
  if (canBook.value) {
    isBookingSheetOpen.value = true
  }
}

function handleBookingSuccess(): void {
  isBookingSheetOpen.value = false
  // TODO: redirect to booking detail page when story b1-3 implements the route
  router.push('/producer')
}

async function fetchReviews(id: number, page: number = 1): Promise<void> {
  reviewsLoading.value = true
  reviewsError.value = false
  try {
    const response = await publicApi.getFaceReviews(id, page)
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

function handleReviewsPageChange(page: number): void {
  if (faceId.value) {
    fetchReviews(faceId.value, page)
  }
}

// Fetch reviews when producer has access
watch(
  [accessLevel, faceId],
  ([level, id]) => {
    if (level === 'producer_with_access' && id && id > 0) {
      fetchReviews(id)
    } else {
      reviews.value = []
      reviewsCurrentPage.value = 1
      reviewsError.value = false
    }
  },
)

// Get face username from route params with validation
const faceUsername = computed(() => {
  const username = route.params.username
  return typeof username === 'string' && username.length > 0 ? username : null
})

// Fetch face on mount and when username changes
watch(
  faceUsername,
  async (newUsername) => {
    if (newUsername) {
      await fetchFace(newUsername)
    }
  },
  { immediate: true }
)

// SEO - Dynamic page title
const pageTitle = computed(() => {
  if (notFound.value) {
    return 'Profil non trouvé | WEACT'
  }
  if (!face.value) {
    return 'Chargement... | WEACT'
  }
  const firstCategory = face.value.categories?.[0]?.label ?? 'Talent'
  return `${face.value.prenom} - ${firstCategory} | WEACT`
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
  if (face.value) {
    const firstCat = face.value.categories?.[0]?.label ?? 'Talent'
    const suffix = accessLevel.value === 'guest'
      ? ' Inscrivez-vous sur WEACT pour accéder au profil complet.'
      : ' Profil de talent sur WEACT.'
    const description = `Découvrez le profil de ${face.value.prenom}, ${firstCat}${face.value.ville ? ` à ${face.value.ville}` : ''}.${suffix}`
    setMetaTag('description', description)
    setMetaTag('og:title', `${face.value.prenom} - ${firstCat} | WEACT`, true)
    setMetaTag('og:description', 'Profil de talent sur WEACT', true)
    if (face.value.profile_photo_url) {
      setMetaTag('og:image', face.value.profile_photo_url, true)
    }
  }
})

onUnmounted(() => {
  metaTags.forEach(tag => tag.remove())
})

// Retry handler for errors
async function handleRetry(): Promise<void> {
  if (faceUsername.value) {
    await fetchFace(faceUsername.value)
  }
}
</script>

<template>
  <div class="min-h-screen bg-gray-50" data-testid="public-face-profile-view">
    <!-- Breadcrumb Navigation -->
    <nav
      aria-label="Breadcrumb"
      class="max-w-7xl mx-auto px-4 md:px-6 pt-8 md:pt-10"
    >
      <RouterLink
        to="/faces"
        class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-[#198496] transition-all duration-200 group"
        data-testid="back-to-list"
      >
        <ChevronLeft
          :size="16"
          class="transition-transform group-hover:-translate-x-0.5"
          aria-hidden="true"
        />
        Retour aux talents
      </RouterLink>
    </nav>

    <main id="main-content" class="max-w-7xl mx-auto px-4 md:px-6 pt-6 pb-8 md:pt-8 md:pb-12">
      <!-- LOADING STATE -->
      <div
        v-if="isLoading"
        class="lg:grid lg:grid-cols-[35%_1fr] lg:gap-12 xl:gap-16"
        data-testid="loading-state"
      >
        <!-- Photo Skeleton -->
        <div class="mb-8 lg:mb-0">
          <Skeleton class="aspect-[3/4] w-full rounded-2xl" />
          <Skeleton class="h-4 w-32 mx-auto mt-3" />
        </div>
        <!-- Info Skeleton -->
        <div class="space-y-6 bg-white rounded-2xl p-6 border border-gray-100">
          <div class="space-y-2">
            <Skeleton class="h-9 w-40" />
            <Skeleton class="h-4 w-28" />
          </div>
          <div class="flex gap-2">
            <Skeleton class="h-6 w-20 rounded-full" />
            <Skeleton class="h-6 w-16 rounded-full" />
          </div>
          <Skeleton class="h-4 w-32" />
          <Skeleton class="h-px w-full" />
          <Skeleton class="h-24 w-full rounded-xl" />
          <Skeleton class="h-12 w-full rounded-md" />
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
          <UserX :size="32" aria-hidden="true" />
        </div>
        <div class="space-y-2">
          <h1 class="text-2xl font-semibold text-gray-900">
            Face non trouvée
          </h1>
          <p class="text-gray-500">
            Le profil que vous recherchez n'existe pas ou a été supprimé.
          </p>
        </div>
        <RouterLink
          to="/faces"
          class="text-sm font-medium bg-[#198496] text-white px-6 py-2.5 rounded-md hover:bg-[#146c7a] transition-colors focus:outline-none focus:ring-2 focus:ring-[#198496]/20"
          data-testid="back-to-faces-cta"
        >
          Découvrir nos talents
        </RouterLink>
      </div>

      <!-- SUCCESS STATE -->
      <div
        v-else-if="face"
        class="space-y-8"
        data-testid="face-profile"
      >
        <div class="lg:grid lg:grid-cols-[35%_1fr] lg:gap-12 xl:gap-16 items-start">
          <!-- Photo Section (Left on desktop, top on mobile) -->
          <aside class="mb-8 lg:mb-0 lg:sticky lg:top-8">
            <ProfilePhotoSection
              :photo-url="face.profile_photo_url"
              :prenom="face.prenom"
              :is-available="face.is_available"
              :has-album-photos="face.has_album_photos"
              :album-photos-count="face.album_photos_count"
              :show-album-lock="accessLevel === 'guest'"
            />
          </aside>

          <!-- Info Section (Right on desktop, bottom on mobile) -->
          <div class="space-y-6">
            <article class="bg-white rounded-2xl p-6 md:p-8 border border-gray-100 shadow-sm">
              <ProfileInfoSection
                :prenom="face.prenom"
                :ville="face.ville"
                :categories="face.categories"
                :niches="face.niches"
                :average-rating="face.average_rating"
                :ratings-count="face.ratings_count"
                :has-videos="hasVideos"
                :videos-count="videosCount"
                :access-level="accessLevel"
              />
            </article>

            <!-- Resume Summary (producer only, inside right column on desktop) -->
            <CandidateResumeSummary
              v-if="accessLevel === 'producer_with_access' && fullProfile"
              :candidate="fullProfile"
            />

            <!-- Booking CTA Button -->
            <div v-if="showBookingButton" class="relative group">
              <button
                type="button"
                :disabled="bookingButtonDisabled"
                class="w-full flex items-center justify-center gap-2 text-sm font-semibold px-6 py-3 rounded-xl transition-all focus:outline-none focus:ring-2 focus:ring-[#198496]/20 shadow-sm"
                :class="bookingButtonDisabled
                  ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                  : 'bg-[#198496] text-white hover:bg-[#146c7a] active:scale-[0.98]'"
                data-testid="booking-cta"
                @click="handleBookingClick"
              >
                <CalendarPlus :size="18" aria-hidden="true" />
                Réserver cette Face
              </button>
              <div
                v-if="bookingButtonDisabled"
                class="absolute -top-10 left-1/2 -translate-x-1/2 bg-gray-800 text-white text-xs px-3 py-1.5 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none"
                role="tooltip"
              >
                Cette Face n'est pas disponible
              </div>
            </div>
          </div>
        </div>

        <!-- GUEST: Locked Content Teasers -->
        <div
          v-if="accessLevel === 'guest'"
          class="grid md:grid-cols-2 gap-6"
          data-testid="locked-teasers"
        >
          <LockedContentTeaser
            title="Bio & Expériences professionnelles"
            description="Découvrez le parcours complet et les expériences de ce talent."
            cta-text="S'inscrire pour voir"
            cta-link="/register/producer"
            :icon="FileText"
          />
          <LockedContentTeaser
            title="Tarifs & Caractéristiques"
            description="Accédez aux tarifs, mensurations et détails physiques."
            cta-text="S'inscrire pour voir"
            cta-link="/register/producer"
            :icon="Banknote"
          />
        </div>

        <!-- PRODUCER WITH ACCESS: Full Profile -->
        <template v-else-if="accessLevel === 'producer_with_access' && fullProfile">
          <!-- Rating Display -->
          <div class="px-2">
            <RatingDisplay
              :average-rating="fullProfile.average_rating"
              :review-count="fullProfile.ratings_count"
            />
          </div>

          <!-- Photo Gallery -->
          <CandidatePhotoGallery :photos="fullProfile.photos" />

          <!-- Videos Section -->
          <CandidateVideosSection :candidate="fullProfile" />

          <!-- Bio and Info -->
          <CandidateInfoSection :candidate="fullProfile" />

          <!-- Experiences -->
          <CandidateExperiencesSection :experiences="fullProfile.experiences" />

          <!-- Reviews Section -->
          <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold text-gray-900">Avis et évaluations</h2>
            <div
              v-if="reviewsError"
              class="text-center py-6"
              data-testid="reviews-error"
            >
              <p class="text-gray-500 text-sm">Impossible de charger les avis.</p>
              <button
                type="button"
                class="mt-2 text-sm text-[#198496] hover:text-[#146c7a] underline"
                data-testid="reviews-retry"
                @click="fetchReviews(faceId!)"
              >
                Réessayer
              </button>
            </div>
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
        </template>

        <!-- LOADING FULL PROFILE (Producer) -->
        <div
          v-else-if="isLoadingFullProfile"
          class="space-y-6"
          data-testid="full-profile-loading"
        >
          <Skeleton class="h-48 w-full rounded-2xl" />
          <Skeleton class="h-32 w-full rounded-2xl" />
          <Skeleton class="h-24 w-full rounded-2xl" />
        </div>

        <!-- FACE USER -->
        <div
          v-else-if="accessLevel === 'face_user'"
          class="flex flex-col items-center justify-center py-16 text-center space-y-4 bg-white rounded-2xl border border-gray-100 shadow-sm"
          data-testid="face-user-message"
        >
          <div class="p-4 bg-gray-100 text-gray-400 rounded-full">
            <Lock :size="32" aria-hidden="true" />
          </div>
          <div class="space-y-2 max-w-md">
            <h2 class="text-xl font-semibold text-gray-900">
              Le profil complet est réservé aux producteurs
            </h2>
            <p class="text-gray-500 text-sm">
              Seuls les producteurs peuvent consulter les profils détaillés des talents.
            </p>
          </div>
        </div>

        <!-- Booking Form Sheet (outside access-level conditionals, uses Teleport) -->
        <BookingFormSheet
          v-if="canBook && fullProfile"
          :is-open="isBookingSheetOpen"
          :face-id="face!.user_id"
          :face-name="fullProfile.prenom"
          :tarif-horaire="fullProfile.tarif_horaire"
          :tarif-journalier="fullProfile.tarif_journalier"
          @close="isBookingSheetOpen = false"
          @success="handleBookingSuccess"
        />
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
