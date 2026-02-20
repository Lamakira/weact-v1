<script setup lang="ts">
import { computed, ref } from 'vue'
import { MapPin, User, X } from 'lucide-vue-next'
import type { CandidateFullProfile } from '../types'

/**
 * Props
 */
const props = defineProps<{
  candidate: CandidateFullProfile
}>()

/**
 * Computed: Full display name
 */
const displayName = computed((): string => {
  const { prenom, nom } = props.candidate
  return [prenom, nom].filter(Boolean).join(' ') || 'Candidat'
})

/**
 * Computed: Initials for fallback avatar
 */
const initials = computed((): string => {
  const name = displayName.value
  return name
    .split(' ')
    .filter((n) => n.length > 0)
    .map((n) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2) || '?'
})

/**
 * State: Photo lightbox
 */
const showPhotoLightbox = ref(false)

/**
 * Computed: Availability badge class
 */
const availabilityBadgeClass = computed((): string => {
  const color = props.candidate.availability_badge_color
  if (color === 'green') {
    return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
  }
  return 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400'
})
</script>

<template>
  <div class="rounded-2xl border border-border bg-card p-6">
    <div class="flex flex-col items-center gap-6 sm:flex-row sm:items-start">
      <!-- Profile Photo -->
      <div
        class="relative h-28 w-28 shrink-0 overflow-hidden rounded-full border-4 border-primary/20 bg-muted sm:h-32 sm:w-32"
        :class="{ 'cursor-pointer': candidate.profile_photo_url }"
        @click="candidate.profile_photo_url && (showPhotoLightbox = true)"
      >
        <img
          v-if="candidate.profile_photo_url"
          :src="candidate.profile_photo_url"
          :alt="displayName"
          class="h-full w-full object-cover transition-transform duration-200 hover:scale-105"
        />
        <div
          v-else
          class="flex h-full w-full items-center justify-center bg-primary/10"
        >
          <User class="h-12 w-12 text-primary/60" />
        </div>
        <div
          class="absolute -bottom-1 -right-1 flex h-8 w-8 items-center justify-center rounded-full bg-card text-lg font-bold shadow-md"
        >
          {{ initials }}
        </div>
      </div>

      <!-- Profile Info -->
      <div class="flex-1 text-center sm:text-left">
        <!-- Name and Availability -->
        <div class="flex flex-col items-center gap-3 sm:flex-row sm:items-start sm:justify-between">
          <h1 class="text-2xl font-bold text-foreground sm:text-3xl">
            {{ displayName }}
          </h1>
          <span
            class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium"
            :class="availabilityBadgeClass"
          >
            {{ candidate.availability_badge }}
          </span>
        </div>

        <!-- Categories and Niches -->
        <div class="mt-3 flex flex-wrap items-center justify-center gap-2 sm:justify-start">
          <span
            v-for="cat in candidate.categories"
            :key="cat.value"
            class="inline-flex items-center rounded-full bg-primary/10 px-3 py-1 text-sm font-medium text-primary"
          >
            {{ cat.label }}
          </span>
          <span
            v-for="niche in candidate.niches"
            :key="niche.value"
            class="inline-flex items-center rounded-full bg-muted px-3 py-1 text-sm font-medium text-muted-foreground"
          >
            {{ niche.label }}
          </span>
        </div>

        <!-- Location -->
        <div
          v-if="candidate.formatted_location"
          class="mt-3 flex items-center justify-center gap-2 text-muted-foreground sm:justify-start"
        >
          <MapPin class="h-4 w-4" />
          <span>{{ candidate.formatted_location }}</span>
        </div>

        <!-- Username -->
        <p
          v-if="candidate.username"
          class="mt-2 text-sm text-muted-foreground"
        >
          @{{ candidate.username }}
        </p>
      </div>
    </div>

    <!-- Photo Lightbox -->
    <Teleport to="body">
      <div
        v-if="showPhotoLightbox && candidate.profile_photo_url"
        role="dialog"
        aria-modal="true"
        aria-label="Photo de profil"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4"
        @click.self="showPhotoLightbox = false"
        @keydown.escape="showPhotoLightbox = false"
      >
        <!-- Close button -->
        <button
          type="button"
          aria-label="Fermer"
          class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20"
          @click="showPhotoLightbox = false"
        >
          <X class="h-6 w-6" />
        </button>

        <!-- Photo -->
        <img
          :src="candidate.profile_photo_url"
          :alt="displayName"
          class="max-h-[85vh] max-w-[90vw] rounded-lg object-contain shadow-2xl"
        />
      </div>
    </Teleport>
  </div>
</template>
