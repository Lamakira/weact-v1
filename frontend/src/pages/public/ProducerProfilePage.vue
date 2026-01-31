<script setup lang="ts">
import { watch, computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePublicProducer } from '@/features/public/composables/usePublicProducer'
import { publicApi } from '@/features/public/services/publicApi'
import type { Review } from '@/features/rating/types'
import ReviewsList from '@/components/ReviewsList.vue'

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
    // Show error state for reviews section
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
    // Reset image error states on new producer
    profilePhotoError.value = false
    agencyLogoError.value = false
    reviews.value = []
    reviewsCurrentPage.value = 1
    reviewsError.value = false

    if (newId) {
      await fetchProducer(newId)
      // Fetch reviews after producer is loaded
      await fetchReviews(newId)
    }
  },
  { immediate: true }
)

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

// Default avatar placeholder
const defaultAvatar = computed(() => {
  return 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="%239ca3af"%3E%3Cpath stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /%3E%3C/svg%3E'
})
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-sm">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <button
          @click="handleGoBack"
          class="inline-flex items-center gap-2 text-gray-600 hover:text-gray-900 transition-colors"
          data-testid="back-button"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="1.5"
            stroke="currentColor"
            class="w-5 h-5"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"
            />
          </svg>
          <span class="text-sm font-medium">Retour</span>
        </button>
      </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Loading State -->
      <div
        v-if="isLoading"
        class="flex flex-col items-center justify-center py-16"
        data-testid="loading-state"
      >
        <svg
          class="animate-spin h-10 w-10 text-weact-600"
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
        >
          <circle
            class="opacity-25"
            cx="12"
            cy="12"
            r="10"
            stroke="currentColor"
            stroke-width="4"
          ></circle>
          <path
            class="opacity-75"
            fill="currentColor"
            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
          ></path>
        </svg>
        <p class="mt-4 text-gray-500">Chargement du profil...</p>
      </div>

      <!-- Not Found State -->
      <div
        v-else-if="notFound"
        class="bg-white rounded-xl shadow-sm p-8 text-center"
        data-testid="not-found-state"
      >
        <div class="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="1.5"
            stroke="currentColor"
            class="w-8 h-8 text-gray-400"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M15.182 16.318A4.486 4.486 0 0 0 12.016 15a4.486 4.486 0 0 0-3.198 1.318M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Z"
            />
          </svg>
        </div>
        <h2 class="text-xl font-semibold text-gray-900 mb-2">Producteur introuvable</h2>
        <p class="text-gray-500 mb-6">
          Le profil que vous recherchez n'existe pas ou a été supprimé.
        </p>
        <button
          @click="handleGoBack"
          class="inline-flex items-center gap-2 px-4 py-2 bg-weact-600 text-white rounded-lg hover:bg-weact-700 transition-colors"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="1.5"
            stroke="currentColor"
            class="w-5 h-5"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"
            />
          </svg>
          Retour
        </button>
      </div>

      <!-- Error State -->
      <div
        v-else-if="error"
        class="bg-white rounded-xl shadow-sm p-8 text-center"
        data-testid="error-state"
      >
        <div class="mx-auto w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mb-4">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="1.5"
            stroke="currentColor"
            class="w-8 h-8 text-red-500"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"
            />
          </svg>
        </div>
        <h2 class="text-xl font-semibold text-gray-900 mb-2">Une erreur est survenue</h2>
        <p class="text-gray-500 mb-6">{{ error }}</p>
        <button
          @click="handleRetry"
          class="inline-flex items-center gap-2 px-4 py-2 bg-weact-600 text-white rounded-lg hover:bg-weact-700 transition-colors"
          data-testid="retry-button"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="1.5"
            stroke="currentColor"
            class="w-5 h-5"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"
            />
          </svg>
          Réessayer
        </button>
      </div>

      <!-- Producer Profile -->
      <div
        v-else-if="producer"
        class="space-y-6"
        data-testid="producer-profile"
      >
        <!-- Profile Card -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
          <!-- Profile Header -->
          <div class="relative bg-gradient-to-r from-weact-600 to-weact-700 h-32 sm:h-40"></div>

          <!-- Profile Info -->
          <div class="relative px-6 pb-6">
            <!-- Avatar -->
            <div class="absolute -top-16 left-6">
              <div class="w-32 h-32 rounded-full border-4 border-white bg-gray-100 overflow-hidden shadow-lg">
                <img
                  v-if="(producer.thumbnail_url || producer.profile_photo_url) && !profilePhotoError"
                  :src="producer.thumbnail_url || producer.profile_photo_url || undefined"
                  :alt="producer.display_name"
                  class="w-full h-full object-cover"
                  data-testid="profile-photo"
                  @error="profilePhotoError = true"
                />
                <div
                  v-else
                  class="w-full h-full flex items-center justify-center"
                  data-testid="profile-placeholder"
                >
                  <img :src="defaultAvatar" alt="Avatar par défaut" class="w-16 h-16 opacity-40" />
                </div>
              </div>
            </div>

            <!-- Agency Logo (for agencies only) -->
            <div
              v-if="isAgency && (producer.agency_logo_url || producer.agency_logo_thumbnail_url) && !agencyLogoError"
              class="absolute -top-8 right-6"
              data-testid="agency-logo-section"
            >
              <div class="w-20 h-20 rounded-lg border-2 border-white bg-white shadow-md overflow-hidden">
                <img
                  :src="producer.agency_logo_thumbnail_url || producer.agency_logo_url || undefined"
                  :alt="`Logo ${producer.agency_name}`"
                  class="w-full h-full object-contain p-1"
                  data-testid="agency-logo"
                  @error="agencyLogoError = true"
                />
              </div>
            </div>

            <!-- Name and Details -->
            <div class="pt-20">
              <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div>
                  <h1 class="text-2xl font-bold text-gray-900" data-testid="display-name">
                    {{ producer.display_name }}
                  </h1>
                  <div class="flex items-center gap-2 mt-1">
                    <span
                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                      :class="isAgency ? 'bg-weact-100 text-weact-800' : 'bg-gray-100 text-gray-800'"
                      data-testid="producer-type-badge"
                    >
                      {{ isAgency ? 'Agence' : 'Particulier' }}
                    </span>
                    <span class="text-sm text-gray-500" data-testid="member-since">
                      Membre depuis {{ producer.member_since }}
                    </span>
                  </div>
                </div>

                <!-- Stats -->
                <div class="flex gap-6">
                  <div class="text-center">
                    <div class="text-2xl font-bold text-gray-900" data-testid="missions-count">
                      {{ producer.missions_count }}
                    </div>
                    <div class="text-xs text-gray-500">Missions publiées</div>
                  </div>
                  <div class="text-center">
                    <div
                      v-if="producer.average_rating !== null"
                      class="flex items-center justify-center gap-1"
                      data-testid="rating-display"
                    >
                      <span class="text-2xl font-bold text-gray-900">{{ producer.average_rating.toFixed(1) }}</span>
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                        class="w-5 h-5 text-yellow-400"
                      >
                        <path
                          fill-rule="evenodd"
                          d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z"
                          clip-rule="evenodd"
                        />
                      </svg>
                    </div>
                    <div
                      v-else
                      class="text-sm text-gray-400"
                      data-testid="no-rating"
                    >
                      -
                    </div>
                    <div class="text-xs text-gray-500">
                      {{ producer.ratings_count > 0 ? `${producer.ratings_count} avis` : 'Aucune note' }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Bio Section -->
        <div class="bg-white rounded-xl shadow-sm p-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">À propos</h2>
          <p
            v-if="producer.bio"
            class="text-gray-600 whitespace-pre-wrap"
            data-testid="bio-content"
          >
            {{ producer.bio }}
          </p>
          <p
            v-else
            class="text-gray-400 italic"
            data-testid="no-bio"
          >
            Aucune description disponible.
          </p>
        </div>

        <!-- Reviews Section -->
        <div class="bg-white rounded-xl shadow-sm p-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Avis et évaluations</h2>
          <!-- Reviews Error State -->
          <div
            v-if="reviewsError"
            class="text-center py-6"
            data-testid="reviews-error"
          >
            <p class="text-gray-500 text-sm">Impossible de charger les avis.</p>
            <button
              type="button"
              class="mt-2 text-sm text-weact-600 hover:text-weact-700 underline"
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
      </div>
    </main>
  </div>
</template>
