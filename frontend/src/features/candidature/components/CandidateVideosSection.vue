<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import { Play, Video, X } from 'lucide-vue-next'
import type { CandidateFullProfile } from '../types'

/**
 * Props
 */
const props = defineProps<{
  candidate: CandidateFullProfile
}>()

/**
 * State for video modal/expanded view
 */
const activeVideo = ref<'presentation' | 'acting' | null>(null)
const closeButtonRef = ref<HTMLButtonElement | null>(null)

/**
 * Computed: Has presentation video
 */
const hasPresentationVideo = computed(
  (): boolean => !!props.candidate.presentation_video_url,
)

/**
 * Computed: Has acting video
 */
const hasActingVideo = computed((): boolean => !!props.candidate.acting_video_url)

/**
 * Computed: Has any videos
 */
const hasAnyVideos = computed(
  (): boolean => hasPresentationVideo.value || hasActingVideo.value,
)

/**
 * Open video player
 */
function openVideo(type: 'presentation' | 'acting'): void {
  activeVideo.value = type
}

/**
 * Close video player
 */
function closeVideo(): void {
  activeVideo.value = null
}

/**
 * Handle keyboard events for modal
 */
function handleKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    closeVideo()
  }
}

/**
 * Focus management when modal opens/closes
 */
watch(activeVideo, async (newValue) => {
  if (newValue) {
    document.addEventListener('keydown', handleKeydown)
    await nextTick()
    closeButtonRef.value?.focus()
  } else {
    document.removeEventListener('keydown', handleKeydown)
  }
})

/**
 * Get current video URL
 */
const currentVideoUrl = computed((): string | null => {
  if (activeVideo.value === 'presentation') {
    return props.candidate.presentation_video_url
  }
  if (activeVideo.value === 'acting') {
    return props.candidate.acting_video_url
  }
  return null
})

/**
 * Get current video title
 */
const currentVideoTitle = computed((): string => {
  if (activeVideo.value === 'presentation') {
    return 'Vidéo de présentation'
  }
  if (activeVideo.value === 'acting') {
    return "Vidéo d'acting"
  }
  return ''
})
</script>

<template>
  <div v-if="hasAnyVideos" class="rounded-2xl border border-border bg-card p-6">
    <h2 class="mb-4 text-lg font-semibold text-foreground">Vidéos</h2>

    <div class="grid gap-4 sm:grid-cols-2">
      <!-- Presentation Video -->
      <div
        v-if="hasPresentationVideo"
        class="group relative cursor-pointer overflow-hidden rounded-xl bg-muted"
        @click="openVideo('presentation')"
      >
        <div class="aspect-video">
          <img
            v-if="candidate.presentation_video_thumbnail_url"
            :src="candidate.presentation_video_thumbnail_url"
            alt="Vidéo de présentation"
            class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
          />
          <div
            v-else
            class="flex h-full w-full items-center justify-center bg-muted"
          >
            <Video class="h-12 w-12 text-muted-foreground" />
          </div>
        </div>

        <!-- Play overlay -->
        <div
          class="absolute inset-0 flex items-center justify-center bg-black/30 opacity-0 transition-opacity group-hover:opacity-100"
        >
          <div
            class="flex h-14 w-14 items-center justify-center rounded-full bg-white/90 shadow-lg"
          >
            <Play class="h-6 w-6 text-primary" />
          </div>
        </div>

        <!-- Label -->
        <div
          class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent p-3"
        >
          <span class="text-sm font-medium text-white">Vidéo de présentation</span>
        </div>
      </div>

      <!-- Acting Video -->
      <div
        v-if="hasActingVideo"
        class="group relative cursor-pointer overflow-hidden rounded-xl bg-muted"
        @click="openVideo('acting')"
      >
        <div class="aspect-video">
          <img
            v-if="candidate.acting_video_thumbnail_url"
            :src="candidate.acting_video_thumbnail_url"
            alt="Vidéo d'acting"
            class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
          />
          <div
            v-else
            class="flex h-full w-full items-center justify-center bg-muted"
          >
            <Video class="h-12 w-12 text-muted-foreground" />
          </div>
        </div>

        <!-- Play overlay -->
        <div
          class="absolute inset-0 flex items-center justify-center bg-black/30 opacity-0 transition-opacity group-hover:opacity-100"
        >
          <div
            class="flex h-14 w-14 items-center justify-center rounded-full bg-white/90 shadow-lg"
          >
            <Play class="h-6 w-6 text-primary" />
          </div>
        </div>

        <!-- Label -->
        <div
          class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent p-3"
        >
          <span class="text-sm font-medium text-white">Vidéo d'acting</span>
        </div>
      </div>
    </div>

    <!-- Video Modal -->
    <Teleport to="body">
      <div
        v-if="activeVideo && currentVideoUrl"
        role="dialog"
        aria-modal="true"
        :aria-label="currentVideoTitle"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
        @click.self="closeVideo"
      >
        <div class="relative w-full max-w-4xl">
          <!-- Close button -->
          <button
            ref="closeButtonRef"
            type="button"
            aria-label="Fermer la vidéo"
            class="absolute -right-2 -top-12 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20"
            @click="closeVideo"
          >
            <X class="h-6 w-6" />
          </button>

          <!-- Video title -->
          <h3 class="mb-3 text-lg font-medium text-white">
            {{ currentVideoTitle }}
          </h3>

          <!-- Video player -->
          <video
            :src="currentVideoUrl"
            controls
            autoplay
            class="w-full rounded-xl bg-black"
          >
            Votre navigateur ne supporte pas la lecture vidéo.
          </video>
        </div>
      </div>
    </Teleport>
  </div>

  <!-- Empty state -->
  <div
    v-else
    class="rounded-2xl border border-dashed border-border bg-muted/30 p-6 text-center"
  >
    <Video class="mx-auto h-10 w-10 text-muted-foreground/50" />
    <p class="mt-2 text-sm text-muted-foreground">
      Aucune vidéo disponible
    </p>
  </div>
</template>
