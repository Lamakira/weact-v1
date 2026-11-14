<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import { Play, Video, X } from 'lucide-vue-next'
import type { CandidateFullProfile } from '../types'
import { labelledPortfolioVideos } from '@/features/face/lib/portfolioVideos'

/**
 * Props
 */
const props = defineProps<{
  candidate: CandidateFullProfile
}>()

interface VideoTile {
  key: string
  url: string
  thumbnailUrl: string | null
  label: string
  locked: boolean
}

/**
 * State for video modal/expanded view
 */
const activeVideo = ref<{ url: string; title: string } | null>(null)
const closeButtonRef = ref<HTMLButtonElement | null>(null)

/**
 * Tuiles : présentation d'abord, puis vidéos de portfolio ordonnées
 */
const tiles = computed((): VideoTile[] => {
  const list: VideoTile[] = []
  if (props.candidate.presentation_video_url) {
    list.push({
      key: 'presentation',
      url: props.candidate.presentation_video_url,
      thumbnailUrl: props.candidate.presentation_video_thumbnail_url,
      label: 'Vidéo de présentation',
      locked: false,
    })
  }
  for (const { video, label, rank } of labelledPortfolioVideos(props.candidate.videos)) {
    list.push({
      key: `${video.type}-${rank}`,
      url: video.video_url as string,
      thumbnailUrl: video.thumbnail_url,
      label,
      locked: video.is_locked === true,
    })
  }
  return list
})

/**
 * Computed: Has any videos
 */
const hasAnyVideos = computed((): boolean => tiles.value.length > 0)

/**
 * Open video player
 */
function openVideo(tile: VideoTile): void {
  activeVideo.value = { url: tile.url, title: tile.label }
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
const currentVideoUrl = computed((): string | null => activeVideo.value?.url ?? null)

/**
 * Get current video title
 */
const currentVideoTitle = computed((): string => activeVideo.value?.title ?? '')
</script>

<template>
  <div v-if="hasAnyVideos" class="rounded-2xl border border-border bg-card p-6">
    <h2 class="mb-4 text-lg font-semibold text-foreground">Vidéos</h2>

    <div class="grid gap-4 sm:grid-cols-2">
      <div
        v-for="tile in tiles"
        :key="tile.key"
        :data-testid="`video-tile-${tile.key}`"
        class="group relative cursor-pointer overflow-hidden rounded-xl bg-muted"
        @click="openVideo(tile)"
      >
        <div class="aspect-video">
          <img
            v-if="tile.thumbnailUrl"
            :src="tile.thumbnailUrl"
            :alt="tile.label"
            class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
            :class="tile.locked ? 'grayscale brightness-50' : ''"
          />
          <div
            v-else
            class="flex h-full w-full items-center justify-center bg-muted"
            :class="tile.locked ? 'grayscale brightness-50' : ''"
          >
            <Video class="h-12 w-12 text-muted-foreground" />
          </div>
        </div>

        <!-- Locked badge -->
        <span
          v-if="tile.locked"
          class="absolute left-2 top-2 rounded-full bg-black/70 px-2.5 py-1 text-xs font-medium text-white"
        >
          Non visible : palier actuel
        </span>

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
          <span class="text-sm font-medium text-white">{{ tile.label }}</span>
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
