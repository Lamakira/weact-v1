<script setup lang="ts">
import { computed, toRef } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, Loader2, AlertCircle } from 'lucide-vue-next'
import { useCandidateProfile } from '@/features/candidature/composables'
import CandidateProfileHeader from '@/features/candidature/components/CandidateProfileHeader.vue'
import CandidateVideosSection from '@/features/candidature/components/CandidateVideosSection.vue'
import CandidatePhotoGallery from '@/features/candidature/components/CandidatePhotoGallery.vue'
import CandidateInfoSection from '@/features/candidature/components/CandidateInfoSection.vue'
import CandidateExperiencesSection from '@/features/candidature/components/CandidateExperiencesSection.vue'

/**
 * Router and route
 */
const route = useRoute()
const router = useRouter()

/**
 * Computed: Face ID from route params
 */
const faceId = computed(() => {
  const id = Number(route.params.id)
  return isNaN(id) ? 0 : id
})

/**
 * Use the composable with reactive faceId
 */
const {
  candidate,
  isLoading,
  error,
} = useCandidateProfile(toRef(faceId))

/**
 * Navigate back
 */
function goBack(): void {
  router.back()
}
</script>

<template>
  <div class="min-h-screen bg-background pb-12">
    <!-- Header Section -->
    <header class="border-b border-border bg-card">
      <div class="container mx-auto px-4 py-6 sm:px-6">
        <!-- Back Button -->
        <button
          type="button"
          class="inline-flex items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
          @click="goBack"
        >
          <ArrowLeft class="h-4 w-4" />
          Retour aux candidatures
        </button>
      </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto mt-6 px-4 sm:px-6">
      <!-- Loading State -->
      <div
        v-if="isLoading"
        class="flex flex-col items-center justify-center py-24"
      >
        <Loader2 class="h-10 w-10 animate-spin text-primary" />
        <p class="mt-4 text-muted-foreground">Chargement du profil...</p>
      </div>

      <!-- Error State -->
      <div
        v-else-if="error"
        class="mx-auto flex max-w-md flex-col items-center justify-center rounded-2xl border-2 border-dashed border-destructive/30 py-16 text-center"
      >
        <div
          class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-destructive/10"
        >
          <AlertCircle class="h-8 w-8 text-destructive" />
        </div>
        <h2 class="text-xl font-bold text-foreground">
          Erreur de chargement
        </h2>
        <p class="mt-2 max-w-xs text-muted-foreground">
          {{ error }}
        </p>
        <button
          type="button"
          class="mt-6 inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
          @click="goBack"
        >
          <ArrowLeft class="h-4 w-4" />
          Retour
        </button>
      </div>

      <!-- Profile Content -->
      <div v-else-if="candidate" class="space-y-6">
        <!-- Profile Header -->
        <CandidateProfileHeader :candidate="candidate" />

        <!-- Videos Section -->
        <CandidateVideosSection :candidate="candidate" />

        <!-- Photo Gallery -->
        <CandidatePhotoGallery :photos="candidate.photos" />

        <!-- Bio and Info -->
        <CandidateInfoSection :candidate="candidate" />

        <!-- Experiences -->
        <CandidateExperiencesSection :experiences="candidate.experiences" />
      </div>
    </main>
  </div>
</template>
