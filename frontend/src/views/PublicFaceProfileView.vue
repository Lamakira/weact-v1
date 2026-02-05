<script setup lang="ts">
import { watch, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTitle } from '@vueuse/core'
import { ChevronLeft, AlertCircle, RefreshCw, UserX } from 'lucide-vue-next'
import { useFaceProfile } from '@/features/public/composables/useFaceProfile'
import ProfilePhotoSection from '@/features/public/components/ProfilePhotoSection.vue'
import ProfileInfoSection from '@/features/public/components/ProfileInfoSection.vue'
import { Skeleton } from '@/components/ui/skeleton'

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

// Get face ID from route params with validation
const faceId = computed(() => {
  const id = route.params.id
  if (typeof id !== 'string') return null
  const parsed = parseInt(id, 10)
  return Number.isNaN(parsed) ? null : parsed
})

// Fetch face on mount and when ID changes
watch(
  faceId,
  async (newId) => {
    if (newId) {
      await fetchFace(newId)
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
  return `${face.value.prenom} - ${face.value.categorie_label} | WEACT`
})

useTitle(pageTitle)

// Retry handler for errors
async function handleRetry(): Promise<void> {
  if (faceId.value) {
    await fetchFace(faceId.value)
  }
}

// Navigate back to faces list
function handleBackToList(): void {
  router.push({ name: 'public-faces-list' })
}
</script>

<template>
  <div class="min-h-screen bg-gray-50" data-testid="public-face-profile-view">
    <!-- Navigation Header -->
    <nav
      aria-label="Breadcrumb"
      class="bg-white border-b border-gray-100"
    >
      <div class="max-w-7xl mx-auto px-4 md:px-6 py-4">
        <RouterLink
          to="/faces"
          class="inline-flex items-center gap-2 text-sm font-medium text-gray-500 hover:text-[#198496] transition-colors group"
          data-testid="back-to-list"
        >
          <ChevronLeft
            :size="18"
            class="transition-transform group-hover:-translate-x-1"
            aria-hidden="true"
          />
          Retour aux talents
        </RouterLink>
      </div>
    </nav>

    <main id="main-content" class="max-w-7xl mx-auto px-4 md:px-6 py-8 md:py-12">
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
        class="lg:grid lg:grid-cols-[35%_1fr] lg:gap-12 xl:gap-16 items-start"
        data-testid="face-profile"
      >
        <!-- Photo Section (Left on desktop, top on mobile) -->
        <aside class="mb-8 lg:mb-0 lg:sticky lg:top-8">
          <ProfilePhotoSection
            :photo-url="face.profile_photo_url"
            :prenom="face.prenom"
            :is-available="face.is_available"
            :has-album-photos="face.has_album_photos"
            :album-photos-count="face.album_photos_count"
          />
        </aside>

        <!-- Info Section (Right on desktop, bottom on mobile) -->
        <article class="bg-white rounded-2xl p-6 md:p-8 border border-gray-100 shadow-sm">
          <ProfileInfoSection
            :prenom="face.prenom"
            :ville="face.ville"
            :categorie="face.categorie"
            :categorie-label="face.categorie_label"
            :niche="face.niche"
            :niche-label="face.niche_label"
            :average-rating="face.average_rating"
            :ratings-count="face.ratings_count"
            :has-videos="hasVideos"
            :videos-count="videosCount"
          />
        </article>
      </div>
    </main>
  </div>
</template>

<style scoped>
/* Ensure the layout respects the 35/65 split on large screens */
@media (min-width: 1024px) {
  .lg\:grid-cols-\[35\%_1fr\] {
    grid-template-columns: 35% 1fr;
  }
}

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
